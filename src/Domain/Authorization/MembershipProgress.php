<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Interface MembershipProgress
 *
 * Reads committed membership and evidence for the specific invocation
 */
interface MembershipProgress
{
    /**
     * Reads only the supplied target; invitation inspection requires its exact pending identity
     */
    public function inspect(OperatorMembershipEvidence $evidence, ?EmailAddress $pendingEmail = null): MembershipState;
}
