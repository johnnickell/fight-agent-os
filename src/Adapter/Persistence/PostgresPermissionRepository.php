<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

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
        return $this->one('SELECT * FROM permissions WHERE id = ?', $id->toString());
    }

    /**
     * @inheritDoc
     */
    public function getByName(PermissionName $name): ?Permission
    {
        return $this->one('SELECT * FROM permissions WHERE name = ?', $name->toString());
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
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM permissions ORDER BY created_at, id LIMIT ? OFFSET ?',
            [$pagination->limit(), $pagination->offset()],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );
        $records = ArrayList::of(Permission::class)->replace(array_map($this->hydrate(...), $rows));

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM permissions'),
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
            $this->connection->fetchAllAssociative(
                'SELECT * FROM permissions WHERE managed = TRUE ORDER BY created_at, id'
            )
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

        return $this->connection->executeStatement(
            <<<'SQL'
UPDATE permissions
SET name = :replacement_name,
    tier = :replacement_tier,
    managed = :replacement_managed,
    updated_at = :replacement_updated_at
WHERE id = :id
  AND name = :expected_name
  AND tier IS NOT DISTINCT FROM :expected_tier
  AND managed = :expected_managed
  AND created_at = :expected_created_at
  AND updated_at = :expected_updated_at
  AND NOT EXISTS (
      SELECT 1 FROM permissions conflicting
      WHERE conflicting.name = :replacement_name
        AND conflicting.id <> :id
  )
SQL,
                [
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
                ],
            [
                'expected_managed' => ParameterType::BOOLEAN,
                'replacement_managed' => ParameterType::BOOLEAN,
            ]
        ) === 1;
    }

    /**
     * @inheritDoc
     */
    public function remove(Permission $permission): bool
    {
        $this->referenceFences->holdPermissionReferences();

        return $this->connection->executeStatement(
            <<<'SQL'
DELETE FROM permissions
WHERE id = :id
  AND name = :name
  AND tier IS NOT DISTINCT FROM :tier
  AND managed = :managed
  AND created_at = :created_at
  AND updated_at = :updated_at
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions WHERE permission_id = :id
  )
SQL,
                [
                    'id' => $permission->getId()->toString(),
                    'name' => $permission->getName()->toString(),
                    'tier' => $permission->getTier()?->value,
                    'managed' => $permission->isManaged(),
                    'created_at' => $this->date($permission->getCreatedAt()),
                    'updated_at' => $this->date($permission->getUpdatedAt()),
                ],
            ['managed' => ParameterType::BOOLEAN]
        ) === 1;
    }

    private function one(string $sql, string $value): ?Permission
    {
        $row = $this->connection->fetchAssociative($sql, [$value]);

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
