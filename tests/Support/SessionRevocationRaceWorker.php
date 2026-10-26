<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Command\RevokeSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Event\RefreshSessionRevoked;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionConflictException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Utility\ClassName;
use RuntimeException;
use Throwable;

/**
 * Class SessionRevocationRaceWorker
 *
 * Holds a genuine package command after its independent PostgreSQL target snapshot
 */
final readonly class SessionRevocationRaceWorker implements RefreshSessionRepository
{
    /**
     * Constructs SessionRevocationRaceWorker
     */
    public function __construct(private RefreshSessionRepository $delegate)
    {
    }

    /**
     * Executes one bounded contender without exporting credentials or exception details
     */
    public static function run(): void
    {
        try {
            $input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
            $url = (string) getenv('TEST_DATABASE_URL');
            $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
            $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
            putenv('DATABASE_URL='.$url);
            $container = require dirname(__DIR__, 2).'/config/services.php';
            $connection = $container->get(Connection::class);
            $guard->assertConnected($connection, $expected);
            $connection->executeStatement("SET statement_timeout = '10s'");
            $clock = new AccessClock(new DateTimeImmutable($input['time']));
            $container->set(Clock::class, static fn(): Clock => $clock);
            $repository = new self($container->get(RefreshSessionRepository::class));
            $container->set(RefreshSessionRepository::class, static fn(): RefreshSessionRepository => $repository);
            $events = 0;
            $container->get(EventDispatcher::class)->addHandler(
                ClassName::underscore(RefreshSessionRevoked::class),
                static function () use (&$events): void {
                    ++$events;
                }
            );
            try {
                $container->get(CommandBus::class)->execute(new RevokeSession(
                    UserId::fromString($input['actor']),
                    RefreshSessionId::fromString($input['current']),
                    RefreshSessionId::fromString($input['target']),
                    null
                ));
                $status = 200;
            } catch (RefreshSessionConflictException) {
                $status = 409;
            }
            echo json_encode(['status' => $status, 'events' => $events], JSON_THROW_ON_ERROR)."\n";
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }

    /**
     * @inheritDoc
     */
    public function getById(RefreshSessionId $id): ?RefreshSession
    {
        $snapshot = $this->delegate->getById($id);
        echo "ready\n";
        flush();
        if (trim((string) fgets(STDIN)) !== 'go') {
            throw new RuntimeException('Barrier was not released.');
        }

        return $snapshot;
    }

    /**
     * @inheritDoc
     */
    public function getByUserId(UserId $userId, DateTimeImmutable $at, Pagination $pagination): ResultSet
    {
        return $this->delegate->getByUserId($userId, $at, $pagination);
    }

    /**
     * @inheritDoc
     */
    public function getAllActiveByUserId(UserId $userId, DateTimeImmutable $at): array
    {
        return $this->delegate->getAllActiveByUserId($userId, $at);
    }

    /**
     * @inheritDoc
     */
    public function getByCredential(RefreshCredential $refreshCredential): ?RefreshSession
    {
        return $this->delegate->getByCredential($refreshCredential);
    }

    /**
     * @inheritDoc
     */
    public function getByUsedCredential(RefreshCredential $refreshCredential): ?RefreshSession
    {
        return $this->delegate->getByUsedCredential($refreshCredential);
    }

    /**
     * @inheritDoc
     */
    public function replace(RefreshSession $expected, RefreshSession $replacement): bool
    {
        return $this->delegate->replace($expected, $replacement);
    }
}
