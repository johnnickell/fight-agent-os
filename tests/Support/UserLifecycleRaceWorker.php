<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\User\Command\DeleteUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\DisableUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\EnableUser;
use Fight\AccessControl\Domain\AccessControl\User\Exception\UserLifecycleException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use RuntimeException;
use Throwable;

/**
 * Class UserLifecycleRaceWorker
 *
 * Pauses a public lifecycle command after its fenced snapshot without replacing persistence or transactions
 */
final class UserLifecycleRaceWorker implements Clock
{
    /**
     * Constructs UserLifecycleRaceWorker
     */
    public function __construct(private readonly DateTimeImmutable $time, private bool $pause)
    {
    }

    /**
     * Executes one independent guarded PostgreSQL contender with bounded barriers and safe output
     */
    public static function run(): void
    {
        try {
            stream_set_timeout(STDIN, 15);
            $input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
            $url = (string) getenv('TEST_DATABASE_URL');
            $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
            $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
            putenv('DATABASE_URL='.$url);
            $container = require dirname(__DIR__, 2).'/config/services.php';
            $connection = $container->get(Connection::class);
            $guard->assertConnected($connection, $expected);
            $connection->executeStatement("SET statement_timeout = '10s'");
            $clock = new self(new DateTimeImmutable($input['time']), $input['pause']);
            $container->set(Clock::class, static fn(): Clock => $clock);
            echo $connection->fetchOne('SELECT pg_backend_pid()')."\n";
            flush();
            $actor = UserId::fromString($input['actor']);
            $target = UserId::fromString($input['target']);
            try {
                if ($input['operation'] === 'disable') {
                    $command = new DisableUser($actor, $target);
                } elseif ($input['operation'] === 'delete') {
                    $command = new DeleteUser($actor, $target);
                } else {
                    $command = new EnableUser($actor, $target);
                }
                $container->get(CommandBus::class)->execute($command);
                echo "success\n";
            } catch (UserLifecycleException) {
                echo "conflict\n";
            } catch (\LogicException $failure) {
                if (
                    $failure::class !== \LogicException::class
                    || $failure->getMessage() !== 'The user lifecycle state changed concurrently.'
                ) {
                    throw $failure;
                }
                echo "conflict\n";
            }
            $connection->close();
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }

    /**
     * Coordinates the first handler at its clock call after loading the real fenced snapshot
     */
    public function now(): DateTimeImmutable
    {
        if ($this->pause) {
            $this->pause = false;
            echo "snapshot\n";
            flush();
            if (trim((string) fgets(STDIN)) !== 'go') {
                throw new RuntimeException('Lifecycle barrier was not released.');
            }
        }

        return $this->time;
    }
}
