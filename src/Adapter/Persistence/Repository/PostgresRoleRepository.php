<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;

final readonly class PostgresRoleRepository implements RoleRepository
{
    /**
     * Constructs PostgresRoleRepository
     */
    public function __construct(
        private Connection $connection,
        private AuthorizationReferenceFences $referenceFences
    ) {
    }

    /**
     * @inheritDoc
     */
    public function add(Role $role): void
    {
        $this->referenceFences->holdPermissionReferences();
        if (!$this->lockAuthoritativePermissions($role->getPermissionIds())) {
            throw new PersistenceConflict('Role permission membership is not authoritative.');
        }

        $inserted = $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO roles (id, name, managed, created_at, updated_at)
VALUES (:id, :name, :managed, :created_at, :updated_at)
ON CONFLICT DO NOTHING
SQL,
            $this->values($role),
            ['managed' => ParameterType::BOOLEAN]
        );
        if ($inserted !== 1) {
            throw new PersistenceConflict('The role identity or name is already in use.');
        }

        $this->insertMemberships($role);
    }

    /**
     * @inheritDoc
     */
    public function getById(RoleId $id): ?Role
    {
        return $this->one('id', $id->toString());
    }

    /**
     * @inheritDoc
     */
    public function getByName(RoleName $name): ?Role
    {
        return $this->one('name', $name->toString());
    }

    /**
     * @inheritDoc
     */
    public function getByIds(array $ids): array
    {
        $roles = [];
        $seen = [];

        foreach ($ids as $id) {
            $serialized = $id->toString();
            if (isset($seen[$serialized])) {
                continue;
            }

            $seen[$serialized] = true;
            $role = $this->getById($id);
            if ($role instanceof Role) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * @inheritDoc
     */
    public function getAll(Pagination $pagination): ResultSet
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('roles')
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->setMaxResults($pagination->limit())
            ->setFirstResult($pagination->offset())
            ->fetchAllAssociative();
        $records = ArrayList::of(Role::class)->replace(array_map($this->hydrate(...), $rows));

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            (int) $this->connection->createQueryBuilder()->select('COUNT(*)')->from('roles')->fetchOne(),
            $records
        );
    }

    /**
     * @inheritDoc
     */
    public function getManaged(): array
    {
        return array_map(
            $this->hydrate(...),
            $this->connection->createQueryBuilder()
                ->select('*')
                ->from('roles')
                ->where('managed = TRUE')
                ->orderBy('created_at')
                ->addOrderBy('id')
                ->fetchAllAssociative()
        );
    }

    /**
     * @inheritDoc
     */
    public function getContainingPermission(PermissionId $id): array
    {
        return array_map(
            $this->hydrate(...),
            $this->connection->createQueryBuilder()
                ->select('roles.*')
                ->from('roles')
                ->innerJoin('roles', 'role_permissions', 'rp', 'rp.role_id = roles.id')
                ->where('rp.permission_id = :id')
                ->setParameter('id', $id->toString())
                ->orderBy('roles.created_at')
                ->addOrderBy('roles.id')
                ->fetchAllAssociative()
        );
    }

    /**
     * @inheritDoc
     */
    public function validatePermissionReference(PermissionId $permissionId): bool
    {
        $this->referenceFences->holdPermissionReferences();

        return $this->lockAuthoritativePermissions([$permissionId]);
    }

    /**
     * @inheritDoc
     */
    public function replace(Role $expected, Role $replacement): bool
    {
        if (
            !$expected->getId()->equals($replacement->getId())
            || $expected->getCreatedAt() != $replacement->getCreatedAt()
        ) {
            return false;
        }

        $this->referenceFences->holdPermissionReferences();
        if (!$this->lockAuthoritativePermissions($replacement->getPermissionIds())) {
            return false;
        }

        $current = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('roles')
            ->where('id = :id')
            ->setParameter('id', $expected->getId()->toString())
            ->forUpdate()
            ->fetchAssociative();
        if ($current === false || !$this->matches($expected, $current)) {
            return false;
        }

        $updated = PostgresUniqueConstraintRace::execute(
            $this->connection,
            'uq_roles_name',
            fn(): int => $this->connection->createQueryBuilder()
                ->update('roles')
                ->set('name', ':name')
                ->set('managed', ':managed')
                ->set('updated_at', ':updated_at')
                ->where('id = :id')
                ->andWhere('name = :expected_name')
                ->andWhere('managed = :expected_managed')
                ->andWhere('created_at = :created_at')
                ->andWhere('updated_at = :expected_updated_at')
                ->andWhere('NOT EXISTS (SELECT 1 FROM roles conflicting '
                    . 'WHERE conflicting.name = :name AND conflicting.id <> :id)')
                ->setParameters([
                    'id' => $expected->getId()->toString(),
                    'name' => $replacement->getName()->toString(),
                    'managed' => $replacement->isManaged(),
                    'updated_at' => $this->date($replacement->getUpdatedAt()),
                    'expected_name' => $expected->getName()->toString(),
                    'expected_managed' => $expected->isManaged(),
                    'created_at' => $this->date($expected->getCreatedAt()),
                    'expected_updated_at' => $this->date($expected->getUpdatedAt()),
                ], ['managed' => ParameterType::BOOLEAN, 'expected_managed' => ParameterType::BOOLEAN])
                ->executeStatement()
        );
        if ($updated !== 1) {
            return false;
        }

        $this->connection->delete('role_permissions', ['role_id' => $expected->getId()->toString()]);
        $this->insertMemberships($replacement);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function remove(Role $role): bool
    {
        $this->referenceFences->holdRoleReferences();

        return $this->connection->createQueryBuilder()
            ->delete('roles')
            ->where('id = :id')
            ->andWhere('name = :name')
            ->andWhere('managed = :managed')
            ->andWhere('created_at = :created_at')
            ->andWhere('updated_at = :updated_at')
            ->setParameters([
                'id' => $role->getId()->toString(),
                'name' => $role->getName()->toString(),
                'managed' => $role->isManaged(),
                'created_at' => $this->date($role->getCreatedAt()),
                'updated_at' => $this->date($role->getUpdatedAt()),
            ], ['managed' => ParameterType::BOOLEAN])
            ->executeStatement() === 1;
    }

    /**
     * @param list<PermissionId> $permissionIds
     */
    private function lockAuthoritativePermissions(array $permissionIds): bool
    {
        $ids = array_values(array_unique(array_map(
            static fn(PermissionId $id): string => $id->toString(),
            $permissionIds
        )));
        sort($ids);
        if ($ids === []) {
            return true;
        }

        // DBAL QueryBuilder only supports FOR UPDATE; membership needs the weaker KEY SHARE lock.
        $found = $this->connection->fetchFirstColumn(
            'SELECT id FROM permissions WHERE id IN (?) ORDER BY id FOR KEY SHARE',
            [$ids],
            [ArrayParameterType::STRING]
        );

        return count($found) === count($ids);
    }

    private function insertMemberships(Role $role): void
    {
        $ids = array_values(array_unique(array_map(
            static fn(PermissionId $id): string => $id->toString(),
            $role->getPermissionIds()
        )));
        sort($ids);

        foreach ($ids as $permissionId) {
            $this->connection->insert('role_permissions', [
                'role_id' => $role->getId()->toString(),
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function one(string $column, string $value): ?Role
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('roles')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Role
    {
        $id = RoleId::fromString((string) $row['id']);
        $name = RoleName::fromString((string) $row['name']);
        $createdAt = new DateTimeImmutable((string) $row['created_at']);
        $permissionIds = array_map(
            static fn(mixed $permissionId): PermissionId => PermissionId::fromString((string) $permissionId),
            $this->membershipIds((string) $row['id'])
        );
        $role = $this->boolean($row['managed'])
            ? Role::defineManaged($id, $name, $permissionIds, $createdAt)
            : Role::define($id, $name, $permissionIds, $createdAt);
        $updatedAt = new DateTimeImmutable((string) $row['updated_at']);

        if ($role->getUpdatedAt() != $updatedAt) {
            $role = $role->isManaged()
                ? $role->reconcileManaged($name, $permissionIds, $updatedAt)
                : $role->renameCustom($name, $updatedAt);
        }

        return $role;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function matches(Role $expected, array $row): bool
    {
        if (
            (string) $row['name'] !== $expected->getName()->toString()
            || $this->boolean($row['managed']) !== $expected->isManaged()
            || new DateTimeImmutable((string) $row['created_at']) != $expected->getCreatedAt()
            || new DateTimeImmutable((string) $row['updated_at']) != $expected->getUpdatedAt()
        ) {
            return false;
        }

        $stored = array_map(
            'strval',
            $this->membershipIds($expected->getId()->toString())
        );
        $wanted = array_values(array_unique(array_map(
            static fn(PermissionId $id): string => $id->toString(),
            $expected->getPermissionIds()
        )));
        sort($wanted);

        return $stored === $wanted;
    }

    /**
     * @return list<mixed>
     */
    private function membershipIds(string $roleId): array
    {
        return $this->connection->createQueryBuilder()
            ->select('permission_id')
            ->from('role_permissions')
            ->where('role_id = :role_id')
            ->setParameter('role_id', $roleId)
            ->orderBy('permission_id')
            ->fetchFirstColumn();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Role $role): array
    {
        return [
            'id' => $role->getId()->toString(),
            'name' => $role->getName()->toString(),
            'managed' => $role->isManaged(),
            'created_at' => $this->date($role->getCreatedAt()),
            'updated_at' => $this->date($role->getUpdatedAt()),
        ];
    }

    private function boolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't';
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.uP');
    }
}
