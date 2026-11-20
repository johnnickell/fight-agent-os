<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Identifier\Uuid;

/**
 * Class OperatorMembershipEvidence
 *
 * Records observed membership rather than claiming a new or crash-atomic assignment
 */
final readonly class OperatorMembershipEvidence
{
    /**
     * Constructs OperatorMembershipEvidence
     *
     * Captures the operator and observed membership
     */
    private function __construct(
        public string $actorId,
        public UserId $userId,
        public RoleId $roleId,
        public Uuid $invocationId
    ) {
    }

    /**
     * Records membership observed after an approved additive invocation
     */
    public static function membershipConfirmed(
        TrustedOperator $operator,
        UserId $userId,
        RoleId $roleId,
        Uuid $invocationId
    ): self {
        return new self($operator->auditActorId, $userId, $roleId, $invocationId);
    }
}
