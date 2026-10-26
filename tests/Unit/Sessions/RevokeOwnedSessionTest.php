<?php

declare(strict_types=1);

namespace Tests\Unit\Sessions;

use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\PermissionDenied;
use App\Application\Sessions\DenySessionAdministration;
use App\Application\Sessions\RevokeOwnedSession;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Command\RevokeSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Protects the non-HTTP permission and principal-only command contract
 */
final class RevokeOwnedSessionTest extends TestCase
{
    /**
     * Requires exact permission and forwards only principal identities and a null reason
     */
    #[DataProvider('permissions')]
    public function testDispatch(string $name): void
    {
        $now = new DateTimeImmutable('2026-10-25T12:00:00Z');
        $permission = Permission::define(PermissionId::generate(), PermissionName::fromString($name), $now);
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_TEST_ADMIN'), [$permission->getId()], $now);
        $user = User::invite(UserId::generate(), EmailAddress::fromString('owner@example.test'), $now);
        $user->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $user->assignRole($role->getId(), $now);
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            1,
            false
        );
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturn([$role]);
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByIds')->willReturn([$permission]);
        $provider = new CurrentPrincipalProvider(
            new RequestAuthenticationContext(new AuthenticationContext($user->getId(), $session->getId(), 1)),
            new AuthoritativePrincipalResolver($users, $sessions, $roles, $permissions, new AccessClock($now))
        );
        $target = RefreshSessionId::generate();
        $bus = $this->createMock(CommandBus::class);
        $bus->expects($name === 'DELETE_OWN_SESSIONS' ? self::once() : self::never())->method('execute')
            ->willReturnCallback(static function ($command) use ($user, $session, $target): void {
                self::assertInstanceOf(RevokeSession::class, $command);
                self::assertTrue($command->getActorId()->equals($user->getId()));
                self::assertTrue($command->getCurrentSessionId()->equals($session->getId()));
                self::assertSame($target, $command->getTargetSessionId());
                self::assertNull($command->getReason());
            });
        if ($name !== 'DELETE_OWN_SESSIONS') {
            $this->expectException(PermissionDenied::class);
        }
        new RevokeOwnedSession(new PermissionGuard(), $bus)->revoke($provider, $target);
    }

    /**
     * Supplies exact own permission and unrelated administrative authority
     *
     * @return iterable<array{string}>
     */
    public static function permissions(): iterable
    {
        yield ['DELETE_OWN_SESSIONS'];
        yield ['DELETE_SESSIONS'];
        yield ['READ_OWN_SESSIONS'];
    }

    /**
     * Denies cross-user management independently of actor and target identity
     */
    public function testDenyCrossUser(): void
    {
        $this->expectException(SessionAdministrationAuthorizationException::class);
        new DenySessionAdministration()->assertCanManageSessions(UserId::generate(), UserId::generate());
    }
}
