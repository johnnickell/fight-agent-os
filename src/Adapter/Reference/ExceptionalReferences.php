<?php

declare(strict_types=1);

namespace App\Adapter\Reference;

use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use Fight\Common\Domain\Value\Identifier\Uuid;

/**
 * Class ExceptionalReferences
 *
 * Generates non-reserved technical references after atomic bounded actor admission
 */
final readonly class ExceptionalReferences
{
    /**
     * Constructs ExceptionalReferences
     */
    public function __construct(private Connection $connection, private Clock $clock)
    {
    }

    /**
     * Generates a reference after consuming one actor attempt without storing output or private request data
     */
    public function generate(UserId $actorId): string
    {
        $this->connection->transactional(function () use ($actorId): void {
            $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24130, 2)');
            $timestamp = $this->clock->now()->getTimestamp();
            $window = intdiv($timestamp, 60) * 60;
            $retryAfter = 60 - ($timestamp - $window);
            $this->connection->executeStatement(
                'DELETE FROM reference_generation_limits WHERE window_start < ?',
                [$window]
            );
            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM reference_generation_limits WHERE actor_id = ?',
                [$actorId->toString()]
            ) !== false;
            if (
                !$exists
                && (int) $this->connection->fetchOne('SELECT count(*) FROM reference_generation_limits') >= 10000
            ) {
                throw new RateLimitExceeded(retryDelaySeconds: $retryAfter);
            }
            $count = $this->connection->fetchOne(<<<'SQL'
INSERT INTO reference_generation_limits (actor_id, window_start, attempts) VALUES (?, ?, 1)
ON CONFLICT (actor_id) DO UPDATE SET attempts = reference_generation_limits.attempts + 1
WHERE reference_generation_limits.attempts < 10 RETURNING attempts
SQL, [$actorId->toString(), $window]);
            if ($count === false) {
                throw new RateLimitExceeded(retryDelaySeconds: $retryAfter);
            }
        });

        return Uuid::comb()->toString();
    }
}
