<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\PostgresUniqueConstraintRace;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;

final readonly class PostgresPermissionRepository implements PermissionRepository
{
    /**
     * Constructs PostgresPermissionRepository
     */
    public function __construct(
        private Connection $connection,
        private AuthorizationReferenceFences $referenceFences
    ) {
    }

    /**
     * @inheritDoc
     */
    public function add(Permission $permission): void
    {
        $inserted = $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO permissions (id, name, tier, managed, created_at, updated_at)
VALUES (:id, :name, :tier, :managed, :created_at, :updated_at)
ON CONFLICT DO NOTHING
SQL,
            $this->values($permission),
            $this->types()
        );
        if ($inserted !== 1) {
            throw new PersistenceConflict('The permission identity or name is already in use.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getById(PermissionId $id): ?Permission
    {
        return $this->one('id', $id->toString());
    }

    /**
     * @inheritDoc
     */
    public function getByName(PermissionName $name): ?Permission
    {
        return $this->one('name', $name->toString());
    }

    /**
     * @inheritDoc
     */
    public function getByIds(array $ids): array
    {
        $permissions = [];
        $seen = [];

        foreach ($ids as $id) {
            $serialized = $id->toString();
            if (isset($seen[$serialized])) {
                continue;
            }

            $seen[$serialized] = true;
            $permission = $this->getById($id);
            if ($permission instanceof Permission) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * @inheritDoc
     */
    public function getAll(Pagination $pagination): ResultSet
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('permissions')
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->setMaxResults($pagination->limit())
            ->setFirstResult($pagination->offset())
            ->fetchAllAssociative();
        $records = ArrayList::of(Permission::class)->replace(array_map($this->hydrate(...), $rows));

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            (int) $this->connection->createQueryBuilder()->select('COUNT(*)')->from('permissions')->fetchOne(),
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
                ->from('permissions')
                ->where('managed = TRUE')
                ->orderBy('created_at')
                ->addOrderBy('id')
                ->fetchAllAssociative()
        );
    }

    /**
     * @inheritDoc
     */
    public function replace(Permission $expected, Permission $replacement): bool
    {
        if (
            !$expected->getId()->equals($replacement->getId())
            || $expected->getCreatedAt() != $replacement->getCreatedAt()
        ) {
            return false;
        }

        return PostgresUniqueConstraintRace::execute(
            $this->connection,
            'uq_permissions_name',
            fn(): int => $this->connection->createQueryBuilder()
                ->update('permissions')
                ->set('name', ':replacement_name')
                ->set('tier', ':replacement_tier')
                ->set('managed', ':replacement_managed')
                ->set('updated_at', ':replacement_updated_at')
                ->where('id = :id')
                ->andWhere('name = :expected_name')
                ->andWhere('tier IS NOT DISTINCT FROM :expected_tier')
                ->andWhere('managed = :expected_managed')
                ->andWhere('created_at = :expected_created_at')
                ->andWhere('updated_at = :expected_updated_at')
                ->andWhere('NOT EXISTS (SELECT 1 FROM permissions conflicting '
                    . 'WHERE conflicting.name = :replacement_name AND conflicting.id <> :id)')
                ->setParameters([
                    'id' => $expected->getId()->toString(),
                    'expected_name' => $expected->getName()->toString(),
                    'expected_tier' => $expected->getTier()?->value,
                    'expected_managed' => $expected->isManaged(),
                    'expected_created_at' => $this->date($expected->getCreatedAt()),
                    'expected_updated_at' => $this->date($expected->getUpdatedAt()),
                    'replacement_name' => $replacement->getName()->toString(),
                    'replacement_tier' => $replacement->getTier()?->value,
                    'replacement_managed' => $replacement->isManaged(),
                    'replacement_updated_at' => $this->date($replacement->getUpdatedAt()),
                ], [
                    'expected_managed' => ParameterType::BOOLEAN,
                    'replacement_managed' => ParameterType::BOOLEAN,
                ])
                ->executeStatement()
        ) === 1;
    }

    /**
     * @inheritDoc
     */
    public function remove(Permission $permission): bool
    {
        $this->referenceFences->holdPermissionReferences();

        return $this->connection->createQueryBuilder()
            ->delete('permissions')
            ->where('id = :id')
            ->andWhere('name = :name')
            ->andWhere('tier IS NOT DISTINCT FROM :tier')
            ->andWhere('managed = :managed')
            ->andWhere('created_at = :created_at')
            ->andWhere('updated_at = :updated_at')
            ->andWhere('NOT EXISTS (SELECT 1 FROM role_permissions WHERE permission_id = :id)')
            ->setParameters([
                'id' => $permission->getId()->toString(),
                'name' => $permission->getName()->toString(),
                'tier' => $permission->getTier()?->value,
                'managed' => $permission->isManaged(),
                'created_at' => $this->date($permission->getCreatedAt()),
                'updated_at' => $this->date($permission->getUpdatedAt()),
            ], ['managed' => ParameterType::BOOLEAN])
            ->executeStatement() === 1;
    }

    private function one(string $column, string $value): ?Permission
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('permissions')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Permission
    {
        $id = PermissionId::fromString((string) $row['id']);
        $name = PermissionName::fromString((string) $row['name']);
        $createdAt = new DateTimeImmutable((string) $row['created_at']);

        if ($this->boolean($row['managed'])) {
            $tier = PermissionTier::from((string) $row['tier']);
            $permission = Permission::defineManaged($id, $name, $tier, $createdAt);

            if ($permission->getUpdatedAt() != new DateTimeImmutable((string) $row['updated_at'])) {
                $permission = $permission->reconcileManaged(
                    $name,
                    $tier,
                    new DateTimeImmutable((string) $row['updated_at'])
                );
            }

            return $permission;
        }

        return Permission::define($id, $name, $createdAt);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Permission $permission): array
    {
        return [
            'id' => $permission->getId()->toString(),
            'name' => $permission->getName()->toString(),
            'tier' => $permission->getTier()?->value,
            'managed' => $permission->isManaged(),
            'created_at' => $this->date($permission->getCreatedAt()),
            'updated_at' => $this->date($permission->getUpdatedAt()),
        ];
    }

    /**
     * @return array<string, ParameterType>
     */
    private function types(): array
    {
        return ['managed' => ParameterType::BOOLEAN];
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
