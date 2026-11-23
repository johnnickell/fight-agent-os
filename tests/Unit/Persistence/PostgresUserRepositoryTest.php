<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\PasswordResetGrant\PasswordResetGrantRecords;
use App\Adapter\Persistence\AccessControl\User\PersistedUser;
use App\Adapter\Persistence\AccessControl\User\PostgresUserRepository;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PgSqlException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\Exception\DuplicateEmailException;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects stored identity mapping and authoritative recovery checks
 */
final class PostgresUserRepositoryTest extends TestCase
{
    /**
     * Rejects a password change smuggled into a lifecycle write
     */
    #[DataProvider('invalidLifecycleCredentials')]
    public function testLifecycleWriteCannotReplacePassword(UserState $before, UserState $after): void
    {
        $user = $this->activeUser();
        $snapshot = PersistedUser::reconstitute(
            $user->getId(),
            $user->getEmail(),
            $before,
            $user->getCreatedAt(),
            $user->getUpdatedAt(),
            $user->getPasswordHash(),
            1,
            0,
            [],
            0,
            null,
            0,
            0
        );
        $proposed = PersistedUser::reconstitute(
            $user->getId(),
            $user->getEmail(),
            $after,
            $user->getCreatedAt(),
            $this->later(),
            $after === UserState::PENDING_ACTIVATION ? $this->hash() : null,
            $after === UserState::DISABLED ? 2 : 1,
            0,
            [],
            0,
            null,
            0,
            0
        );
        $connection = $this->connection(true);
        $connection->expects(self::never())->method('createQueryBuilder');

        self::assertFalse($this->repository($connection)->replaceLifecycleState($snapshot, $proposed));
    }

    /**
     * Supplies reactivation with a leftover password and disabling with a changed password
     *
     * @return iterable<string, array{UserState, UserState}>
     */
    public static function invalidLifecycleCredentials(): iterable
    {
        yield 'pending must clear password' => [UserState::DELETED, UserState::PENDING_ACTIVATION];
        yield 'disable must retain password' => [UserState::ACTIVE, UserState::DISABLED];
    }

    /**
     * Looks up the canonical claim and restores the original address, reservation, roles and distinct revisions
     */
    public function testCanonicalLookupPreservesAuthoritativeIdentityFacts(): void
    {
        $id = UserId::generate();
        $role = RoleId::generate();
        $connection = $this->connection();
        $claim = $this->createStub(Result::class);
        $claim->method('fetchOne')->willReturn($id->toString());
        $user = $this->createStub(Result::class);
        $user->method('fetchAssociative')->willReturn([
            'id'                                => $id->toString(),
            'email'                             => 'Person@Example.test',
            'pending_email_change'              => 'new@example.test',
            'state'                             => UserState::ACTIVE->value,
            'created_at'                        => '2026-10-20T12:00:00Z',
            'updated_at'                        => '2026-10-21T12:00:00.123456Z',
            'password_hash'                     => $this->hash()->toString(),
            'authentication_version'            => '2',
            'authentication_authority_revision' => '3',
            'authorization_assignment_revision' => '4',
            'email_change_reservation_revision' => '5',
            'canonical_email_revision'          => '6'
        ]);
        $roles = $this->createStub(Result::class);
        $roles->method('fetchFirstColumn')->willReturn([$role->toString()]);
        $connection->expects(self::exactly(3))->method('executeQuery')->willReturnCallback(
            static function (string $sql, array $parameters) use ($id, $claim, $user, $roles): Result {
                if (str_contains($sql, 'FROM user_email_claims')) {
                    self::assertSame(['claim_type' => 'canonical', 'email' => 'person@example.test'], $parameters);

                    return $claim;
                }
                if (str_contains($sql, 'FROM user_role_assignments')) {
                    self::assertSame($id->toString(), $parameters['user_id']);

                    return $roles;
                }
                self::assertSame($id->toString(), $parameters['value']);

                return $user;
            }
        );

        $restored = $this->repository($connection)->getByEmail(EmailAddress::fromString('PERSON@example.test'));

        self::assertNotNull($restored);
        self::assertTrue($id->equals($restored->getId()));
        self::assertSame('Person@Example.test', $restored->getEmail()->toString());
        self::assertSame('new@example.test', $restored->getPendingEmailChange()?->toString());
        self::assertTrue($restored->hasRole($role));
        self::assertSame(UserState::ACTIVE, $restored->getState());
        self::assertSame($this->hash()->toString(), $restored->getPasswordHash()?->toString());
        self::assertEquals(new DateTimeImmutable('2026-10-21T12:00:00.123456Z'), $restored->getUpdatedAt());
        self::assertSame([2, 3, 4, 5, 6], [
            $restored->getAuthenticationVersion(),
            $restored->getAuthenticationAuthorityRevision(),
            $restored->getAuthorizationAssignmentRevision(),
            $restored->getEmailChangeReservationRevision(),
            $restored->getCanonicalEmailRevision()
        ]);
    }

