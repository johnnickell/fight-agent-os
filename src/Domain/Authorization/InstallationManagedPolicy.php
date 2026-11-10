<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionDefinition;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyChangeAction;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedRoleDefinition;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use LogicException;

/**
 * Class InstallationManagedPolicy
 *
 * Enforces installation defaults and complete application references independently of catalog storage
 */
final class InstallationManagedPolicy
{
    public const string USER_ROLE_ID = '01a132ed-a1d6-4d5a-ba84-55c8cafc8f43';
    public const string SUPER_ADMIN_ROLE_ID = '01a132ed-a1d6-48d4-8ab3-ee366b77ee3c';

    // These are live application references, not a second desired-definition catalog.
    private const array REFERENCES = [
        'VIEW_DASHBOARD', 'READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS', 'READ_USERS', 'INVITE_USERS',
        'MANAGE_USER_INVITATIONS', 'ASSIGN_SUPER_ADMIN', 'REMOVE_SUPER_ADMIN', 'DISABLE_USERS', 'ENABLE_USERS',
        'DELETE_USERS', 'RESTORE_USERS', 'MANAGE_USER_ROLES', 'READ_SESSIONS', 'DELETE_SESSIONS', 'READ_ROLES',
        'CREATE_ROLES', 'UPDATE_ROLES', 'DELETE_ROLES', 'READ_PERMISSIONS', 'MANAGE_ROLE_PERMISSIONS', 'READ_SWAGGER',
        'READ_PROJECTS', 'REGISTER_PROJECTS', 'GENERATE_REFERENCE_IDS'
    ];
    private const array USER_MEMBERS = ['VIEW_DASHBOARD', 'READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS'];

    /**
     * Builds one supported typed policy from complete decoded catalogs
     *
     * @param array<array-key, mixed> $permissionCatalog
     * @param array<array-key, mixed> $roleCatalog
     */
    public static function create(array $permissionCatalog, array $roleCatalog): ManagedPolicy
    {
        if (!array_is_list($permissionCatalog) || !array_is_list($roleCatalog)) {
            throw new LogicException('Installation catalogs must be lists.');
        }
        $permissions = [];
        $ids = [];
        foreach ($permissionCatalog as $entry) {
            self::requireFields($entry, ['id', 'name', 'tier']);
            $definition = new ManagedPermissionDefinition(
                PermissionId::fromString(self::stringField($entry, 'id')),
                PermissionName::fromString(self::stringField($entry, 'name')),
                PermissionTier::from(self::stringField($entry, 'tier'))
            );
            $permissions[] = $definition;
            $ids[$entry['name']] = $definition->getId();
        }
        $references = [];
        foreach (self::REFERENCES as $name) {
            $references[] = $ids[$name] ?? throw new LogicException('Incomplete installation permission catalog.');
        }
        $roles = [];
        $names = [];
        foreach ($roleCatalog as $entry) {
            self::requireFields($entry, ['id', 'name'], ['permissions']);
            $members = $entry['permissions'];
            if ($entry['name'] === 'ROLE_SUPER_ADMIN') {
                if ($entry['id'] !== self::SUPER_ADMIN_ROLE_ID || $members !== '*') {
                    throw new LogicException('Invalid installation Super Admin policy.');
                }
                $membership = array_values($ids);
            } else {
                if (!is_array($members) || !array_is_list($members)) {
                    throw new LogicException('Invalid installation role membership.');
                }
                $membership = [];
                foreach ($members as $name) {
                    if (!is_string($name) || !isset($ids[$name])) {
                        throw new LogicException('Unknown installation membership reference.');
                    }
                    if (in_array($ids[$name], $membership, true)) {
                        throw new LogicException('Duplicate installation membership reference.');
                    }
                    $membership[] = $ids[$name];
                }
                if ($entry['name'] === 'ROLE_USER') {
                    $expected = self::USER_MEMBERS;
                    sort($members);
                    sort($expected);
                    if ($entry['id'] !== self::USER_ROLE_ID || $members !== $expected) {
                        throw new LogicException('Invalid installation User policy.');
                    }
                }
            }
            $roles[] = new ManagedRoleDefinition(
                RoleId::fromString(self::stringField($entry, 'id')),
                RoleName::fromString(self::stringField($entry, 'name')),
                $membership
            );
            $names[] = $entry['name'];
        }
        if (!in_array('ROLE_USER', $names, true) || !in_array('ROLE_SUPER_ADMIN', $names, true)) {
            throw new LogicException('Incomplete installation role catalog.');
        }

        return new ManagedPolicy($permissions, $roles, $references);
    }

    /**
     * Validates that the complete installation authority is reconciled
     */
    public static function requireReconciled(ManagedPolicyPlan $plan, ManagedPolicy $policy): void
    {
        $actual = [
            array_map(static fn($item): array => $item->getDefinition()->toArray(), $plan->getPermissions()),
            array_map(static fn($item): array => $item->getDefinition()->toArray(), $plan->getRoles())
        ];
        $expected = [
            array_map(static fn($item): array => $item->toArray(), $policy->getPermissions()),
            array_map(static fn($item): array => $item->toArray(), $policy->getRoles())
        ];
        foreach ([0, 1] as $index) {
            sort($actual[$index]);
            sort($expected[$index]);
        }
        if ($actual !== $expected) {
            throw new LogicException('The installation policy preview changed.');
        }
        foreach ([...$plan->getPermissions(), ...$plan->getRoles()] as $item) {
            if ($item->getAction() !== ManagedPolicyChangeAction::UNCHANGED) {
                throw new LogicException('Managed installation authority requires reconciliation.');
            }
        }
    }

    /**
     * Rejects unknown fields and untyped catalog values before package construction
     *
     * @param mixed $entry
     *
     * @phpstan-param list<string> $strings
     * @phpstan-param list<string> $other
     *
     * @phpstan-assert array<string, mixed> $entry
     */
    private static function requireFields(mixed $entry, array $strings, array $other = []): void
    {
        if (!is_array($entry) || count($entry) !== count($strings) + count($other)) {
            throw new LogicException('Invalid installation catalog definition.');
        }
        foreach ($strings as $field) {
            if (!isset($entry[$field]) || !is_string($entry[$field])) {
                throw new LogicException('Invalid installation catalog field.');
            }
        }
        foreach ($other as $field) {
            if (!array_key_exists($field, $entry)) {
                throw new LogicException('Missing installation catalog field.');
            }
        }
    }

    /**
     * Returns a validated string field without coercion
     *
     * @param array<string, mixed> $entry
     */
    private static function stringField(array $entry, string $field): string
    {
        $value = $entry[$field];
        assert(is_string($value));

        return $value;
    }
}
