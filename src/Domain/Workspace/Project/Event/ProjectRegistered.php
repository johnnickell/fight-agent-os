<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project\Event;

use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Class ProjectRegistered
 *
 * Describes a committed Project without promising durable event delivery
 */
final readonly class ProjectRegistered implements Event
{
    /**
     * Constructs ProjectRegistered
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
