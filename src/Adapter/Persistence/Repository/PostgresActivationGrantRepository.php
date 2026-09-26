<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Adapter\Persistence\ActivationGrantRecords;
use App\Adapter\Persistence\ActivationGrantTransitions;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\PostgresAtomicOperation;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\DueCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;

/**
 * Class PostgresActivationGrantRepository
 *
 * Persists activation generations on the shared connection with per-user transaction fences
 */
final readonly class PostgresActivationGrantRepository implements ActivationGrantRepository
{
    private const int FENCE_NAMESPACE = 24123;

    /**
     * Constructs PostgresActivationGrantRepository
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

        // Bound discovery in PostgreSQL and never load encrypted material into a due-work projection.
        // Select the latest generation before eligibility: historical pending work is never discoverable.
        $rows = $this->connection->createQueryBuilder()
            ->select(
                'g.delivery_id',
                'g.user_id',
                'g.delivery_status',
                'g.revision',
                'g.delivery_due_at',
                'g.delivery_lease_until'
            )
            ->from('activation_grants', 'g')
            ->where(<<<'SQL'
NOT EXISTS (SELECT 1 FROM activation_grants newer WHERE newer.user_id = g.user_id
    AND (newer.generation > g.generation OR (newer.generation = g.generation AND newer.id > g.id)))
SQL
            )
            ->andWhere('g.delivery_ciphertext IS NOT NULL')
            ->andWhere('g.expires_at > :at')
            ->andWhere(<<<'SQL'
((g.delivery_status IN (:pending, :retry) AND g.delivery_due_at <= :at)
    OR (g.delivery_status = :claimed AND g.delivery_lease_until <= :at))
SQL
            )
            ->setParameter('at', ActivationGrantRecords::date($at))
            ->setParameter('pending', CredentialDeliveryStatus::PENDING->value)
            ->setParameter('retry', CredentialDeliveryStatus::RETRY_PENDING->value)
            ->setParameter('claimed', CredentialDeliveryStatus::CLAIMED->value)
            ->orderBy('CASE WHEN g.delivery_status = :claimed THEN g.delivery_lease_until ELSE g.delivery_due_at END')
            ->addOrderBy('g.delivery_id')
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_values(array_map(static function (array $row): DueCredentialDelivery {
            $dueAt = $row['delivery_due_at'];
            if ($row['delivery_status'] === CredentialDeliveryStatus::CLAIMED->value) {
                $dueAt = $row['delivery_lease_until'];
            }

            return new DueCredentialDelivery(
                'activation',
                ActivationDeliveryId::fromString((string) $row['delivery_id']),
                UserId::fromString((string) $row['user_id']),
                new DateTimeImmutable((string) $dueAt),
                (int) $row['revision'],
                CredentialDeliveryStatus::from((string) $row['delivery_status'])
            );
        }, $rows));
    }

    /**
     * @inheritDoc
     */
    public function getById(ActivationGrantId $activationGrantId): ?ActivationGrant
    {
        return $this->one('id', $activationGrantId->toString());
    }

    /**
     * @inheritDoc
     */
    public function getByDeliveryId(ActivationDeliveryId $activationDeliveryId): ?ActivationGrant
    {
        return $this->one('delivery_id', $activationDeliveryId->toString());
    }

    /**
     * @inheritDoc
     */
    public function getLatestByUserId(UserId $userId): ?ActivationGrant
    {
        $row = $this->latestRow($userId);

        return $row === false ? null : ActivationGrantRecords::hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function add(ActivationGrant $activationGrant): bool
    {
        $this->hold($activationGrant->getUserId());
        if (
            !ActivationGrantTransitions::pristine($activationGrant)
            || $this->latestRow($activationGrant->getUserId()) !== false
        ) {
            return false;
        }

        return $this->write(fn(): bool => $this->insert($activationGrant, 0));
    }

    /**
     * @inheritDoc
     */
    public function replace(ActivationGrant $predecessor, ActivationGrant $replacement): bool
    {
        $this->hold($predecessor->getUserId());
        $row = $this->latestRow($predecessor->getUserId(), true);
        if (
            !$this->matches($row, $predecessor)
            || !ActivationGrantTransitions::replacement($predecessor, $replacement)
        ) {
            return false;
        }

        return $this->write(fn(): bool => $this->update($replacement, $predecessor));
    }

    /**
     * @inheritDoc
     */
    public function replaceWithSuccessor(
        ActivationGrant $predecessor,
        ActivationGrant $terminalPredecessor,
        ActivationGrant $successor
    ): bool {
        $this->hold($predecessor->getUserId());
        $row = $this->latestRow($predecessor->getUserId(), true);
        if (
            $row === false
            || !$this->matches($row, $predecessor)
            || !ActivationGrantTransitions::replacement($predecessor, $terminalPredecessor)
            || !$this->terminal($terminalPredecessor)
            || !$this->validSuccessor($predecessor, $successor)
        ) {
            return false;
        }

        return $this->write(function () use ($predecessor, $terminalPredecessor, $successor, $row): bool {
            if (!$this->update($terminalPredecessor, $predecessor)) {
                return false;
            }

            return $this->insert($successor, (int) $row['generation'] + 1, $predecessor->getId());
        });
    }

    /**
     * @inheritDoc
     */
    public function addSuccessor(ActivationGrant $successor): bool
    {
        $this->hold($successor->getUserId());
        $row = $this->latestRow($successor->getUserId(), true);
        if ($row === false) {
            return false;
        }
        $previous = ActivationGrantRecords::hydrate($row);
        if (!$this->terminal($previous) || !$this->validSuccessor($previous, $successor)) {
            return false;
        }

        return $this->write(fn(): bool => $this->insert($successor, (int) $row['generation'] + 1, $previous->getId()));
    }

    /**
     * Reconstitutes one grant from a database result
     */
    private function one(string $column, string $id): ?ActivationGrant
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')->from('activation_grants')->where($column.' = :id')
            ->setParameter('id', $id)->fetchAssociative();

        return $row === false ? null : ActivationGrantRecords::hydrate($row);
    }

    /**
     * Fetches the latest grant row for a user
     *
     * @phpstan-return array<string, mixed>|false
     */
    private function latestRow(UserId $userId, bool $lock = false): array|false
    {
        $sql = 'SELECT * FROM activation_grants WHERE user_id = ? ORDER BY generation DESC, id DESC LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        return $this->connection->fetchAssociative($sql, [$userId->toString()]);
    }

    /**
     * Checks a stored grant against the expected predecessor
     *
     * @phpstan-param array<string, mixed>|false $row
     */
    private function matches(array|false $row, ActivationGrant $predecessor): bool
    {
        return $row !== false && ActivationGrantRecords::same(ActivationGrantRecords::hydrate($row), $predecessor);
    }

    /**
     * Checks whether a grant has reached a terminal state
     */
    private function terminal(ActivationGrant $grant): bool
    {
        return !$grant->isIssued() && !$grant->getDelivery()->isRetryable();
    }

    /**
     * Checks that a successor grant follows the predecessor
     */
    private function validSuccessor(ActivationGrant $previous, ActivationGrant $successor): bool
    {
        return ActivationGrantTransitions::pristine($successor)
            && $previous->getUserId()->equals($successor->getUserId())
            && !$previous->getId()->equals($successor->getId())
            && !$previous->getDelivery()->getId()->equals($successor->getDelivery()->getId());
    }

    /**
     * Inserts a grant record
     */
    private function insert(ActivationGrant $grant, int $generation, ?ActivationGrantId $predecessorId = null): bool
    {
        $this->connection->insert('activation_grants', ActivationGrantRecords::fields($grant) + [
            'generation'     => $generation,
            'predecessor_id' => $predecessorId?->toString()
        ]);

        return true;
    }

    /**
     * Updates a grant record
     */
    private function update(ActivationGrant $replacement, ActivationGrant $predecessor): bool
    {
        $fields = ActivationGrantRecords::fields($replacement);
        unset(
            $fields['id'],
            $fields['user_id'],
            $fields['credential_digest'],
            $fields['expires_at'],
            $fields['delivery_id']
        );

        return $this->connection->update('activation_grants', $fields, [
            'id' => $predecessor->getId()->toString(), 'revision' => $predecessor->getRevision()
        ]) === 1;
    }

    /**
     * Acquires the authoritative record lock for the current transaction
     */
    private function hold(UserId $userId): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Activation-grant persistence requires an enclosing transaction.');
        }
        // Fence deletion of the owning user until this transaction ends.
        if (
            $this->connection->fetchOne(
                'SELECT 1 FROM users WHERE id = ? FOR KEY SHARE',
                [$userId->toString()]
            ) === false
        ) {
            throw new PersistenceConflict('The activation grant owner is unavailable.');
        }
        $this->connection->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, ?))',
            [$userId->toString(), self::FENCE_NAMESPACE]
        );
    }

    /**
     * Executes a grant write while classifying known conflicts
     *
     * @phpstan-param \Closure(): bool $operation
     */
    private function write(\Closure $operation): bool
    {
        try {
            return PostgresAtomicOperation::execute($this->connection, $operation);
        } catch (UniqueConstraintViolationException) {
            return false;
        } catch (ForeignKeyConstraintViolationException) {
            throw new PersistenceConflict('The activation grant owner is unavailable.');
        } catch (DriverException $exception) {
            // PostgreSQL check failures are not translated into a dedicated DBAL exception type.
            // Recognize only this table's named constraints; do not classify unknown driver failures by SQLSTATE.
            $constraintPattern = implode('', [
                '/constraint "(ck_activation_grants_(?:generation|digest|terminal|delivery_status|',
                'delivery_claim|delivery_material|delivery_attempts))"/'
            ]);
            $isKnownConstraint = $exception->getSQLState() === '23514'
                && preg_match($constraintPattern, $exception->getMessage()) === 1;
            if ($isKnownConstraint) {
                return false;
            }
            throw $exception;
        }
    }
}
