<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project;

use App\Domain\Workspace\Workspace\WorkspaceId;

/**
 * Interface ProjectRepository
 *
 * Collects Project roots and answers bounded active-claim membership without transaction ownership
 */
interface ProjectRepository
{
    /**
     * Adds a Project and its source claims inside the caller's transaction
     */
    public function add(Project $project): void;

    /**
     * Finds a Project only within the declared Workspace
     */
    public function getById(WorkspaceId $workspaceId, ProjectId $projectId): ?Project;

    /**
     * Returns a deterministic bounded slice ordered by creation time then identity
     *
     * @return list<Project>
     */
    public function slice(WorkspaceId $workspaceId, int $offset, int $limit): array;

    /**
     * Checks active slug membership without reserving it
     */
    public function hasActiveSlug(WorkspaceId $workspaceId, string $slug): bool;

    /**
     * Checks canonical active source membership without disclosing the owning Project
     */
    public function hasActiveSource(WorkspaceId $workspaceId, SourceDescriptor $source): bool;
}
