<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Failure\PermissionDenied;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Throwable;

/**
 * Class AuthenticatedPermissions
 *
 * Rechecks invocation-bound authentication and exact current permissions in Application
 */
final readonly class AuthenticatedPermissions
{
    /**
     * Constructs AuthenticatedPermissions
     */
    public function __construct(private AuthoritativePrincipalResolver $principals, private PermissionGuard $guard)
    {
    }

    /**
     * Returns the authoritative actor only after every current permission is satisfied
     *
     * @param AuthenticationContext $authentication
     * @param array $permissions
     *
     * @phpstan-param list<PermissionName> $permissions
     */
    public function require(AuthenticationContext $authentication, array $permissions): UserId
    {
        try {
            $principal = $this->principals->resolve($authentication);
            $this->guard->assertAllowed(new SecurityContext($principal), $permissions);

            return $principal->getUserId();
        } catch (Throwable) {
            throw new PermissionDenied();
        }
    }
}
