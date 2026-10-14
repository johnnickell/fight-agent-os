<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Failure\PermissionDenied;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;

/**
 * Class PermissionGuard
 *
 * Requires every declared permission from the selected authoritative request snapshot
 */
final readonly class PermissionGuard
{
    /**
     * Rejects missing permissions without a role-name or administrator bypass
     *
     * @param SecurityContext      $context
     * @param PermissionName[] $permissions
     *
     * @phpstan-param list<PermissionName> $permissions
     */
    public function assertAllowed(SecurityContext $context, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (!$context->hasPermission($permission)) {
                throw new PermissionDenied();
            }
        }
    }
}
