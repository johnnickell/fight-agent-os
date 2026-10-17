<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Domain\Invitation\InvitationOperation;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Interface InvitationRateLimiter
 *
 * Consumes one atomic actor/action budget after authorization and before work
 */
interface InvitationRateLimiter
{
    /**
     * Rejects exhausted or full-capacity windows with safe retry metadata
     */
    public function consume(UserId $actorId, InvitationOperation $operation): void;
}
