<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Adapter\Persistence\Hydration\PersistedUser;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\PostgresAtomicOperation;
use App\Adapter\Persistence\PostgresUniqueConstraintRace;
use App\Adapter\Persistence\RefreshSessionRecords;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query\QueryBuilder;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Exception\DuplicateEmailException;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;

/**
 * Implements the stable Fight Access Control user repository on the shared PostgreSQL connection
 *
 * Scalar identity state compares completely through conditional statements. Canonical and live-reservation email
 * claims share one unique table. Authentication-authority mutation holds a per-user transaction lock, and role
 * assignment holds the shared role-reference fence.
 */
final readonly class PostgresUserRepository implements UserRepository
{
    /**
     * Constructs PostgresUserRepository
     */
    public function __construct(
        private Connection $connection,
        private AuthorizationReferenceFences $referenceFences,
        private AuthenticationAuthorityFences $authenticationFences
    ) {
    }

    /**
     * @inheritDoc
     */
    public function add(User $user): void
    {
        $this->assertTransaction();

        try {
            $inserted = PostgresUniqueConstraintRace::execute(
                $this->connection,
                'uq_user_email_claims_email',
                function () use ($user): int {
                    $this->insertUser($user);
                    $this->insertEmailClaims($user);
                    $this->insertAssignments($user);

                    return 1;
                }
            );
        } catch (UniqueConstraintViolationException) {
            throw new PersistenceConflict('The user identity is already in use.');
        } catch (ForeignKeyConstraintViolationException) {
            throw new PersistenceConflict('The user identity or role reference is invalid.');
        }

        if ($inserted !== 1) {
            throw new DuplicateEmailException('The email address is already reserved.');
        }
    }

    /**
     * @inheritDoc
     */
    public function hasRoleAssignment(RoleId $roleId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM user_role_assignments WHERE role_id = ? LIMIT 1',
            [$roleId->toString()]
        ) !== false;
    }

    /**
     * @inheritDoc
     */
    public function getByEmail(EmailAddress $email): ?User
    {
        $userId = $this->connection->createQueryBuilder()
            ->select('user_id')
            ->from('user_email_claims')
            ->where('claim_type = :claim_type')
            ->andWhere('email = :email')
            ->setParameter('claim_type', 'canonical')
            ->setParameter('email', $email->canonical())
            ->fetchOne();
        if ($userId === false) {
            return null;
        }

        return $this->one('id', (string) $userId);
    }

    /**
     * @inheritDoc
     */
    public function getById(UserId $id): ?User
    {
        return $this->one('id', $id->toString());
    }

    /**
     * @inheritDoc
     */
    public function replaceAuthenticationAuthority(User $expected, User $replacement): bool
    {
        $this->assertTransaction();
        $this->authenticationFences->hold($expected->getId());

        if (!$this->authenticationReplacementIsValid($expected, $replacement)) {
            return false;
        }

        return $this->conditionalAuthorityUpdate($expected, $replacement) === 1;
    }

    /**
     * @inheritDoc
     */
    public function replaceAuthenticationAuthorityAndAddRefreshSession(
        User $expected,
        User $replacement,
        RefreshSession $refreshSession
    ): bool {
        $this->assertTransaction();
        $this->authenticationFences->hold($expected->getId());

        if (
            !$this->authenticationReplacementIsValid($expected, $replacement)
            || !$refreshSession->getUserId()->equals($replacement->getId())
            || $refreshSession->getAuthenticationVersion() !== $replacement->getAuthenticationVersion()
        ) {
            return false;
        }

        try {
            return PostgresAtomicOperation::execute(
                $this->connection,
                function () use ($expected, $replacement, $refreshSession): bool {
                    if ($this->conditionalAuthorityUpdate($expected, $replacement) !== 1) {
                        return false;
                    }

                    RefreshSessionRecords::insert($this->connection, $refreshSession);

                    return true;
                }
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function validateRoleAssignmentReference(RoleId $roleId): bool
    {
        $this->assertTransaction();
        $this->referenceFences->holdRoleReferences();

        return $this->lockAuthoritativeRoles([$roleId]);
    }

    /**
     * @inheritDoc
     */
    public function replaceRoleAssignments(User $expected, User $replacement): bool
    {
        $this->assertTransaction();

        if (!$this->roleAssignmentReplacementIsValid($expected, $replacement)) {
            return false;
        }

        $this->referenceFences->holdRoleReferences();
        if (!$this->lockAuthoritativeRoles([...$expected->getRoleIds(), ...$replacement->getRoleIds()])) {
            return false;
        }

        $builder = $this->connection->createQueryBuilder()->update('users');
        $builder
            ->set('authorization_assignment_revision', ':replacement_authorization_assignment_revision')
            ->set('updated_at', ':replacement_updated_at');
        $this->applyExpectedState($builder, $expected);
        $builder
            ->setParameter(
                'replacement_authorization_assignment_revision',
                $replacement->getAuthorizationAssignmentRevision()
            )
            ->setParameter('replacement_updated_at', RefreshSessionRecords::date($replacement->getUpdatedAt()));

        if ($builder->executeStatement() !== 1) {
            return false;
        }

        $this->connection->delete('user_role_assignments', ['user_id' => $replacement->getId()->toString()]);
        $this->insertAssignments($replacement);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function replaceEmailChangeReservation(User $expected, User $replacement): bool
    {
        $this->assertTransaction();

        if (!$this->emailChangeReservationReplacementIsValid($expected, $replacement)) {
            return false;
        }

        $destination = $replacement->getPendingEmailChange();

        try {
            return PostgresAtomicOperation::execute(
                $this->connection,
                function () use ($expected, $replacement, $destination): bool {
                    $builder = $this->connection->createQueryBuilder()->update('users');
                    $builder
                        ->set('pending_email_change', ':replacement_pending_email_change')
                        ->set(
                            'email_change_reservation_revision',
                            ':replacement_email_change_reservation_revision'
                        )
                        ->set('updated_at', ':replacement_updated_at');
                    $this->applyExpectedState($builder, $expected);
                    $builder
                        ->setParameter(
                            'replacement_pending_email_change',
                            $destination instanceof EmailAddress ? $destination->toString() : null
                        )
                        ->setParameter(
                            'replacement_email_change_reservation_revision',
                            $replacement->getEmailChangeReservationRevision()
                        )
                        ->setParameter(
                            'replacement_updated_at',
                            RefreshSessionRecords::date($replacement->getUpdatedAt())
                        );

                    if ($builder->executeStatement() !== 1) {
                        return false;
                    }

                    if ($destination instanceof EmailAddress) {
                        $this->connection->insert('user_email_claims', [
                            'user_id' => $replacement->getId()->toString(),
                            'claim_type' => 'reservation',
                            'email' => $destination->canonical(),
                        ]);
                    } else {
                        $this->connection->delete('user_email_claims', [
                            'user_id' => $replacement->getId()->toString(),
                            'claim_type' => 'reservation',
                        ]);
                    }

                    return true;
                }
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function replaceEmailChangeConfirmation(User $expected, User $replacement): bool
    {
        $this->assertTransaction();
        $this->authenticationFences->hold($expected->getId());

        if (!$this->emailChangeConfirmationReplacementIsValid($expected, $replacement)) {
            return false;
        }

        try {
            return PostgresAtomicOperation::execute(
                $this->connection,
                function () use ($expected, $replacement): bool {
                    $builder = $this->connection->createQueryBuilder()->update('users');
                    $builder
                        ->set('email', ':replacement_email')
                        ->set('pending_email_change', 'NULL')
                        ->set('authentication_version', ':replacement_authentication_version')
                        ->set(
                            'authentication_authority_revision',
                            ':replacement_authentication_authority_revision'
                        )
                        ->set(
                            'email_change_reservation_revision',
                            ':replacement_email_change_reservation_revision'
                        )
                        ->set('canonical_email_revision', ':replacement_canonical_email_revision')
                        ->set('updated_at', ':replacement_updated_at');
                    $this->applyExpectedState($builder, $expected);
                    $builder
                        ->setParameter('replacement_email', $replacement->getEmail()->toString())
                        ->setParameter(
                            'replacement_authentication_version',
                            $replacement->getAuthenticationVersion()
                        )
                        ->setParameter(
                            'replacement_authentication_authority_revision',
                            $replacement->getAuthenticationAuthorityRevision()
                        )
                        ->setParameter(
                            'replacement_email_change_reservation_revision',
                            $replacement->getEmailChangeReservationRevision()
                        )
                        ->setParameter(
                            'replacement_canonical_email_revision',
                            $replacement->getCanonicalEmailRevision()
                        )
                        ->setParameter(
                            'replacement_updated_at',
                            RefreshSessionRecords::date($replacement->getUpdatedAt())
                        );

                    if ($builder->executeStatement() !== 1) {
                        return false;
                    }

                    $this->connection->delete('user_email_claims', [
                        'user_id' => $replacement->getId()->toString(),
                        'claim_type' => 'reservation',
                    ]);
                    $this->connection->update('user_email_claims', [
                        'email' => $replacement->getEmail()->canonical(),
                    ], [
                        'user_id' => $replacement->getId()->toString(),
                        'claim_type' => 'canonical',
                    ]);

                    return true;
                }
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function replacePendingInvitationEmail(User $expected, User $replacement): bool
    {
        $this->assertTransaction();

        if (!$this->pendingInvitationEmailReplacementIsValid($expected, $replacement)) {
            return false;
        }

        try {
            return PostgresAtomicOperation::execute(
                $this->connection,
                function () use ($expected, $replacement): bool {
                    $builder = $this->connection->createQueryBuilder()->update('users');
                    $builder
                        ->set('email', ':replacement_email')
                        ->set('canonical_email_revision', ':replacement_canonical_email_revision')
                        ->set('updated_at', ':replacement_updated_at');
                    $this->applyExpectedState($builder, $expected);
                    $builder
                        ->setParameter('replacement_email', $replacement->getEmail()->toString())
                        ->setParameter(
                            'replacement_canonical_email_revision',
                            $replacement->getCanonicalEmailRevision()
                        )
                        ->setParameter(
                            'replacement_updated_at',
                            RefreshSessionRecords::date($replacement->getUpdatedAt())
                        );

                    if ($builder->executeStatement() !== 1) {
                        return false;
                    }

                    $this->connection->update('user_email_claims', [
                        'email' => $replacement->getEmail()->canonical(),
                    ], [
                        'user_id' => $replacement->getId()->toString(),
                        'claim_type' => 'canonical',
                    ]);

                    return true;
                }
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function replaceLifecycleState(User $expected, User $replacement): bool
    {
        $this->assertTransaction();

        if (!$this->lifecycleStateReplacementIsValid($expected, $replacement)) {
            return false;
        }

        $builder = $this->connection->createQueryBuilder()->update('users');
        $builder
            ->set('state', ':replacement_state')
            ->set('password_hash', ':replacement_password_hash')
            ->set('updated_at', ':replacement_updated_at');
        $this->applyExpectedState($builder, $expected);
        $builder
            ->setParameter('replacement_state', $replacement->getState()->value)
            ->setParameter(
                'replacement_password_hash',
                $replacement->getPasswordHash() instanceof PasswordHash
                    ? $replacement->getPasswordHash()->toString()
                    : null
            )
            ->setParameter('replacement_updated_at', RefreshSessionRecords::date($replacement->getUpdatedAt()));

        return $builder->executeStatement() === 1;
    }

    /**
     * @inheritDoc
     */
    public function getAll(Pagination $pagination): ResultSet
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->setMaxResults($pagination->limit())
            ->setFirstResult($pagination->offset())
            ->fetchAllAssociative();
        $records = ArrayList::of(User::class)->replace(array_map($this->hydrate(...), $rows));

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            (int) $this->connection->createQueryBuilder()->select('COUNT(*)')->from('users')->fetchOne(),
            $records
        );
    }

    private function conditionalAuthorityUpdate(User $expected, User $replacement): int
    {
        $builder = $this->connection->createQueryBuilder()->update('users');
        $builder
            ->set('password_hash', ':replacement_password_hash')
            ->set('state', ':replacement_state')
            ->set('authentication_version', ':replacement_authentication_version')
            ->set('authentication_authority_revision', ':replacement_authentication_authority_revision')
            ->set('updated_at', ':replacement_updated_at');
        $this->applyExpectedState($builder, $expected);
        $builder
            ->setParameter('replacement_password_hash', $replacement->getPasswordHash()?->toString())
            ->setParameter('replacement_state', $replacement->getState()->value)
            ->setParameter('replacement_authentication_version', $replacement->getAuthenticationVersion())
            ->setParameter(
                'replacement_authentication_authority_revision',
                $replacement->getAuthenticationAuthorityRevision()
            )
            ->setParameter('replacement_updated_at', RefreshSessionRecords::date($replacement->getUpdatedAt()));

        return $builder->executeStatement();
    }

    private function applyExpectedState(QueryBuilder $builder, User $expected): void
    {
        $builder
            ->andWhere('users.id = :expected_id')
            ->andWhere('users.email = :expected_email')
            ->andWhere('users.state = :expected_state')
            ->andWhere('users.password_hash IS NOT DISTINCT FROM :expected_password_hash')
            ->andWhere('users.authentication_version = :expected_authentication_version')
            ->andWhere('users.authentication_authority_revision = :expected_authentication_authority_revision')
            ->andWhere('users.authorization_assignment_revision = :expected_authorization_assignment_revision')
            ->andWhere('users.pending_email_change IS NOT DISTINCT FROM :expected_pending_email_change')
            ->andWhere('users.email_change_reservation_revision = :expected_email_change_reservation_revision')
            ->andWhere('users.canonical_email_revision = :expected_canonical_email_revision')
            ->andWhere('users.created_at = :expected_created_at')
            ->andWhere('users.updated_at = :expected_updated_at')
            ->andWhere(
                'NOT EXISTS (SELECT 1 FROM user_role_assignments expected_extra '
                . 'WHERE expected_extra.user_id = users.id '
                . 'AND expected_extra.role_id <> ALL(:expected_roles::uuid[]))'
            )
            ->andWhere(
                'NOT EXISTS (SELECT 1 FROM unnest(:expected_roles::uuid[]) AS expected_role(role_id) '
                . 'WHERE NOT EXISTS (SELECT 1 FROM user_role_assignments expected_stored '
                . 'WHERE expected_stored.user_id = users.id AND expected_stored.role_id = expected_role.role_id))'
            )
            ->setParameter('expected_id', $expected->getId()->toString())
            ->setParameter('expected_email', $expected->getEmail()->toString())
            ->setParameter('expected_state', $expected->getState()->value)
            ->setParameter('expected_password_hash', $expected->getPasswordHash()?->toString())
            ->setParameter('expected_authentication_version', $expected->getAuthenticationVersion())
            ->setParameter(
                'expected_authentication_authority_revision',
                $expected->getAuthenticationAuthorityRevision()
            )
            ->setParameter(
                'expected_authorization_assignment_revision',
                $expected->getAuthorizationAssignmentRevision()
            )
            ->setParameter(
                'expected_pending_email_change',
                $expected->getPendingEmailChange() instanceof EmailAddress
                    ? $expected->getPendingEmailChange()->toString()
                    : null
            )
            ->setParameter(
                'expected_email_change_reservation_revision',
                $expected->getEmailChangeReservationRevision()
            )
            ->setParameter('expected_canonical_email_revision', $expected->getCanonicalEmailRevision())
            ->setParameter('expected_created_at', RefreshSessionRecords::date($expected->getCreatedAt()))
            ->setParameter('expected_updated_at', RefreshSessionRecords::date($expected->getUpdatedAt()))
            ->setParameter('expected_roles', $this->roleLiteral($expected->getRoleIds()));
    }

    /**
     * @param list<RoleId> $roleIds
     */
    private function lockAuthoritativeRoles(array $roleIds): bool
    {
        $ids = array_values(array_unique(array_map(
            static fn(RoleId $id): string => $id->toString(),
            $roleIds
        )));
        sort($ids);
        if ($ids === []) {
            return true;
        }

        $found = $this->connection->fetchFirstColumn(
            'SELECT id FROM roles WHERE id IN (?) ORDER BY id FOR KEY SHARE',
            [$ids],
            [ArrayParameterType::STRING]
        );

        return count($found) === count($ids);
    }

    /**
     * @param list<RoleId> $roleIds
     */
    private function roleLiteral(array $roleIds): string
    {
        $ids = array_values(array_unique(array_map(
            static fn(RoleId $id): string => $id->toString(),
            $roleIds
        )));

        return '{' . implode(',', $ids) . '}';
    }

    private function one(string $column, string $value): ?User
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        $roleIds = array_map(
            static fn(mixed $roleId): RoleId => RoleId::fromString((string) $roleId),
            $this->connection->createQueryBuilder()
                ->select('role_id')
                ->from('user_role_assignments')
                ->where('user_id = :user_id')
                ->setParameter('user_id', (string) $row['id'])
                ->orderBy('role_id')
                ->fetchFirstColumn()
        );

        return PersistedUser::reconstitute(
            UserId::fromString((string) $row['id']),
            EmailAddress::fromString((string) $row['email']),
            UserState::from((string) $row['state']),
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
            $row['password_hash'] === null ? null : PasswordHash::fromString((string) $row['password_hash']),
            (int) $row['authentication_version'],
            (int) $row['authentication_authority_revision'],
            $roleIds,
            (int) $row['authorization_assignment_revision'],
            $row['pending_email_change'] === null
                ? null
                : EmailAddress::fromString((string) $row['pending_email_change']),
            (int) $row['email_change_reservation_revision'],
            (int) $row['canonical_email_revision']
        );
    }

    private function insertUser(User $user): void
    {
        $this->connection->insert('users', [
            'id' => $user->getId()->toString(),
            'email' => $user->getEmail()->toString(),
            'state' => $user->getState()->value,
            'password_hash' => $user->getPasswordHash()?->toString(),
            'authentication_version' => $user->getAuthenticationVersion(),
            'authentication_authority_revision' => $user->getAuthenticationAuthorityRevision(),
            'authorization_assignment_revision' => $user->getAuthorizationAssignmentRevision(),
            'pending_email_change' => $user->getPendingEmailChange()?->toString(),
            'email_change_reservation_revision' => $user->getEmailChangeReservationRevision(),
            'canonical_email_revision' => $user->getCanonicalEmailRevision(),
            'created_at' => RefreshSessionRecords::date($user->getCreatedAt()),
            'updated_at' => RefreshSessionRecords::date($user->getUpdatedAt()),
        ]);
    }

    private function insertEmailClaims(User $user): void
    {
        $this->connection->insert('user_email_claims', [
            'user_id' => $user->getId()->toString(),
            'claim_type' => 'canonical',
            'email' => $user->getEmail()->canonical(),
        ]);

        $pendingEmailChange = $user->getPendingEmailChange();
        if ($pendingEmailChange instanceof EmailAddress) {
            $this->connection->insert('user_email_claims', [
                'user_id' => $user->getId()->toString(),
                'claim_type' => 'reservation',
                'email' => $pendingEmailChange->canonical(),
            ]);
        }
    }

    private function insertAssignments(User $user): void
    {
        $ids = array_values(array_unique(array_map(
            static fn(RoleId $id): string => $id->toString(),
            $user->getRoleIds()
        )));
        sort($ids);

        foreach ($ids as $roleId) {
            $this->connection->insert('user_role_assignments', [
                'user_id' => $user->getId()->toString(),
                'role_id' => $roleId,
            ]);
        }
    }

    private function authenticationReplacementIsValid(User $expected, User $replacement): bool
    {
        $stateIsValid = $replacement->getState() === $expected->getState()
            || (
                $expected->getState() === UserState::PENDING_ACTIVATION
                && $replacement->getState() === UserState::ACTIVE
            );
        $versionIsValid = $replacement->getAuthenticationVersion() === $expected->getAuthenticationVersion()
            || $replacement->getAuthenticationVersion() === $expected->getAuthenticationVersion() + 1;

        return $expected->getId()->equals($replacement->getId())
            && $expected->getCreatedAt() == $replacement->getCreatedAt()
            && $this->emailStateMatches($expected, $replacement)
            && $stateIsValid
            && $versionIsValid
            && $replacement->getAuthenticationAuthorityRevision()
                === $expected->getAuthenticationAuthorityRevision() + 1
            && $replacement->getAuthorizationAssignmentRevision()
                === $expected->getAuthorizationAssignmentRevision()
            && $this->roleAssignmentsMatch($expected, $replacement)
            && $replacement->getPasswordHash() instanceof PasswordHash;
    }

    private function roleAssignmentReplacementIsValid(User $expected, User $replacement): bool
    {
        return $expected->getId()->equals($replacement->getId())
            && $expected->getCreatedAt() == $replacement->getCreatedAt()
            && $this->emailStateMatches($expected, $replacement)
            && $expected->getState() === $replacement->getState()
            && $expected->getAuthenticationVersion() === $replacement->getAuthenticationVersion()
            && $expected->getAuthenticationAuthorityRevision() === $replacement->getAuthenticationAuthorityRevision()
            && $this->passwordHashesMatch($expected->getPasswordHash(), $replacement->getPasswordHash())
            && $replacement->getAuthorizationAssignmentRevision()
                === $expected->getAuthorizationAssignmentRevision() + 1
            && !$this->roleAssignmentsMatch($expected, $replacement);
    }

    private function emailChangeReservationReplacementIsValid(User $expected, User $replacement): bool
    {
        $expectedPending = $expected->getPendingEmailChange();
        $replacementPending = $replacement->getPendingEmailChange();
        $reservationTransitionIsValid = (
            !$expectedPending instanceof EmailAddress
            && $replacementPending instanceof EmailAddress
        ) || (
            $expectedPending instanceof EmailAddress
            && !$replacementPending instanceof EmailAddress
        );

        return $expected->getId()->equals($replacement->getId())
            && $expected->getCreatedAt() == $replacement->getCreatedAt()
            && $expected->getEmail()->canonical() === $replacement->getEmail()->canonical()
            && $expected->getState() === $replacement->getState()
            && $this->passwordHashesMatch($expected->getPasswordHash(), $replacement->getPasswordHash())
            && $expected->getAuthenticationVersion() === $replacement->getAuthenticationVersion()
            && $expected->getAuthenticationAuthorityRevision() === $replacement->getAuthenticationAuthorityRevision()
            && $expected->getAuthorizationAssignmentRevision()
                === $replacement->getAuthorizationAssignmentRevision()
            && $this->roleAssignmentsMatch($expected, $replacement)
            && $replacement->getEmailChangeReservationRevision()
                === $expected->getEmailChangeReservationRevision() + 1
            && $replacement->getCanonicalEmailRevision() === $expected->getCanonicalEmailRevision()
            && $reservationTransitionIsValid;
    }

    private function emailChangeConfirmationReplacementIsValid(User $expected, User $replacement): bool
    {
        $pendingEmailChange = $expected->getPendingEmailChange();
        if (!$pendingEmailChange instanceof EmailAddress) {
            return false;
        }

        return $expected->getId()->equals($replacement->getId())
            && $expected->getCreatedAt() == $replacement->getCreatedAt()
            && $replacement->getEmail()->canonical() === $pendingEmailChange->canonical()
            && $expected->getState() === UserState::ACTIVE
            && $replacement->getState() === UserState::ACTIVE
            && $this->passwordHashesMatch($expected->getPasswordHash(), $replacement->getPasswordHash())
            && $replacement->getAuthenticationVersion() === $expected->getAuthenticationVersion() + 1
            && $replacement->getAuthenticationAuthorityRevision()
                === $expected->getAuthenticationAuthorityRevision() + 1
            && $replacement->getAuthorizationAssignmentRevision()
                === $expected->getAuthorizationAssignmentRevision()
            && $this->roleAssignmentsMatch($expected, $replacement)
            && $replacement->getEmailChangeReservationRevision()
                === $expected->getEmailChangeReservationRevision() + 1
            && $replacement->getCanonicalEmailRevision()
                === $expected->getCanonicalEmailRevision() + 1;
    }

    private function pendingInvitationEmailReplacementIsValid(User $expected, User $replacement): bool
    {
        return $expected->getId()->equals($replacement->getId())
            && $expected->getCreatedAt() == $replacement->getCreatedAt()
            && $expected->getEmail()->canonical() !== $replacement->getEmail()->canonical()
            && $expected->getState() === UserState::PENDING_ACTIVATION
            && $replacement->getState() === UserState::PENDING_ACTIVATION
            && $this->passwordHashesMatch($expected->getPasswordHash(), $replacement->getPasswordHash())
            && $expected->getAuthenticationVersion() === $replacement->getAuthenticationVersion()
            && $expected->getAuthenticationAuthorityRevision() === $replacement->getAuthenticationAuthorityRevision()
            && $expected->getAuthorizationAssignmentRevision()
                === $replacement->getAuthorizationAssignmentRevision()
            && $this->roleAssignmentsMatch($expected, $replacement)
            && $replacement->getEmailChangeReservationRevision()
                === $expected->getEmailChangeReservationRevision()
            && $replacement->getCanonicalEmailRevision() === $expected->getCanonicalEmailRevision() + 1;
    }

    private function lifecycleStateReplacementIsValid(User $expected, User $replacement): bool
    {
        if (!$this->lifecycleTransitionIsValid($expected->getState(), $replacement->getState())) {
            return false;
        }

        if ($replacement->getState() === UserState::PENDING_ACTIVATION) {
            if ($replacement->getPasswordHash() instanceof PasswordHash) {
                return false;
            }
        } elseif (!$this->passwordHashesMatch($expected->getPasswordHash(), $replacement->getPasswordHash())) {
            return false;
        }

        return $expected->getId()->equals($replacement->getId())
            && $expected->getCreatedAt() == $replacement->getCreatedAt()
            && $this->emailStateMatches($expected, $replacement)
            && $expected->getAuthenticationVersion() === $replacement->getAuthenticationVersion()
            && $expected->getAuthenticationAuthorityRevision() === $replacement->getAuthenticationAuthorityRevision()
            && $expected->getAuthorizationAssignmentRevision()
                === $replacement->getAuthorizationAssignmentRevision()
            && $this->roleAssignmentsMatch($expected, $replacement);
    }

    private function lifecycleTransitionIsValid(UserState $expected, UserState $target): bool
    {
        return match (true) {
            $expected === UserState::ACTIVE && $target === UserState::DISABLED,
            $expected === UserState::DISABLED && $target === UserState::ACTIVE,
            $expected === UserState::ACTIVE && $target === UserState::DELETED,
            $expected === UserState::DISABLED && $target === UserState::DELETED,
            $expected === UserState::DELETED && $target === UserState::ACTIVE,
            $expected === UserState::DELETED && $target === UserState::PENDING_ACTIVATION => true,
            default => false,
        };
    }

    private function emailStateMatches(User $left, User $right): bool
    {
        return $left->getEmail()->canonical() === $right->getEmail()->canonical()
            && $left->getPendingEmailChange()?->canonical() === $right->getPendingEmailChange()?->canonical()
            && $left->getEmailChangeReservationRevision() === $right->getEmailChangeReservationRevision()
            && $left->getCanonicalEmailRevision() === $right->getCanonicalEmailRevision();
    }

    private function roleAssignmentsMatch(User $left, User $right): bool
    {
        $leftRoleIds = $left->getRoleIds();
        if (count($leftRoleIds) !== count($right->getRoleIds())) {
            return false;
        }

        return array_all(
            $leftRoleIds,
            static fn(RoleId $roleId): bool => $right->hasRole($roleId)
        );
    }

    private function passwordHashesMatch(?PasswordHash $left, ?PasswordHash $right): bool
    {
        if (!$left instanceof PasswordHash || !$right instanceof PasswordHash) {
            return $left === $right;
        }

        return hash_equals($left->toString(), $right->toString());
    }

    private function assertTransaction(): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Identity persistence requires an enclosing transaction.');
        }
    }
}
