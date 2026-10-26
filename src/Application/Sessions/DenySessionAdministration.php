<?php

declare(strict_types=1);

namespace App\Application\Sessions;

use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionAdministrationAuthorization;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class DenySessionAdministration
 *
 * Refuses every cross-user session request regardless of installation permissions or Roles
 */
final readonly class DenySessionAdministration implements SessionAdministrationAuthorization
{
    /**
     * @inheritDoc
     */
    public function assertCanManageSessions(UserId $actorId, UserId $userId): void
    {
        throw new SessionAdministrationAuthorizationException('Session administration is unavailable.');
    }
}
