<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\Repository\PostgresAgentRepository;
use App\Adapter\Persistence\Repository\PostgresFeatureRepository;
use App\Adapter\Persistence\Repository\PostgresPermissionRepository;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\InvitationCorrectionAuthorization;
use App\Application\Failure\PermissionDenied;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\GrantPermissionToCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\InvitationAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\Agent\Agent;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentCredentialId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentName;
use Fight\AccessControl\Domain\AccessControl\Agent\Exception\AgentOperationRejectedException;
use Fight\AccessControl\Domain\AccessControl\Agent\Operation\AgentOperationFailure;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureConflictException;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureReferenceException;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureStateException;
use Fight\AccessControl\Domain\AccessControl\Feature\Feature;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureId;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureName;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureStatus;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Role\Command\GrantPermissionToCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Exception\CustomRoleException;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class AuthorityRepositoryTest
 */
final class AuthorityRepositoryTest extends TestCase
{
    private Connection $connection;
    private DoctrineTransactionalUnitOfWork $unitOfWork;
    private PostgresPermissionRepository $permissions;
    private PostgresRoleRepository $roles;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('TRUNCATE agents, role_permissions, roles, permissions CASCADE');
        $fences = new AuthorizationReferenceFences($this->connection);
        $this->permissions = new PostgresPermissionRepository($this->connection, $fences);
        $this->roles = new PostgresRoleRepository($this->connection, $fences);
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $entityManager = new EntityManager($this->connection, $configuration);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);
    }

    /**
     * Releases the fixture connection independently of garbage collection
     */
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    /**
     * Verifies permission contract round trips managed and custom state
     */
    public function testPermissionContractRoundTripsManagedAndCustomState(): void
    {
        $createdAt = new DateTimeImmutable('2026-10-01T12:00:00.123456+00:00');
        $custom = Permission::define(
            PermissionId::generate(),
            PermissionName::fromString('VIEW_USERS'),
            $createdAt
        );
        $managed = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('MANAGE_USERS'),
            PermissionTier::SUPER_ADMIN_ONLY,
            $createdAt->modify('+1 second')
        );
        $this->permissions->add($custom);
        $this->permissions->add($managed);

        self::assertPermissionEquals($custom, $this->permissions->getById($custom->getId()));
        self::assertPermissionEquals($managed, $this->permissions->getByName($managed->getName()));
        self::assertNull($this->permissions->getById(PermissionId::generate()));
        self::assertSame(
            [$managed->getId()->toString(), $custom->getId()->toString()],
            array_map(
                static fn(Permission $permission): string => $permission->getId()->toString(),
                $this->permissions->getByIds([
                    $managed->getId(),
                    PermissionId::generate(),
                    $custom->getId(),
                    $managed->getId()
                ])
            )
        );
        self::assertSame(2, $this->permissions->getAll(new Pagination(1, 1))->totalRecords());
        self::assertSame(
            [$managed->getId()->toString()],
            array_map(
                static fn(Permission $permission): string => $permission->getId()->toString(),
                $this->permissions->getManaged()
            )
        );
    }

    /**
     * Verifies permission replacement compares complete expected state
     */
    public function testPermissionReplacementComparesCompleteExpectedState(): void
    {
        $current = $this->managedPermission('MANAGE_USERS');
        $this->permissions->add($current);
        $replacement = $current->reconcileManaged(
            PermissionName::fromString('ADMINISTER_USERS'),
            PermissionTier::ADMIN_SAFE,
            $current->getUpdatedAt()->modify('+1 second')
        );

        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->permissions->replace($current, $replacement)
        ));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->permissions->replace($current, $replacement)
        ));
        self::assertPermissionEquals($replacement, $this->permissions->getById($current->getId()));
    }

    /**
     * Verifies known identity conflicts are safe and do not leak sql
     */
    public function testKnownIdentityConflictsAreSafeAndDoNotLeakSql(): void
    {
        $first = $this->customPermission('VIEW_USERS');
        $this->permissions->add($first);

        try {
            $this->permissions->add(Permission::define(
                PermissionId::generate(),
                $first->getName(),
                $first->getCreatedAt()
            ));
            self::fail('A duplicate canonical name must conflict.');
        } catch (PersistenceConflict $conflict) {
            self::assertSame('The permission identity or name is already in use.', $conflict->getMessage());
            self::assertStringNotContainsString('SQL', $conflict->getMessage());
            self::assertStringNotContainsString('permissions', $conflict->getMessage());
        }
    }

    /**
     * Verifies role contract round trips membership queries and pages
     */
    public function testRoleContractRoundTripsMembershipQueriesAndPages(): void
    {
        $first = $this->customPermission('VIEW_USERS');
        $second = $this->managedPermission('MANAGE_USERS');
        $this->permissions->add($first);
        $this->permissions->add($second);
        $role = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [$second->getId(), $first->getId()],
            new DateTimeImmutable('2026-10-01T13:00:00+00:00')
        );
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($role));

        self::assertRoleEquals($role, $this->roles->getById($role->getId()));
        self::assertRoleEquals($role, $this->roles->getByName($role->getName()));
        self::assertSame(
            [$role->getId()->toString()],
            array_map(
                static fn(Role $candidate): string => $candidate->getId()->toString(),
                $this->roles->getContainingPermission($first->getId())
            )
        );
        self::assertSame(1, $this->roles->getAll(new Pagination())->totalRecords());
        self::assertCount(1, $this->roles->getManaged());
        self::assertSame(
            [$role->getId()->toString()],
            array_map(
                static fn(Role $candidate): string => $candidate->getId()->toString(),
                $this->roles->getByIds([$role->getId(), RoleId::generate(), $role->getId()])
            )
        );
    }

    /**
     * Preserves managed role reconciliation and safely rejects duplicate role identity
     */
    public function testManagedRoleReconciliationAndDuplicateIdentity(): void
    {
        $permission = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('MANAGE_USERS'),
            PermissionTier::SUPER_ADMIN_ONLY,
            new DateTimeImmutable('2026-10-01T12:00:00Z')
        );
        $this->permissions->add($permission);
        $role = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [],
            new DateTimeImmutable('2026-10-01T13:00:00Z')
        );
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($role));
        $updated = $role->reconcileManaged(
            $role->getName(),
            [$permission->getId()],
            new DateTimeImmutable('2026-10-01T13:01:00Z')
        );
        self::assertTrue($this->unitOfWork->commitTransactional(fn(): bool => $this->roles->replace($role, $updated)));
        self::assertRoleEquals($updated, $this->roles->getById($role->getId()));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->roles->validateCustomPermissionGrant($permission)
        ));
        $this->expectException(PersistenceConflict::class);
        $this->expectExceptionMessage('The role identity or name is already in use.');
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($role));
    }

    /**
     * Exercises the tagged custom-role grant through the owned PostgreSQL adapters
     */
    public function testTaggedGrantAcceptsOnlyAuthoritativeAdminSafePermission(): void
    {
        $at = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $eligible = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('READ_DASHBOARD'),
            PermissionTier::ADMIN_SAFE,
            $at
        );
        $protected = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('MANAGE_AUTHORITY'),
            PermissionTier::SUPER_ADMIN_ONLY,
            $at
        );
        $this->permissions->add($eligible);
        $this->permissions->add($protected);
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_READER'), [], $at);
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($role));

        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($at->modify('+1 second'));
        $handler = new GrantPermissionToCustomRoleHandler(
            $this->roles,
            $this->permissions,
            $clock,
            $this->unitOfWork,
            $this->createStub(EventDispatcher::class)
        );
        $actor = UserId::generate();
        $handler->handle(CommandMessage::create(new GrantPermissionToCustomRole(
            $actor,
            $role->getId(),
            $eligible->getId()
        )));
        self::assertTrue($this->roles->getById($role->getId())->hasPermission($eligible->getId()));

        // A repeated grant is a no-op, but must still validate current eligibility.
        $handler->handle(CommandMessage::create(new GrantPermissionToCustomRole(
            $actor,
            $role->getId(),
            $eligible->getId()
        )));
        self::assertEquals($at->modify('+1 second'), $this->roles->getById($role->getId())->getUpdatedAt());

        try {
            $handler->handle(CommandMessage::create(new GrantPermissionToCustomRole(
                $actor,
                $role->getId(),
                $protected->getId()
            )));
            self::fail('A protected Permission must not be granted to a custom Role.');
        } catch (CustomRoleException) {
            self::assertFalse($this->roles->getById($role->getId())->hasPermission($protected->getId()));
        }
    }

    /**
     * Checks fresh actor membership and target scope against installed PostgreSQL authority
     */
    public function testInstallationAuthorityUsesCurrentPostgresAssignments(): void
    {
        $at = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $permission = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('MANAGE_USER_INVITATIONS'),
            PermissionTier::SUPER_ADMIN_ONLY,
            $at
        );
        $role = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [$permission->getId()],
            $at
        );
        $actorId = UserId::generate();
        $actor = User::invite(
            $actorId,
            EmailAddress::fromString('actor-'.$actorId->toString().'@example.test'),
            $at
        );
        $actor->activate(PasswordHash::fromString((string) password_hash('test-password', PASSWORD_BCRYPT)), $at);
        $actor->assignRole($role->getId(), $at);
        $target = User::invite(
            UserId::generate(),
            EmailAddress::fromString('target-'.$actorId->toString().'@example.test'),
            $at
        );
        $users = new PostgresUserRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection),
            new AuthenticationAuthorityFences($this->connection)
        );
        $authority = new InstallationAuthority($users, $this->roles, $this->permissions);
        $invitationCallback = new InvitationCorrectionAuthorization($authority);

        $this->connection->beginTransaction();
        try {
            $this->permissions->add($permission);
            $this->roles->add($role);
            $users->add($actor);
            $users->add($target);
            $authority->assertInstallationPermission($actorId, $permission->getName(), $target->getId());
            $invitationCallback->assertCanCorrectInvitation($actorId, $target->getId());
            $updated = clone $actor;
            $updated->removeRole($role->getId(), $at->modify('+1 second'));
            self::assertTrue($users->replaceRoleAssignments($actor, $updated));
            try {
                $authority->assertInstallationPermission($actorId, $permission->getName(), $target->getId());
                self::fail('Revoked membership must be observed on the next decision.');
            } catch (PermissionDenied) {
                self::assertSame([], $users->getById($actorId)->getRoleIds());
            }
            try {
                $invitationCallback->assertCanCorrectInvitation($actorId, $target->getId());
                self::fail('Revoked membership cannot authorize pending invitation correction.');
            } catch (InvitationAdministrationAuthorizationException) {
                self::assertSame([], $users->getById($actorId)->getRoleIds());
            }
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Persist and reconstruct Agent authority while leaving operation admission unavailable
     */
    public function testAgentAuthorityRoundTripsAndRetainsDirectPermissions(): void
    {
        $agents = new PostgresAgentRepository($this->connection, new AuthorizationReferenceFences($this->connection));
        $permission = $this->customPermission('AGENT_READ');
        $this->permissions->add($permission);
        $at = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $agent = Agent::provision(
            AgentId::generate(),
            AgentName::fromString('test-agent'),
            AgentCredentialId::generate(),
            'opaque-fixture-envelope',
            $at
        );
        self::assertNull($agents->getById($agent->getId()));
        self::assertFalse($agents->hasPermissionAssignment($permission->getId()));

        $this->connection->beginTransaction();
        try {
            $agents->add($agent);
            $loaded = $agents->getByCredentialId($agent->getCredentialId());
            self::assertSame($agent->getId()->toString(), $loaded->getId()->toString());
            self::assertSame('opaque-fixture-envelope', $loaded->getEncryptedHmacSharedSecretEnvelope());
            self::assertSame(1, $agents->getAll(new Pagination())->totalRecords());
            self::assertTrue($agents->validatePermissionAssignments([$permission]));
            $granted = $loaded->grantPermission($permission->getId(), $at->modify('+1 second'));
            self::assertTrue($agents->replacePermissionAssignments($loaded, $granted));
            self::assertFalse($agents->replacePermissionAssignments($loaded, $granted));
            self::assertTrue($agents->hasPermissionAssignment($permission->getId()));
            self::assertSame(2, $agents->getById($agent->getId())->getPermissionAssignmentRevision());
            self::assertFalse($this->permissions->remove($permission));
            $renamedAt = $at->modify('+2 seconds');
            self::assertEquals($renamedAt, $agents->rename(
                $agent->getId(),
                AgentName::fromString('renamed-agent'),
                static fn(): DateTimeImmutable => $renamedAt
            ));
            self::assertSame('renamed-agent', $agents->getById($agent->getId())->getName()->toString());
            self::assertFalse($agents->replace($granted, $granted->revoke($at->modify('+3 seconds'))));
            $current = $agents->getById($agent->getId());
            self::assertNull($agents->rename(
                $agent->getId(),
                $current->getName(),
                static fn(): DateTimeImmutable => $renamedAt
            ));
            $successorId = AgentCredentialId::generate();
            $rotated = $current->rotateRecoverableCredential(
                $current->getCredentialId(),
                $current->getCredentialRevision(),
                $successorId,
                'opaque-replacement-envelope',
                $at->modify('+3 seconds')
            );
            self::assertTrue($agents->replace($current, $rotated));
            self::assertNull($agents->getByCredentialId($agent->getCredentialId()));
            self::assertSame(
                $agent->getId()->toString(),
                $agents->getByCredentialId($successorId)->getId()->toString()
            );
            self::assertFalse($agents->replace($current, $current->revoke($at->modify('+4 seconds'))));
            $current = $agents->getById($agent->getId());
            self::assertTrue($agents->replace($current, $current->revoke($at->modify('+4 seconds'))));
            self::assertSame('revoked', $agents->getById($agent->getId())->getState()->value);
            try {
                $agents->getOperationContract();
                self::fail('The missing operation cohort cannot be advertised as ready.');
            } catch (AgentOperationRejectedException $failure) {
                self::assertSame(AgentOperationFailure::UNAVAILABLE, $failure->getReason());
                self::assertNull($failure->getPrevious());
            }
        } finally {
            $this->connection->rollBack();
        }
        self::assertNull($agents->getById($agent->getId()));
        self::assertFalse($agents->hasPermissionAssignment($permission->getId()));
    }

    /**
     * Rejects duplicate credentials and direct protected Agent assignments without changing authority
     */
    public function testAgentAuthorityRejectsUnsafeOrDuplicateWrites(): void
    {
        $at = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $protected = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('PROTECTED_AGENT_FIXTURE'),
            PermissionTier::SUPER_ADMIN_ONLY,
            $at
        );
        $this->permissions->add($protected);
        $agents = new PostgresAgentRepository($this->connection, new AuthorizationReferenceFences($this->connection));
        $agent = Agent::provision(
            AgentId::generate(),
            AgentName::fromString('protected-fixture'),
            AgentCredentialId::generate(),
            'opaque-fixture-envelope',
            $at
        );
        $duplicate = Agent::provision(
            AgentId::generate(),
            AgentName::fromString('another-fixture'),
            $agent->getCredentialId(),
            'different-opaque-envelope',
            $at
        );
        $this->connection->beginTransaction();
        try {
            $agents->add($agent);
            self::assertFalse($agents->validatePermissionAssignments([$protected]));
            self::assertFalse($agents->replacePermissionAssignments(
                $agent,
                $agent->grantPermission($protected->getId(), $at->modify('+1 second'))
            ));
            self::assertFalse($agents->hasPermissionAssignment($protected->getId()));
            try {
                $agents->add($duplicate);
                self::fail('A credential cannot identify two Agents.');
            } catch (PersistenceConflict) {
                self::assertNull($agents->getById($duplicate->getId()));
                self::assertSame(1, $agents->getAll(new Pagination())->totalRecords());
            }
            $other = Agent::provision(
                AgentId::generate(),
                AgentName::fromString('second-fixture'),
                AgentCredentialId::generate(),
                'second-opaque-envelope',
                $at
            );
            $agents->add($other);
            $conflictingRotation = $agent->rotateRecoverableCredential(
                $agent->getCredentialId(),
                $agent->getCredentialRevision(),
                $other->getCredentialId(),
                'rotated-opaque-envelope',
                $at->modify('+1 second')
            );
            self::assertFalse($agents->replace($agent, $conflictingRotation));
            self::assertSame(
                $agent->getCredentialId()->toString(),
                $agents->getById($agent->getId())->getCredentialId()->toString()
            );
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Serializes direct Agent grants and protected promotion in both winner orders
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tierWinners')]
    public function testAgentGrantAndPromotionCannotBothCommit(bool $grantWins): void
    {
        $permission = $this->managedPermission('AGENT_GRANT_RACE');
        $this->permissions->add($permission);
        $promoted = $permission->reconcileManaged(
            $permission->getName(),
            PermissionTier::SUPER_ADMIN_ONLY,
            $permission->getCreatedAt()->modify('+1 second')
        );
        $agent = Agent::provision(
            AgentId::generate(),
            AgentName::fromString('racing-agent'),
            AgentCredentialId::generate(),
            'opaque-fixture-envelope',
            $permission->getCreatedAt()
        );
        $agents = new PostgresAgentRepository($this->connection, new AuthorizationReferenceFences($this->connection));
        $this->unitOfWork->commitTransactional(fn() => $agents->add($agent));
        $granted = $agent->grantPermission($permission->getId(), $permission->getCreatedAt()->modify('+1 second'));
        $second = $this->connection();
        $secondAgents = new PostgresAgentRepository($second, new AuthorizationReferenceFences($second));
        $secondPermissions = new PostgresPermissionRepository($second, new AuthorizationReferenceFences($second));
        $this->connection->beginTransaction();
        $second->beginTransaction();
        $second->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            if ($grantWins) {
                self::assertTrue($agents->validatePermissionAssignments([$permission]));
                self::assertTrue($agents->replacePermissionAssignments($agent, $granted));
            } else {
                self::assertTrue($this->permissions->replace($permission, $promoted));
            }
            $compete = match ($grantWins) {
                true => fn(): bool => $secondPermissions->replace($permission, $promoted),
                false => fn(): bool => $secondAgents->validatePermissionAssignments([$permission])
            };
            try {
                $compete();
                self::fail('The competing tier operation must wait for the reference fence.');
            } catch (DriverException $failure) {
                self::assertSame('55P03', $failure->getSQLState());
            }
            $second->rollBack();
            $this->connection->commit();
            $second->beginTransaction();
            if ($grantWins) {
                self::assertFalse($secondPermissions->replace($permission, $promoted));
                self::assertSame(
                    PermissionTier::ADMIN_SAFE,
                    $this->permissions->getById($permission->getId())->getTier()
                );
            } else {
                self::assertFalse($secondAgents->validatePermissionAssignments([$permission]));
                self::assertFalse($secondAgents->replacePermissionAssignments($agent, $granted));
                self::assertFalse($secondAgents->hasPermissionAssignment($permission->getId()));
            }
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($second->isTransactionActive()) {
                $second->rollBack();
            }
            $second->close();
        }
    }

    /**
     * Block protected promotion across persisted direct Agent references
     */
    public function testUncorrelatedAgentAuthorityCannotEnableProtectedPromotion(): void
    {
        $agents = new PostgresAgentRepository($this->connection, new AuthorizationReferenceFences($this->connection));
        $permission = $this->managedPermission('AGENT_SCOPE');
        $this->permissions->add($permission);
        $protected = $permission->reconcileManaged(
            $permission->getName(),
            PermissionTier::SUPER_ADMIN_ONLY,
            $permission->getCreatedAt()->modify('+1 second')
        );
        $agentId = AgentId::generate();
        $credentialId = AgentCredentialId::generate();
        $at = new DateTimeImmutable('2026-10-10T12:00:00Z');

        // Isolate the persisted-reference check from the repository's Agent creation path.
        $this->connection->beginTransaction();
        try {
            $this->connection->insert('agents', [
                'id'                                    => $agentId->toString(),
                'name'                                  => 'uncorrelated',
                'state'                                 => 'active',
                'credential_id'                         => $credentialId->toString(),
                'credential_revision'                   => 0,
                'encrypted_hmac_shared_secret_envelope' => 'opaque-fixture-envelope',
                'permission_assignment_revision'        => 1,
                'created_at'                            => $at->format('Y-m-d H:i:s.uP'),
                'updated_at'                            => $at->format('Y-m-d H:i:s.uP')
            ]);
            $this->connection->insert('agent_permission_assignments', [
                'agent_id'      => $agentId->toString(),
                'permission_id' => $permission->getId()->toString()
            ]);
            self::assertTrue($agents->hasPermissionAssignment($permission->getId()));
            self::assertFalse($agents->hasPermissionAssignment(PermissionId::generate()));
            self::assertFalse($this->permissions->replace($permission, $protected));
            self::assertFalse($this->permissions->remove($permission));
            self::assertSame($agentId->toString(), $agents->getById($agentId)->getId()->toString());
            self::assertSame(1, $agents->getAll(new Pagination())->totalRecords());
        } finally {
            $this->connection->rollBack();
        }
        self::assertFalse($agents->hasPermissionAssignment($permission->getId()));
        self::assertSame(PermissionTier::ADMIN_SAFE, $this->permissions->getById($permission->getId())->getTier());
    }

    /**
     * Persists Feature settings and prevents removal of a referenced Permission
     */
    public function testFeatureReferenceFencesAndOptimisticWrites(): void
    {
        $first = $this->customPermission('FEATURE_FIRST');
        $second = $this->customPermission('FEATURE_SECOND');
        $this->permissions->add($first);
        $this->permissions->add($second);
        $features = new PostgresFeatureRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $feature = Feature::define(FeatureId::generate(), FeatureName::fromString('feature-first'), $first->getId());

        $this->connection->beginTransaction();
        try {
            $features->add($feature);
            self::assertTrue($this->permissions->hasFeatureReference($first->getId()));
            self::assertFalse($this->permissions->remove($first));
            self::assertSame(
                $feature->getId()->toString(),
                $features->getByName($feature->getName())->getId()->toString()
            );
            self::assertSame(1, $features->getAll(new Pagination(1, 10))->totalRecords());
            self::assertTrue($features->replace($feature, $feature));
            $intermediate = $feature->withPermission($second->getId());
            $changed = $intermediate->withStatus(FeatureStatus::ON);
            self::assertTrue($features->replace($feature, $intermediate));
            self::assertFalse($features->replace($feature, $intermediate));
            self::assertTrue($features->replace($intermediate, $changed));
            self::assertSame(FeatureStatus::ON, $features->getById($feature->getId())->getStatus());
            self::assertFalse($this->permissions->hasFeatureReference($first->getId()));
            self::assertTrue($this->permissions->remove($first));
            self::assertFalse($this->permissions->remove($second));
            self::assertFalse($features->remove($feature));
            self::assertTrue($features->remove($changed));
            self::assertTrue($this->permissions->remove($second));
            self::assertNull($features->getByName($feature->getName()));
        } finally {
            $this->connection->rollBack();
        }
        self::assertNull($features->getById($feature->getId()));
        self::assertNotNull($this->permissions->getById($first->getId()));
    }

    /**
     * Rejects duplicate Feature names and absent references without writing authority
     */
    public function testFeatureDuplicateAndMissingReferenceReject(): void
    {
        $permission = $this->customPermission('FEATURE_VALID');
        $this->permissions->add($permission);
        $features = new PostgresFeatureRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $feature = Feature::define(
            FeatureId::generate(),
            FeatureName::fromString('feature-valid'),
            $permission->getId()
        );
        $this->connection->beginTransaction();
        try {
            $features->add($feature);
            try {
                $features->add(Feature::define(FeatureId::generate(), $feature->getName(), $permission->getId()));
                self::fail('A duplicate Feature name cannot replace its stored identity.');
            } catch (FeatureConflictException) {
                self::assertSame(
                    $feature->getId()->toString(),
                    $features->getByName($feature->getName())->getId()->toString()
                );
            }
            try {
                $features->add(Feature::define(
                    FeatureId::generate(),
                    FeatureName::fromString('feature-missing'),
                    PermissionId::generate()
                ));
                self::fail('A missing Permission cannot be referenced.');
            } catch (FeatureReferenceException) {
                self::assertNull($features->getByName(FeatureName::fromString('feature-missing')));
            }
            try {
                $features->replace($feature, $feature->withPermission(PermissionId::generate()));
                self::fail('A missing replacement Permission cannot be referenced.');
            } catch (FeatureReferenceException) {
                self::assertSame(
                    $permission->getId()->toString(),
                    $features->getById($feature->getId())->getPermissionId()->toString()
                );
            }
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Allows only one winner when Feature creation competes with Permission removal
     */
    public function testFeatureReferenceAndPermissionRemovalRace(): void
    {
        $permission = $this->customPermission('FEATURE_RACE');
        $other = $this->customPermission('FEATURE_OTHER_RACE');
        $this->permissions->add($permission);
        $this->permissions->add($other);
        $first = new PostgresFeatureRepository($this->connection, new AuthorizationReferenceFences($this->connection));
        $secondConnection = $this->connection();
        $second = new PostgresFeatureRepository(
            $secondConnection,
            new AuthorizationReferenceFences($secondConnection)
        );
        $secondPermissions = new PostgresPermissionRepository(
            $secondConnection,
            new AuthorizationReferenceFences($secondConnection)
        );
        $feature = Feature::define(
            FeatureId::generate(),
            FeatureName::fromString('feature-race'),
            $permission->getId()
        );
        $otherFeature = Feature::define(FeatureId::generate(), FeatureName::fromString('other-race'), $other->getId());
        try {
            $this->connection->beginTransaction();
            $first->add($feature);
            $secondConnection->beginTransaction();
            $secondConnection->executeStatement("SET LOCAL lock_timeout = '100ms'");
            try {
                $secondPermissions->remove($permission);
                self::fail('A competing removal must wait for the reference writer.');
            } catch (DriverException $failure) {
                self::assertSame('55P03', $failure->getSQLState());
            }
            $secondConnection->rollBack();
            $this->connection->commit();
            $secondConnection->beginTransaction();
            self::assertFalse($secondPermissions->remove($permission));
            $secondConnection->rollBack();

            $this->connection->beginTransaction();
            self::assertTrue($this->permissions->remove($other));
            $secondConnection->beginTransaction();
            $secondConnection->executeStatement("SET LOCAL lock_timeout = '100ms'");
            try {
                $second->add($otherFeature);
                self::fail('A competing reference writer must wait for removal.');
            } catch (\Throwable $failure) {
                self::assertInstanceOf(DriverException::class, $failure);
                self::assertSame('55P03', $failure->getSQLState());
            }
            $secondConnection->rollBack();
            $this->connection->commit();
            $secondConnection->beginTransaction();
            try {
                $second->add($otherFeature);
                self::fail('A removed Permission cannot be referenced after commit.');
            } catch (FeatureReferenceException) {
                self::assertNull($second->getById($otherFeature->getId()));
            }
            $secondConnection->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($secondConnection->isTransactionActive()) {
                $secondConnection->rollBack();
            }
            $secondConnection->close();
        }
    }

    /**
     * Rejects invalid persisted Feature state instead of reporting the definition absent
     */
    public function testMalformedFeatureStateFailsClosed(): void
    {
        $permission = $this->customPermission('FEATURE_STATE');
        $this->permissions->add($permission);
        $features = new PostgresFeatureRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $feature = Feature::define(
            FeatureId::generate(),
            FeatureName::fromString('feature-state'),
            $permission->getId()
        );
        $this->connection->beginTransaction();
        try {
            $features->add($feature);
            $this->connection->update('features', ['name' => 'INVALID'], ['id' => $feature->getId()->toString()]);
            $this->expectException(FeatureStateException::class);
            $features->getById($feature->getId());
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Verifies role add and replace reject missing permission references
     */
    public function testRoleAddAndReplaceRejectMissingPermissionReferences(): void
    {
        $role = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_EDITOR'),
            [PermissionId::generate()],
            new DateTimeImmutable('2026-10-01T13:00:00+00:00')
        );

        $this->expectException(PersistenceConflict::class);
        $this->expectExceptionMessage('Role permission membership is not authoritative.');
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($role));
    }

    /**
     * Verifies role replacement compares membership and rejects stale state
     */
    public function testRoleReplacementComparesMembershipAndRejectsStaleState(): void
    {
        $first = $this->customPermission('VIEW_USERS');
        $second = $this->customPermission('EDIT_USERS');
        $this->permissions->add($first);
        $this->permissions->add($second);
        $current = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_EDITOR'),
            [$first->getId()],
            new DateTimeImmutable('2026-10-01T13:00:00+00:00')
        );
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($current));
        $replacement = $current->grantPermissionToCustom(
            $second->getId(),
            $current->getUpdatedAt()->modify('+1 second')
        );

        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->roles->replace($current, $replacement)
        ));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->roles->replace($current, $replacement)
        ));
        self::assertRoleEquals($replacement, $this->roles->getById($current->getId()));
    }

    /**
     * Verifies competing permission name replacements return a controlled loser
     */
    public function testCompetingPermissionNameReplacementsReturnAControlledLoser(): void
    {
        $winner = $this->managedPermission('MANAGE_USERS');
        $loser = $this->managedPermission('MANAGE_ROLES');
        $this->permissions->add($winner);
        $this->permissions->add($loser);
        $replacementName = PermissionName::fromString('MANAGE_AUTHORITY');
        $winnerReplacement = $winner->reconcileManaged(
            $replacementName,
            PermissionTier::SUPER_ADMIN_ONLY,
            $winner->getUpdatedAt()->modify('+1 second')
        );
        $loserReplacement = $loser->reconcileManaged(
            $replacementName,
            PermissionTier::SUPER_ADMIN_ONLY,
            $loser->getUpdatedAt()->modify('+1 second')
        );
        $competingConnection = $this->connection();
        $competingRepository = new PostgresPermissionRepository(
            $competingConnection,
            new AuthorizationReferenceFences($competingConnection)
        );

        $this->connection->beginTransaction();
        $competingConnection->beginTransaction();
        try {
            self::assertNotNull($competingRepository->getById($loser->getId()));
            self::assertTrue($this->permissions->replace($winner, $winnerReplacement));
            $this->connection->commit();

            self::assertFalse($competingRepository->replace($loser, $loserReplacement));
            self::assertSame(1, $competingConnection->fetchOne('SELECT 1'));
            $competingConnection->commit();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($competingConnection->isTransactionActive()) {
                $competingConnection->rollBack();
            }
            $competingConnection->close();
        }

        self::assertPermissionEquals($winnerReplacement, $this->permissions->getById($winner->getId()));
        self::assertPermissionEquals($loser, $this->permissions->getById($loser->getId()));
    }

    /**
     * Verifies competing role name replacements return a controlled loser
     */
    public function testCompetingRoleNameReplacementsReturnAControlledLoser(): void
    {
        $winner = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_EDITOR'),
            [],
            new DateTimeImmutable('2026-10-01T13:00:00+00:00')
        );
        $loser = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_REVIEWER'),
            [],
            new DateTimeImmutable('2026-10-01T13:00:01+00:00')
        );
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($winner));
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($loser));
        $replacementName = RoleName::fromString('ROLE_AUTHORITY_MANAGER');
        $winnerReplacement = $winner->renameCustom(
            $replacementName,
            $winner->getUpdatedAt()->modify('+1 second')
        );
        $loserReplacement = $loser->renameCustom(
            $replacementName,
            $loser->getUpdatedAt()->modify('+1 second')
        );
        $competingConnection = $this->connection();
        $competingRepository = new PostgresRoleRepository(
            $competingConnection,
            new AuthorizationReferenceFences($competingConnection)
        );

        $this->connection->beginTransaction();
        $competingConnection->beginTransaction();
        try {
            self::assertNotNull($competingRepository->getById($loser->getId()));
            self::assertTrue($this->roles->replace($winner, $winnerReplacement));
            $this->connection->commit();

            self::assertFalse($competingRepository->replace($loser, $loserReplacement));
            self::assertSame(1, $competingConnection->fetchOne('SELECT 1'));
            $competingConnection->commit();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($competingConnection->isTransactionActive()) {
                $competingConnection->rollBack();
            }
            $competingConnection->close();
        }

        self::assertRoleEquals($winnerReplacement, $this->roles->getById($winner->getId()));
        self::assertRoleEquals($loser, $this->roles->getById($loser->getId()));
    }

    /**
     * Verifies permission removal rejects role membership and then succeeds
     */
    public function testPermissionRemovalRejectsRoleMembershipAndThenSucceeds(): void
    {
        $permission = $this->customPermission('VIEW_USERS');
        $this->permissions->add($permission);
        $role = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_VIEWER'),
            [$permission->getId()],
            new DateTimeImmutable('2026-10-01T13:00:00+00:00')
        );
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($role));

        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->permissions->remove($permission)
        ));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->roles->remove($role)
        ));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->permissions->remove($permission)
        ));
        self::assertNull($this->permissions->getById($permission->getId()));
    }

    /**
     * Verifies transaction rolls back authority state after injected failure
     */
    public function testTransactionRollsBackAuthorityStateAfterInjectedFailure(): void
    {
        $permission = $this->customPermission('VIEW_USERS');

        try {
            $this->unitOfWork->commitTransactional(function () use ($permission): never {
                $this->permissions->add($permission);
                throw new RuntimeException('Injected failure');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('Injected failure', $exception->getMessage());
        }

        self::assertNull($this->permissions->getById($permission->getId()));
    }

    /**
     * Verifies reference fence serializes competing membership and removal
     */
    public function testReferenceFenceSerializesCompetingMembershipAndRemoval(): void
    {
        $permission = $this->customPermission('VIEW_USERS');
        $this->permissions->add($permission);
        $first = $this->connection;
        $second = $this->connection();
        $secondPermissions = new PostgresPermissionRepository(
            $second,
            new AuthorizationReferenceFences($second)
        );

        $first->beginTransaction();
        self::assertTrue($this->roles->validatePermissionReference($permission->getId()));
        $second->beginTransaction();
        $second->executeStatement("SET LOCAL lock_timeout = '100ms'");

        try {
            $secondPermissions->remove($permission);
            self::fail('A competing removal must wait on the permission-reference fence.');
        } catch (DriverException) {
            self::assertNotNull($this->permissions->getById($permission->getId()));
        } finally {
            $second->rollBack();
            $first->commit();
            $second->close();
        }
    }

    /**
     * Verifies fenced operations fail closed without an enclosing transaction
     */
    public function testFencedOperationsFailClosedWithoutAnEnclosingTransaction(): void
    {
        $this->expectException(LogicException::class);
        $this->roles->validatePermissionReference(PermissionId::generate());
    }

    /**
     * Rejects taking ownership of a custom Permission through a fabricated managed successor
     */
    public function testPermissionReplacementCannotClaimCustomOwnership(): void
    {
        $custom = $this->customPermission('VIEW_USERS');
        $this->permissions->add($custom);
        $claimed = Permission::defineManaged(
            $custom->getId(),
            $custom->getName(),
            PermissionTier::SUPER_ADMIN_ONLY,
            $custom->getCreatedAt()
        );
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->permissions->replace($custom, $claimed)
        ));
        self::assertFalse($this->permissions->getById($custom->getId())->isManaged());
    }

    /**
     * Rejects taking ownership of a custom Role through a fabricated managed successor
     */
    public function testRoleReplacementCannotClaimCustomOwnership(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $custom = Role::define(RoleId::generate(), RoleName::fromString('ROLE_EDITOR'), [], $at);
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($custom));
        $claimed = Role::defineManaged($custom->getId(), $custom->getName(), [], $at);
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->roles->replace($custom, $claimed)
        ));
        self::assertFalse($this->roles->getById($custom->getId())->isManaged());
    }

    /**
     * Rejects tier writes from an isolation level whose old snapshot can miss committed membership
     */
    public function testTierFencesRejectStaleSnapshotIsolation(): void
    {
        $permission = $this->customPermission('VIEW_USERS');
        $this->permissions->add($permission);
        $this->connection->beginTransaction();
        $this->connection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        try {
            $this->expectException(LogicException::class);
            $this->roles->validateCustomPermissionGrant($permission);
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Serializes grant and protected promotion in both winner orders including unchanged membership validation
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tierWinners')]
    public function testGrantAndPromotionCannotBothCommit(bool $grantWins): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $permission = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('MANAGE_USERS'),
            PermissionTier::ADMIN_SAFE,
            $at
        );
        $promoted = $permission->reconcileManaged(
            $permission->getName(),
            PermissionTier::SUPER_ADMIN_ONLY,
            $at->modify('+1 second')
        );
        $this->permissions->add($permission);
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_EDITOR'), [], $at);
        $this->unitOfWork->commitTransactional(fn() => $this->roles->add($role));
        $granted = $role->grantPermissionToCustom($permission->getId(), $at->modify('+1 second'));
        $second = $this->connection();
        $secondPermissions = new PostgresPermissionRepository($second, new AuthorizationReferenceFences($second));
        $secondRoles = new PostgresRoleRepository($second, new AuthorizationReferenceFences($second));
        $this->connection->beginTransaction();
        $second->beginTransaction();
        $second->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            if ($grantWins) {
                self::assertTrue($this->roles->validateCustomPermissionGrant($permission));
                self::assertTrue($this->roles->replace($role, $granted));
                // Already-granted validation must hold the same fence through commit.
                self::assertTrue($this->roles->validateCustomPermissionGrant($permission));
            } else {
                self::assertTrue($this->permissions->replace($permission, $promoted));
            }
            $compete = match ($grantWins) {
                true => fn(): bool => $secondPermissions->replace($permission, $promoted),
                false => fn(): bool => $secondRoles->validateCustomPermissionGrant($permission)
            };
            try {
                $compete();
                self::fail('The competing tier operation must wait.');
            } catch (DriverException $failure) {
                self::assertSame('55P03', $failure->getSQLState());
            }
            $second->rollBack();
            $this->connection->commit();
            $second->beginTransaction();
            if ($grantWins) {
                self::assertFalse($secondPermissions->replace($permission, $promoted));
                self::assertSame(
                    PermissionTier::ADMIN_SAFE,
                    $secondPermissions->getById($permission->getId())->getTier()
                );
            } else {
                self::assertFalse($secondRoles->validateCustomPermissionGrant($permission));
                self::assertFalse($secondRoles->replace($role, $granted));
                self::assertSame([], $secondRoles->getById($role->getId())->getPermissionIds());
            }
            $second->commit();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($second->isTransactionActive()) {
                $second->rollBack();
            }
            $second->close();
        }
    }

    /**
     * Supplies both tier writer orders
     *
     * @return iterable<array{bool}>
     */
    public static function tierWinners(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * Opens a guarded PostgreSQL test connection
     */
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
            'postgres'   => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql'
        ]))->parse($databaseUrl));
        $guard->assertConnected($connection, $expected);

        return $connection;
    }

    /**
     * Creates a custom permission for repository checks
     */
    private function customPermission(string $name): Permission
    {
        return Permission::define(
            PermissionId::generate(),
            PermissionName::fromString($name),
            new DateTimeImmutable('2026-10-01T12:00:00+00:00')
        );
    }

    /**
     * Creates a managed permission for repository checks
     */
    private function managedPermission(string $name): Permission
    {
        return Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString($name),
            PermissionTier::ADMIN_SAFE,
            new DateTimeImmutable('2026-10-01T12:00:00+00:00')
        );
    }

    /**
     * Verifies the persisted permission matches the expected state
     */
    private static function assertPermissionEquals(Permission $expected, ?Permission $actual): void
    {
        self::assertNotNull($actual);
        self::assertSame($expected->getId()->toString(), $actual->getId()->toString());
        self::assertSame($expected->getName()->toString(), $actual->getName()->toString());
        self::assertSame($expected->getTier(), $actual->getTier());
        self::assertSame($expected->isManaged(), $actual->isManaged());
        self::assertEquals($expected->getCreatedAt(), $actual->getCreatedAt());
        self::assertEquals($expected->getUpdatedAt(), $actual->getUpdatedAt());
    }

    /**
     * Verifies the persisted role matches the expected state
     */
    private static function assertRoleEquals(Role $expected, ?Role $actual): void
    {
        self::assertNotNull($actual);
        self::assertSame($expected->getId()->toString(), $actual->getId()->toString());
        self::assertSame($expected->getName()->toString(), $actual->getName()->toString());
        self::assertSame($expected->isManaged(), $actual->isManaged());
        self::assertEquals($expected->getCreatedAt(), $actual->getCreatedAt());
        self::assertEquals($expected->getUpdatedAt(), $actual->getUpdatedAt());
        $expectedIds = array_map(
            static fn(PermissionId $id): string => $id->toString(),
            $expected->getPermissionIds()
        );
        $actualIds = array_map(
            static fn(PermissionId $id): string => $id->toString(),
            $actual->getPermissionIds()
        );
        sort($expectedIds);
        sort($actualIds);
        self::assertSame($expectedIds, $actualIds);
    }
}
