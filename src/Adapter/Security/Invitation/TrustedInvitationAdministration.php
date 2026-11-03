<?php

declare(strict_types=1);

namespace App\Adapter\Security\Invitation;

use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationAdministrationAuthorization;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class TrustedInvitationAdministration
 *
 * Binds the package correction callback to the adapter-admitted in-process command bus
 *
 * Entry adapters establish actor, operation permission and installation scope before dispatch.
 * The package owns pending state, generation replacement, uniqueness and atomic audit.
 * Ordinary admission is not repeated or serialized against revocation inside its transaction.
 */
final readonly class TrustedInvitationAdministration implements InvitationAdministrationAuthorization
{
    /**
     * @inheritDoc
     */
    public function assertCanCorrectInvitation(UserId $actorId, UserId $userId): void
    {
    }
}
