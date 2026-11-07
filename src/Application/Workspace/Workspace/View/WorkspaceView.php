<?php

declare(strict_types=1);

namespace App\Application\Workspace\Workspace\View;

use App\Domain\Workspace\Workspace\Workspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;

/**
 * Class WorkspaceView
 */
final readonly class WorkspaceView
{
    /**
     * Constructs WorkspaceView
     */
    public function __construct(public WorkspaceId $id, public DateTimeImmutable $createdAt)
    {
    }

    /**
     * Projects safe current Workspace facts
     */
    public static function fromWorkspace(Workspace $workspace): self
    {
        return new self($workspace->id, $workspace->createdAt);
    }
}
