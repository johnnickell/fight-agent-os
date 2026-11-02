<?php

declare(strict_types=1);

namespace Tests\Unit\Users;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\PermissionDenied;
use App\Application\Failure\ResourceNotFound;
use App\Application\Users\ReadUserDetail;
use App\Application\Users\UserRolePresentation;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
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
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Protects non-HTTP detail reads with real authoritative resolution and exact package results
 */
final class ReadUserDetailTest extends TestCase
{
    private ReadUserDetail $reader;
    private AuthenticationContext $authentication;
    private User $actor;
    private User $target;
    private mixed $result;
    private string $fault = '';
    private int $dispatches = 0;
    private int $actorReads = 0;

    /**
     * Constructs authoritative actor stores independently from the package target response
     */
    protected function setUp(): void
    {
        $now = new DateTimeImmutable('2026-11-02T12:00:00Z');
        $permission = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('READ_USERS'),
            PermissionTier::SUPER_ADMIN_ONLY,
            $now
        );
        $managed = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [$permission->getId()],
            $now
        );
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('reader@example.test'), $now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('fixture', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $this->actor->assignRole($managed->getId(), $now);
        $this->target = User::invite(UserId::generate(), EmailAddress::fromString('target@example.test'), $now);
        $this->result = UserView::fromUser($this->target);
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $this->actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            1,
            false
        );
        $this->authentication = new AuthenticationContext($this->actor->getId(), $session->getId(), 1);
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturnCallback(function (UserId $id): ?User {
            self::assertTrue($id->equals($this->actor->getId()), 'Target must not be looked up before dispatch.');
            ++$this->actorReads;
            if ($this->fault === 'race' && $this->actorReads === 2) {
                return null;
            }

            return $this->fault === 'unknown' ? null : $this->actor;
        });
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturnCallback(fn() => $this->fault === 'session' ? null : $session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturnCallback(fn() => $this->fault === 'roles' ? [] : [$managed]);
        $roles->method('getByName')->willReturn($managed);
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByIds')->willReturnCallback(
            fn() => $this->fault === 'permission' ? [] : [$permission]
        );
        $permissions->method('getByName')->willReturnCallback(fn() => $this->fault === 'policy' ? null : $permission);
        $bus = $this->createStub(QueryBus::class);
        $bus->method('fetch')->willReturnCallback(function (GetUserById $query): mixed {
            ++$this->dispatches;
            self::assertTrue($query->getUserId()->equals($this->target->getId()));

            return $this->result;
        });
        $this->reader = new ReadUserDetail(
            new AuthenticatedPermissions(new AuthoritativePrincipalResolver(
                $users,
                $sessions,
                $roles,
                $permissions,
                new AccessClock($now)
            ), new PermissionGuard()),
            new InstallationAuthority($users, $roles, $permissions),
            $bus,
            new UserRolePresentation($roles)
        );
    }

    /**
     * Denies stale or unresolved invocation authority without any target dispatch
     */
    #[DataProvider('denials')]
    public function testNoDispatch(string $fault): void
    {
        $this->fault = $fault;
        try {
            $this->reader->read($this->authentication, $this->target->getId());
            self::fail('Unresolved authority allowed target lookup.');
        } catch (PermissionDenied) {
            self::assertSame(0, $this->dispatches);
        }
    }

    /**
     * Supplies principal and subsequent installation-policy failures
     *
     * @return iterable<array{string}>
     */
    public static function denials(): iterable
    {
        foreach (['unknown', 'session', 'roles', 'permission', 'policy', 'race'] as $fault) {
            yield [$fault];
        }
    }

    /**
     * Uses the package view without inventing assignments or a second identity model
     */
    public function testRead(): void
    {
        $detail = $this->reader->read($this->authentication, $this->target->getId());
        self::assertSame($this->result, $detail['user']);
        self::assertSame([], $detail['roles']);
        self::assertSame(1, $this->dispatches);
    }

    /**
     * Maps a disappeared authorized target to not found rather than guessing retained state
     */
    public function testMissingTarget(): void
    {
        $this->result = null;
        $this->expectException(ResourceNotFound::class);
        $this->reader->read($this->authentication, $this->target->getId());
    }

    /**
     * Rejects invalid or wrong-target package results atomically
     */
    #[DataProvider('invalidResults')]
    public function testInvalidResult(bool $wrongTarget): void
    {
        $this->result = $wrongTarget ? UserView::fromUser($this->actor) : 'private-query-marker';
        $this->expectException(LogicException::class);
        $this->reader->read($this->authentication, $this->target->getId());
    }

    /**
     * Supplies wrong identity and wrong type failures
     *
     * @return iterable<array{bool}>
     */
    public static function invalidResults(): iterable
    {
        yield [false];
        yield [true];
    }
}
