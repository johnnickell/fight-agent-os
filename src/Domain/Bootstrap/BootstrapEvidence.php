<?php

declare(strict_types=1);

namespace App\Domain\Bootstrap;

use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;

/**
 * Class BootstrapEvidence
 *
 * Distinguishes a trusted bootstrap operator from an authenticated or pending target
 */
final class BootstrapEvidence extends AuditEvidence
{
    /**
     * Records the permanently consumed one-time claim
     */
    public static function claimed(BootstrapSuperAdmin $command): self
    {
        return new self('bootstrap:'.$command->input->operatorId, 'user.bootstrap_claimed', $command->userId);
    }

    /**
     * Records verified bootstrap membership in a separate mandatory completion transaction
     */
    public static function completed(BootstrapSuperAdmin $command): self
    {
        return new self('bootstrap:'.$command->input->operatorId, 'user.bootstrap_completed', $command->userId);
    }
}
