<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\RequestPasswordReset;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Throwable;

/**
 * Class ResetRequestRaceWorker
 *
 * Competes through the configured package bus using a separate guarded PostgreSQL connection
 */
final class ResetRequestRaceWorker
{
    /**
     * Reports command readiness and waits for the shared start before competing
     */
    public static function run(): void
    {
        $exitCode = 0;
        try {
            $input = json_decode(
                self::readProtocolLine(STDIN, hrtime(true) + 15_000_000_000, 256),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $url = (string) getenv('TEST_DATABASE_URL');
            $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
            $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
            $connection = DriverManager::getConnection(new DsnParser([
                'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
            ])->parse($url));
            $guard->assertConnected($connection, $expected);
            $connection->executeStatement("SET lock_timeout = '5s'");
            $connection->executeStatement("SET statement_timeout = '10s'");
            $container = require dirname(__DIR__, 2).'/config/services.php';
            $container->set(Connection::class, static fn(): Connection => $connection);
            $clock = new AccessClock(new DateTimeImmutable($input['now']));
            $container->set(Clock::class, static fn(): Clock => $clock);
            $bus = $container->get(CommandBus::class);
            $command = new RequestPasswordReset(EmailAddress::fromString('reset@example.test'));
            echo 'ready '.$connection->fetchOne('SELECT pg_backend_pid()')."\n";
            flush();
            if (self::readProtocolLine(STDIN, hrtime(true) + 15_000_000_000, 6) !== "start\n") {
                throw new \RuntimeException('Missing race start.');
            }
            $bus->execute($command);
            echo "committed\n";
        } catch (Throwable) {
            echo "failed\n";
            $exitCode = 1;
        } finally {
            if (isset($connection)) {
                $connection->close();
            }
        }
        exit($exitCode);
    }

    /**
     * Reads one bounded race-protocol line without blocking on PHP process pipes
     *
     * @param resource $stream
     * @param integer $deadline
     * @param integer $maximumBytes
     *
     * @return string
     */
    public static function readProtocolLine($stream, int $deadline, int $maximumBytes): string
    {
        if (!stream_set_blocking($stream, false)) {
            throw new \RuntimeException('Cannot read race protocol nonblockingly.');
        }
        $line = '';
        while (hrtime(true) < $deadline) {
            // Read only this line: a subsequent disposition must remain for the collector.
            $byte = fread($stream, 1);
            if ($byte === false || ($byte === '' && feof($stream))) {
                throw new \RuntimeException('Race protocol closed before a complete line.');
            }
            if ($byte === '') {
                usleep(1000);
                continue;
            }
            $line .= $byte;
            if ($byte === "\n") {
                return $line;
            }
            if (strlen($line) >= $maximumBytes) {
                throw new \RuntimeException('Race protocol line exceeded its byte limit.');
            }
        }
        throw new \RuntimeException('Race protocol deadline exceeded.');
    }
}
