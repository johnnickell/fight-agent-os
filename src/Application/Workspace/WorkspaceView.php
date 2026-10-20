<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Domain\Workspace\WorkspaceId;
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
}
