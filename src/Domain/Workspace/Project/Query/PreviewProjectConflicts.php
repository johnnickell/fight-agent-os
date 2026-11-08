<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project\Query;

use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class PreviewProjectConflicts
 *
 * Requests advisory active-claim facts without a reservation or identity disclosure
 */
final readonly class PreviewProjectConflicts implements Query
{
    /**
     * Constructs PreviewProjectConflicts
     */
    public function __construct(
        public WorkspaceId $workspaceId,
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
            WorkspaceId::fromString($data['workspace_id']),
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
            'workspace_id'       => $this->workspaceId->toString(),
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
