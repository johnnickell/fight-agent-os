<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Adapter\Persistence\PostgresAtomicOperation;
use App\Adapter\Persistence\RefreshSessionRecords;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use LogicException;

/**
 * Implements the stable Fight Access Control refresh-session repository on the shared PostgreSQL connection
 *
 * Sessions keep a unique current one-way credential digest plus unique historical digests. Active scans evaluate
 * revocation and both expiries at the supplied time. Replacement compares complete expected state and the exact
 * next revision before appending the superseded digest.
 */
final readonly class PostgresRefreshSessionRepository implements RefreshSessionRepository
{
    /**
     * Constructs PostgresRefreshSessionRepository
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function getById(RefreshSessionId $id): ?RefreshSession
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('refresh_sessions')
            ->where('id = :id')
            ->setParameter('id', $id->toString())
            ->fetchAssociative();

        return $row === false ? null : RefreshSessionRecords::hydrate($this->connection, $row);
    }

    /**
     * @inheritDoc
     */
    public function getByUserId(UserId $userId, DateTimeImmutable $at, Pagination $pagination): ResultSet
    {
        $builder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('refresh_sessions');
        $this->applyActiveFilter($builder, $userId, $at);
        $rows = $builder
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->setMaxResults($pagination->limit())
            ->setFirstResult($pagination->offset())
            ->fetchAllAssociative();
        $records = ArrayList::of(RefreshSession::class)->replace(array_map(
            fn(array $row): RefreshSession => RefreshSessionRecords::hydrate($this->connection, $row),
            $rows
        ));

        $countBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('refresh_sessions');
        $this->applyActiveFilter($countBuilder, $userId, $at);

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            (int) $countBuilder->fetchOne(),
            $records
        );
    }

    /**
     * @inheritDoc
     */
    public function getAllActiveByUserId(UserId $userId, DateTimeImmutable $at): array
    {
        $builder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('refresh_sessions');
        $this->applyActiveFilter($builder, $userId, $at);
        $rows = $builder
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->fetchAllAssociative();

        return array_map(
            fn(array $row): RefreshSession => RefreshSessionRecords::hydrate($this->connection, $row),
            $rows
        );
    }

    /**
     * @inheritDoc
     */
    public function getByCredential(RefreshCredential $refreshCredential): ?RefreshSession
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('refresh_sessions')
            ->where('credential_digest = :digest')
            ->setParameter('digest', $refreshCredential->digest())
            ->fetchAssociative();

        return $row === false ? null : RefreshSessionRecords::hydrate($this->connection, $row);
    }

    /**
     * @inheritDoc
     */
    public function getByUsedCredential(RefreshCredential $refreshCredential): ?RefreshSession
    {
        $sessionId = $this->connection->createQueryBuilder()
            ->select('refresh_session_id')
            ->from('refresh_session_used_credentials')
            ->where('credential_digest = :digest')
            ->setParameter('digest', $refreshCredential->digest())
            ->fetchOne();
        if ($sessionId === false) {
            return null;
        }

        return $this->getById(RefreshSessionId::fromString((string) $sessionId));
    }

    /**
     * @inheritDoc
     */
    public function replace(RefreshSession $expected, RefreshSession $replacement): bool
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Refresh-session persistence requires an enclosing transaction.');
        }

        if (!$this->replacementIsValid($expected, $replacement)) {
            return false;
        }

        try {
            return PostgresAtomicOperation::execute(
                $this->connection,
                function () use ($expected, $replacement): bool {
                    $isRotation = $replacement->getCredentialDigest() !== $expected->getCredentialDigest();
                    $builder = $this->connection->createQueryBuilder()->update('refresh_sessions');
                    $builder
                        ->set('credential_digest', ':replacement_credential_digest')
                        ->set('last_activity_at', ':replacement_last_activity_at')
                        ->set('idle_expires_at', ':replacement_idle_expires_at')
                        ->set('revision', ':replacement_revision')
                        ->set('revoked', ':replacement_revoked');
                    if ($isRotation) {
                        $builder->set('rotated_at', ':replacement_rotated_at');
                    }
                    $this->applyExpectedState($builder, $expected);
                    $builder
                        ->setParameter('replacement_credential_digest', $replacement->getCredentialDigest())
                        ->setParameter(
                            'replacement_last_activity_at',
                            RefreshSessionRecords::date($replacement->getLastActivityAt())
                        )
                        ->setParameter(
                            'replacement_idle_expires_at',
                            RefreshSessionRecords::date($replacement->getIdleExpiresAt())
                        )
                        ->setParameter('replacement_revision', $replacement->getRevision())
                        ->setParameter(
                            'replacement_revoked',
                            $replacement->isRevoked(),
                            ParameterType::BOOLEAN
                        );
                    if ($isRotation) {
                        $builder->setParameter(
                            'replacement_rotated_at',
                            RefreshSessionRecords::date($replacement->getLastActivityAt())
                        );
                    }

                    if ($builder->executeStatement() !== 1) {
                        return false;
                    }

                    if ($isRotation) {
                        RefreshSessionRecords::appendUsedDigest(
                            $this->connection,
                            $expected->getId()->toString(),
                            $expected->getCredentialDigest()
                        );
                    }

                    return true;
                }
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function applyActiveFilter(QueryBuilder $builder, UserId $userId, DateTimeImmutable $at): void
    {
        $builder
            ->where('user_id = :user_id')
            ->andWhere('revoked = FALSE')
            ->andWhere('idle_expires_at > :active_at')
            ->andWhere('absolute_expires_at > :active_at')
            ->setParameter('user_id', $userId->toString())
            ->setParameter('active_at', RefreshSessionRecords::date($at));
    }

    private function applyExpectedState(QueryBuilder $builder, RefreshSession $expected): void
    {
        $builder
            ->where('id = :expected_id')
            ->andWhere('user_id = :expected_user_id')
            ->andWhere('credential_digest = :expected_credential_digest')
            ->andWhere('created_at = :expected_created_at')
            ->andWhere('last_activity_at = :expected_last_activity_at')
            ->andWhere('idle_expires_at = :expected_idle_expires_at')
            ->andWhere('absolute_expires_at = :expected_absolute_expires_at')
            ->andWhere('authentication_version = :expected_authentication_version')
            ->andWhere('remembered = :expected_remembered')
            ->andWhere('revision = :expected_revision')
            ->andWhere('revoked = :expected_revoked')
            ->setParameter('expected_id', $expected->getId()->toString())
            ->setParameter('expected_user_id', $expected->getUserId()->toString())
            ->setParameter('expected_credential_digest', $expected->getCredentialDigest())
            ->setParameter('expected_created_at', RefreshSessionRecords::date($expected->getCreatedAt()))
            ->setParameter(
                'expected_last_activity_at',
                RefreshSessionRecords::date($expected->getLastActivityAt())
            )
            ->setParameter(
                'expected_idle_expires_at',
                RefreshSessionRecords::date($expected->getIdleExpiresAt())
            )
            ->setParameter(
                'expected_absolute_expires_at',
                RefreshSessionRecords::date($expected->getAbsoluteExpiresAt())
            )
            ->setParameter('expected_authentication_version', $expected->getAuthenticationVersion())
            ->setParameter('expected_remembered', $expected->isRemembered(), ParameterType::BOOLEAN)
            ->setParameter('expected_revision', $expected->getRevision())
            ->setParameter('expected_revoked', $expected->isRevoked(), ParameterType::BOOLEAN);
    }

    private function replacementIsValid(RefreshSession $expected, RefreshSession $replacement): bool
    {
        if (
            !$expected->getId()->equals($replacement->getId())
            || $replacement->getRevision() !== $expected->getRevision() + 1
            || !$expected->getUserId()->equals($replacement->getUserId())
            || $expected->getCreatedAt() != $replacement->getCreatedAt()
            || $expected->getAbsoluteExpiresAt() != $replacement->getAbsoluteExpiresAt()
            || $expected->getAuthenticationVersion() !== $replacement->getAuthenticationVersion()
            || $expected->isRemembered() !== $replacement->isRemembered()
        ) {
            return false;
        }

        if ($replacement->getCredentialDigest() !== $expected->getCredentialDigest()) {
            return $replacement->getLastActivityAt() > $replacement->getCreatedAt()
                && $replacement->getIdleExpiresAt() <= $replacement->getAbsoluteExpiresAt()
                && $replacement->getIdleExpiresAt() > $replacement->getLastActivityAt();
        }

        return $expected->getLastActivityAt() == $replacement->getLastActivityAt()
            && $expected->getIdleExpiresAt() == $replacement->getIdleExpiresAt();
    }
}
