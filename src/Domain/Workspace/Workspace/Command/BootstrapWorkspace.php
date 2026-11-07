<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Workspace\Command;

use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class BootstrapWorkspace
 *
 * Requests explicit initial Workspace creation with trusted operator attribution, not authentication
 */
final readonly class BootstrapWorkspace implements Command
{
    /**
     * Constructs BootstrapWorkspace
     */
    public function __construct(public WorkspaceId $workspaceId, public UserId $operatorId)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(WorkspaceId::fromString($data['workspace_id']), UserId::fromString($data['operator_id']));
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['workspace_id' => $this->workspaceId->toString(), 'operator_id' => $this->operatorId->toString()];
    }
}
