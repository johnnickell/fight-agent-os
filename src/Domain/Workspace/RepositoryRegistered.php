<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Class RepositoryRegistered
 *
 * Describes a committed registration without claiming durable event delivery
 */
final readonly class RepositoryRegistered implements Event
{
    /**
     * Constructs RepositoryRegistered
     */
    public function __construct(public WorkspaceId $workspaceId, public RepositoryId $repositoryId)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(
            WorkspaceId::fromString($data['workspace_id']),
            RepositoryId::fromString($data['repository_id'])
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'workspace_id'  => $this->workspaceId->toString(),
            'repository_id' => $this->repositoryId->toString()
        ];
    }
}
