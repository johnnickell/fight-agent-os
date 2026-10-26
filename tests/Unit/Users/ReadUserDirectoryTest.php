<?php

declare(strict_types=1);

namespace Tests\Unit\Users;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\PermissionDenied;
use App\Application\Users\ReadUserDirectory;
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
use Fight\AccessControl\Domain\AccessControl\User\Query\ListUsers;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
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
 * Protects non-HTTP directory authority and exact, fail-closed role presentation
 */
final class ReadUserDirectoryTest extends TestCase
{
    private ReadUserDirectory $reader;
    private AuthenticationContext $authentication;
    private User $actor;
    private Role $managed;
    private Role $custom;
    private DateTimeImmutable $now;
    private mixed $result;
    private string $fault = '';
    /** @var list<ListUsers> */
    private array $queries = [];
    /** @var list<Role>|null */
    private ?array $presentationRoles = null;

    /**
     * Constructs real authoritative permission resolution over controlled stores
     */
    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-26T12:00:00Z');
        $permission = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('READ_USERS'),
            PermissionTier::SUPER_ADMIN_ONLY,
            $this->now
        );
        $this->managed = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [$permission->getId()],
            $this->now
        );
        $this->custom = Role::define(RoleId::generate(), RoleName::fromString('ROLE_CUSTOM'), [], $this->now);
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('reader@example.test'), $this->now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('fixture', PASSWORD_BCRYPT, ['cost' => 4])),
            $this->now
        );
        $this->actor->assignRole($this->managed->getId(), $this->now);
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $this->actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $this->now,
            $this->now->modify('+1 day'),
            $this->now->modify('+2 days'),
            1,
            false
        );
        $this->authentication = new AuthenticationContext($this->actor->getId(), $session->getId(), 1);
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturnCallback(fn() => $this->fault === 'unknown' ? null : $this->actor);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturnCallback(fn() => $this->fault === 'session' ? null : $session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturnCallback(function (): array {
            if ($this->queries !== []) {
                return $this->presentationRoles ?? [$this->managed, $this->custom];
            }

            return $this->fault === 'roles' ? [] : [$this->managed];
        });
        $roles->method('getByName')->willReturn($this->managed);
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByIds')->willReturnCallback(
            fn() => $this->fault === 'permission' ? [] : [$permission]
        );
        $permissions->method('getByName')->willReturnCallback(fn() => $this->fault === 'policy' ? null : $permission);
        $bus = $this->createStub(QueryBus::class);
        $bus->method('fetch')->willReturnCallback(function (ListUsers $query): mixed {
            $this->queries[] = $query;

            return $this->result;
        });
        $this->reader = new ReadUserDirectory(
            new AuthenticatedPermissions(new AuthoritativePrincipalResolver(
                $users,
                $sessions,
                $roles,
                $permissions,
                new AccessClock($this->now)
            ), new PermissionGuard()),
            new InstallationAuthority($users, $roles, $permissions),
            $bus,
            $roles
        );
    }

    /**
     * Requires exact live authority before a package query for every non-HTTP caller
     */
    #[DataProvider('denials')]
    public function testNoDispatch(string $fault): void
    {
        $this->fault = $fault;
        try {
            $this->reader->read($this->authentication);
            self::fail('Unresolved authority allowed a directory query.');
        } catch (PermissionDenied) {
            self::assertSame([], $this->queries);
        }
    }

    /**
     * Supplies independent stale authority boundaries
     *
     * @return iterable<array{string}>
     */
    public static function denials(): iterable
    {
        foreach (['unknown', 'session', 'roles', 'permission', 'policy'] as $fault) {
            yield [$fault];
        }
    }

    /**
     * Fixes pagination and sorts complete safe role presentation independently of lookup order
     */
    public function testProjection(): void
    {
        $view = $this->view([$this->managed->getId(), $this->custom->getId()]);
        $this->result = new ResultSet(2, 3, 4, ArrayList::of(UserView::class)->replace([$view]));
        $page = $this->reader->read($this->authentication, 2, 3);
        self::assertSame($this->result, $page['users']);
        self::assertSame([
            ['name' => 'ROLE_CUSTOM', 'managed' => false],
            ['name' => 'ROLE_SUPER_ADMIN', 'managed' => true]
        ], $page['roles'][$view->getUserId()->toString()]);
        self::assertSame([], $this->queries[0]->getPagination()->orderings());
        self::assertSame(3, $this->queries[0]->getPagination()->limit());
        self::assertSame(3, $this->queries[0]->getPagination()->offset());
    }

    /**
     * Preserves zero assignments without inventing ROLE_USER and accepts an empty installation page
     */
    #[DataProvider('emptyPages')]
    public function testEmpty(bool $one): void
    {
        $view = $this->view([]);
        $this->result = new ResultSet(1, 25, (int) $one, ArrayList::of(UserView::class)->replace($one ? [$view] : []));
        $page = $this->reader->read($this->authentication);
        self::assertSame($one ? [$view->getUserId()->toString() => []] : [], $page['roles']);
    }

    /**
     * Supplies empty page and empty role assignment cases
     *
     * @return iterable<array{bool}>
     */
    public static function emptyPages(): iterable
    {
        yield [false];
        yield [true];
    }

    /**
     * Rejects unbounded direct callers before package work
     */
    #[DataProvider('bounds')]
    public function testBounds(int $page, int $perPage): void
    {
        try {
            $this->reader->read($this->authentication, $page, $perPage);
            self::fail('Invalid pagination dispatched.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $this->queries);
        }
    }

    /**
     * Supplies each pagination boundary
     *
     * @return iterable<array{int, int}>
     */
    public static function bounds(): iterable
    {
        yield [0, 25];
        yield [1001, 25];
        yield [1, 0];
        yield [1, 101];
    }

    /**
     * Fails the whole response on missing, unexpected, duplicate or aliased references
     */
    #[DataProvider('badReferences')]
    public function testRoleIntegrity(string $fault): void
    {
        $ids = [$this->managed->getId(), $this->custom->getId()];
        $this->presentationRoles = match ($fault) {
            'missing' => [$this->managed],
            'duplicate' => [$this->managed, $this->managed, $this->custom],
            'unexpected' => [$this->managed, Role::define(
                RoleId::generate(),
                RoleName::fromString('ROLE_OTHER'),
                [],
                $this->now
            )],
            'name' => [$this->managed, Role::defineManaged(
                $this->custom->getId(),
                $this->managed->getName(),
                [],
                $this->now
            )],
            default => [$this->managed, $this->custom]
        };
        if ($fault === 'assignment') {
            $ids[] = $this->custom->getId();
        }
        $view = $this->view($ids);
        $records = $fault === 'user' ? [$view, $view] : [$view];
        $this->result = new ResultSet(1, 25, count($records), ArrayList::of(UserView::class)->replace($records));
        $this->expectException(LogicException::class);
        $this->reader->read($this->authentication);
    }

    /**
     * Supplies each exact role resolution failure
     *
     * @return iterable<array{string}>
     */
    public static function badReferences(): iterable
    {
        foreach (['missing', 'duplicate', 'unexpected', 'name', 'assignment', 'user'] as $fault) {
            yield [$fault];
        }
    }

    /**
     * Refuses invalid package result types and mismatched pagination
     */
    #[DataProvider('badPages')]
    public function testPackageFailure(string $fault): void
    {
        $this->result = match ($fault) {
            'type' => 'private-query-marker',
            'records' => new ResultSet(1, 25, 0, ArrayList::of('string')),
            'page' => new ResultSet(2, 25, 0, ArrayList::of(UserView::class)),
            'size' => new ResultSet(1, 100, 0, ArrayList::of(UserView::class)),
            default => new ResultSet(
                1,
                25,
                26,
                ArrayList::of(UserView::class)->replace(array_fill(0, 26, $this->view([])))
            )
        };
        $this->expectException(LogicException::class);
        $this->reader->read($this->authentication);
    }

    /**
     * Supplies invalid result categories
     *
     * @return iterable<array{string}>
     */
    public static function badPages(): iterable
    {
        foreach (['type', 'records', 'page', 'size', 'overflow'] as $fault) {
            yield [$fault];
        }
    }

    /**
     * Supplies package-safe identity with explicitly selected role references
     *
     * @param array $ids
     *
     * @phpstan-param list<RoleId> $ids
     */
    private function view(array $ids): UserView
    {
        return new UserView(
            UserId::generate(),
            EmailAddress::fromString('target@example.test'),
            UserState::PENDING_ACTIVATION,
            $ids,
            $this->now,
            $this->now
        );
    }
}
