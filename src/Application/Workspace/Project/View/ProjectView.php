<?php

declare(strict_types=1);

namespace App\Application\Workspace\Project\View;

use App\Domain\Workspace\Project\Project;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class ProjectView
 *
 * Projects safe immutable current facts, not an HTTP response or physical availability
 */
final readonly class ProjectView
{
    /**
     * Constructs ProjectView
     *
     * @phpstan-param list<SourceDescriptor> $equivalentSources
     */
    private function __construct(
        public ProjectId $projectId,
        public WorkspaceId $workspaceId,
        public string $displayName,
        public string $slug,
        public ?SourceDescriptor $primarySource,
        public array $equivalentSources,
        public UserId $createdBy,
        public UserId $updatedBy,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public int $metadataRevision,
        public int $sourceRevision,
        public int $lifecycleRevision
    ) {
    }

    /**
     * Projects validated current Project facts without a transport representation
     */
    public static function fromProject(Project $project): self
    {
        return new self(
            $project->id,
            $project->workspaceId,
            $project->metadata->displayName,
            $project->metadata->slug,
            $project->sources->primary,
            $project->sources->equivalent,
            $project->createdBy,
            $project->updatedBy,
            $project->createdAt,
            $project->updatedAt,
            $project->metadataRevision,
            $project->sourceRevision,
            $project->lifecycleRevision
        );
    }
}
