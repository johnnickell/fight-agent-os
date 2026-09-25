<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
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
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IdentityRepositoryTest extends TestCase
{
    private Connection $connection;
    private DoctrineTransactionalUnitOfWork $unitOfWork;
    private PostgresUserRepository $users;
    private PostgresRoleRepository $roles;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement(
            'TRUNCATE refresh_session_used_credentials, refresh_sessions, user_role_assignments, '
            . 'user_email_claims, users, role_permissions, roles, permissions CASCADE'
        );
        $this->users = new PostgresUserRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection),
            new AuthenticationAuthorityFences($this->connection)
        );
        $this->roles = new PostgresRoleRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $entityManager = new EntityManager($this->connection, $configuration);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);
    }

    public function test_add_and_resolve_round_trips_pending_identity_with_roles(): void
    {
        $roleId = $this->role('ROLE_VIEWER');
        $user = $this->pendingUser('pending@example.test');
        $user->assignRole($roleId, $this->clock('2026-10-01T12:00:01+00:00'));
        $this->commitAdd($user);

        self::assertUserEquals($user, $this->users->getById($user->getId()));
        self::assertUserEquals($user, $this->users->getByEmail($user->getEmail()));
        self::assertNull($this->users->getById(UserId::generate()));
        self::assertTrue($this->users->hasRoleAssignment($roleId));
        self::assertSame(1, $this->users->getAll(new Pagination())->totalRecords());
    }

    public function test_add_rejects_canonical_and_live_reservation_email_claims(): void
    {
        $this->commitAdd($this->pendingUser('claimed@example.test'));

        try {
            $this->commitAdd($this->pendingUser('CLAIMED@example.test'));
            self::fail('A canonical email claim must be rejected.');
        } catch (DuplicateEmailException $exception) {
            self::assertSame('The email address is already reserved.', $exception->getMessage());
            $this->rebuildUnitOfWork();
        }

        $reserving = $this->activeUser('reserving@example.test');
        $this->commitAdd($reserving);
        $current = $this->users->getById($reserving->getId());
        $replacement = clone $current;
        $replacement->requestEmailChange(
            EmailAddress::fromString('reserved@example.test'),
            $this->clock('2026-10-01T12:00:05+00:00')
        );
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceEmailChangeReservation($current, $replacement)
        ));

        try {
            $this->commitAdd($this->pendingUser('reserved@example.test'));
            self::fail('A live reservation claim must be rejected.');
        } catch (DuplicateEmailException $exception) {
            self::assertSame('The email address is already reserved.', $exception->getMessage());
            $this->rebuildUnitOfWork();
        }
    }

    public function test_replace_authentication_authority_compares_expected_state(): void
    {
        $user = $this->activeUser('authority@example.test');
        $this->commitAdd($user);
        $expected = $this->users->getById($user->getId());
        $replacement = clone $expected;
        $replacement->changePassword($this->passwordHash('new-secret'), $this->clock('2026-10-01T12:01:00+00:00'));
        $replacement->advanceAuthenticationAuthorityRevision();

        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceAuthenticationAuthority($expected, $replacement)
        ));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceAuthenticationAuthority($expected, $replacement)
        ));
        self::assertUserEquals($replacement, $this->users->getById($user->getId()));

        $stale = clone $expected;
        $stale->changePassword($this->passwordHash('stale-secret'), $this->clock('2026-10-01T12:02:00+00:00'));
        $stale->advanceAuthenticationAuthorityRevision();
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceAuthenticationAuthority($expected, $stale)
        ));

        $missingRevision = clone $replacement;
        $missingRevision->changePassword(
            $this->passwordHash('other-secret'),
            $this->clock('2026-10-01T12:03:00+00:00')
        );
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceAuthenticationAuthority($replacement, $missingRevision)
        ));
    }

    public function test_replace_authentication_authority_and_add_refresh_session_is_indivisible(): void
    {
        $user = $this->activeUser('coupled@example.test');
        $this->commitAdd($user);
        $expected = $this->users->getById($user->getId());
        $replacement = clone $expected;
        $replacement->advanceAuthenticationAuthorityRevision();
        $session = $this->session($replacement, $this->clock('2026-10-01T12:05:00+00:00'));

        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceAuthenticationAuthorityAndAddRefreshSession(
                $expected,
                $replacement,
                $session
            )
        ));
        self::assertUserEquals($replacement, $this->users->getById($user->getId()));
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_sessions')
        );

        $foreignSession = $this->session($replacement, $this->clock('2026-10-01T12:06:00+00:00'), UserId::generate());
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceAuthenticationAuthorityAndAddRefreshSession(
                $replacement,
                $this->withAdvancedAuthority($replacement),
                $foreignSession
            )
        ));
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_sessions')
        );
    }

    public function test_replace_role_assignments_handles_roles_and_stale_state(): void
    {
        $first = $this->role('ROLE_EDITOR');
        $second = $this->role('ROLE_REVIEWER');
        $user = $this->activeUser('assignments@example.test');
        $user->assignRole($first, $this->clock('2026-10-01T12:00:01+00:00'));
        $this->commitAdd($user);
        $expected = $this->users->getById($user->getId());
        $replacement = clone $expected;
        $replacement->assignRole($second, $this->clock('2026-10-01T12:07:00+00:00'));

        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceRoleAssignments($expected, $replacement)
        ));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceRoleAssignments($expected, $replacement)
        ));
        self::assertUserEquals($replacement, $this->users->getById($user->getId()));

        $removal = clone $this->users->getById($user->getId());
        $removal->removeRole($first, $this->clock('2026-10-01T12:08:00+00:00'));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceRoleAssignments(
                $this->users->getById($user->getId()),
                $removal
            )
        ));

        $unknown = clone $this->users->getById($user->getId());
        $unknown->assignRole(RoleId::generate(), $this->clock('2026-10-01T12:09:00+00:00'));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceRoleAssignments(
                $this->users->getById($user->getId()),
                $unknown
            )
        ));
    }

    public function test_live_reservation_conflicts_are_atomically_rejected(): void
    {
        $first = $this->activeUser('first-reserver@example.test');
        $second = $this->activeUser('second-reserver@example.test');
        $this->commitAdd($first);
        $this->commitAdd($second);
        $destination = EmailAddress::fromString('shared-destination@example.test');

        $firstExpected = $this->users->getById($first->getId());
        $firstReplacement = clone $firstExpected;
        $firstReplacement->requestEmailChange($destination, $this->clock('2026-10-01T12:20:00+00:00'));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceEmailChangeReservation($firstExpected, $firstReplacement)
        ));

        $secondExpected = $this->users->getById($second->getId());
        $secondReplacement = clone $secondExpected;
        $secondReplacement->requestEmailChange($destination, $this->clock('2026-10-01T12:21:00+00:00'));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceEmailChangeReservation($secondExpected, $secondReplacement)
        ));
        self::assertSame(
            $destination->toString(),
            $this->users->getById($first->getId())?->getPendingEmailChange()?->toString()
        );
        self::assertNull($this->users->getById($second->getId())?->getPendingEmailChange());
    }

    public function test_email_change_reservation_confirmation_and_correction(): void
    {
        $user = $this->activeUser('emailchange@example.test');
        $this->commitAdd($user);
        $expected = $this->users->getById($user->getId());
        $reserving = clone $expected;
        $reserving->requestEmailChange(
            EmailAddress::fromString('destination@example.test'),
            $this->clock('2026-10-01T12:10:00+00:00')
        );
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceEmailChangeReservation($expected, $reserving)
        ));
        self::assertUserEquals($reserving, $this->users->getById($user->getId()));

        $confirming = clone $reserving;
        $confirming->confirmEmailChange($this->clock('2026-10-01T12:11:00+00:00'));
        $confirming->advanceAuthenticationAuthorityRevision();
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceEmailChangeConfirmation($reserving, $confirming)
        ));
        self::assertUserEquals($confirming, $this->users->getById($user->getId()));
        self::assertUserEquals($confirming, $this->users->getByEmail($confirming->getEmail()));

        $pending = $this->pendingUser('correct@pending.example.test');
        $this->commitAdd($pending);
        $pendingExpected = $this->users->getById($pending->getId());
        $corrected = clone $pendingExpected;
        $corrected->correctPendingInvitationEmail(
            EmailAddress::fromString('corrected@pending.example.test'),
            $this->clock('2026-10-01T12:12:00+00:00')
        );
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replacePendingInvitationEmail($pendingExpected, $corrected)
        ));
        self::assertUserEquals($corrected, $this->users->getById($pending->getId()));
    }

    public function test_lifecycle_transitions_and_coupled_rollback(): void
    {
        $user = $this->activeUser('lifecycle@example.test');
        $this->commitAdd($user);

        $disabled = clone $this->users->getById($user->getId());
        $disabled->disable($this->clock('2026-10-01T12:13:00+00:00'));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceLifecycleState(
                $this->users->getById($user->getId()),
                $disabled
            )
        ));

        $enabled = clone $this->users->getById($user->getId());
        $enabled->enable($this->clock('2026-10-01T12:13:30+00:00'));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceLifecycleState(
                $this->users->getById($user->getId()),
                $enabled
            )
        ));

        $deleted = clone $this->users->getById($user->getId());
        $deleted->delete($this->clock('2026-10-01T12:13:45+00:00'));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceLifecycleState(
                $this->users->getById($user->getId()),
                $deleted
            )
        ));

        $restored = clone $this->users->getById($user->getId());
        $restored->restore(UserState::PENDING_ACTIVATION, $this->clock('2026-10-01T12:14:00+00:00'));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceLifecycleState(
                $this->users->getById($user->getId()),
                $restored
            )
        ));
        self::assertNull($this->users->getById($user->getId())?->getPasswordHash());

        $rollback = $this->activeUser('rollback@example.test');

        try {
            $this->unitOfWork->commitTransactional(function () use ($rollback): never {
                $this->users->add($rollback);
                throw new RuntimeException('Injected failure');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('Injected failure', $exception->getMessage());
        }

        self::assertNull($this->users->getById($rollback->getId()));
    }

    public function test_competing_authentication_authority_replacements_serialize(): void
    {
        $user = $this->activeUser('race@example.test');
        $this->commitAdd($user);
        $expected = $this->users->getById($user->getId());
        $winner = clone $expected;
        $winner->changePassword($this->passwordHash('winner'), $this->clock('2026-10-01T12:15:00+00:00'));
        $winner->advanceAuthenticationAuthorityRevision();
        $loser = clone $expected;
        $loser->changePassword($this->passwordHash('loser'), $this->clock('2026-10-01T12:15:01+00:00'));
        $loser->advanceAuthenticationAuthorityRevision();

        $competingConnection = $this->connection();
        $competingUsers = new PostgresUserRepository(
            $competingConnection,
            new AuthorizationReferenceFences($competingConnection),
            new AuthenticationAuthorityFences($competingConnection)
        );

        $this->connection->beginTransaction();
        $competingConnection->beginTransaction();
        $competingConnection->executeStatement("SET LOCAL lock_timeout = '100ms'");

        try {
            self::assertTrue($this->users->replaceAuthenticationAuthority($expected, $winner));
            try {
                $competingUsers->replaceAuthenticationAuthority($expected, $loser);
                self::fail('A competing authority replacement must wait on the per-user fence.');
            } catch (DriverException) {
                self::assertTrue(true);
            }
            $this->connection->commit();
            $competingConnection->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($competingConnection->isTransactionActive()) {
                $competingConnection->rollBack();
            }
            $competingConnection->close();
        }

        self::assertUserEquals($winner, $this->users->getById($user->getId()));
    }

    public function test_role_reference_fence_serializes_competing_role_removal(): void
    {
        $roleId = $this->role('ROLE_FENCED');
        $user = $this->activeUser('fenced@example.test');
        $this->commitAdd($user);
        $expected = $this->users->getById($user->getId());
        $replacement = clone $expected;
        $replacement->assignRole($roleId, $this->clock('2026-10-01T12:16:00+00:00'));

        $competingConnection = $this->connection();
        $competingRoles = new PostgresRoleRepository(
            $competingConnection,
            new AuthorizationReferenceFences($competingConnection)
        );

        $this->connection->beginTransaction();
        $competingConnection->beginTransaction();
        $competingConnection->executeStatement("SET LOCAL lock_timeout = '100ms'");

        try {
            self::assertTrue($this->users->replaceRoleAssignments($expected, $replacement));
            $role = $this->roles->getById($roleId);
            self::assertNotNull($role);
            try {
                $competingRoles->remove($role);
                self::fail('A competing role removal must wait on the role-reference fence.');
            } catch (DriverException) {
                self::assertTrue(true);
            }
            $this->connection->commit();
            $competingConnection->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($competingConnection->isTransactionActive()) {
                $competingConnection->rollBack();
            }
            $competingConnection->close();
        }

        self::assertTrue($this->users->hasRoleAssignment($roleId));
    }

    public function test_add_fails_closed_without_an_enclosing_transaction(): void
    {
        $this->expectException(\LogicException::class);
        $this->users->add($this->pendingUser('no-transaction@example.test'));
    }

    private function commitAdd(User $user): void
    {
        $this->unitOfWork->commitTransactional(fn() => $this->users->add($user));
    }

    private function rebuildUnitOfWork(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $entityManager = new EntityManager($this->connection, $configuration);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);
    }

    private function role(string $name): RoleId
    {
        $roleId = RoleId::generate();
        $role = Role::define(
            $roleId,
            RoleName::fromString($name),
            [],
            $this->clock('2026-10-01T11:00:00+00:00')
        );
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($role));

        return $roleId;
    }

    private function pendingUser(string $email): User
    {
        return User::invite(
            UserId::generate(),
            EmailAddress::fromString($email),
            $this->clock('2026-10-01T12:00:00+00:00')
        );
    }

    private function activeUser(string $email): User
    {
        $user = $this->pendingUser($email);
        $user->activate($this->passwordHash('initial-secret'), $this->clock('2026-10-01T12:00:01+00:00'));

        return $user;
    }

    private function withAdvancedAuthority(User $user): User
    {
        $replacement = clone $user;
        $replacement->advanceAuthenticationAuthorityRevision();

        return $replacement;
    }

    private function session(
        User $user,
        DateTimeImmutable $createdAt,
        ?UserId $userId = null
    ): RefreshSession {
        return RefreshSession::start(
            RefreshSessionId::generate(),
            $userId ?? $user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $createdAt,
            $createdAt->modify('+1 hour'),
            $createdAt->modify('+1 day'),
            $user->getAuthenticationVersion(),
            false
        );
    }

    private function passwordHash(string $plain): PasswordHash
    {
        return PasswordHash::fromString((string) password_hash($plain, PASSWORD_BCRYPT));
    }

    private function clock(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    private function connection(): Connection
    {
        $databaseUrl = getenv('TEST_DATABASE_URL');
        $allowedHost = getenv('TEST_DATABASE_ALLOWED_HOST');
        self::assertIsString($databaseUrl);
        self::assertNotSame('', $databaseUrl);
        self::assertIsString($allowedHost);
        self::assertNotSame('', $allowedHost);

        $guard = new DatabaseTargetGuard(array_values(array_filter(array_map(
            'trim',
            explode(',', $allowedHost)
        ))));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $databaseUrl);
        $connection = DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql',
        ]))->parse($databaseUrl));
        $guard->assertConnected($connection, $expected);

        return $connection;
    }

    private static function assertUserEquals(User $expected, ?User $actual): void
    {
        self::assertNotNull($actual);
        self::assertSame($expected->getId()->toString(), $actual->getId()->toString());
        self::assertSame($expected->getEmail()->toString(), $actual->getEmail()->toString());
        self::assertSame($expected->getState(), $actual->getState());
        self::assertSame($expected->getPasswordHash()?->toString(), $actual->getPasswordHash()?->toString());
        self::assertSame($expected->getAuthenticationVersion(), $actual->getAuthenticationVersion());
        self::assertSame(
            $expected->getAuthenticationAuthorityRevision(),
            $actual->getAuthenticationAuthorityRevision()
        );
        self::assertSame(
            $expected->getAuthorizationAssignmentRevision(),
            $actual->getAuthorizationAssignmentRevision()
        );
        self::assertSame(
            $expected->getPendingEmailChange()?->toString(),
            $actual->getPendingEmailChange()?->toString()
        );
        self::assertSame(
            $expected->getEmailChangeReservationRevision(),
            $actual->getEmailChangeReservationRevision()
        );
        self::assertSame($expected->getCanonicalEmailRevision(), $actual->getCanonicalEmailRevision());
        self::assertEquals($expected->getCreatedAt(), $actual->getCreatedAt());
        self::assertEquals($expected->getUpdatedAt(), $actual->getUpdatedAt());

        $expectedRoles = array_map(
            static fn(RoleId $id): string => $id->toString(),
            $expected->getRoleIds()
        );
        $actualRoles = array_map(
            static fn(RoleId $id): string => $id->toString(),
            $actual->getRoleIds()
        );
        sort($expectedRoles);
        sort($actualRoles);
        self::assertSame($expectedRoles, $actualRoles);
    }
}