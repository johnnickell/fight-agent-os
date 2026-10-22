<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\PermissionGuard;
use App\Application\Authorization\ReadAuthorityCatalogs;
use App\Application\Failure\PermissionDenied;
use App\Application\Failure\ResourceNotFound;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\GetPermissionById;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\ListPermissions;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\ListRoles;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Protects every non-HTTP catalog caller against stale and partial installation authority
 */
final class ReadAuthorityCatalogsTest extends TestCase
{
    private AuthenticationContext $authentication;
    private ReadAuthorityCatalogs $reader;
    private RoleView $role;
    private PermissionView $permission;
    private mixed $result;
    /** @var list<object> */
    private array $queries = [];
    private string $fault = '';

    /**
     * Builds real authentication resolution over controlled authoritative repositories
     */
    protected function setUp(): void
    {
        $now = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $permissions = [];
        foreach (['READ_ROLES', 'READ_PERMISSIONS'] as $name) {
            $permissions[] = Permission::defineManaged(
                PermissionId::generate(),
                PermissionName::fromString($name),
                PermissionTier::SUPER_ADMIN_ONLY,
                $now
            );
        }
        $role = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            array_map(static fn(Permission $p) => $p->getId(), $permissions),
            $now
        );
        $user = User::invite(UserId::generate(), EmailAddress::fromString('reader@example.test'), $now);
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
            $user->getAuthenticationVersion(),
            false
        );
        $this->authentication = new AuthenticationContext($user->getId(), $session->getId(), 1);
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturnCallback(fn() => $this->fault === 'session' ? null : $session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturnCallback(fn() => $this->fault === 'roles' ? [] : [$role]);
        $roles->method('getByName')->willReturn($role);
        $store = $this->createStub(PermissionRepository::class);
        $store->method('getByIds')->willReturnCallback(
            fn() => $this->fault === 'partial' ? [$permissions[0]] : $permissions
        );
        $store->method('getByName')->willReturnCallback(function (PermissionName $name) use ($permissions, $now) {
            foreach ($permissions as $permission) {
                if ($permission->getName()->equals($name)) {
                    return match ($this->fault) {
                        'missing' => null,
                        'tier' => Permission::defineManaged(
                            $permission->getId(),
                            $name,
                            PermissionTier::ADMIN_SAFE,
                            $now
                        ),
                        default => $permission
                    };
                }
            }

            return null;
        });
        $bus = $this->createStub(QueryBus::class);
        $bus->method('fetch')->willReturnCallback(function ($query): mixed {
            $this->queries[] = $query;

            return $this->result;
        });
        $this->reader = new ReadAuthorityCatalogs(
            new AuthenticatedPermissions(
                new AuthoritativePrincipalResolver($users, $sessions, $roles, $store, new AccessClock($now)),
                new PermissionGuard()
            ),
            new InstallationAuthority($users, $roles, $store),
            $bus
        );
        $this->role = RoleView::fromRole($role);
        $this->permission = PermissionView::fromPermission($permissions[0]);
    }

    /**
     * Requires fresh authentication and protected metadata before any query on every entry
     */
    #[DataProvider('denials')]
    public function testNoDispatch(string $operation, string $fault): void
    {
        $this->fault = $fault;
        try {
            $this->invoke($operation);
            self::fail('Stale authority allowed a read.');
        } catch (PermissionDenied) {
            self::assertSame([], $this->queries);
        }
    }

    /**
     * Supplies each entry boundary and each incomplete authority category
     *
     * @return iterable<array{string, string}>
     */
    public static function denials(): iterable
    {
        foreach (['roles', 'role', 'permissions', 'permission'] as $operation) {
            foreach (['session', 'roles', 'partial', 'missing', 'tier'] as $fault) {
                yield [$operation, $fault];
            }
        }
    }

    /**
     * Preserves safe package types and exact query identities through the configured bus contract
     */
    public function testReads(): void
    {
        $this->result = $this->role;
        self::assertSame($this->role, $this->invoke('role'));
        self::assertInstanceOf(GetRoleById::class, $this->queries[0]);
        self::assertEquals($this->role->getRoleId(), $this->queries[0]->getRoleId());
        $this->result = $this->permission;
        self::assertSame($this->permission, $this->invoke('permission'));
        self::assertInstanceOf(GetPermissionById::class, $this->queries[1]);
        self::assertEquals($this->permission->getPermissionId(), $this->queries[1]->getPermissionId());
        foreach (['roles' => $this->role, 'permissions' => $this->permission] as $operation => $view) {
            $this->result = new ResultSet(2, 3, 4, ArrayList::of($view::class)->replace([$view]));
            self::assertSame($this->result, $this->reader->$operation($this->authentication, 2, 3));
            $query = $this->queries[array_key_last($this->queries)];
            self::assertInstanceOf($operation === 'roles' ? ListRoles::class : ListPermissions::class, $query);
            self::assertSame(3, $query->getPagination()->offset());
            self::assertSame(3, $query->getPagination()->limit());
            self::assertSame([], $query->getPagination()->orderings());
        }
    }

    /**
     * Bounds every direct list caller before package dispatch
     */
    #[DataProvider('invalidPages')]
    public function testPagination(string $operation, int $page, int $perPage): void
    {
        try {
            $this->reader->$operation($this->authentication, $page, $perPage);
            self::fail('Unbounded pagination dispatched.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $this->queries);
        }
    }

    /**
     * Supplies both list boundaries and each numeric bound
     *
     * @return iterable<array{string, int, int}>
     */
    public static function invalidPages(): iterable
    {
        foreach (['roles', 'permissions'] as $operation) {
            foreach ([[0, 25], [1001, 25], [1, 0], [1, 101]] as [$page, $perPage]) {
                yield [$operation, $page, $perPage];
            }
        }
    }

    /**
     * Rejects absent, incorrect and mismatched package results without exposing internals
     */
    #[DataProvider('badResults')]
    public function testResultFailures(string $operation, string $kind): void
    {
        $mismatch = new RoleView(RoleId::generate(), $this->role->getName(), false, [], $this->role->getRevision());
        if ($operation === 'permission') {
            $mismatch = new PermissionView(
                PermissionId::generate(),
                $this->permission->getName(),
                PermissionTier::ADMIN_SAFE,
                false
            );
        }
        $this->result = match ($kind) {
            'null' => null,
            'wrong' => 'private-store-error',
            'page' => new ResultSet(1, 25, 0, ArrayList::of('string')),
            default => $mismatch
        };
        $absent = $kind === 'null' && in_array($operation, ['role', 'permission'], true);
        $this->expectException($absent ? ResourceNotFound::class : LogicException::class);
        $this->invoke($operation);
    }

    /**
     * Supplies each result failure branch independently of repository behavior
     *
     * @return iterable<array{string, string}>
     */
    public static function badResults(): iterable
    {
        foreach (['roles', 'permissions'] as $operation) {
            foreach (['null', 'page'] as $kind) {
                yield [$operation, $kind];
            }
        }
        foreach (['role', 'permission'] as $operation) {
            foreach (['null', 'wrong', 'mismatch'] as $kind) {
                yield [$operation, $kind];
            }
        }
    }

    /**
     * Calls the actual public Application boundary for the selected catalog operation
     */
    private function invoke(string $operation): mixed
    {
        return match ($operation) {
            'role' => $this->reader->role($this->authentication, $this->role->getRoleId()),
            'permission' => $this->reader->permission($this->authentication, $this->permission->getPermissionId()),
            default => $this->reader->$operation($this->authentication)
        };
    }
}
