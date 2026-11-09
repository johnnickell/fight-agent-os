<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Authorization\EffectiveAdministrators;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\StateConflict;

/**
 * Class PreventAdministratorLockout
 *
 * Protects against deliberate self-targeting and observed last-administrator loss
 *
 * The precheck is intentionally independent of the following package transaction. Concurrent requests
 * can both pass; this policy does not promise atomic last-administrator protection
 */
final readonly class PreventAdministratorLockout
{
    /**
     * Constructs PreventAdministratorLockout
     */
    public function __construct(private EffectiveAdministrators $administrators)
    {
    }

    /**
     * Rejects self-targeted lifecycle transitions without duplicating entry permission admission
     */
    public function assertOtherUser(UserId $actorId, UserId $targetId): void
    {
        if ($actorId->equals($targetId)) {
            throw new StateConflict();
        }
    }

    /**
     * Checks current effective administrator authority before disabling
     */
    public function assertCanDisable(UserId $actorId, UserId $targetId): void
    {
        $this->assertOtherUser($actorId, $targetId);
        if ($this->administrators->isLast($targetId)) {
            throw new StateConflict();
        }
    }
}
