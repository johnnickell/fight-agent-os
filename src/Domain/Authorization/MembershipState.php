<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class MembershipState
 *
 * Reports safe facts observed independently of the success of the write
 */
final readonly class MembershipState
{
    /**
     * Constructs MembershipState
     *
     * @phpstan-param 'confirmed'|'missing'|'unknown' $assignment
     * @phpstan-param 'confirmed'|'unconfirmed' $audit
     */
    public function __construct(
        public ?UserId $knownUserId = null,
        public string $assignment = 'unknown',
        public string $audit = 'unconfirmed'
    ) {
    }

    /**
     * Requires known membership and evidence from the same invocation
     */
    public function isComplete(): bool
    {
        return $this->knownUserId !== null && $this->assignment === 'confirmed' && $this->audit === 'confirmed';
    }
}
