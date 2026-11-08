<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Workspace\Project;

use App\Domain\Workspace\Project\Project;
use App\Domain\Workspace\Project\ProjectConflict;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectInputRejected;
use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\ProjectRepository;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;

/**
 * Class PostgresProjectRepository
 *
 * Persists aggregate state and authoritative canonical claims on the shared connection
 */
final readonly class PostgresProjectRepository implements ProjectRepository
{
    /**
     * Constructs PostgresProjectRepository
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function add(Project $project): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Project persistence requires an enclosing transaction.');
        }
        try {
            $this->connection->insert('projects', [
                'id'                 => $project->id->toString(),
                'workspace_id'       => $project->workspaceId->toString(),
                'display_name'       => $project->metadata->displayName,
                'canonical_slug'     => $project->metadata->slug,
                'lifecycle'          => 'active',
                'metadata_revision'  => $project->metadataRevision,
                'source_revision'    => $project->sourceRevision,
                'lifecycle_revision' => $project->lifecycleRevision,
                'created_by'         => $project->createdBy->toString(),
                'updated_by'         => $project->updatedBy->toString(),
                'created_at'         => $project->createdAt->format('Y-m-d H:i:s.uP'),
                'updated_at'         => $project->updatedAt->format('Y-m-d H:i:s.uP')
            ]);
            foreach ($project->sources->all() as $index => $source) {
                $this->connection->insert('project_sources', [
                    'workspace_id'       => $project->workspaceId->toString(),
                    'project_id'         => $project->id->toString(),
                    'descriptor_version' => 1,
                    'source_key'         => $source->key(),
                    'source_role'        => $index === 0 ? 'primary' : 'equivalent',
                    'active'             => 1
                ]);
            }
        } catch (UniqueConstraintViolationException $exception) {
            foreach (
                [
                'uq_workspace_project_active_slug'   => 'slug',
                'uq_workspace_project_active_source' => 'source'
                ] as $constraint => $reason
            ) {
                if (str_contains($exception->getMessage(), 'unique constraint "'.$constraint.'"')) {
                    throw new ProjectConflict($reason);
                }
            }
            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function getById(WorkspaceId $workspaceId, ProjectId $projectId): ?Project
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM projects WHERE workspace_id = ? AND id = ?',
            [$workspaceId->toString(), $projectId->toString()]
        );

        return $row === false ? null : $this->restore($row);
    }

    /**
     * @inheritDoc
     */
    public function slice(WorkspaceId $workspaceId, int $offset, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()->select('*')->from('projects')
            ->where('workspace_id = :workspace')->setParameter('workspace', $workspaceId->toString())
            ->orderBy('created_at')->addOrderBy('id')->setFirstResult($offset)->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_values(array_map($this->restore(...), $rows));
    }

    /**
     * @inheritDoc
     */
    public function hasActiveSlug(WorkspaceId $workspaceId, string $slug): bool
    {
        return $this->connection->fetchOne(
            "SELECT 1 FROM projects WHERE workspace_id = ? AND canonical_slug = ? AND lifecycle = 'active'",
            [$workspaceId->toString(), $slug]
        ) !== false;
    }

    /**
     * @inheritDoc
     */
    public function hasActiveSource(WorkspaceId $workspaceId, SourceDescriptor $source): bool
    {
        return $this->connection->fetchOne(
            <<<'SQL'
SELECT 1 FROM project_sources WHERE workspace_id = ? AND descriptor_version = 1 AND source_key = ? AND active
SQL,
            [$workspaceId->toString(), $source->key()]
        ) !== false;
    }

    /**
     * Restores declared aggregate facts without original-response data
     *
     * @param array<string, mixed> $row
     */
    private function restore(array $row): Project
    {
        if ($row['lifecycle'] !== 'active') {
            throw new LogicException('Unsupported Project lifecycle.');
        }
        $primary = null;
        $equivalent = [];
        $sourceRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT source_key, source_role FROM project_sources WHERE workspace_id = ? AND project_id = ? ORDER BY source_key
SQL,
            [$row['workspace_id'], $row['id']]
        );
        try {
            foreach ($sourceRows as $sourceRow) {
                $source = SourceDescriptor::reconstitute(json_decode(
                    $sourceRow['source_key'],
                    true,
                    32,
                    JSON_THROW_ON_ERROR
                ));
                if ($sourceRow['source_role'] === 'primary') {
                    $primary = $source;
                } else {
                    $equivalent[] = $source;
                }
            }

            return Project::reconstitute(
                ProjectId::fromString($row['id']),
                WorkspaceId::fromString($row['workspace_id']),
                new ProjectMetadata($row['display_name'], $row['canonical_slug']),
                new ProjectSources($primary, $equivalent),
                UserId::fromString($row['created_by']),
                UserId::fromString($row['updated_by']),
                new DateTimeImmutable($row['created_at']),
                new DateTimeImmutable($row['updated_at']),
                (int) $row['metadata_revision'],
                (int) $row['source_revision'],
                (int) $row['lifecycle_revision']
            );
        } catch (ProjectInputRejected) {
            throw new LogicException('Invalid persisted Project facts.');
        }
    }
}
