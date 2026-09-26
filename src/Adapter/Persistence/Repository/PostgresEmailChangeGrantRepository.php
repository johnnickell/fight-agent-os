<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Adapter\Persistence\EmailChangeGrantRecords;
use App\Adapter\Persistence\PostgresAtomicOperation;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\DueCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDeliveryId;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrant;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;
use Throwable;

/**
 * Persists purpose-separated email-change generations on the caller's transaction
 */
final readonly class PostgresEmailChangeGrantRepository implements EmailChangeGrantRepository
{
    /**
     * Constructs PostgresEmailChangeGrantRepository
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
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT g.* FROM email_change_grants g
WHERE g.generation = (SELECT MAX(other.generation) FROM email_change_grants other WHERE other.user_id = g.user_id)
  AND g.consumed_at IS NULL AND g.revoked_at IS NULL AND g.expired_at IS NULL
  AND g.delivery_ciphertext IS NOT NULL AND ? < g.delivery_expires_at
  AND ((g.delivery_status IN ('pending', 'retry_pending') AND g.delivery_due_at <= ?)
    OR (g.delivery_status = 'claimed' AND g.delivery_lease_until <= ?))
ORDER BY CASE WHEN g.delivery_status = 'claimed' THEN g.delivery_lease_until ELSE g.delivery_due_at END,
    g.delivery_id
LIMIT ?
SQL, [$this->date($at), $this->date($at), $this->date($at), $limit],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]);

        return array_map(static function (array $row): DueCredentialDelivery {
            $grant = EmailChangeGrantRecords::hydrate($row);
            $delivery = $grant->getDelivery();

            return new DueCredentialDelivery($grant->purpose(), $delivery->getId(), $grant->getUserId(),
                $delivery->getNextAttemptAt(), $grant->getRevision(), $delivery->getStatus());
        }, $rows);
    }

    /**
     * @inheritDoc
     */
    public function getByDeliveryId(EmailChangeDeliveryId $emailChangeDeliveryId): ?EmailChangeGrant
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM email_change_grants WHERE delivery_id = ?', [$emailChangeDeliveryId->toString()]);

        return $row === false ? null : EmailChangeGrantRecords::hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function getLatestByUserId(UserId $userId): ?EmailChangeGrant
    {
        $row = $this->latestRow($userId);

        return $row === false ? null : EmailChangeGrantRecords::hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function add(EmailChangeGrant $emailChangeGrant): bool
    {
        $this->requireTransaction();
        if (!$this->pristine($emailChangeGrant)) {
            return false;
        }
        $this->hold($emailChangeGrant->getUserId());
        if ($this->latestRow($emailChangeGrant->getUserId()) !== false) {
            return false;
        }

        return $this->insertSafely($emailChangeGrant, 0);
    }

    /**
     * @inheritDoc
     */
    public function appendAfterTerminal(EmailChangeGrant $terminalPredecessor, EmailChangeGrant $successor): bool
    {
        $this->requireTransaction();
        $this->hold($terminalPredecessor->getUserId());
        $row = $this->latestRow($terminalPredecessor->getUserId(), true);
        if ($row === false || !$this->sameState(EmailChangeGrantRecords::hydrate($row), $terminalPredecessor)
            || $terminalPredecessor->isIssued() || $terminalPredecessor->getDelivery()->isRecoverable()
            || !$this->successorValid($terminalPredecessor, $successor)) {
            return false;
        }

        return $this->insertSafely($successor, (int) $row['generation'] + 1);
    }

    /**
     * @inheritDoc
     */
    public function replace(EmailChangeGrant $predecessor, EmailChangeGrant $replacement): bool
    {
        $this->requireTransaction();
        $this->hold($predecessor->getUserId());
        $row = $this->latestRow($predecessor->getUserId(), true);
        if ($row === false || !$this->sameState(EmailChangeGrantRecords::hydrate($row), $predecessor)
            || !$this->allowedReplacement($predecessor, $replacement)) {
            return false;
        }
        $fields = EmailChangeGrantRecords::fields($replacement);
        unset($fields['id'], $fields['user_id'], $fields['credential_digest'], $fields['expires_at'],
            $fields['delivery_id'], $fields['delivery_email'], $fields['delivery_expires_at']);

        return $this->connection->update('email_change_grants', $fields,
            ['id' => $predecessor->getId()->toString(), 'revision' => $predecessor->getRevision()]) === 1;
    }

    private function requireTransaction(): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Email-change persistence requires an enclosing transaction.');
        }
    }

    private function hold(UserId $userId): void
    {
        $this->connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(?, ?))', [$userId->toString(), 24124]);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function latestRow(UserId $userId, bool $lock = false): array|false
    {
        return $this->connection->fetchAssociative('SELECT * FROM email_change_grants WHERE user_id = ? '
            . 'ORDER BY generation DESC, id DESC LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), [$userId->toString()]);
    }

    private function insertSafely(EmailChangeGrant $grant, int $generation): bool
    {
        try {
            PostgresAtomicOperation::execute($this->connection,
                fn() => $this->connection->insert('email_change_grants', EmailChangeGrantRecords::fields($grant) + ['generation' => $generation]));

            return true;
        } catch (UniqueConstraintViolationException|ForeignKeyConstraintViolationException) {
            return false;
        }
    }

    private function pristine(EmailChangeGrant $grant): bool
    {
        $delivery = $grant->getDelivery();

        return $grant->getRevision() === 0 && $grant->isIssued() && $delivery->isPristine()
            && $delivery->getUserId()->equals($grant->getUserId())
            && $delivery->getExpiresAt() == $grant->getExpiresAt()
            && preg_match('/^[0-9a-f]{64}$/D', $grant->getCredentialHash()) === 1;
    }

    private function successorValid(EmailChangeGrant $before, EmailChangeGrant $after): bool
    {
        return $this->pristine($after) && $before->getUserId()->equals($after->getUserId())
            && !$before->getId()->equals($after->getId())
            && !$before->getDelivery()->getId()->equals($after->getDelivery()->getId());
    }

    private function sameState(EmailChangeGrant $left, EmailChangeGrant $right): bool
    {
        return $left->getId()->equals($right->getId()) && $left->getUserId()->equals($right->getUserId())
            && $left->getCredentialHash() === $right->getCredentialHash()
            && $left->getExpiresAt() == $right->getExpiresAt()
            && $left->getConsumedAt() == $right->getConsumedAt()
            && $left->getRevokedAt() == $right->getRevokedAt()
            && $left->getExpiredAt() == $right->getExpiredAt()
            && $left->getRevision() === $right->getRevision()
            && $left->getDelivery()->sameStateAs($right->getDelivery());
    }

    private function allowedReplacement(EmailChangeGrant $before, EmailChangeGrant $after): bool
    {
        if (!$before->getId()->equals($after->getId()) || !$before->getUserId()->equals($after->getUserId())
            || $before->getCredentialHash() !== $after->getCredentialHash()
            || $before->getExpiresAt() != $after->getExpiresAt()
            || !$before->getDelivery()->getId()->equals($after->getDelivery()->getId())
            || !$before->getDelivery()->getUserId()->equals($after->getDelivery()->getUserId())
            || $before->getDelivery()->getEmail()->canonical() !== $after->getDelivery()->getEmail()->canonical()
            || $before->getDelivery()->getExpiresAt() != $after->getDelivery()->getExpiresAt()
            || $after->getRevision() !== $before->getRevision() + 1) {
            return false;
        }

        try {
            if ($before->isIssued() && $after->isIssued()) {
                $delivery = $before->getDelivery();
                $next = $after->getDelivery();
                $expected = match ($next->getStatus()) {
                    CredentialDeliveryStatus::CLAIMED => $before->claimDelivery($next->getClaimToken(), $next->getClaimedAt(), $next->getLeaseUntil()),
                    CredentialDeliveryStatus::RETRY_PENDING => $before->failDelivery($delivery->getClaimToken(), $next->getLastOutcomeAt(), $next->getLastFailure()),
                    CredentialDeliveryStatus::PENDING => $before->requestDeliveryRetry(),
                    CredentialDeliveryStatus::DELIVERED => $before->confirmDelivery($delivery->getClaimToken(), $next->getLastOutcomeAt()),
                    CredentialDeliveryStatus::PERMANENT_FAILURE => $before->failDeliveryPermanently($delivery->getClaimToken(), $next->getLastOutcomeAt()),
                    CredentialDeliveryStatus::EXPIRED => $this->expectedExpiry($before, $after),
                    CredentialDeliveryStatus::INVALIDATED => throw new LogicException('Delivery invalidation must follow an authority transition.'),
                };
            } elseif ($before->isIssued() && $after->isConsumed()) {
                $expected = $before->consume($after->getConsumedAt());
            } elseif ($before->isIssued() && $after->isRevoked()) {
                $expected = $before->revoke($after->getRevokedAt());
            } elseif ($before->isIssued() && $after->isExpired()) {
                $expected = $before->expireAt($after->getExpiredAt());
            } else {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return $this->sameState($expected, $after);
    }

    private function expectedExpiry(EmailChangeGrant $before, EmailChangeGrant $after): EmailChangeGrant
    {
        $next = $after->getDelivery();
        $expired = $before->expireDeliveryAt($next->getExpiresAt());
        if ($this->sameState($expired, $after)) {
            return $expired;
        }

        // A live claim can also expire when package backoff crosses grant expiry.
        // Compare the entire result: retained failure evidence from an older claim
        // must not be mistaken for a new provider outcome.
        $delivery = $before->getDelivery();
        if ($delivery->getStatus() === CredentialDeliveryStatus::CLAIMED
            && $next->getLastOutcomeAt() !== null && $next->getLastFailure() !== null) {
            return $before->failDelivery($delivery->getClaimToken(), $next->getLastOutcomeAt(), $next->getLastFailure());
        }

        return $expired;
    }

    private function date(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
