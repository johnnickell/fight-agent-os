<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Users\GetUserAction;
use App\Adapter\Http\Action\Api\V1\Users\ListUsersAction;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Responder\Api\V1\Users\UserDetailResponder;
use App\Adapter\Http\Responder\Api\V1\Users\UserDirectoryResponder;
use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\PermissionGuard;
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
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\ListUsers;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Tests\Support\AccessClock;

/**
 * Protects fresh Action authorization, bounded package reads and credential-free responses
 */
final class UsersReadActionsTest extends TestCase
{
    private GetUserAction $detail;
    private ListUsersAction $directory;
    private ServerRequestInterface $request;
    private User $actor;
    private User $target;
    private Role $role;
    private mixed $result;
    private string $fault = '';
    private int $actorReads = 0;
    /** @var list<Query> */
    private array $queries = [];

    /**
     * Constructs authoritative stores separately from returned target Views
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
        $this->role = Role::defineManaged(
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
        $this->actor->assignRole($this->role->getId(), $now);
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
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturnCallback(function (UserId $id): ?User {
            self::assertTrue($id->equals($this->actor->getId()), 'No target lookup before authorized dispatch.');
            ++$this->actorReads;

            if ($this->fault === 'unknown' || ($this->fault === 'race' && $this->actorReads === 2)) {
                return null;
            }

            return $this->actor;
        });
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturnCallback(fn() => $this->fault === 'session' ? null : $session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturnCallback(fn() => $this->fault === 'roles' ? [] : [$this->role]);
        $roles->method('getByName')->willReturn($this->role);
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByIds')->willReturnCallback(
            fn() => $this->fault === 'permission' ? [] : [$permission]
        );
        $permissions->method('getByName')->willReturnCallback(fn() => $this->fault === 'policy' ? null : $permission);
        $bus = $this->createStub(QueryBus::class);
        $bus->method('fetch')->willReturnCallback(function (Query $query): mixed {
            $this->queries[] = $query;
            if ($query instanceof GetUserById) {
                self::assertTrue($query->getUserId()->equals($this->target->getId()));
            }
            if ($query instanceof GetRoleById) {
                self::assertTrue($query->getRoleId()->equals($this->role->getId()));

                return match ($this->fault) {
                    'missing-role' => null,
                    'wrong-role' => 'private-result-marker',
                    'aliased-role' => RoleView::fromRole(Role::define(
                        RoleId::generate(),
                        RoleName::fromString('ROLE_OTHER'),
                        [],
                        $this->target->getCreatedAt()
                    )),
                    default => RoleView::fromRole($this->role)
                };
            }

            return $this->result;
        });
        $authentication = new AuthenticatedPermissions(new AuthoritativePrincipalResolver(
            $users,
            $sessions,
            $roles,
            $permissions,
            new AccessClock($now)
        ), new PermissionGuard());
        $installation = new InstallationAuthority($users, $roles, $permissions);
        $responses = new JSendResponseFactory(new ResponseFactory(), new StreamFactory());
        $this->detail = new GetUserAction($authentication, $installation, $bus, new UserDetailResponder($responses));
        $this->directory = new ListUsersAction(
            $authentication,
            $installation,
            $bus,
            new UserDirectoryResponder($responses)
        );
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturn($this->target->getId()->toString());
        $this->request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/users')
            ->withAttribute(AuthenticationContext::class, new AuthenticationContext(
                $this->actor->getId(),
                $session->getId(),
                1
            ))->withAttribute(QueryString::class, [])
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
    }

    /**
     * Rejects stale invocation authority without dispatching any user or role query
     */
    #[DataProvider('denials')]
    public function testNoDispatch(bool $detail, string $fault): void
    {
        $this->fault = $fault;
        try {
            ($detail ? $this->detail : $this->directory)->handle($this->request);
            self::fail('Unresolved authority allowed target dispatch.');
        } catch (PermissionDenied) {
            self::assertSame([], $this->queries);
        }
    }

    /**
     * Supplies both reads and independently stale authority boundaries
     *
     * @return iterable<array{bool, string}>
     */
    public static function denials(): iterable
    {
        foreach ([false, true] as $detail) {
            foreach (['unknown', 'session', 'roles', 'permission', 'policy', 'race'] as $fault) {
                yield [$detail, $fault];
            }
        }
    }

    /**
     * Preserves no assignments and returns only the documented no-store identity fields
     */
    public function testDetail(): void
    {
        $response = $this->detail->handle($this->request);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame(['user_id', 'email', 'state', 'roles', 'created_at', 'updated_at'], array_keys($data));
        self::assertSame('target@example.test', $data['email']);
        self::assertSame([], $data['roles']);
        self::assertCount(1, $this->queries);
    }

