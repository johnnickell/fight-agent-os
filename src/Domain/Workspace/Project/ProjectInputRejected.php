<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Project;

use DomainException;

/**
 * Class ProjectInputRejected
 *
 * Carries no submitted locator or private diagnostic
 */
final class ProjectInputRejected extends DomainException
{
    /**
     * Constructs ProjectInputRejected
     */
    public function __construct()
    {
        parent::__construct('Invalid Project input.');
    }
}
