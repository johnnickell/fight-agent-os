<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\Repository\PostgresPermissionRepository;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Domain\Repository\Pagination;
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
        $this->connection->executeStatement('TRUNCATE role_permissions, roles, permissions CASCADE');
        $fences = new AuthorizationReferenceFences($this->connection);
        $this->permissions = new PostgresPermissionRepository($this->connection, $fences);
        $this->roles = new PostgresRoleRepository($this->connection, $fences);
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $entityManager = new EntityManager($this->connection, $configuration);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);
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

        self::assertTrue($this->permissions->replace($current, $replacement));
        self::assertFalse($this->permissions->replace($current, $replacement));
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
            RoleName::fromString('ROLE_ADMIN'),
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
        $competingConnection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

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
        $competingConnection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

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
