<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\ManagedPolicyCatalog;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;

/**
 * Class InstallationCatalogFixture
 *
 * Supplies an in-memory installation catalog with generated permission identities
 */
final class InstallationCatalogFixture implements ManagedPolicyCatalog
{
    /**
     * Builds complete decoded catalogs for policy scenarios
     *
     * @return array{
     *     list<array{id: string, name: string, tier: string}>,
     *     array{array{id: string, name: string, permissions: list<string>},
     *           array{id: string, name: string, permissions: string}}
     * }
     */
    public static function catalogs(): array
    {
        static $catalogs = null;
        if ($catalogs !== null) {
            return $catalogs;
        }
        $members = ['VIEW_DASHBOARD', 'READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS'];
        $safe = [...$members, 'READ_PROJECTS', 'REGISTER_PROJECTS', 'GENERATE_REFERENCE_IDS'];
        $permissions = array_map(static fn (string $name): array => [
            'id'   => PermissionId::generate()->toString(),
            'name' => $name,
            'tier' => in_array($name, $safe, true) ? 'ADMIN_SAFE' : 'SUPER_ADMIN_ONLY'
        ], [
            ...$members, 'READ_USERS', 'INVITE_USERS', 'MANAGE_USER_INVITATIONS', 'ASSIGN_SUPER_ADMIN',
            'REMOVE_SUPER_ADMIN', 'DISABLE_USERS', 'ENABLE_USERS', 'DELETE_USERS', 'RESTORE_USERS',
            'MANAGE_USER_ROLES', 'READ_SESSIONS', 'DELETE_SESSIONS', 'READ_ROLES', 'CREATE_ROLES',
            'UPDATE_ROLES', 'DELETE_ROLES', 'READ_PERMISSIONS', 'MANAGE_ROLE_PERMISSIONS', 'READ_SWAGGER',
            'READ_PROJECTS', 'REGISTER_PROJECTS', 'GENERATE_REFERENCE_IDS', 'READ_LOGS', 'EXPORT_LOGS',
            'RESTORE_LOG_ARCHIVES', 'DOWNLOAD_LOG_ARCHIVES'
        ]);

        return $catalogs = [$permissions, [
            ['id' => InstallationManagedPolicy::USER_ROLE_ID, 'name' => 'ROLE_USER', 'permissions' => $members],
            ['id' => InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID, 'name' => 'ROLE_SUPER_ADMIN', 'permissions' => '*']
        ]];
    }

    /**
     * Loads the in-memory desired policy
     */
    public function load(): ManagedPolicy
    {
        return InstallationManagedPolicy::create(...self::catalogs());
    }
}