    /**
     * Keeps the count independent of the requested page and uses an identity tie breaker
     */
    public function testPageBeyondLastRecordStillReportsTotal(): void
    {
        $connection = $this->connection();
        $connection->expects(self::exactly(2))->method('executeQuery')->willReturnCallback(
            function (string $sql): Result {
                $result = $this->createStub(Result::class);
                if (str_contains($sql, 'COUNT(*)')) {
                    self::assertSame('SELECT COUNT(*) FROM users', $sql);
                    $result->method('fetchOne')->willReturn('3');
                } else {
                    self::assertStringContainsString('ORDER BY created_at, id LIMIT 2 OFFSET 4', $sql);
                    $result->method('fetchAllAssociative')->willReturn([]);
                }

                return $result;
            }
        );

        $page = $this->repository($connection)->getAll(new Pagination(3, 2));

        self::assertSame(3, $page->totalRecords());
        self::assertSame([], iterator_to_array($page->records()));
    }

    /**
     * Reports whether the exact role is assigned without loading users
     */
    #[DataProvider('recoveryMatches')]
    public function testRoleAssignmentExistence(bool $matches): void
    {
        $role = RoleId::generate();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with(
            'SELECT 1 FROM user_role_assignments WHERE role_id = ? LIMIT 1',
            [$role->toString()]
        )->willReturn($matches ? 1 : false);

        self::assertSame($matches, $this->repository($connection)->hasRoleAssignment($role));
    }

    /**
     * Locks only the requested authoritative role and refuses a missing reference
     */
    #[DataProvider('recoveryMatches')]
    public function testValidatesRoleReference(bool $matches): void
    {
        $role = RoleId::generate();
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('fetchFirstColumn')->with(
            self::stringContains('FOR KEY SHARE'),
            [[$role->toString()]]
        )->willReturn($matches ? [$role->toString()] : []);

        self::assertSame($matches, $this->repository($connection)->validateRoleAssignmentReference($role));
    }

    /**
     * Refuses unchanged replacements before they can mutate an unrelated authority component
     */
    public function testInvalidReplacementsDoNotWrite(): void
    {
        $active = $this->activeUser();
        $pending = $this->user();
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::never())->method('executeStatement');
        $repository = $this->repository($connection);

