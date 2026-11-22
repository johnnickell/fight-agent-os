<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\User\PostgresUserRepository;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
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
    private function repository(Connection $connection): PostgresUserRepository
    {
        return new PostgresUserRepository(
            $connection,
            new AuthorizationReferenceFences($connection),
            new AuthenticationAuthorityFences($connection)
        );
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
     * Supplies fixed synthetic encoded password data without performing password hashing
     */
    private function hash(): PasswordHash
    {
        return PasswordHash::fromString('$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
    }
}