    /**
     * Maps an authorized disappeared target to not found
     */
    public function testMissingTarget(): void
    {
        $this->result = null;
        $this->expectException(ResourceNotFound::class);
        $this->detail->handle($this->request);
    }

    /**
     * Rejects wrong-target or malformed user results rather than disclosing them
     */
    #[DataProvider('invalidDetail')]
    public function testInvalidDetail(bool $wrongTarget): void
    {
        $this->result = $wrongTarget ? UserView::fromUser($this->actor) : 'private-query-marker';
        $this->expectException(LogicException::class);
        $this->detail->handle($this->request);
    }

    /**
     * Supplies invalid identity and result type
     *
     * @return iterable<array{bool}>
     */
    public static function invalidDetail(): iterable
    {
        yield [false];
        yield [true];
    }

    /**
     * Queries safe roles without serializing package permission memberships or revisions
     */
    #[DataProvider('reads')]
    public function testRoles(bool $detail): void
    {
        $this->target->assignRole($this->role->getId(), $this->target->getCreatedAt());
        $view = UserView::fromUser($this->target);
        $this->result = $detail ? $view : new ResultSet(1, 25, 1, ArrayList::of(UserView::class)->replace([$view]));
        $response = ($detail ? $this->detail : $this->directory)->handle($this->request);
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame([
            ['name' => 'ROLE_SUPER_ADMIN', 'managed' => true]
        ], ($detail ? $data : $data['records'][0])['roles']);
        self::assertCount(2, $this->queries);
    }

    /**
     * Supplies both transport reads
     *
     * @return iterable<array{bool}>
     */
    public static function reads(): iterable
    {
        yield [false];
        yield [true];
    }

    /**
     * Refuses incomplete, malformed or aliased role Views for either endpoint
     */
    #[DataProvider('roleFailures')]
    public function testRoleFailures(bool $detail, string $fault): void
    {
        $this->fault = $fault;
        $this->target->assignRole($this->role->getId(), $this->target->getCreatedAt());
        $view = UserView::fromUser($this->target);
        $this->result = $detail ? $view : new ResultSet(1, 25, 1, ArrayList::of(UserView::class)->replace([$view]));
        $this->expectException(LogicException::class);
        ($detail ? $this->detail : $this->directory)->handle($this->request);
    }

    /**
     * Supplies unsupported role query results
     *
     * @return iterable<array{bool, string}>
     */
    public static function roleFailures(): iterable
    {
        foreach ([false, true] as $detail) {
            foreach (['missing-role', 'wrong-role', 'aliased-role'] as $fault) {
                yield [$detail, $fault];
            }
        }
    }

    /**
     * Preserves bounded explicit pagination and a genuinely empty page
     */
    public function testDirectoryPagination(): void
    {
        $this->result = new ResultSet(2, 3, 0, ArrayList::of(UserView::class));
        $response = $this->directory->handle($this->request->withAttribute(QueryString::class, [
            'page' => '2', 'per_page' => '3'
        ]));
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame([
            'page' => 2, 'per_page' => 3, 'total_pages' => 1, 'total_records' => 0, 'records' => []
        ], $data);
        self::assertInstanceOf(ListUsers::class, $this->queries[0]);
        self::assertSame([], $this->queries[0]->getPagination()->orderings());
        self::assertSame(3, $this->queries[0]->getPagination()->offset());
        self::assertSame(3, $this->queries[0]->getPagination()->limit());
    }

    /**
     * Rejects unbounded pagination without dispatch
     */
    #[DataProvider('bounds')]
    public function testBounds(int $page, int $perPage): void
    {
        try {
            $this->directory->handle($this->request->withAttribute(QueryString::class, [
                'page' => (string) $page, 'per_page' => (string) $perPage
            ]));
            self::fail('Invalid pagination dispatched.');
        } catch (ValidationException) {
            self::assertSame([], $this->queries);
        }
    }

    /**
     * Supplies pagination limits
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
     * Refuses malformed or unbounded package pages
     */
    #[DataProvider('badPages')]
    public function testPackagePageFailure(string $fault): void
    {
        $this->result = match ($fault) {
            'type' => 'private-query-marker',
            'records' => new ResultSet(1, 25, 0, ArrayList::of('string')),
            'page' => new ResultSet(2, 25, 0, ArrayList::of(UserView::class)),
            'size' => new ResultSet(1, 100, 0, ArrayList::of(UserView::class)),
            default => new ResultSet(1, 25, 26, ArrayList::of(UserView::class)->replace(
                array_fill(0, 26, UserView::fromUser($this->target))
            ))
        };
        $this->expectException(LogicException::class);
        $this->directory->handle($this->request);
    }

    /**
     * Supplies invalid page categories
     *
     * @return iterable<array{string}>
     */
    public static function badPages(): iterable
    {
        foreach (['type', 'records', 'page', 'size', 'overflow'] as $fault) {
            yield [$fault];
        }
    }
}
