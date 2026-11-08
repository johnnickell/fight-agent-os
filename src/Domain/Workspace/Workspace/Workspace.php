<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Workspace;

use DateTimeImmutable;

/**
 * Class Workspace
 *
 * Represents the installation's initial Workspace without enrolling a Host or granting access
 */
final readonly class Workspace
{
    /**
     * Constructs Workspace
     */
    public function __construct(public WorkspaceId $id, public DateTimeImmutable $createdAt)
    {
    }
}
