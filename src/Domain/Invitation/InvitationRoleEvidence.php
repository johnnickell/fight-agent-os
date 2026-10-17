<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class InvitationRoleEvidence
 *
 * Attributes observed managed membership to the authenticated actor and target
 */
final class InvitationRoleEvidence extends AuditEvidence
{
    /**
     * Records current membership without claiming atomic assignment and audit
     */
    public static function confirmed(UserId $actor, UserId $target, InvitationMode $mode): self
    {
        return new self($actor->toString(), 'user.invitation_role_confirmed', $target, [
            'role_id' => $mode->roleId()->toString(),
            'result'  => 'membership_confirmed'
        ]);
    }
}
