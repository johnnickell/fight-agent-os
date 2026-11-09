<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Interface EffectiveAdministrators
 *
 * Queries current active membership of the reconciled managed administrator authority
 */
interface EffectiveAdministrators
{
    /**
     * Determines whether disabling this target would remove the last effective administrator
     *
     * This is a committed-state precheck, not a reservation or a concurrent-mutation guarantee
     */
    public function isLast(UserId $targetId): bool;
}
