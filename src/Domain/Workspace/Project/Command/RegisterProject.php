<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project\Command;

use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class RegisterProject
 *
 * Carries entry-generated identity and trusted attribution, never an original-response request
 */
final readonly class RegisterProject implements Command
{
    /**
     * Constructs RegisterProject
     */
    public function __construct(
        public ProjectId $projectId,
        public WorkspaceId $workspaceId,
        public UserId $actorId,
        public ProjectMetadata $metadata,
        public ProjectSources $sources
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(
            ProjectId::fromString($data['project_id']),
            WorkspaceId::fromString($data['workspace_id']),
            UserId::fromString($data['actor_id']),
            new ProjectMetadata($data['display_name'], $data['slug']),
            new ProjectSources(
                $data['primary_source'] === null ? null : SourceDescriptor::reconstitute($data['primary_source']),
                array_values(array_map(SourceDescriptor::reconstitute(...), $data['equivalent_sources']))
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'project_id'         => $this->projectId->toString(),
            'workspace_id'       => $this->workspaceId->toString(),
            'actor_id'           => $this->actorId->toString(),
            'display_name'       => $this->metadata->displayName,
            'slug'               => $this->metadata->slug,
            'primary_source'     => $this->sources->primary?->toArray(),
            'equivalent_sources' => array_map(
                static fn(SourceDescriptor $source): array => $source->toArray(),
                $this->sources->equivalent
            )
        ];
    }
}
