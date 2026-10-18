<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\Common\Domain\Exception\ValidationException;

/**
 * Enum InvitationMode
 *
 * Restricts invitations to the two installation-managed destinations
 */
enum InvitationMode: string
{
    case ORDINARY = 'ordinary';
    case SUPER_ADMIN = 'super_admin';

    /**
     * Resolves only the stable installation-managed role
     */
    public function roleId(): RoleId
    {
        $id = match ($this) {
            self::ORDINARY => InstallationManagedPolicy::USER_ROLE_ID,
            self::SUPER_ADMIN => InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID
        };

        return RoleId::fromString($id);
    }

    /**
     * Requires deliberate confirmation for elevation without truthy coercion
     */
    public function requireConfirmation(bool $confirmed): void
    {
        if ($this === self::SUPER_ADMIN && !$confirmed) {
            throw new ValidationException(['confirm_super_admin' => ['Explicit confirmation is required.']]);
        }
    }
}
