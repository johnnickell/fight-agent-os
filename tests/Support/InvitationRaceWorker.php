<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Invitation\PostgresInvitationRateLimiter;
use App\Application\Failure\RateLimited;
use App\Application\Failure\StateConflict;
use App\Application\Invitation\IssueInvitation;
use App\Domain\Invitation\InvitationMode;
use App\Domain\Invitation\InvitationOperation;
use App\Domain\Invitation\InvitationRatePolicy;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Value\Internet\EmailAddress;
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
                } catch (RateLimited) {
                    echo "throttled\n";
                }
            } else {
                putenv('DATABASE_URL='.$url);
                $container = require dirname(__DIR__, 2).'/config/services.php';
                $container->set(Connection::class, static fn(): Connection => $connection);
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
                try {
                    $container->get(IssueInvitation::class)->issue(new AuthenticationContext(
                        UserId::fromString($input['actor']),
                        RefreshSessionId::fromString($input['session']),
                        $input['version']
                    ), EmailAddress::fromString('race@example.test'), InvitationMode::ORDINARY, false);
                    echo "issued\n";
                } catch (StateConflict) {
                    echo "conflict\n";
                }
            }
        } catch (Throwable) {
            echo "failed\n";
            exit(1);
        }
    }
}
