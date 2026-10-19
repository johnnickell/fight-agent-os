<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationAdministrationAuthorization;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\InvitationAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Throwable;

/**
 * Class InvitationCorrectionAuthorization
 *
 * Retains the package's actor and target check for pending invitation correction
 */
final readonly class InvitationCorrectionAuthorization implements InvitationAdministrationAuthorization
{
    /**
     * Constructs InvitationCorrectionAuthorization
     */
    public function __construct(private InstallationAuthority $authority)
    {
    }

    /**
     * @inheritDoc
     */
    public function assertCanCorrectInvitation(UserId $actorId, UserId $userId): void
    {
        try {
            $this->authority->assertInvitationPermission($actorId, $userId);
        } catch (Throwable) {
            throw new InvitationAdministrationAuthorizationException('Invitation correction is not authorized.');
        }
    }
}
