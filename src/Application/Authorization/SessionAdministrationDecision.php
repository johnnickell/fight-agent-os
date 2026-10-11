<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Failure\PermissionDenied;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionAdministrationAuthorization;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class SessionAdministrationDecision
 *
 * Checks the intent-specific installation permission and target before cross-user session access
 */
final readonly class SessionAdministrationDecision implements SessionAdministrationAuthorization
{
    /**
     * Constructs SessionAdministrationDecision
     */
    private function __construct(
        private InstallationAuthority $authority,
        private PermissionName $required,
        private ?PermissionName $additional = null
    ) {
    }

    /**
     * Creates the callback for the cross-user session read handler
     */
    public static function forRead(InstallationAuthority $authority): self
    {
        return new self($authority, PermissionName::fromString('READ_SESSIONS'));
    }

    /**
     * Creates the callback for the cross-user session revocation handler
     */
    public static function forRevocation(InstallationAuthority $authority): self
    {
        return new self($authority, PermissionName::fromString('DELETE_SESSIONS'));
    }

    /**
     * Creates a safe shared-port fallback until the read and revoke handlers have separate composition
     */
    public static function forSharedFallback(InstallationAuthority $authority): self
    {
        return new self(
            $authority,
            PermissionName::fromString('READ_SESSIONS'),
            PermissionName::fromString('DELETE_SESSIONS')
        );
    }

    /**
     * @inheritDoc
     */
    public function assertCanManageSessions(UserId $actorId, UserId $userId): void
    {
        try {
            $this->authority->assertInstallationPermission($actorId, $this->required, $userId);
            if ($this->additional !== null) {
                $this->authority->assertInstallationPermission($actorId, $this->additional, $userId);
            }
        } catch (PermissionDenied) {
            throw new SessionAdministrationAuthorizationException('Session administration is not authorized.');
        }
    }
}
