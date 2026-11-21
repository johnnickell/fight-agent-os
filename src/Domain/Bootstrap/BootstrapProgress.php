<?php

declare(strict_types=1);

namespace App\Domain\Bootstrap;

use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;

/**
 * Interface BootstrapProgress
 *
 * Inspects independent bootstrap commits without controlling admission or writing evidence
 */
interface BootstrapProgress
{
    /**
     * Reads safe committed facts for this invocation independently of a failed manager
     */
    public function inspect(BootstrapSuperAdmin $command): BootstrapState;
}
