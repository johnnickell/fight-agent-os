<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Application\Failure\StateConflict;
use App\Application\Invitation\RecoverInvitation;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use RuntimeException;
use Throwable;

/**
 * Class InvitationRecoveryRaceWorker
 *
 * Exercises production recovery through independent guarded PostgreSQL connections
 */
final class InvitationRecoveryRaceWorker
{
    /**
     * Runs one bounded synchronized operation without printing credentials or exception diagnostics
     */
    public static function run(): void
    {
        try {
            $input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
            $url = (string) getenv('TEST_DATABASE_URL');
            $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
            $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
            $connection = DriverManager::getConnection(new DsnParser([
                'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
            ])->parse($url));
            $guard->assertConnected($connection, $expected);
            $connection->executeStatement("SET statement_timeout = '10s'");
            $connection->executeStatement("SET lock_timeout = '5s'");
            putenv('DATABASE_URL='.$url);
            $container = require dirname(__DIR__, 2).'/config/services.php';
            $container->set(Connection::class, static fn(): Connection => $connection);
            $clock = new AccessClock(new DateTimeImmutable($input['now']));
            $container->set(Clock::class, static fn(): Clock => $clock);
            $container->set(AuthoritativePrincipalResolver::class, static fn(
                Container $c
            ): AuthoritativePrincipalResolver => new AuthoritativePrincipalResolver(
                $c->get(UserRepository::class),
                $c->get(RefreshSessionRepository::class),
                $c->get(RoleRepository::class),
                $c->get(PermissionRepository::class),
                $clock
            ));
            $inner = $container->get(ActivationGrantRepository::class);
            $waited = false;
            $barrier = new RecoveryGrantBarrier($inner, static function () use ($connection, &$waited): void {
                if ($waited || !$connection->isTransactionActive()) {
                    return;
                }
                $waited = true;
                echo "ready\n";
                flush();
                if (trim((string) fgets(STDIN)) !== 'go') {
                    throw new RuntimeException('The transaction barrier was not released.');
                }
            });
            $container->set(ActivationGrantRepository::class, static fn(): ActivationGrantRepository => $barrier);
            // Post-commit coordination is optional; lost dispatch must remain discoverable from durable work.
            $events = new ServiceAwareEventDispatcher($container);
            $container->set(EventDispatcher::class, static fn(): EventDispatcher => $events);
            $recovery = $container->get(RecoverInvitation::class);
            $authentication = new AuthenticationContext(
                UserId::fromString($input['actor']),
                RefreshSessionId::fromString($input['session']),
                $input['version']
            );
            $target = UserId::fromString($input['target']);
            try {
                match ($input['operation']) {
                    'retry' => $recovery->retry($authentication, $target),
                    'resend' => $recovery->resend($authentication, $target),
                    'email' => $recovery->correct($authentication, $target,
                        EmailAddress::fromString('race-corrected@example.test')),
                    default => throw new RuntimeException('Unsupported race operation.')
                };
                echo "committed\n";
            } catch (StateConflict) {
                echo "conflict\n";
            }
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }
}
