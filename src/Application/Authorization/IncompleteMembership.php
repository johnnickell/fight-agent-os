<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Authorization\MembershipState;
use RuntimeException;

/**
 * Class IncompleteMembership
 *
 * Carries only safe retained facts when assignment completion cannot be confirmed
 */
final class IncompleteMembership extends RuntimeException
{
    /**
     * Constructs IncompleteMembership
     */
    public function __construct(public readonly MembershipState $state)
    {
        parent::__construct('Membership operation is incomplete or uncertain.');
    }
}
