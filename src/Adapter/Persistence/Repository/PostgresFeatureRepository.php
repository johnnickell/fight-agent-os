<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureConflictException;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureNameException;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureReferenceException;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureStateException;
use Fight\AccessControl\Domain\AccessControl\Feature\Feature;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureId;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureName;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureRepository;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureStatus;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use ValueError;

/**
 * Class PostgresFeatureRepository
 *
 * Persists Feature settings and their Permission references on the shared fenced connection
 */
final readonly class PostgresFeatureRepository implements FeatureRepository
{
    /**
     * Constructs PostgresFeatureRepository
     */
    public function __construct(
        private Connection $connection,
        private AuthorizationReferenceFences $referenceFences
    ) {
    }

    /**
     * @inheritDoc
     */
    public function add(Feature $feature): void
    {
        $this->referenceFences->holdPermissionReferences();
        if (!$this->hasPermission($feature->getPermissionId())) {
            throw new FeatureReferenceException('The Feature Permission reference is unavailable.');
        }

        try {
            $inserted = $this->connection->executeStatement(<<<'SQL'
INSERT INTO features (id, name, permission_id, status, revision)
VALUES (:id, :name, :permission_id, :status, :revision)
ON CONFLICT DO NOTHING
SQL, $this->values($feature));
        } catch (ForeignKeyConstraintViolationException) {
            throw new FeatureReferenceException('The Feature Permission reference is unavailable.');
        }
        if ($inserted !== 1) {
            throw new FeatureConflictException('The Feature identity or name is already in use.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getById(FeatureId $id): ?Feature
    {
        return $this->one('id', $id->toString());
    }

    /**
     * @inheritDoc
     */
    public function getByName(FeatureName $name): ?Feature
    {
        return $this->one('name', $name->toString());
    }

    /**
     * @inheritDoc
     */
    public function getAll(Pagination $pagination): ResultSet
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('features')
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults($pagination->limit())
            ->setFirstResult($pagination->offset())
            ->fetchAllAssociative();

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            (int) $this->connection->createQueryBuilder()->select('COUNT(*)')->from('features')->fetchOne(),
            ArrayList::of(Feature::class)->replace(array_map($this->hydrate(...), $rows))
        );
    }

    /**
     * @inheritDoc
     */
    public function replace(Feature $expected, Feature $replacement): bool
    {
        if (
            !$expected->getId()->equals($replacement->getId())
            || !$expected->getName()->equals($replacement->getName())
        ) {
            return false;
        }
        $changed = $expected->getPermissionId()->toString() !== $replacement->getPermissionId()->toString()
            || $expected->getStatus() !== $replacement->getStatus();
        if ($replacement->getRevision() !== $expected->getRevision() + ($changed ? 1 : 0)) {
            return false;
        }

        $this->referenceFences->holdPermissionReferences();
        $parameters = [
            'id'                => $expected->getId()->toString(),
            'name'              => $expected->getName()->toString(),
            'old_permission_id' => $expected->getPermissionId()->toString(),
            'old_status'        => $expected->getStatus()->value,
            'old_revision'      => $expected->getRevision(),
            'permission_id'     => $replacement->getPermissionId()->toString(),
            'status'            => $replacement->getStatus()->value,
            'revision'          => $replacement->getRevision()
        ];
        $current = $this->connection->fetchOne(<<<'SQL'
SELECT 1 FROM features WHERE id = :id AND name = :name AND permission_id = :old_permission_id
    AND status = :old_status AND revision = :old_revision
SQL, $parameters);
        if ($current === false) {
            return false;
        }
        if (!$this->hasPermission($replacement->getPermissionId())) {
            throw new FeatureReferenceException('The Feature Permission reference is unavailable.');
        }
        if (!$changed) {
            return true;
        }

        try {
            return $this->connection->executeStatement(<<<'SQL'
UPDATE features SET permission_id = :permission_id, status = :status, revision = :revision
WHERE id = :id AND name = :name AND permission_id = :old_permission_id
    AND status = :old_status AND revision = :old_revision
SQL, $parameters) === 1;
        } catch (ForeignKeyConstraintViolationException) {
            throw new FeatureReferenceException('The Feature Permission reference is unavailable.');
        }
    }

    /**
     * @inheritDoc
     */
    public function remove(Feature $expected): bool
    {
        $this->referenceFences->holdPermissionReferences();

        return $this->connection->executeStatement(<<<'SQL'
DELETE FROM features WHERE id = :id AND name = :name AND permission_id = :permission_id
    AND status = :status AND revision = :revision
SQL, $this->values($expected)) === 1;
    }

    /**
     * Loads one fresh definition by stable identity or name
     */
    private function one(string $column, string $value): ?Feature
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('features')
            ->where($column.' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Reconstitutes validated persisted state without interpreting its availability
     *
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Feature
    {
        try {
            return Feature::reconstitute(
                FeatureId::fromString((string) $row['id']),
                FeatureName::fromString((string) $row['name']),
                PermissionId::fromString((string) $row['permission_id']),
                FeatureStatus::from((string) $row['status']),
                (int) $row['revision']
            );
        } catch (ValueError | FeatureNameException $exception) {
            throw new FeatureStateException('Invalid persisted Feature definition.', 0, $exception);
        }
    }

    /**
     * Returns whether the referenced Permission currently exists
     */
    private function hasPermission(PermissionId $id): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM permissions WHERE id = ?', [$id->toString()]) !== false;
    }

    /**
     * Maps authoritative Feature state to named database parameters
     *
     * @return array<string, string|int>
     */
    private function values(Feature $feature): array
    {
        return [
            'id'            => $feature->getId()->toString(),
            'name'          => $feature->getName()->toString(),
            'permission_id' => $feature->getPermissionId()->toString(),
            'status'        => $feature->getStatus()->value,
            'revision'      => $feature->getRevision()
        ];
    }
}