        self::assertFalse($repository->addWithRoleAssignments($active, []));
        self::assertFalse($repository->addWithRoleAssignments($pending, []));
        self::assertFalse($repository->replaceAuthenticationAuthority($active, clone $active));
        self::assertFalse($repository->replaceRoleAssignments($active, clone $active));
        self::assertFalse($repository->replaceEmailChangeReservation($active, clone $active));
        self::assertFalse($repository->replacePendingInvitationEmail($pending, clone $pending));
        self::assertFalse($repository->replaceLifecycleState($active, clone $active));
    }

    /**
     * Stops at an absent canonical claim without treating an email reservation as an active identity
     */
    public function testMissingCanonicalEmailDoesNotReadUser(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchOne')->willReturn(false);
        $connection = $this->connection();
        $connection->expects(self::once())->method('executeQuery')->with(self::anything(), [
            'claim_type' => 'canonical', 'email' => 'missing@example.test'
        ])->willReturn($result);

        self::assertNull($this->repository($connection)->getByEmail(EmailAddress::fromString('Missing@example.test')));
    }

    /**
     * Rejects a recovery authority read detached from its owning transaction
     */
    public function testRecoveryRequiresTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('executeQuery');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Identity persistence requires an enclosing transaction.');
        $this->repository($connection)->validateRecoveryAuthority($this->user());
    }

    /**
     * Rejects recovery for pending activation without consulting a mutable state result
     */
    public function testPendingUserDoesNotHaveRecoveryAuthority(): void
    {
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeQuery')->with(self::stringContains('pg_advisory_xact_lock'))
            ->willReturn($this->createStub(Result::class));

        self::assertFalse($this->repository($connection)->validateRecoveryAuthority($this->user()));
    }

    /**
     * Uses expected authority facts, not just the identity, and rejects a missing match
     */
    #[DataProvider('recoveryMatches')]
    public function testActiveRecoveryUsesCompleteExpectedAuthority(bool $matches): void
    {
        $user = $this->user();
        $now = new DateTimeImmutable('2026-10-21T12:00:00.123456Z');
        $user->activate($this->hash(), $now);
        $role = RoleId::generate();
        $user->assignRole($role, $now);
        $user->requestEmailChange(EmailAddress::fromString('pending@example.test'), $now);
        $connection = $this->connection(inTransaction: true);
        $lock = $this->createStub(Result::class);
        $result = $this->createStub(Result::class);
        $result->method('fetchOne')->willReturn($matches ? 1 : false);
        $connection->expects(self::exactly(2))->method('executeQuery')->willReturnCallback(
            static function (string $sql, array $parameters) use ($user, $role, $lock, $result): Result {
                if (str_contains($sql, 'pg_advisory_xact_lock')) {
                    self::assertSame($user->getId()->toString(), $parameters[0]);

                    return $lock;
                }
                self::assertSame($user->getId()->toString(), $parameters['expected_id']);
                self::assertSame('pending@example.test', $parameters['expected_pending_email_change']);
                self::assertSame('{'.$role->toString().'}', $parameters['expected_roles']);
                self::assertSame(
                    $user->getAuthenticationAuthorityRevision(),
                    $parameters['expected_authentication_authority_revision']
                );
                self::assertSame(
                    $user->getAuthorizationAssignmentRevision(),
                    $parameters['expected_authorization_assignment_revision']
                );
                self::assertSame($user->getCanonicalEmailRevision(), $parameters['expected_canonical_email_revision']);

                return $result;
            }
        );

        self::assertSame($matches, $this->repository($connection)->validateRecoveryAuthority($user));
    }

    /**
     * Persists one identity with separate normalized canonical and pending claims
     */
    public function testAddsCanonicalAndPendingClaimsForTheSameIdentity(): void
    {
        $user = $this->user();
        $now = new DateTimeImmutable('2026-10-21T12:00:00Z');
        $user->activate($this->hash(), $now);
        $role = RoleId::generate();
        $user->assignRole($role, $now);
        $user->requestEmailChange(EmailAddress::fromString('New@Example.test'), $now);
        $connection = $this->connection(inTransaction: true);
        $connection->method('fetchFirstColumn')->with(self::anything(), [[$role->toString()]])
            ->willReturn([$role->toString()]);
        $rows = [];
        $connection->expects(self::exactly(4))->method('insert')->willReturnCallback(
            static function (string $table, array $row) use (&$rows): int {
                $rows[$table][] = $row;

                return 1;
            }
        );

        $this->repository($connection)->add($user);

        $id = $user->getId()->toString();
        self::assertSame($id, $rows['users'][0]['id']);
        self::assertSame('New@Example.test', $rows['users'][0]['pending_email_change']);
        self::assertSame([
            ['user_id' => $id, 'claim_type' => 'canonical', 'email' => 'person@example.test'],
            ['user_id' => $id, 'claim_type' => 'reservation', 'email' => 'new@example.test']
        ], $rows['user_email_claims']);
        self::assertSame([['user_id' => $id, 'role_id' => $role->toString()]], $rows['user_role_assignments']);
    }

    /**
     * Leaves no identity or email claim when a required role reference no longer exists
     */
    public function testMissingRoleBlocksIdentityWrite(): void
    {
        $user = $this->user();
        $role = RoleId::generate();
        $user->assignRole($role, new DateTimeImmutable('2026-10-21T12:00:00Z'));
        $connection = $this->connection(inTransaction: true);
        $connection->method('fetchFirstColumn')->with(self::anything(), [[$role->toString()]])->willReturn([]);
        $connection->expects(self::never())->method('insert');

        $this->expectException(PersistenceConflict::class);
        $this->repository($connection)->add($user);
    }

    /**
     * Touches recovery grants only when the changed lifecycle state replaces the expected snapshot
     */
    #[DataProvider('recoveryMatches')]
    public function testDisableDoesNotCancelRecoveryForStaleState(bool $matches): void
    {
        $user = $this->user();
        $user->activate($this->hash(), new DateTimeImmutable('2026-10-21T12:00:00Z'));
        $disabled = clone $user;
        $disabled->disable(new DateTimeImmutable('2026-10-22T12:00:00Z'));
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters) use ($user, $matches): int {
                self::assertSame($user->getId()->toString(), $parameters['expected_id']);
                self::assertSame('active', $parameters['expected_state']);
                self::assertSame('disabled', $parameters['replacement_state']);
                self::assertStringContainsString('UPDATE users', $sql);
                self::assertSame(
                    $user->getAuthenticationVersion() + 1,
                    $parameters['replacement_authentication_version']
                );

                return $matches ? 1 : 0;
            }
        );
        $grant = $connection->expects(self::exactly($matches ? 1 : 0))->method('fetchAssociative');
        if ($matches) {
            $grant->with(self::stringContains('password_reset_grants'), [$user->getId()->toString()])
                ->willReturn(false);
        }

        self::assertSame($matches, $this->repository($connection)->replaceLifecycleState($user, $disabled));
    }

    /**
     * Keeps a destination reservation separate from the email used for login
     */
    #[DataProvider('recoveryMatches')]
    public function testReservationClaimsOnlyTheDestinationAfterMatchingState(bool $matches): void
    {
        $user = $this->activeUser();
        $reserved = clone $user;
        $reserved->requestEmailChange(EmailAddress::fromString('New@Example.test'), $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters) use ($matches): int {
                self::assertStringContainsString('SET pending_email_change', $sql);
                self::assertNull($parameters['expected_pending_email_change']);
                self::assertSame('New@Example.test', $parameters['replacement_pending_email_change']);
                self::assertSame(1, $parameters['replacement_email_change_reservation_revision']);

                return $matches ? 1 : 0;
            }
        );
        $connection->expects(self::never())->method('delete');
        $insert = $connection->expects(self::exactly($matches ? 1 : 0))->method('insert');
        if ($matches) {
            $insert->with('user_email_claims', [
                'user_id'    => $user->getId()->toString(),
                'claim_type' => 'reservation',
                'email'      => 'new@example.test'
            ])->willReturn(1);
        }

        self::assertSame($matches, $this->repository($connection)->replaceEmailChangeReservation($user, $reserved));
    }

    /**
     * Moves the canonical claim and advances login authority only after a confirmation matches
     */
    #[DataProvider('recoveryMatches')]
    public function testConfirmationPromotesTheReservedAddress(bool $matches): void
    {
        $reserved = $this->activeUser();
        $reserved->requestEmailChange(EmailAddress::fromString('New@Example.test'), $this->later());
        $confirmed = clone $reserved;
        $confirmed->confirmEmailChange(new DateTimeImmutable('2026-10-23T12:00:00Z'));
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters) use ($matches, $reserved): int {
                self::assertStringContainsString('pending_email_change = NULL', $sql);
                self::assertSame('New@Example.test', $parameters['expected_pending_email_change']);
                self::assertSame('New@Example.test', $parameters['replacement_email']);
                self::assertSame(
                    $reserved->getAuthenticationVersion() + 1,
                    $parameters['replacement_authentication_version']
                );

                return $matches ? 1 : 0;
            }
        );
        $recovery = $connection->expects(self::exactly($matches ? 1 : 0))->method('fetchAssociative');
        $delete = $connection->expects(self::exactly($matches ? 1 : 0))->method('delete');
        $update = $connection->expects(self::exactly($matches ? 1 : 0))->method('update');
        if ($matches) {
            $id = $reserved->getId()->toString();
            $recovery->with(self::stringContains('password_reset_grants'), [$id])->willReturn(false);
            $delete->with('user_email_claims', ['user_id' => $id, 'claim_type' => 'reservation'])->willReturn(1);
            $update->with('user_email_claims', ['email' => 'new@example.test'], [
                'user_id'    => $id,
                'claim_type' => 'canonical'
            ])->willReturn(1);
        }

        self::assertSame(
            $matches,
            $this->repository($connection)->replaceEmailChangeConfirmation($reserved, $confirmed)
        );
    }

    /**
     * Rolls back reservation and confirmation when their email claim cannot be written
     */
    #[DataProvider('recoveryMatches')]
    public function testEmailClaimConflictRollsBack(bool $confirmation): void
    {
        $active = $this->activeUser();
        $reserved = clone $active;
        $reserved->requestEmailChange(EmailAddress::fromString('New@Example.test'), $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->willReturn(1);
        $connection->expects(self::once())->method('rollbackSavepoint');
        $repository = $this->repository($connection);
        $conflict = new UniqueConstraintViolationException(new PgSqlException('Synthetic claim conflict'), null);
        if (!$confirmation) {
            $connection->expects(self::once())->method('insert')->with('user_email_claims', [
                'user_id' => $active->getId()->toString(), 'claim_type' => 'reservation', 'email' => 'new@example.test'
            ])->willThrowException($conflict);
            self::assertFalse($repository->replaceEmailChangeReservation($active, $reserved));

            return;
        }
        $confirmed = clone $reserved;
        $confirmed->confirmEmailChange($this->later()->modify('+1 day'));
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('password_reset_grants'),
            [$active->getId()->toString()]
        )->willReturn(false);
        $connection->expects(self::once())->method('update')->with(
            'user_email_claims',
            ['email' => 'new@example.test'],
            ['user_id' => $active->getId()->toString(), 'claim_type' => 'canonical']
        )->willThrowException($conflict);
        self::assertFalse($repository->replaceEmailChangeConfirmation($reserved, $confirmed));
    }

    /**
     * Maps a taken destination to a rejected correction and rolls back the attempted replacement
     */
    public function testInvitationCorrectionRollsBackWhenCanonicalClaimConflicts(): void
    {
        $pending = $this->user();
        $corrected = clone $pending;
        $corrected->correctPendingInvitationEmail(EmailAddress::fromString('Taken@Example.test'), $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('canonical_email_revision = :replacement_canonical_email_revision'),
            self::callback(static fn (array $fields): bool => $fields['replacement_email'] === 'Taken@Example.test')
        )->willReturn(1);
        $connection->expects(self::once())->method('update')->with(
            'user_email_claims',
            ['email' => 'taken@example.test'],
            ['user_id' => $pending->getId()->toString(), 'claim_type' => 'canonical']
        )->willThrowException(new UniqueConstraintViolationException(
            new PgSqlException('Synthetic canonical claim conflict', '23505'),
            null
        ));
        $connection->expects(self::once())->method('rollbackSavepoint');

        self::assertFalse($this->repository($connection)->replacePendingRegistration($pending, $corrected));
    }

    /**
     * Moves only the canonical invitation claim after the guarded user update succeeds
     */
    #[DataProvider('recoveryMatches')]
    public function testInvitationCorrectionRequiresMatchingSnapshot(bool $matches): void
    {
        $pending = $this->user();
        $corrected = clone $pending;
        $corrected->correctPendingInvitationEmail(EmailAddress::fromString('New@Example.test'), $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('canonical_email_revision = :replacement_canonical_email_revision'),
            self::callback(static fn (array $fields): bool => $fields['replacement_email'] === 'New@Example.test'
                && $fields['expected_email'] === 'person@example.test')
        )->willReturn((int) $matches);
        $update = $connection->expects(self::exactly((int) $matches))->method('update');
        if ($matches) {
            $update->with('user_email_claims', ['email' => 'new@example.test'], [
                'user_id' => $pending->getId()->toString(), 'claim_type' => 'canonical'
            ])->willReturn(1);
        }

        self::assertSame($matches, $this->repository($connection)->replacePendingRegistration($pending, $corrected));
    }

    /**
     * Revalidates a no-change registration against the persisted snapshot without writing
     */
    #[DataProvider('recoveryMatches')]
    public function testUnchangedRegistrationRequiresCurrentPendingIdentity(bool $matches): void
    {
        $user = $this->user();
        $connection = $this->connection(inTransaction: true);
        $result = $this->createStub(Result::class);
        $result->method('fetchOne')->willReturn($matches ? 1 : false);
        $connection->method('executeQuery')->willReturnCallback(
            function (string $sql, array $parameters) use ($user, $result): Result {
                if (str_contains($sql, 'FROM users')) {
                    self::assertSame($user->getId()->toString(), $parameters['expected_id']);
                    self::assertSame(UserState::PENDING_ACTIVATION->value, $parameters['expected_state']);

                    return $result;
                }

                return $this->createStub(Result::class);
            }
        );
        $connection->expects(self::never())->method('executeStatement');
        $connection->expects(self::never())->method('update');

        self::assertSame($matches, $this->repository($connection)->replacePendingRegistration($user, clone $user));
    }

    /**
     * Rejects unrelated state changes before any write takes place
     */
    public function testActiveIdentityCannotUsePendingRegistrationOrConfirmWithoutReservation(): void
    {
        $active = $this->activeUser();
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::never())->method('executeStatement');
        $connection->expects(self::never())->method('update');
        $repository = $this->repository($connection);

        self::assertFalse($repository->replacePendingRegistration($active, clone $active));
        self::assertFalse($repository->replaceEmailChangeConfirmation($active, clone $active));
    }

    /**
     * Writes activation authority only for the expected identity without changing email or roles
     */
    #[DataProvider('recoveryMatches')]
    public function testActivationWritesTheNewPasswordAndAuthorityRevision(bool $matches): void
    {
        $pending = $this->user();
        $active = clone $pending;
        $active->activate($this->hash(), $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->willReturnCallback(
            function (string $sql, array $parameters) use ($pending, $matches): int {
                self::assertStringContainsString('UPDATE users', $sql);
                self::assertSame($pending->getId()->toString(), $parameters['expected_id']);
                self::assertSame('pending_activation', $parameters['expected_state']);
                self::assertNull($parameters['expected_password_hash']);
                self::assertSame('active', $parameters['replacement_state']);
                self::assertSame($this->hash()->toString(), $parameters['replacement_password_hash']);
                self::assertSame(1, $parameters['replacement_authentication_authority_revision']);

                return $matches ? 1 : 0;
            }
        );
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('insert');

        self::assertSame($matches, $this->repository($connection)->replaceAuthenticationAuthority($pending, $active));
    }

    /**
     * Prevents a stale role assignment replacement from removing existing grants
     */
    #[DataProvider('recoveryMatches')]
    public function testRoleAssignmentWritesRequireTheExpectedSnapshot(bool $matches): void
    {
        $user = $this->activeUser();
        $role = RoleId::generate();
        $assigned = clone $user;
        $assigned->assignRole($role, $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->method('fetchFirstColumn')->with(self::anything(), [[$role->toString()]])
            ->willReturn([$role->toString()]);
        $connection->expects(self::once())->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters) use ($matches): int {
                self::assertStringContainsString('SET authorization_assignment_revision', $sql);
                self::assertSame('{}', $parameters['expected_roles']);
                self::assertSame(1, $parameters['replacement_authorization_assignment_revision']);

                return $matches ? 1 : 0;
            }
        );
        $delete = $connection->expects(self::exactly($matches ? 1 : 0))->method('delete');
        $insert = $connection->expects(self::exactly($matches ? 1 : 0))->method('insert');
        if ($matches) {
            $id = $user->getId()->toString();
            $delete->with('user_role_assignments', ['user_id' => $id])->willReturn(0);
            $insert->with('user_role_assignments', ['user_id' => $id, 'role_id' => $role->toString()])->willReturn(1);
        }

        self::assertSame($matches, $this->repository($connection)->replaceRoleAssignments($user, $assigned));
    }

    /**
     * Refuses to apply role assignment if the new reference no longer exists
     */
    public function testMissingRoleBlocksAssignmentReplacement(): void
    {
        $user = $this->activeUser();
        $assigned = clone $user;
        $role = RoleId::generate();
        $assigned->assignRole($role, $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('fetchFirstColumn')->with(
            self::stringContains('FOR KEY SHARE'),
            [[$role->toString()]]
        )->willReturn([]);
        $connection->expects(self::never())->method('executeStatement');
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('insert');

        self::assertFalse($this->repository($connection)->replaceRoleAssignments($user, $assigned));
    }

    /**
     * Distinguishes a claimed email from an identity collision and a missing reference
     *
     * @phpstan-param class-string<\Throwable> $failure
     */
    #[DataProvider('insertConflicts')]
    public function testAddMapsOnlyTheEmailConstraintToDuplicateEmail(
        string $constraint,
        bool $foreignKey,
        string $failure,
        string $message
    ): void {
        $user = $this->user();
        $connection = $this->connection(inTransaction: true);
        $driver = new PgSqlException(sprintf('unique constraint "%s"', $constraint), $foreignKey ? '23503' : '23505');
        if ($foreignKey) {
            $exception = new ForeignKeyConstraintViolationException($driver, null);
        } else {
            $exception = new UniqueConstraintViolationException($driver, null);
        }
        $claimConflict = $failure === DuplicateEmailException::class;
        $conflictingTable = $claimConflict ? 'user_email_claims' : 'users';
        $connection->expects(self::exactly($claimConflict ? 2 : 1))->method('insert')->willReturnCallback(
            static function (string $table) use ($conflictingTable, $exception): int {
                if ($table === $conflictingTable) {
                    throw $exception;
                }

                return 1;
            }
        );
        if ($claimConflict) {
            $connection->expects(self::once())->method('rollbackSavepoint');
        }

        $this->expectException($failure);
        $this->expectExceptionMessage($message);
        $this->repository($connection)->add($user);
    }

    /**
     * Supplies PostgreSQL failures with distinct application outcomes
     *
     * @return iterable<string, array{string, bool, class-string<\Throwable>, string}>
     */
    public static function insertConflicts(): iterable
    {
        yield 'email claim' => [
            'uq_user_email_claims_email', false, DuplicateEmailException::class,
            'The email address is already reserved.'
        ];
        yield 'identity collision' => [
            'users_pkey', false, PersistenceConflict::class, 'The user identity is already in use.'
        ];
        yield 'missing reference' => [
            'user_role_assignments_role_id_fkey', true, PersistenceConflict::class,
            'The user identity or role reference is invalid.'
        ];
    }

    /**
     * Releases only the reservation and retains the canonical identity when the request is cancelled
     */
    public function testCancellationReleasesOnlyPendingEmailClaim(): void
    {
        $reserved = $this->activeUser();
        $reserved->requestEmailChange(EmailAddress::fromString('New@Example.test'), $this->later());
        $cancelled = clone $reserved;
        $cancelled->cancelEmailChange(new DateTimeImmutable('2026-10-23T12:00:00Z'));
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('SET pending_email_change'),
            self::callback(static function (array $fields): bool {
                return $fields['expected_pending_email_change'] === 'New@Example.test'
                    && array_key_exists('replacement_pending_email_change', $fields)
                    && $fields['replacement_pending_email_change'] === null;
            })
        )->willReturn(1);
        $connection->expects(self::never())->method('insert');
        $connection->expects(self::once())->method('delete')->with('user_email_claims', [
            'user_id'    => $reserved->getId()->toString(),
            'claim_type' => 'reservation'
        ])->willReturn(1);

        self::assertTrue($this->repository($connection)->replaceEmailChangeReservation($reserved, $cancelled));
    }

    /**
     * Compares persisted role permissions before granting the requested role to a new identity
     */
    #[DataProvider('recoveryMatches')]
    public function testRegistrationRequiresCurrentRoleState(bool $matches): void
    {
        $user = $this->user();
        $permission = PermissionId::generate();
        $role = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_REGISTERED'),
            [$permission],
            $this->later()
        );
        $roleId = $role->getId()->toString();
        $user->assignRole($role->getId(), $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('FROM roles'),
            [$roleId]
        )->willReturn([
            'id'         => $roleId,
            'name'       => 'ROLE_REGISTERED',
            'managed'    => 'f',
            'created_at' => '2026-10-22T12:00:00Z',
            'updated_at' => '2026-10-22T12:00:00Z',
            'revision'   => $role->getRevision()->toString()
        ]);
        $permissions = $matches ? [$permission->toString()] : [];
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static fn (string $sql): array => str_contains($sql, 'FROM role_permissions') ? $permissions : [$roleId]
        );
        $assignments = [];
        $connection->expects(self::exactly($matches ? 3 : 0))->method('insert')->willReturnCallback(
            static function (string $table, array $fields) use (&$assignments): int {
                if ($table === 'user_role_assignments') {
                    $assignments[] = $fields;
                }

                return 1;
            }
        );

        self::assertSame($matches, $this->repository($connection)->addWithRoleAssignments($user, [$role]));
        $expected = $matches ? [['user_id' => $user->getId()->toString(), 'role_id' => $roleId]] : [];
        self::assertSame($expected, $assignments);
    }

    /**
     * Refuses a disappeared role and a mismatch between validated and requested identities
     */
    #[DataProvider('recoveryMatches')]
    public function testRegistrationRejectsMissingOrSubstitutedRole(bool $exists): void
    {
        $user = $this->user();
        $user->assignRole(RoleId::generate(), $this->later());
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_REGISTERED'), [], $this->later());
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('FROM roles'),
            [$role->getId()->toString()]
        )->willReturn($exists ? [
            'id'         => $role->getId()->toString(),
            'name'       => 'ROLE_REGISTERED',
            'managed'    => 'f',
            'created_at' => '2026-10-22T12:00:00Z',
            'updated_at' => '2026-10-22T12:00:00Z',
            'revision'   => $role->getRevision()->toString()
        ] : false);
        $connection->expects(self::exactly((int) $exists))->method('fetchFirstColumn')->willReturn([]);
        $connection->expects(self::never())->method('insert');

        self::assertFalse($this->repository($connection)->addWithRoleAssignments($user, [$role]));
    }

    /**
     * Fences transaction-bound lifecycle reads before loading the user snapshot
     */
    #[DataProvider('recoveryMatches')]
    public function testLifecycleSnapshotFencesOnlyInsideTransaction(bool $inTransaction): void
    {
        $id = UserId::generate();
        $connection = $this->connection($inTransaction);
        $read = $this->createStub(Result::class);
        $read->method('fetchAssociative')->willReturn(false);
        $queries = [];
        $connection->expects(self::exactly($inTransaction ? 2 : 1))->method('executeQuery')->willReturnCallback(
            static function (string $sql, array $parameters) use (&$queries, $read): Result {
                $queries[] = [$sql, $parameters];

                return $read;
            }
        );

        self::assertNull($this->repository($connection, lockLifecycleSnapshot: true)->getById($id));
        if ($inTransaction) {
            self::assertStringContainsString('pg_advisory_xact_lock', $queries[0][0]);
            self::assertSame($id->toString(), $queries[0][1][0]);
        }
        $snapshot = $queries[$inTransaction ? 1 : 0];
        self::assertStringContainsString('FROM users', $snapshot[0]);
        self::assertSame(['value' => $id->toString()], $snapshot[1]);
    }

    /**
     * Issues a bound session only after authority advances and restores the savepoint on an insert conflict
     */
    #[DataProvider('coupledSessionOutcomes')]
    public function testSessionWriteDependsOnAuthorityUpdate(
        bool $matches,
        bool $duplicate,
        bool $foreignOwner,
        bool $staleVersion
    ): void {
        $expected = $this->activeUser();
        $replacement = clone $expected;
        $replacement->recordSuccessfulLogin($this->later());
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $foreignOwner ? UserId::generate() : $replacement->getId(),
            RefreshCredential::fromString(str_repeat('ab', 32)),
            $this->later(),
            $this->later()->modify('+1 hour'),
            $this->later()->modify('+1 day'),
            $replacement->getAuthenticationVersion() + (int) $staleVersion,
            false
        );
        $connection = $this->connection(inTransaction: true);
        $writes = [];
        $admitted = !$foreignOwner && !$staleVersion;
        $connection->expects(self::exactly((int) $admitted))->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $fields) use (&$writes, $expected, $replacement, $matches): int {
                $writes[] = 'authority';
                self::assertStringContainsString('UPDATE users', $sql);
                self::assertSame($expected->getId()->toString(), $fields['expected_id']);
                self::assertSame(
                    $expected->getAuthenticationAuthorityRevision(),
                    $fields['expected_authentication_authority_revision']
                );
                self::assertSame(
                    $replacement->getAuthenticationAuthorityRevision(),
                    $fields['replacement_authentication_authority_revision']
                );

                return (int) $matches;
            }
        );
        $connection->expects(self::exactly((int) ($admitted && $matches)))->method('insert')->willReturnCallback(
            static function (string $table, array $fields) use (&$writes, $session, $duplicate): int {
                $writes[] = 'session';
                self::assertSame('refresh_sessions', $table);
                self::assertSame($session->getId()->toString(), $fields['id']);
                self::assertSame($session->getUserId()->toString(), $fields['user_id']);
                self::assertSame($session->getAuthenticationVersion(), $fields['authentication_version']);
                self::assertSame($session->getCredentialDigest(), $fields['credential_digest']);
                if ($duplicate) {
                    throw new UniqueConstraintViolationException(new PgSqlException('Duplicate session ID'), null);
                }

                return 1;
            }
        );
        $connection->expects(self::exactly((int) $duplicate))->method('rollbackSavepoint');

        self::assertSame(
            $admitted && $matches && !$duplicate,
            $this->repository($connection)->replaceAuthenticationAuthorityAndAddRefreshSession(
                $expected,
                $replacement,
                $session
            )
        );
        self::assertSame(!$admitted ? [] : ($matches ? ['authority', 'session'] : ['authority']), $writes);
    }

    /**
     * Supplies successful, conflicting and ineligible session writes
     *
     * @return iterable<string, array{bool, bool, bool, bool}>
     */
    public static function coupledSessionOutcomes(): iterable
    {
        yield 'bound session' => [true, false, false, false];
        yield 'stale authority snapshot' => [false, false, false, false];
        yield 'duplicate session' => [true, true, false, false];
        yield 'different session owner' => [true, false, true, false];
        yield 'wrong authentication version' => [true, false, false, true];
    }

    /**
     * Supplies matching and stale database snapshots
     *
     * @return iterable<string, array{bool}>
     */
    public static function recoveryMatches(): iterable
    {
        yield 'matching snapshot' => [true];
        yield 'stale snapshot' => [false];
    }

    /**
     * Keeps actual query construction while substituting only its database boundary
     */
    private function connection(bool $inTransaction = false): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn($inTransaction);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->method('createQueryBuilder')->willReturnCallback(
            static fn (): QueryBuilder => new QueryBuilder($connection)
        );
        $connection->method('fetchOne')->with('SHOW transaction_isolation')->willReturn('read committed');

        return $connection;
    }

    /**
     * Uses the real reference and authority fences on the same isolated connection
     */
    private function repository(Connection $connection, bool $lockLifecycleSnapshot = false): PostgresUserRepository
    {
        return new PostgresUserRepository(
            $connection,
            new AuthorizationReferenceFences($connection),
            new AuthenticationAuthorityFences($connection),
            $lockLifecycleSnapshot
        );
    }

    /**
     * Cancels outstanding recovery with the lifecycle change but does not rewrite an already revoked grant
     */
    #[DataProvider('recoveryMatches')]
    public function testDisableCancelsOnlyAnIssuedRecoveryGrant(bool $issued): void
    {
        $user = $this->activeUser();
        $disabled = clone $user;
        $disabled->disable($this->later());
        $at = $this->later()->modify('-5 minutes');
        $grant = PasswordResetGrant::issue(
            $user->getId(),
            PasswordResetCredential::fromString(str_repeat('ab', 32)),
            $at,
            $at->modify('+1 hour'),
            $user->getEmail(),
            'fixture-ciphertext',
            $user->getRecoveryAuthority()
        );
        $stored = $issued ? $grant : $grant->revoke($at);
        $connection = $this->connection(inTransaction: true);
        $connection->expects(self::once())->method('executeStatement')->with(self::stringContains('UPDATE users'))
            ->willReturn(1);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('ORDER BY generation DESC LIMIT 1 FOR UPDATE'),
            [$user->getId()->toString()]
        )->willReturn(PasswordResetGrantRecords::fields($stored));
        $write = $connection->expects(self::exactly((int) $issued))->method('update');
        if ($issued) {
            $write->with(
                'password_reset_grants',
                self::callback(static function (array $fields): bool {
                    self::assertSame('2026-10-22 12:00:00.000000+00:00', $fields['revoked_at']);
                    self::assertSame('invalidated', $fields['delivery_status']);
                    self::assertNull($fields['delivery_ciphertext']);

                    return true;
                }),
                ['id' => $grant->getId()->toString()]
            )->willReturn(1);
        }

        self::assertTrue($this->repository($connection)->replaceLifecycleState($user, $disabled));
    }

    /**
     * Supplies a pending user distinct from every other identity in the test
     */
    private function user(): User
    {
        return User::invite(
            UserId::generate(),
            EmailAddress::fromString('person@example.test'),
            new DateTimeImmutable('2026-10-20T12:00:00Z')
        );
    }

    /**
     * Supplies an activated identity before a persistence transition
     */
    private function activeUser(): User
    {
        $user = $this->user();
        $user->activate($this->hash(), new DateTimeImmutable('2026-10-21T12:00:00Z'));

        return $user;
    }

    /**
     * Supplies the next write time after activation
     */
    private function later(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-22T12:00:00Z');
    }

    /**
     * Supplies fixed synthetic encoded password data without performing password hashing
     */
    private function hash(): PasswordHash
    {
        return PasswordHash::fromString('$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
    }
}
