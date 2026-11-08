<?php

declare(strict_types=1);

namespace App\Application\Workspace\Workspace;

use App\Domain\Workspace\Workspace\Workspace;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Interface WorkspaceBootstrapAudit
 *
 * Records required bootstrap attribution independently of creation-response bookkeeping
 */
interface WorkspaceBootstrapAudit
{
    /**
     * Records the created Workspace in the same transaction as its state
     */
    public function record(Workspace $workspace, UserId $operatorId): void;
}
