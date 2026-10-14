<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Me;

use Fight\AccessControl\Domain\AccessControl\Authorization\AuthenticatedUserPrincipal;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class CurrentPrincipalData
 *
 * Projects only safe identity and authoritative role and permission names
 */
final readonly class CurrentPrincipalData implements Arrayable
{
    /**
     * Constructs CurrentPrincipalData
     */
    public function __construct(private UserView $identity, private AuthenticatedUserPrincipal $principal)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $roles = array_map(static fn($role): string => $role->getName()->toString(), $this->principal->getRoles());
        $permissions = array_map(
            static fn($permission): string => $permission->getName()->toString(),
            $this->principal->getPermissions()
        );
        sort($roles, SORT_STRING);
        sort($permissions, SORT_STRING);

        return [
            'user_id'     => $this->identity->getUserId()->toString(),
            'email'       => $this->identity->getEmail()->toString(),
            'roles'       => $roles,
            'permissions' => $permissions
        ];
    }
}
