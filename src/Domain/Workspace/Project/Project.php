<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project;

use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;

/**
 * Class Project
 *
 * Owns one collaboration target and source evidence without physical or execution authority
 */
final readonly class Project
{
    /**
     * Constructs Project
     */
    private function __construct(
        public ProjectId $id,
        public WorkspaceId $workspaceId,
        public ProjectMetadata $metadata,
        public ProjectSources $sources,
        public UserId $createdBy,
        public UserId $updatedBy,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public int $metadataRevision,
        public int $sourceRevision,
        public int $lifecycleRevision
    ) {
        if (min($metadataRevision, $sourceRevision, $lifecycleRevision) < 1) {
            throw new LogicException('Invalid persisted Project revisions.');
        }
    }

    /**
     * Registers a target with the entry-generated identity and independent revision baselines
     */
    public static function register(
        ProjectId $projectId,
        WorkspaceId $workspaceId,
        ProjectMetadata $metadata,
        ProjectSources $sources,
        UserId $actorId,
        DateTimeImmutable $now
    ): self {
        return new self($projectId, $workspaceId, $metadata, $sources, $actorId, $actorId, $now, $now, 1, 1, 1);
    }

    /**
     * Restores facts without minting identities or overwriting historical authorship
     */
    public static function reconstitute(
        ProjectId $projectId,
        WorkspaceId $workspaceId,
        ProjectMetadata $metadata,
        ProjectSources $sources,
        UserId $createdBy,
        UserId $updatedBy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        int $metadataRevision,
        int $sourceRevision,
        int $lifecycleRevision
    ): self {
        return new self(
            $projectId,
            $workspaceId,
            $metadata,
            $sources,
            $createdBy,
            $updatedBy,
            $createdAt,
            $updatedAt,
            $metadataRevision,
            $sourceRevision,
            $lifecycleRevision
        );
    }
}
