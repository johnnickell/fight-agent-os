<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Adapter\Persistence\PasswordResetGrantRecords;
use App\Adapter\Persistence\PostgresAtomicOperation;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\DueCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;
use Throwable;

/**
 * Class PostgresPasswordResetGrantRepository
 *
 * Persists purpose-specific reset generations on the caller's PostgreSQL transaction
 *
 * Per-user advisory locks serialize the latest-generation decision, while constraints arbitrate identities,
 * ownership, and historical digest claims. Savepoints ensure known conflicts never leave partial succession.
 */
final readonly class PostgresPasswordResetGrantRepository implements PasswordResetGrantRepository
{
    /**
     * Constructs PostgresPasswordResetGrantRepository
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function findDue(DateTimeImmutable $at, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT g.* FROM password_reset_grants g
WHERE g.generation = (SELECT MAX(other.generation) FROM password_reset_grants other WHERE other.user_id = g.user_id)
  AND g.delivery_ciphertext IS NOT NULL AND ? < g.delivery_expires_at
  AND ((g.delivery_status IN ('pending', 'retry_pending') AND g.delivery_due_at <= ?)
    OR (g.delivery_status = 'claimed' AND g.delivery_lease_until <= ?))
ORDER BY CASE WHEN g.delivery_status = 'claimed' THEN g.delivery_lease_until ELSE g.delivery_due_at END,
    g.delivery_id
LIMIT ?
SQL,
            [$this->date($at), $this->date($at), $this->date($at), $limit],
            [
                \Doctrine\DBAL\ParameterType::STRING,
                \Doctrine\DBAL\ParameterType::STRING,
                \Doctrine\DBAL\ParameterType::STRING,
                \Doctrine\DBAL\ParameterType::INTEGER
            ]
        );

        return array_map(static function (array $row): DueCredentialDelivery {
            $grant = PasswordResetGrantRecords::hydrate($row);
            $delivery = $grant->getDelivery();

            return new DueCredentialDelivery(
                $grant->purpose(),
                $delivery->getId(),
                $grant->getUserId(),
                $delivery->getNextAttemptAt(),
                $grant->getRevision(),
                $delivery->getStatus()
            );
        }, $rows);
    }

    /**
     * @inheritDoc
     */
    public function getById(PasswordResetGrantId $passwordResetGrantId): ?PasswordResetGrant
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM password_reset_grants WHERE id = ?',
            [$passwordResetGrantId->toString()]
        );

        return $row === false ? null : PasswordResetGrantRecords::hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function getByDeliveryId(PasswordResetDeliveryId $passwordResetDeliveryId): ?PasswordResetGrant
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM password_reset_grants WHERE delivery_id = ?',
            [$passwordResetDeliveryId->toString()]
        );

        return $row === false ? null : PasswordResetGrantRecords::hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function getLatestByUserId(UserId $userId): ?PasswordResetGrant
    {
        $row = $this->latestRow($userId);

        return $row === false ? null : PasswordResetGrantRecords::hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function add(PasswordResetGrant $passwordResetGrant): bool
    {
        $this->requireTransaction();
        if (!$this->pristine($passwordResetGrant)) {
            return false;
        }
        $this->hold($passwordResetGrant->getUserId());
        if ($this->latestRow($passwordResetGrant->getUserId()) !== false) {
            return false;
        }

        return $this->insertSafely($passwordResetGrant, 0);
    }

    /**
     * @inheritDoc
     */
    public function appendAfterTerminal(PasswordResetGrant $terminalPredecessor, PasswordResetGrant $successor): bool
    {
        $this->requireTransaction();
        $this->hold($terminalPredecessor->getUserId());
        $row = $this->latestRow($terminalPredecessor->getUserId(), true);
        if (
            $row === false || !$this->sameState(PasswordResetGrantRecords::hydrate($row), $terminalPredecessor)
            || $terminalPredecessor->isIssued() || $terminalPredecessor->getDelivery()->isRecoverable()
            || !$this->successorValid($terminalPredecessor, $successor)
        ) {
            return false;
        }

        return $this->insertSafely($successor, (int) $row['generation'] + 1);
    }

    /**
     * @inheritDoc
     */
    public function replace(PasswordResetGrant $predecessor, PasswordResetGrant $replacement): bool
    {
        $this->requireTransaction();
        $this->hold($predecessor->getUserId());
        $row = $this->latestRow($predecessor->getUserId(), true);
        if (
            $row === false || !$this->sameState(PasswordResetGrantRecords::hydrate($row), $predecessor)
            || !$this->allowedReplacement($predecessor, $replacement)
        ) {
            return false;
        }

        return $this->update($predecessor, $replacement);
    }

    /**
     * @inheritDoc
     */
    public function replaceWithSuccessor(
        PasswordResetGrant $predecessor,
        PasswordResetGrant $terminalPredecessor,
        PasswordResetGrant $successor
    ): bool {
        $this->requireTransaction();
        $this->hold($predecessor->getUserId());
        $row = $this->latestRow($predecessor->getUserId(), true);
        if (
            $row === false || !$this->sameState(PasswordResetGrantRecords::hydrate($row), $predecessor)
            || $terminalPredecessor->isIssued() || $terminalPredecessor->getDelivery()->isRecoverable()
            || !$this->allowedReplacement($predecessor, $terminalPredecessor)
            || !$this->successorValid($predecessor, $successor)
        ) {
            return false;
        }

        try {
            return PostgresAtomicOperation::execute($this->connection, function () use (
                $predecessor,
                $terminalPredecessor,
                $successor,
                $row
            ): bool {
                if (!$this->update($predecessor, $terminalPredecessor)) {
                    return false;
                }
                $this->insert($successor, (int) $row['generation'] + 1);

                return true;
            });
        } catch (UniqueConstraintViolationException | ForeignKeyConstraintViolationException) {
            return false;
        }
    }

    /**
     * Requires an active transaction for a state transition
     */
    private function requireTransaction(): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Password-reset persistence requires an enclosing transaction.');
        }
    }

    /**
     * Acquires the authoritative record lock for the current transaction
     */
    private function hold(UserId $userId): void
    {
        $this->connection->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, ?))',
            [$userId->toString(), 24123]
        );
    }

    /**
     * Fetches the latest reset grant row for a user
     *
     * @return array<string, mixed>|false
     */
    private function latestRow(UserId $userId, bool $lock = false): array|false
    {
        $sql = 'SELECT * FROM password_reset_grants WHERE user_id = ? ORDER BY generation DESC, id DESC LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        return $this->connection->fetchAssociative($sql, [$userId->toString()]);
    }

    /**
     * Inserts a grant while handling uniqueness conflicts
     */
    private function insertSafely(PasswordResetGrant $grant, int $generation): bool
    {
        try {
            PostgresAtomicOperation::execute($this->connection, fn() => $this->insert($grant, $generation));

            return true;
        } catch (UniqueConstraintViolationException | ForeignKeyConstraintViolationException) {
            return false;
        }
    }

    /**
     * Inserts a grant record
     */
    private function insert(PasswordResetGrant $grant, int $generation): void
    {
        $this->connection->insert(
            'password_reset_grants',
            PasswordResetGrantRecords::fields($grant) + ['generation' => $generation]
        );
    }

    /**
     * Updates a grant record
     */
    private function update(PasswordResetGrant $before, PasswordResetGrant $after): bool
    {
        $fields = PasswordResetGrantRecords::fields($after);
        unset(
            $fields['id'],
            $fields['user_id'],
            $fields['credential_digest'],
            $fields['expires_at'],
            $fields['delivery_id'],
            $fields['delivery_email'],
            $fields['delivery_expires_at']
        );

        return $this->connection->update(
            'password_reset_grants',
            $fields,
            ['id' => $before->getId()->toString(), 'revision' => $before->getRevision()]
        ) === 1;
    }

    /**
     * Checks whether a grant is unchanged since issuance
     */
    private function pristine(PasswordResetGrant $grant): bool
    {
        $delivery = $grant->getDelivery();

        return $grant->getRevision() === 0 && $grant->isIssued() && $delivery->isPristine()
            && $delivery->getUserId()->equals($grant->getUserId())
            && $delivery->getExpiresAt() == $grant->getExpiresAt()
            && preg_match('/^[0-9a-f]{64}$/D', $grant->getCredentialHash()) === 1;
    }

    /**
     * Checks that the successor belongs to the expected grant generation
     */
    private function successorValid(PasswordResetGrant $before, PasswordResetGrant $after): bool
    {
        return $this->pristine($after) && $before->getUserId()->equals($after->getUserId())
            && !$before->getId()->equals($after->getId())
            && !$before->getDelivery()->getId()->equals($after->getDelivery()->getId());
    }

    /**
     * Checks that persisted and expected grant states match
     */
    private function sameState(PasswordResetGrant $left, PasswordResetGrant $right): bool
    {
        return $left->getId()->equals($right->getId())
            && $left->getUserId()->equals($right->getUserId())
            && $left->getCredentialHash() === $right->getCredentialHash()
            && $left->getExpiresAt() == $right->getExpiresAt()
            && $left->getConsumedAt() == $right->getConsumedAt()
            && $left->getRevokedAt() == $right->getRevokedAt()
            && $left->getRevision() === $right->getRevision()
            && $left->getDelivery()->sameStateAs($right->getDelivery());
    }

    /**
     * Checks that the proposed grant transition is allowed
     */
    private function allowedReplacement(PasswordResetGrant $before, PasswordResetGrant $after): bool
    {
        if (
            !$before->getId()->equals($after->getId()) || !$before->getUserId()->equals($after->getUserId())
            || $before->getCredentialHash() !== $after->getCredentialHash()
            || $before->getExpiresAt() != $after->getExpiresAt()
            || !$before->getDelivery()->getId()->equals($after->getDelivery()->getId())
            || !$before->getDelivery()->getUserId()->equals($after->getDelivery()->getUserId())
            || $before->getDelivery()->getEmail()->canonical() !== $after->getDelivery()->getEmail()->canonical()
            || $before->getDelivery()->getExpiresAt() != $after->getDelivery()->getExpiresAt()
            || $after->getRevision() !== $before->getRevision() + 1
        ) {
            return false;
        }

        try {
            if ($before->isIssued() && $after->isIssued()) {
                $delivery = $before->getDelivery();
                $next = $after->getDelivery();
                $expected = match ($next->getStatus()) {
                    CredentialDeliveryStatus::CLAIMED => $before->claimDelivery(
                        $next->getClaimToken(),
                        $next->getClaimedAt(), $next->getLeaseUntil()
                    ),
                    CredentialDeliveryStatus::RETRY_PENDING => $before->failDelivery(
                        $delivery->getClaimToken(),
                        $next->getLastOutcomeAt(), $next->getLastFailure()
                    ),
                    CredentialDeliveryStatus::PENDING => $before->requestDeliveryRetry(),
                    CredentialDeliveryStatus::DELIVERED => $before->confirmDelivery(
                        $delivery->getClaimToken(),
                        $next->getLastOutcomeAt()
                    ),
                    CredentialDeliveryStatus::PERMANENT_FAILURE => $before->failDeliveryPermanently(
                        $delivery->getClaimToken(),
                        $next->getLastOutcomeAt()
                    ),
                    CredentialDeliveryStatus::EXPIRED => match (true) {
                        $delivery->getStatus() === CredentialDeliveryStatus::CLAIMED
                            && $next->getLastOutcomeAt() !== null && $next->getLastFailure() !== null
                            => $before->failDelivery(
                                $delivery->getClaimToken(),
                                $next->getLastOutcomeAt(), $next->getLastFailure()
                            ),
                        default => $before->expireDeliveryAt($next->getExpiresAt())
                    },
                    CredentialDeliveryStatus::INVALIDATED => $before->invalidateDelivery(),
                };
            } elseif ($before->isIssued() && ($after->isConsumed() xor $after->isRevoked())) {
                $at = $after->isConsumed() ? $after->getConsumedAt() : $after->getRevokedAt();
                $expected = $after->isConsumed() ? $before->consume($at) : $before->revoke($at);
            } else {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return $this->sameState($expected, $after);
    }

    /**
     * Formats a date for PostgreSQL
     */
    private function date(DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s.uP');
    }
}
