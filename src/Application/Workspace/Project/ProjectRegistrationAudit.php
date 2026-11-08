<?php

declare(strict_types=1);

namespace App\Application\Workspace\Project;

use App\Domain\Workspace\Project\Project;

/**
 * Interface ProjectRegistrationAudit
 */
interface ProjectRegistrationAudit
{
    /**
     * Records required creation attribution inside the Project transaction
     */
    public function record(Project $project): void;
}
