<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;

/**
 * Class ConsoleRoleEvidence
 *
 * Records observed membership rather than claiming a new or crash-atomic assignment
 */
final class ConsoleRoleEvidence extends AuditEvidence
{
    /**
     * Records membership observed after an approved additive invocation
     */
    public static function membershipConfirmed(ConsoleRoleInput $input): self
    {
        return new self($input->actorId(), 'user.console_role_assigned', $input->userId, [
            'role_id' => $input->roleId->toString(),
            'result'  => 'membership_confirmed'
        ]);
    }
}
