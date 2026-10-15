<?php

declare(strict_types=1);

namespace App\Domain\Bootstrap;

use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;

/**
 * Interface BootstrapProgress
 *
 * Inspects independent bootstrap commits and records verified completion without controlling admission
 */
interface BootstrapProgress
{
    /**
     * Records completion only after the intended invitation and membership are confirmed
     */
    public function complete(BootstrapSuperAdmin $command): void;

    /**
     * Reads safe committed facts for this invocation independently of a failed manager
     */
    public function inspect(BootstrapSuperAdmin $command): BootstrapState;
}
