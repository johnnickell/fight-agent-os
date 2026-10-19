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
 * Atomically admits actor/action and optional actor/action/target budgets within a global capacity
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
    public function consume(UserId $actorId, InvitationOperation $operation, ?UserId $targetId = null): void
    {
        if ($this->connection->isTransactionActive()) {
            throw new LogicException('Invitation admission requires an independent transaction.');
        }
        $this->connection->transactional(function () use ($actorId, $operation, $targetId): void {
            $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24121, 41)');
            $now = $this->clock->now()->getTimestamp();
            $end = (int) (floor($now / $this->policy->windowSeconds) + 1) * $this->policy->windowSeconds;
            $this->connection->executeStatement('DELETE FROM invitation_rate_limits WHERE window_end <= ?', [$now]);
            $scopes = $targetId === null ? [''] : ['', $targetId->toString()];
            $missing = 0;
            foreach ($scopes as $scope) {
                $row = $this->connection->fetchAssociative(
                    <<<'SQL'
SELECT attempts, window_end FROM invitation_rate_limits
WHERE actor_id = ? AND operation = ? AND target_scope = ?
SQL,
                    [$actorId->toString(), $operation->value, $scope]
                );
                if ($row === false) {
                    ++$missing;
                } elseif ((int) $row['attempts'] >= $this->policy->limit($operation)) {
                    throw new RateLimited(max(1, min(86400, (int) $row['window_end'] - $now)));
                }
            }
            $count = (int) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits');
            if ($count + $missing > $this->policy->capacity) {
                $earliest = $this->connection->fetchOne('SELECT min(window_end) FROM invitation_rate_limits');
                throw new RateLimited(max(1, min(86400, ($earliest === null ? $end : (int) $earliest) - $now)));
            }
            foreach ($scopes as $scope) {
                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO invitation_rate_limits (actor_id, operation, target_scope, window_end, attempts) VALUES (?, ?, ?, ?, 1)
ON CONFLICT (actor_id, operation, target_scope) DO UPDATE SET attempts = invitation_rate_limits.attempts + 1
SQL,
                    [$actorId->toString(), $operation->value, $scope, $end]
                );
            }
        });
    }
}
