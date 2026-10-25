<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Application\Failure\StateConflict;
use App\Application\Security\PasswordChange\ChangePassword;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionConflictException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Throwable;

/**
 * Class PasswordChangeRaceWorker
 *
 * Runs independent package transactions against held production authority fences
 */
final class PasswordChangeRaceWorker
{
    /**
     * Exchanges readiness and safe dispositions only over private process pipes
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
            $connection->executeStatement("SET lock_timeout = '10s'");
            $connection->executeStatement("SET statement_timeout = '15s'");
            putenv('DATABASE_URL='.$url);
            $container = require dirname(__DIR__, 2).'/config/services.php';
            $container->set(Connection::class, static fn(): Connection => $connection);
            $clock = new AccessClock(new \DateTimeImmutable($input['now']));
            $container->set(Clock::class, static fn(): Clock => $clock);
            echo $connection->fetchOne('SELECT pg_backend_pid()')."\n";
            flush();
            if (trim((string) fgets(STDIN)) !== 'go') {
                throw new \RuntimeException('Barrier not released.');
            }
            try {
                $authentication = $container->get(AuthenticationService::class);
                if ($input['mode'] === 'refresh') {
                    $authentication->refresh($input['refresh']);
                } elseif (in_array($input['mode'], ['revoke', 'other-revoke'], true)) {
                    $authentication->logout($input['refresh']);
                } elseif ($input['mode'] === 'authority') {
                    $container->get(Connection::class)->transactional(function () use ($container, $input): void {
                        $users = $container->get(UserRepository::class);
                        $user = $users->getById(UserId::fromString($input['user']));
                        if (!$user instanceof User) {
                            throw new StateConflict();
                        }
                        $replacement = clone $user;
                        $replacement->advanceAuthenticationAuthorityRevision();
                        if (!$users->replaceAuthenticationAuthority($user, $replacement)) {
                            throw new StateConflict();
                        }
                    });
                } else {
                    $container->get(ChangePassword::class)->change(
                        UserId::fromString($input['user']),
                        $input['current'],
                        $input['password']
                    );
                }
                echo "committed\n";
            } catch (StateConflict | RefreshSessionNotFoundException | RefreshSessionConflictException) {
                echo "conflict\n";
            }
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }
}
