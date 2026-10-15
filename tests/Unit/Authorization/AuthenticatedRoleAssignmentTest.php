<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Adapter\Security\UnauthenticatedContextProvider;
use App\Application\Authorization\AuthenticatedRoleAssignmentGuard;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Failure\PermissionDenied;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContextProvider;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Proves authoritative permission conjunction, exact actor matching and stale projection rejection
 */
final class AuthenticatedRoleAssignmentTest extends TestCase
{
    /**
     * Enforces owned authorization against fresh package principal resolution before dispatch
     */
    #[DataProvider('decisions')]
    public function testDecisions(string $case, bool $allowed): void
    {
        $now = new DateTimeImmutable('2026-10-15T12:00:00Z');
        $permissionNames = match ($case) {
            'missing-elevate', 'prefix-only' => ['MANAGE_USER_ROLES', 'ASSIGN_SUPER_ADMINS'],
            'missing-parent' => ['ASSIGN_SUPER_ADMIN'],
            'role-only' => [],
            default => ['ASSIGN_SUPER_ADMIN', 'MANAGE_USER_ROLES']
        };
        $permissions = array_map(static fn(string $name): Permission => Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString($name),
            PermissionTier::SUPER_ADMIN_ONLY,
            $now
        ), $permissionNames);
        $role = Role::defineManaged(
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            array_map(static fn(Permission $p): PermissionId => $p->getId(), $permissions),
            $now
        );
        $actor = User::invite(UserId::generate(), EmailAddress::fromString('actor@example.test'), $now);
        $actor->activate(PasswordHash::fromString((string) password_hash('unit-only-password', PASSWORD_BCRYPT)), $now);
        $actor->assignRole($role->getId(), $now);
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            1,
            false
        );
        $authentication = $this->createStub(AuthenticationContextProvider::class);
        $authentication->method('getAuthenticationContext')->willReturn(new AuthenticationContext(
            $actor->getId(),
            $session->getId(),
            $case === 'stale-version' ? 2 : 1
        ));
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($case === 'unknown' ? null : $actor);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($case === 'revoked' ? $session->revoke() : $session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturn([$role]);
        $roles->method('getByName')->willReturn($role);
        $roles->method('getById')->willReturn($case === 'missing-role' ? null : $role);
        $permissionStore = $this->createStub(PermissionRepository::class);
        $permissionStore->method('getByIds')->willReturn($permissions);
        $guard = new AuthenticatedRoleAssignmentGuard(
            $authentication,
            new AuthoritativePrincipalResolver($users, $sessions, $roles, $permissionStore, new AccessClock($now)),
            $roles
        );
        $message = CommandMessage::create(new AssignRoleToUser(
            $case === 'wrong-actor' ? UserId::generate() : $actor->getId(),
            UserId::generate(),
            $role->getId()
        ));
        $routed = 0;
        try {
            $guard->process($message, static function () use (&$routed): void {
                $routed++;
            });
            self::assertTrue($allowed);
            self::assertSame(1, $routed);
        } catch (PermissionDenied $error) {
            self::assertFalse($allowed);
            self::assertSame(0, $routed);
            self::assertNull($error->getPrevious());
        }
        if ($allowed) {
            // An earlier successful projection is not authorization for a later mutation.
            $role->getId();
            $actor->disable($now);
            $this->expectException(PermissionDenied::class);
            $guard->process($message, static function (): never {
                self::fail('Stale active-authority projection must not route.');
            });
        }
    }

    /**
     * Supplies exact permissions, stale authority and mismatched actor cases
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function decisions(): iterable
    {
        foreach (
            ['allowed', 'missing-elevate', 'missing-parent', 'role-only', 'prefix-only', 'stale-version',
            'revoked', 'unknown', 'wrong-actor', 'missing-role'] as $case
        ) {
            yield $case => [$case, $case === 'allowed'];
        }
    }

    /**
     * Rejects absent authenticated invocation context by default
     */
    public function testNoAuthenticationFallback(): void
    {
        $this->expectException(AuthenticationRequired::class);
        new UnauthenticatedContextProvider()->getAuthenticationContext();
    }
}
