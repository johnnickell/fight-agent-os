<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use PHPUnit\Framework\TestCase;

/**
 * Protects the approved installation catalog and least-privilege memberships
 */
final class InstallationManagedPolicyTest extends TestCase
{
    /**
     * Defines exactly the approved authority and references every supported permission
     */
    public function testExactCatalogAndMemberships(): void
    {
        $policy = InstallationManagedPolicy::create();
        $names = [];
        $tiers = [];
        foreach ($policy->getPermissions() as $permission) {
            $names[$permission->getId()->toString()] = $permission->getName()->toString();
            $tiers[$permission->getName()->toString()] = $permission->getTier()->value;
        }
        $expected = [
            'VIEW_DASHBOARD', 'READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS', 'READ_USERS', 'INVITE_USERS',
            'MANAGE_USER_INVITATIONS', 'ASSIGN_SUPER_ADMIN', 'REMOVE_SUPER_ADMIN', 'DISABLE_USERS',
            'ENABLE_USERS', 'DELETE_USERS', 'RESTORE_USERS', 'MANAGE_USER_ROLES', 'READ_SESSIONS',
            'DELETE_SESSIONS', 'READ_ROLES', 'CREATE_ROLES', 'UPDATE_ROLES', 'DELETE_ROLES',
            'READ_PERMISSIONS', 'MANAGE_ROLE_PERMISSIONS', 'READ_SWAGGER'
        ];
        self::assertSame($expected, array_values($names));
        $safe = ['VIEW_DASHBOARD', 'READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS'];
        foreach ($expected as $name) {
            self::assertSame(in_array($name, $safe, true) ? 'ADMIN_SAFE' : 'SUPER_ADMIN_ONLY', $tiers[$name]);
        }
        $roles = $policy->getRoles();
        self::assertCount(2, $roles);
        self::assertSame('ROLE_USER', $roles[0]->getName()->toString());
        self::assertSame('ROLE_SUPER_ADMIN', $roles[1]->getName()->toString());
        $toName = static fn(PermissionId $id): string => $names[$id->toString()];
        self::assertSame(
            ['VIEW_DASHBOARD', 'READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS'],
            array_map($toName, $roles[0]->getPermissionIds())
        );
        self::assertSame($expected, array_map($toName, $roles[1]->getPermissionIds()));
        self::assertSame($expected, array_map($toName, $policy->getReferencedPermissionIds()));
        self::assertSame($policy->toArray(), InstallationManagedPolicy::create()->toArray());
        // Freeze the once-generated identity mapping as well as the separately asserted authority rules.
        self::assertSame(
            'bcf091f15a00e0611174a14344242bff0e448a8b2e3dde2300cc3c64a0129e8b',
            hash('sha256', json_encode($policy->toArray(), JSON_THROW_ON_ERROR))
        );
    }
}
