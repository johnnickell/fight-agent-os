<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Application\Failure\StateConflict;
use App\Application\Security\PasswordReset\CompletePasswordReset;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\RequestPasswordReset;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Throwable;

/**
 * Class ResetCompletionRaceWorker
 *
 * Exercises real separate transactions behind the production per-user authority fence
 */
final class ResetCompletionRaceWorker
{
    /**
     * Exchanges only bounded readiness and dispositions over process pipes
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
                if ($input['mode'] === 'request') {
                    $container->get(CommandBus::class)->execute(new RequestPasswordReset(
                        EmailAddress::fromString('reset@example.test')
                    ));
                } else {
                    $container->get(CompletePasswordReset::class)->complete(
                        UserId::fromString($input['user']),
                        $input['credential'],
                        $input['password']
                    );
                }
                echo "committed\n";
            } catch (StateConflict) {
                echo "conflict\n";
            } catch (\LogicException) {
                echo "superseded\n";
            }
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }
}
