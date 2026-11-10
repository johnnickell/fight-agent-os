<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Security\AccessToken\AccessJwt;
use App\Adapter\Security\AccessToken\AccessKeyring;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Service\Container;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Class WorkspaceTestClock
 *
 * Keeps every time-dependent authority and reference consumer coherent in Workspace scenarios
 */
final class WorkspaceTestClock
{
    /**
     * Creates the real routed application after configuring fixed security time
     *
     * @return App<Container>
     */
    public static function application(DateTimeImmutable $time, ?Connection $connection = null): App
    {
        $container = require dirname(__DIR__, 2).'/config/services.php';
        if ($connection !== null) {
            $container->set(Connection::class, static fn(): Connection => $connection);
        }
        self::configure($container, $time);
        AppFactory::setContainer($container);
        AppFactory::setResponseFactory(new ResponseFactory());
        /** @var App<Container> $app */
        $app = AppFactory::create();
        $container->set(App::class, static fn(): App => $app);
        require dirname(__DIR__, 2).'/config/routes.php';
        require dirname(__DIR__, 2).'/config/middleware.php';

        return $app;
    }

    /**
     * Configures fixed time before resolving invocation-dependent services
     */
    public static function configure(Container $container, DateTimeImmutable $time): void
    {
        $clock = new AccessClock($time);
        $container->set(Clock::class, static fn(): Clock => $clock);
        $container->set(AccessJwt::class, static fn(): AccessJwt => new AccessJwt(
            new AccessKeyring((string) getenv('APP_ACCESS_TOKEN_CONFIG'), $clock),
            $clock
        ));
        $container->set(AuthenticateAccessToken::class, static fn(Container $c): AuthenticateAccessToken =>
            new AuthenticateAccessToken(
                $c->get(AccessJwt::class),
                $c->get(UserRepository::class),
                $c->get(RefreshSessionRepository::class),
                $clock
            ));
        $container->set(AuthoritativePrincipalResolver::class, static fn(
            Container $c
        ): AuthoritativePrincipalResolver =>
            new AuthoritativePrincipalResolver(
                $c->get(UserRepository::class),
                $c->get(RefreshSessionRepository::class),
                $c->get(RoleRepository::class),
                $c->get(PermissionRepository::class),
                $clock
            ));
    }
}
