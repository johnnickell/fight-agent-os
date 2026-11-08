<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Workspace\Query;

use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class GetWorkspaceById
 *
 * Requests the current state of a required Workspace
 */
final readonly class GetWorkspaceById implements Query
{
    /**
     * Constructs GetWorkspaceById
     */
    public function __construct(public WorkspaceId $workspaceId)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(WorkspaceId::fromString($data['workspace_id']));
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['workspace_id' => $this->workspaceId->toString()];
    }
}
