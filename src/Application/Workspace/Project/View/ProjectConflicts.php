<?php

declare(strict_types=1);

namespace App\Application\Workspace\Project\View;

/**
 * Class ProjectConflicts
 *
 * Reveals bounded advisory membership without another Project's identity
 */
final readonly class ProjectConflicts
{
    /**
     * Constructs ProjectConflicts
     */
    public function __construct(public bool $slugClaimed, public bool $sourceClaimed)
    {
    }
}
