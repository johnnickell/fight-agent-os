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
 * Defines the complete installation-owned authority catalog
 */
final class InstallationManagedPolicy
{
    public const string USER_ROLE_ID = '01a132ed-a1d6-4d5a-ba84-55c8cafc8f43';
    public const string SUPER_ADMIN_ROLE_ID = '01a132ed-a1d6-48d4-8ab3-ee366b77ee3c';

    private const array PERMISSIONS = [
        'VIEW_DASHBOARD'          => '01a132ed-a1d6-4a32-a485-18ed67ed70b3',
        'READ_OWN_SESSIONS'       => '01a132ed-a1d6-4c4d-8e5e-bd2532f84c46',
        'DELETE_OWN_SESSIONS'     => '01a132ed-a1d6-47ed-8bc5-e30129d147af',
        'READ_USERS'              => '01a132ed-a1d6-4c39-83d4-497ab8b43d72',
        'INVITE_USERS'            => '01a132ed-a1d6-445b-96f4-cbbd8143855c',
        'MANAGE_USER_INVITATIONS' => '01a132ed-a1d6-40da-8e96-e94d029a37e4',
        'ASSIGN_SUPER_ADMIN'      => '01a132ed-a1d6-4b60-9edd-50b99d83a548',
        'REMOVE_SUPER_ADMIN'      => '01a132ed-a1d6-428b-b1ca-af0eaae52d59',
        'DISABLE_USERS'           => '01a132ed-a1d6-495e-bb28-3d357529701c',
        'ENABLE_USERS'            => '01a132ed-a1d6-4db4-ab1f-cf5a0b838a00',
        'DELETE_USERS'            => '01a132ed-a1d6-4cb8-be53-135707cd0929',
        'RESTORE_USERS'           => '01a132ed-a1d6-47c0-8666-0ea73a33f336',
        'MANAGE_USER_ROLES'       => '01a132ed-a1d6-4eaa-8634-fd5f26b4344a',
        'READ_SESSIONS'           => '01a132ed-a1d6-44cd-972b-5f54e7784ff8',
        'DELETE_SESSIONS'         => '01a132ed-a1d6-4174-8e53-3202244c4e81',
        'READ_ROLES'              => '01a132ed-a1d6-47d4-bf8b-5e144c2e50e6',
        'CREATE_ROLES'            => '01a132ed-a1d6-4b84-9c40-d8861f927982',
        'UPDATE_ROLES'            => '01a132ed-a1d6-4935-8ab1-6d9863d25bad',
        'DELETE_ROLES'            => '01a132ed-a1d6-4b44-897a-4a965247a786',
        'READ_PERMISSIONS'        => '01a132ed-a1d6-4834-8ca8-f9981c2e7815',
        'MANAGE_ROLE_PERMISSIONS' => '01a132ed-a1d6-4e78-a5e1-ca2f189bdae9',
        'READ_SWAGGER'            => '01a132ed-a1d6-4d73-acf1-882187ebe9f8'
    ];
    private const array SELF_SERVICE = ['VIEW_DASHBOARD', 'READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS'];

    /**
     * Validates that the complete installation authority is reconciled
     */
    public static function requireReconciled(ManagedPolicyPlan $plan): void
    {
        $policy = self::create();
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
     * Builds typed definitions and retains every approved application reference
     */
    public static function create(): ManagedPolicy
    {
        $permissions = [];
        $all = [];
        $selfService = [];
        foreach (self::PERMISSIONS as $name => $identity) {
            $id = PermissionId::fromString($identity);
            $safe = in_array($name, self::SELF_SERVICE, true);
            $permissions[] = new ManagedPermissionDefinition(
                $id,
                PermissionName::fromString($name),
                $safe ? PermissionTier::ADMIN_SAFE : PermissionTier::SUPER_ADMIN_ONLY
            );
            $all[] = $id;
            if ($safe) {
                $selfService[] = $id;
            }
        }

        return new ManagedPolicy($permissions, [
            new ManagedRoleDefinition(
                RoleId::fromString(self::USER_ROLE_ID),
                RoleName::fromString('ROLE_USER'),
                $selfService
            ),
            new ManagedRoleDefinition(
                RoleId::fromString(self::SUPER_ADMIN_ROLE_ID),
                RoleName::fromString('ROLE_SUPER_ADMIN'),
                $all
            )
        ], $all);
    }
}
