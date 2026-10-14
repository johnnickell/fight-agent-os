<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use InvalidArgumentException;
use LogicException;

/**
 * Class ConsoleRoleInput
 *
 * Bounds trusted additive correction and excludes installation elevation
 */
final readonly class ConsoleRoleInput
{
    public UserId $operatorId;
    public UserId $userId;
    public RoleId $roleId;

    /**
     * Constructs ConsoleRoleInput
     */
    public function __construct(string $operatorId, string $userId, string $roleId)
    {
        foreach ([$operatorId, $userId, $roleId] as $id) {
            if (preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $id) !== 1) {
                throw new InvalidArgumentException('Invalid console role input.');
            }
        }
        $this->operatorId = UserId::fromString($operatorId);
        $this->userId = UserId::fromString($userId);
        $this->roleId = RoleId::fromString($roleId);
    }

    /**
     * Requires the requested current role without accepting renamed or aliased elevation
     */
    public function requireEligibleRole(?RoleView $role): void
    {
        if (
            $role === null || !$role->getRoleId()->equals($this->roleId)
            || $this->roleId->toString() === InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID
            || $role->getName()->toString() === Role::SUPER_ADMIN_NAME
            || ($role->isManaged() && $this->roleId->toString() !== InstallationManagedPolicy::USER_ROLE_ID)
        ) {
            throw new LogicException('The requested role is not eligible for console correction.');
        }
    }

    /**
     * Returns explicit trusted-console attribution
     */
    public function actorId(): string
    {
        return 'console:'.$this->operatorId->toString();
    }
}
