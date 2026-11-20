<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class ConsoleRoleEvidence
 *
 * Records observed membership rather than claiming a new or crash-atomic assignment
 */
final readonly class ConsoleRoleEvidence
{
    /**
     * Constructs ConsoleRoleEvidence
     *
     * Captures the operator and observed membership
     */
    private function __construct(public string $actorId, public UserId $userId, public RoleId $roleId)
    {
    }

    /**
     * Records membership observed after an approved additive invocation
     */
    public static function membershipConfirmed(ConsoleRoleInput $input): self
    {
        return new self($input->actorId(), $input->userId, $input->roleId);
    }
}
