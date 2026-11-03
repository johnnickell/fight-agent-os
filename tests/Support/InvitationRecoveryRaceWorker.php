<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\ResendInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\RetryInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotResendableException;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotRetryableException;
use Fight\AccessControl\Domain\AccessControl\User\Command\CorrectPendingInvitation;
use Fight\AccessControl\Domain\AccessControl\User\Exception\PendingInvitationCorrectionException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
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
            // These trusted fixtures qualify persistence races; real HTTP admission is tested separately.
            $actor = UserId::fromString($input['actor']);
            $target = UserId::fromString($input['target']);
            $command = match ($input['operation']) {
                'retry' => new RetryInvitationDelivery($actor->toString(), $target),
                'resend' => new ResendInvitationDelivery($actor->toString(), $target),
                'email' => new CorrectPendingInvitation($actor, $target,
                    EmailAddress::fromString('race-corrected@example.test')),
                default => throw new RuntimeException('Unsupported race operation.')
            };
            try {
                $container->get(CommandBus::class)->execute($command);
                echo "committed\n";
            } catch (ActivationDeliveryNotRetryableException | ActivationDeliveryNotResendableException) {
                echo "conflict\n";
            } catch (PendingInvitationCorrectionException) {
                echo "conflict\n";
            } catch (LogicException $failure) {
                if (
                    $failure::class !== LogicException::class || !in_array($failure->getMessage(), [
                    'The pending invitation email changed concurrently or is reserved.',
                    'The activation grant changed concurrently.'
                    ], true)
                ) {
                    throw $failure;
                }
                echo "conflict\n";
            }
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }
}
