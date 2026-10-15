<?php

declare(strict_types=1);

namespace App\Domain\Bootstrap;

use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;

/**
 * Interface BootstrapClaims
 *
 * Owns permanent eligibility claims and safe independent inspection, never identity or delivery writes
 */
interface BootstrapClaims
{
    /**
     * Records eligibility and attributed claim audit under a transaction-duration guard or refuses
     *
     * Only an acknowledged return permits this invocation to proceed; an exception never permits retry
     */
    public function claim(BootstrapSuperAdmin $command): void;

    /**
     * Records separate completion evidence after the caller verifies the package outcome
     */
    public function complete(BootstrapSuperAdmin $command): void;

    /**
     * Returns safe actual state independently of a possibly failed mutation manager
     */
    public function inspect(BootstrapSuperAdmin $command): BootstrapState;
}
