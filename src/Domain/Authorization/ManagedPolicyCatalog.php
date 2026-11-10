<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy;

/**
 * Interface ManagedPolicyCatalog
 *
 * Supplies complete validated installation authority only when an operation needs it
 */
interface ManagedPolicyCatalog
{
    /**
     * Loads one complete desired-policy snapshot without authority writes
     */
    public function load(): ManagedPolicy;
}
