<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Invitation;

use App\Application\Failure\RateLimited;
use App\Application\Invitation\InvitationRateLimiter;
use App\Domain\Invitation\InvitationOperation;
use App\Domain\Invitation\InvitationRatePolicy;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;

/**
 * Class PostgresInvitationRateLimiter
 *
 * Serializes bounded fixed-window admission across processes without private target keys
 */
final readonly class PostgresInvitationRateLimiter implements InvitationRateLimiter
{
    /**
     * Constructs PostgresInvitationRateLimiter
     */
    public function __construct(
        private Connection $connection,
        private Clock $clock,
        private InvitationRatePolicy $policy
    ) {
    }

    /**
     * @inheritDoc
     */
    public function consume(UserId $actorId, InvitationOperation $operation): void
    {
        if ($this->connection->isTransactionActive()) {
            throw new LogicException('Invitation admission requires an independent transaction.');
        }
        $now = $this->clock->now()->getTimestamp();
        $end = (int) (floor($now / $this->policy->windowSeconds) + 1) * $this->policy->windowSeconds;
        $retry = $this->connection->transactional(function () use ($actorId, $operation, $now, $end): int {
            // One short global admission lock also makes cleanup and the total row cap atomic.
            $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24121, 41)');
            $this->connection->executeStatement('DELETE FROM invitation_rate_limits WHERE window_end <= ?', [$now]);
            $row = $this->connection->fetchAssociative(
                'SELECT attempts, window_end FROM invitation_rate_limits WHERE actor_id = ? AND operation = ?',
                [$actorId->toString(), $operation->value]
            );
            if ($row !== false) {
                if ((int) $row['attempts'] >= $this->policy->limit($operation)) {
                    return max(1, min(86400, (int) $row['window_end'] - $now));
                }
                $this->connection->executeStatement(
                    'UPDATE invitation_rate_limits SET attempts = attempts + 1 WHERE actor_id = ? AND operation = ?',
                    [$actorId->toString(), $operation->value]
                );
            } else {
                $count = (int) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits');
                if ($count >= $this->policy->capacity) {
                    $earliest = (int) $this->connection->fetchOne('SELECT min(window_end) FROM invitation_rate_limits');

                    return max(1, min(86400, $earliest - $now));
                }
                $this->connection->insert('invitation_rate_limits', [
                    'actor_id'   => $actorId->toString(),
                    'operation'  => $operation->value,
                    'window_end' => $end,
                    'attempts'   => 1
                ]);
            }

            return 0;
        });
        if ($retry > 0) {
            throw new RateLimited($retry);
        }
    }
}
