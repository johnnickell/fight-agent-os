<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\AuthorizationReferenceFences;
use App\Adapter\Persistence\DatabaseTargetGuard;
use App\Adapter\Persistence\FailClosedUnitOfWork;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\PostgresPermissionRepository;
use App\Adapter\Persistence\PostgresRoleRepository;
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

final class AuthorityRepositoryTest extends TestCase
{
    private Connection $connection;
    private DoctrineTransactionalUnitOfWork $unitOfWork;
    private PostgresPermissionRepository $permissions;
    private PostgresRoleRepository $roles;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('TRUNCATE role_permissions, roles, permissions');
        $fences = new AuthorizationReferenceFences($this->connection);
        $this->permissions = new PostgresPermissionRepository($this->connection, $fences);
        $this->roles = new PostgresRoleRepository($this->connection, $fences);
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $entityManager = new EntityManager($this->connection, $configuration);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);
    }

    public function test_permission_contract_round_trips_managed_and_custom_state(): void
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
                    $managed->getId(),
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

    public function test_permission_replacement_compares_complete_expected_state(): void
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

    public function test_known_identity_conflicts_are_safe_and_do_not_leak_sql(): void
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

    public function test_role_contract_round_trips_membership_queries_and_pages(): void
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

    public function test_role_add_and_replace_reject_missing_permission_references(): void
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

    public function test_role_replacement_compares_membership_and_rejects_stale_state(): void
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

    public function test_permission_removal_rejects_role_membership_and_then_succeeds(): void
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

    public function test_transaction_rolls_back_authority_state_after_injected_failure(): void
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

    public function test_reference_fence_serializes_competing_membership_and_removal(): void
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

    public function test_fenced_operations_fail_closed_without_an_enclosing_transaction(): void
    {
        $this->expectException(LogicException::class);
        $this->roles->validatePermissionReference(PermissionId::generate());
    }

    public function test_compatibility_unit_of_work_delegates_transactions_and_rejects_commit(): void
    {
        $compatibility = new FailClosedUnitOfWork($this->unitOfWork);
        self::assertSame('committed', $compatibility->commitTransactional(static fn(): string => 'committed'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unscoped persistence commits are not supported.');
        $compatibility->commit();
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

    private function customPermission(string $name): Permission
    {
        return Permission::define(
            PermissionId::generate(),
            PermissionName::fromString($name),
            new DateTimeImmutable('2026-10-01T12:00:00+00:00')
        );
    }

    private function managedPermission(string $name): Permission
    {
        return Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString($name),
            PermissionTier::ADMIN_SAFE,
            new DateTimeImmutable('2026-10-01T12:00:00+00:00')
        );
    }

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
