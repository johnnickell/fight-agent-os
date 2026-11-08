<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project\Query;

use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class GetProjectById
 */
final readonly class GetProjectById implements Query
{
    /**
     * Constructs GetProjectById
     */
    public function __construct(public WorkspaceId $workspaceId, public ProjectId $projectId)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(WorkspaceId::fromString($data['workspace_id']), ProjectId::fromString($data['project_id']));
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['workspace_id' => $this->workspaceId->toString(), 'project_id' => $this->projectId->toString()];
    }
}
