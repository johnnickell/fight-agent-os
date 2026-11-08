<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project;

use DomainException;

/**
 * Class ProjectConflict
 *
 * Reports a recognized business claim conflict without another Project's identity
 */
final class ProjectConflict extends DomainException
{
    /**
     * Constructs ProjectConflict
     */
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Project conflicts.');
    }
}
