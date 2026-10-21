<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleCreated;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleRemoved;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleRenamed;

/**
 * Interface RoleMutationAudit
 */
interface RoleMutationAudit
{
    /**
     * Records a safe Role fact inside its mutation transaction
     */
    public function record(CustomRoleCreated|CustomRoleRenamed|CustomRoleRemoved $event): void;
}
