<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Workspace;

/**
 * Interface WorkspaceRepository
 *
 * Collects Workspace roots without completing transactions or creating identities on reads
 */
interface WorkspaceRepository
{
    /**
     * Returns the current initial Workspace or absence
     */
    public function findInitial(): ?Workspace;

    /**
     * Returns a Workspace by its stable identity or absence
     */
    public function getById(WorkspaceId $workspaceId): ?Workspace;

    /**
     * Adds the initial Workspace within the caller's transaction
     *
     * @throws InitialWorkspaceAlreadyExists When another root owns the initial slot
     */
    public function add(Workspace $workspace): void;
}
