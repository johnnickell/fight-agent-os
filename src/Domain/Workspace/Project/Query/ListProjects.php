<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project\Query;

use App\Domain\Workspace\Project\ProjectInputRejected;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class ListProjects
 */
final readonly class ListProjects implements Query
{
    /**
     * Constructs ListProjects
     */
    public function __construct(public WorkspaceId $workspaceId, public int $offset = 0, public int $limit = 25)
    {
        if ($offset < 0 || $offset > 100000 || $limit < 1 || $limit > 100) {
            throw new ProjectInputRejected();
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(WorkspaceId::fromString($data['workspace_id']), $data['offset'], $data['limit']);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['workspace_id' => $this->workspaceId->toString(), 'offset' => $this->offset, 'limit' => $this->limit];
    }
}
