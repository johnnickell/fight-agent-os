<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Application\Authorization\InstallationAuthority;
use App\Application\Failure\PermissionDenied;
use ArrayObject;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\TestCase;

/**
 * Proves fresh actor and installation scope checks before protected operations
 */
final class InstallationAuthorityTest extends TestCase
{
    /**
     * Checks a current protected grant for an active actor and an installed pending target
     */
    public function testActiveManagedMembershipPermitsInstalledTarget(): void
    {
        [$authority, $actor, $target, $permission] = $this->authority();
        $authority->assertInstallationPermission($actor->getId(), $permission->getName(), $target->getId());
        self::assertSame('pending_activation', $target->getState()->value);
    }

    /**
     * Rejects a role assignment after its actor ceases to be active
     */
    public function testCurrentActorStateIsRecheckedOnEveryDecision(): void
    {
        [$authority, $actor, , $permission] = $this->authority();
        $authority->assertInstallationPermission($actor->getId(), $permission->getName());
        $actor->disable(new DateTimeImmutable('2026-10-10T12:01:00Z'));

        $this->expectException(PermissionDenied::class);
        $authority->assertInstallationPermission($actor->getId(), $permission->getName());
    }

    /**
     * Rejects unknown actors rather than treating a trusted ID as authority
     */
    public function testUnknownActorFailsClosed(): void
    {
        [$authority, , , $permission] = $this->authority();
        $this->expectException(PermissionDenied::class);
        $authority->assertInstallationPermission(UserId::generate(), $permission->getName());
    }

    /**
     * Rejects a known managed permission if it is not classified as protected
     */
    public function testUnprotectedPermissionCannotAuthorizeInstallationAdministration(): void
    {
        [$authority, $actor, , $permission] = $this->authority(PermissionTier::ADMIN_SAFE);
        $this->expectException(PermissionDenied::class);
        $authority->assertInstallationPermission($actor->getId(), $permission->getName());
    }

    /**
     * Rejects missing managed authority even if the user still references its old ID
     */
    public function testMissingManagedRoleFailsClosed(): void
    {
        [$authority, $actor, , $permission, , $designated] = $this->authority();
        $designated->exchangeArray([]);

        $this->expectException(PermissionDenied::class);
        $authority->assertInstallationPermission($actor->getId(), $permission->getName());
    }

    /**
     * Rejects a managed role after its permission membership is removed
     */
    public function testRevokedPermissionMembershipFailsClosed(): void
    {
        [$authority, $actor, , $permission, $role, $designated] = $this->authority();
        $designated->exchangeArray([Role::defineManaged($role->getId(), $role->getName(), [], $role->getCreatedAt())]);

        $this->expectException(PermissionDenied::class);
        $authority->assertInstallationPermission($actor->getId(), $permission->getName());
    }

    /**
     * Rejects a protected permission when the actor loses the managed role
     */
    public function testRevokedRoleAssignmentFailsClosed(): void
    {
        [$authority, $actor, , $permission, $role] = $this->authority();
        $actor->removeRole($role->getId(), $role->getCreatedAt()->modify('+1 second'));

        $this->expectException(PermissionDenied::class);
        $authority->assertInstallationPermission($actor->getId(), $permission->getName());
    }

    /**
     * Rejects an uninstalled target without exposing its state to the caller
     */
    public function testUnknownTargetFailsClosed(): void
    {
        [$authority, $actor, , $permission] = $this->authority();
        $this->expectException(PermissionDenied::class);
        $authority->assertInstallationPermission($actor->getId(), $permission->getName(), UserId::generate());
    }

    /**
     * Creates current package authority snapshots behind injectable repository ports
     *
     * @return array{InstallationAuthority, User, User, Permission, Role, ArrayObject<int, Role>}
     */
    private function authority(PermissionTier $tier = PermissionTier::SUPER_ADMIN_ONLY): array
    {
        $at = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $permission = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('MANAGE_USERS'),
            $tier,
            $at
        );
        $role = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString(Role::SUPER_ADMIN_NAME),
            [$permission->getId()],
            $at
        );
        $actor = User::invite(UserId::generate(), EmailAddress::fromString('actor@example.test'), $at);
        $actor->activate(PasswordHash::fromString((string) password_hash('test-password', PASSWORD_BCRYPT)), $at);
        $actor->assignRole($role->getId(), $at);
        $target = User::invite(UserId::generate(), EmailAddress::fromString('target@example.test'), $at);
        $usersById = [$actor->getId()->toString() => $actor, $target->getId()->toString() => $target];
        $designated = new ArrayObject([$role]);

        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturnCallback(
            static fn(UserId $id): ?User => $usersById[$id->toString()] ?? null
        );
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByName')->willReturnCallback(
            static function (RoleName $name) use ($designated): ?Role {
                return $name->toString() === Role::SUPER_ADMIN_NAME ? ($designated[0] ?? null) : null;
            }
        );
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByName')->willReturn($permission);

        return [
            new InstallationAuthority($users, $roles, $permissions),
            $actor, $target, $permission, $role, $designated
        ];
    }
}
