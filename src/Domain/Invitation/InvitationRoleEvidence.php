<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class InvitationRoleEvidence
 *
 * Attributes observed managed membership to the authenticated actor and target
 */
final readonly class InvitationRoleEvidence
{
    /**
     * Constructs InvitationRoleEvidence
     *
     * Captures the authenticated actor and observed membership
     */
    private function __construct(public UserId $actorId, public UserId $userId, public RoleId $roleId)
    {
    }

    /**
     * Records current membership without claiming atomic assignment and audit
     */
    public static function confirmed(UserId $actor, UserId $target, InvitationMode $mode): self
    {
        return new self($actor, $target, $mode->roleId());
    }
}
