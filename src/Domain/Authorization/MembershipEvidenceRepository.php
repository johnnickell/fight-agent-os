<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Domain\Bootstrap\BootstrapEvidence;
use App\Domain\Invitation\ConsoleRoleEvidence;
use App\Domain\Invitation\InvitationRoleEvidence;

/**
 * Interface MembershipEvidenceRepository
 *
 * Retains Agent OS membership confirmations separately from package mutation audit
 */
interface MembershipEvidenceRepository
{
    /**
     * Adds confirmation evidence in the caller's transaction
     */
    public function add(BootstrapEvidence|ConsoleRoleEvidence|InvitationRoleEvidence $evidence): void;
}
