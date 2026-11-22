<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Http\Api\Invitation\InvitationOperation;
use App\Adapter\Http\Api\Invitation\InvitationRatePolicy;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Invitation\PostgresInvitationRateLimiter;
use App\Adapter\Security\AccessToken\AccessJwt;
use App\Adapter\Security\AccessToken\AccessKeyring;
use App\Adapter\Security\AccessToken\AuthenticateAccessToken;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Throwable;

/**
 * Class InvitationRaceWorker
 *
 * Exercises independent production connections behind bounded parent-controlled barriers
 */
final class InvitationRaceWorker
{
    /**
     * Runs one guarded child and emits only protocol state rather than private diagnostics
     */
    public static function run(): void
    {
        try {
            $input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
            $url = (string) getenv('TEST_DATABASE_URL');
            $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
            $target = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
            $connection = DriverManager::getConnection(new DsnParser([
                'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
            ])->parse($url));
            $guard->assertConnected($connection, $target);
            $connection->executeStatement("SET statement_timeout = '10s'");
            if ($input['operation'] === 'rate') {
                echo $connection->fetchOne('SELECT pg_backend_pid()')."\n";
                flush();
                try {
                    new PostgresInvitationRateLimiter(
                        $connection,
                        new AccessClock(new DateTimeImmutable($input['now'])),
                        new InvitationRatePolicy(60, 1, 2, 10, 1, 1, 1)
                    )->consume(
                        UserId::fromString($input['actor']),
                        InvitationOperation::from($input['rate_operation'] ?? 'issue'),
                        isset($input['target']) ? UserId::fromString($input['target']) : null
                    );
                    echo "admitted\n";
                } catch (RateLimitExceeded) {
                    echo "throttled\n";
                }
            } else {
                putenv('DATABASE_URL='.$url);
                $container = require dirname(__DIR__, 2).'/config/services.php';
                $container->set(Connection::class, static fn(): Connection => $connection);
                $clock = new AccessClock(new DateTimeImmutable($input['now']));
                $container->set(Clock::class, static fn(): Clock => $clock);
                $jwt = new AccessJwt(new AccessKeyring((string) getenv('APP_ACCESS_TOKEN_CONFIG'), $clock), $clock);
                $container->set(AuthenticateAccessToken::class, static fn(Container $c): AuthenticateAccessToken =>
                    new AuthenticateAccessToken(
                        $jwt,
                        $c->get(UserRepository::class),
                        $c->get(RefreshSessionRepository::class),
                        $clock
                    ));
                $container->set(
                    AuthoritativePrincipalResolver::class,
                    static fn(Container $c): AuthoritativePrincipalResolver => new AuthoritativePrincipalResolver(
                        $c->get(UserRepository::class),
                        $c->get(RefreshSessionRepository::class),
                        $c->get(RoleRepository::class),
                        $c->get(PermissionRepository::class),
                        $clock
                    )
                );
                $filter = new class implements CommandFilter {
                    /**
                     * @inheritDoc
                     */
                    public function process(CommandMessage $message, callable $next): void
                    {
                        if ($message->payload() instanceof InvitePendingUser) {
                            echo "ready\n";
                            flush();
                            if (trim((string) fgets(STDIN)) !== 'go') {
                                throw new \RuntimeException('Race barrier was not released.');
                            }
                        }
                        $next($message);
                    }
                };
                $container->get('messaging.command.bus')->addFilter($filter);
                AppFactory::setContainer($container);
                AppFactory::setResponseFactory(new ResponseFactory());
                $app = AppFactory::create();
                $container->set(App::class, static fn(): App => $app);
                require dirname(__DIR__, 2).'/config/routes.php';
                require dirname(__DIR__, 2).'/config/middleware.php';
                $request = new ServerRequestFactory()
                    ->createServerRequest('POST', 'https://agent-os.test/api/v1/invitations')
                    ->withHeader('Authorization', 'Bearer '.$input['token'])
                    ->withHeader('Content-Type', 'application/json');
                $request->getBody()->write(json_encode($input['body'], JSON_THROW_ON_ERROR));
                $response = $app->handle($request);
                echo json_encode([
                    'status'  => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'body'    => (string) $response->getBody()
                ], JSON_THROW_ON_ERROR)."\n";
            }
        } catch (Throwable) {
            echo "failed\n";
            exit(1);
        }
    }
}
