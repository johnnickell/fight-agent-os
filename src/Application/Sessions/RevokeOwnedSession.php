<?php

declare(strict_types=1);

namespace App\Application\Sessions;

use App\Application\Authorization\PermissionGuard;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Command\RevokeSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\Common\Application\Messaging\Command\CommandBus;

/**
 * Class RevokeOwnedSession
 *
 * Enforces exact self-service permission and principal-only identity before package revocation
 */
final readonly class RevokeOwnedSession
{
    /**
     * Constructs RevokeOwnedSession
     */
    public function __construct(private PermissionGuard $permissions, private CommandBus $commands)
    {
    }

    /**
     * Dispatches one target with no caller-selected actor, current session or administrative reason
     */
    public function revoke(CurrentPrincipalProvider $provider, RefreshSessionId $target): void
    {
        $principal = $provider->getCurrentPrincipal();
        $this->permissions->assertAllowed(
            new SecurityContext($principal),
            [PermissionName::fromString('DELETE_OWN_SESSIONS')]
        );
        $this->commands->execute(new RevokeSession(
            $principal->getUserId(),
            $principal->getRefreshSessionId(),
            $target,
            null
        ));
    }
}
