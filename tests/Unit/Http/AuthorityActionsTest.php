<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Authorization\InstallationPermissionAdmission;
use App\Adapter\Http\Action\Api\V1\Authority\CreateRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\GetPermissionAction;
use App\Adapter\Http\Action\Api\V1\Authority\GetRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListPermissionsAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListRolesAction;
use App\Adapter\Http\Action\Api\V1\Authority\RemoveRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\RenameRoleAction;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Responder\Api\V1\Authority\CatalogResponder;
use App\Adapter\Http\Responder\Api\V1\Authority\RoleMutationResponder;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\GetPermissionById;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\ListPermissions;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\Command\CreateCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Command\RemoveCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Command\RenameCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\ListRoles;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRevision;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Collection\ArrayList;
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

/**
 * Protects authority HTTP translation after central authentication
 */
final class AuthorityActionsTest extends TestCase
{
    private User $actor;
    private RoleId $roleId;
    private RoleRevision $revision;
    private JSendResponseFactory $responses;
    private DateTimeImmutable $at;

    /**
     * Supplies exact actor, target and revision for HTTP admission
     */
    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-20T12:00:00Z');
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('admin@example.test'), $this->at);
        $this->actor->activate(PasswordHash::fromString(password_hash('fixture', PASSWORD_BCRYPT)), $this->at);
        $this->roleId = RoleId::generate();
        $this->revision = RoleRevision::initial();
        $this->responses = new JSendResponseFactory(new ResponseFactory(), new StreamFactory());
    }

    /**
     * Distinguishes a lost authoritative read after a successful mutation from ordinary missing input
     */
    public function testRenameNeedsReconciliationAfterSuccessfulDispatch(): void
    {
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::once())->method('execute')->with(new RenameCustomRole(
            $this->actor->getId(),
            $this->roleId,
            RoleName::fromString('ROLE_RENAMED'),
            $this->revision
        ));
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('fetch')->with(new GetRoleById($this->roleId))
            ->willThrowException(new ResourceNotFound());
        $action = new RenameRoleAction(
            $commands,
            $queries,
            $this->admission('UPDATE_ROLES'),
            new RoleMutationResponder($this->responses)
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The Role mutation outcome requires reconciliation.');
        $action->handle($this->request($this->roleId->toString()));
    }

    /**
     * Returns the authoritative read rather than synthesizing a response from the request
     */
    public function testRenameReturnsCurrentRole(): void
    {
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturn(RoleView::fromRole(Role::define(
            $this->roleId,
            RoleName::fromString('ROLE_CURRENT'),
            [],
            $this->at
        )));
        $action = new RenameRoleAction(
            $this->createStub(CommandBus::class),
            $queries,
            $this->admission('UPDATE_ROLES'),
            new RoleMutationResponder($this->responses)
        );

        $response = $action->handle($this->request($this->roleId->toString()));
        self::assertStringContainsString('ROLE_CURRENT', (string) $response->getBody());
    }

    /**
     * Returns the same identity sent to the void creation command
     */
    public function testCreateReturnsDispatchedIdentity(): void
    {
        $commands = $this->createMock(CommandBus::class);
        $createdId = null;
        $commands->expects(self::once())->method('execute')->willReturnCallback(
            static function (CreateCustomRole $command) use (&$createdId): void {
                $createdId = $command->getRoleId()->toString();
            }
        );
        $action = new CreateRoleAction($commands, $this->admission('CREATE_ROLES'), new RoleMutationResponder(
            $this->responses
        ));

        $response = $action->handle($this->request(''));
        self::assertSame(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['role_id' => $createdId], $body['data']);
    }

    /**
     * Dispatches removal only with confirmation and the requested revision
     */
    #[DataProvider('flags')]
    public function testRemoveRequiresConfirmedRevision(bool $confirmed): void
    {
        $commands = $this->createMock(CommandBus::class);
        if ($confirmed) {
            $commands->expects(self::once())->method('execute')->with(new RemoveCustomRole(
                $this->actor->getId(),
                $this->roleId,
                $this->revision
            ));
        } else {
            $commands->expects(self::never())->method('execute');
            $this->expectException(ValidationException::class);
        }
        $action = new RemoveRoleAction($commands, $this->admission('DELETE_ROLES'), new RoleMutationResponder(
            $this->responses
        ));

        $response = $action->handle($this->request($this->roleId->toString(), $confirmed));
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * Preserves explicit page intent for each catalog
     */
    #[DataProvider('flags')]
    public function testCatalogPage(bool $roles): void
    {
        $queries = $this->createMock(QueryBus::class);
        $viewClass = $roles ? RoleView::class : PermissionView::class;
        $queries->expects(self::once())->method('fetch')->willReturnCallback(
            static function (ListRoles|ListPermissions $query) use ($roles, $viewClass): ResultSet {
                self::assertInstanceOf($roles ? ListRoles::class : ListPermissions::class, $query);
                self::assertSame(3, $query->getPagination()->offset());
                self::assertSame(3, $query->getPagination()->limit());

                return new ResultSet(2, 3, 0, ArrayList::of($viewClass));
            }
        );
        $responder = new CatalogResponder($this->responses);
        $admission = $this->admission($roles ? 'READ_ROLES' : 'READ_PERMISSIONS');
        $action = $roles ? new ListRolesAction($queries, $admission, $responder) : new ListPermissionsAction(
            $queries,
            $admission,
            $responder
        );

        $response = $action->handle($this->request('')->withAttribute(QueryString::class, [
            'page' => '2', 'per_page' => '3'
        ]));
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $body['data']['records']);
        self::assertSame(2, $body['data']['page']);
    }

    /**
     * Rejects malformed IDs before dispatch and projects a valid detail View safely
     */
    #[DataProvider('details')]
    public function testCatalogDetail(bool $roles, bool $valid): void
    {
        $queries = $this->createMock(QueryBus::class);
        $responder = new CatalogResponder($this->responses);
        $permission = $this->permission('READ_PERMISSIONS');
        $id = $roles ? $this->roleId : $permission->getId();
        $admission = $this->admission($roles ? 'READ_ROLES' : 'READ_PERMISSIONS');
        $action = $roles ? new GetRoleAction($queries, $admission, $responder) : new GetPermissionAction(
            $queries,
            $admission,
            $responder
        );
        if ($valid) {
            $queries->expects(self::once())->method('fetch')->with(
                $roles ? new GetRoleById($this->roleId) : new GetPermissionById($permission->getId())
            )->willReturn($roles ? RoleView::fromRole(Role::define(
                $this->roleId,
                RoleName::fromString('ROLE_SELECTED'),
                [],
                $this->at
            )) : PermissionView::fromPermission($permission));
        } else {
            $queries->expects(self::never())->method('fetch');
            $this->expectException(ValidationException::class);
        }

        $response = $action->handle($this->request($valid ? $id->toString() : 'not-an-id'));
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($id->toString(), $body['data'][$roles ? 'role_id' : 'permission_id']);
    }

    /**
     * Refuses a catalog read without the verified authentication context
     */
    public function testCatalogWithoutAuthenticationDoesNotDispatch(): void
    {
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('fetch');
        $action = new ListRolesAction($queries, $this->admission('READ_ROLES'), new CatalogResponder(
            $this->responses
        ));

        $this->expectException(AuthenticationRequired::class);
        $action->handle($this->request('')->withoutAttribute(AuthenticationContext::class));
    }

    /**
     * Supplies both options
     *
     * @return iterable<array{bool}>
     */
    public static function flags(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * Supplies valid and invalid identifiers for both catalog resources
     *
     * @return iterable<array{bool, bool}>
     */
    public static function details(): iterable
    {
        foreach ([true, false] as $roles) {
            yield [$roles, true];
            yield [$roles, false];
        }
    }

    /**
     * Provides an authenticated request with the route inputs already parsed
     */
    private function request(string $id, bool $confirmed = true): ServerRequestInterface
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturnMap([['roleId', null, $id], ['permissionId', null, $id]]);

        return (new ServerRequestFactory())->createServerRequest('GET', '/')->withAttribute(
            AuthenticationContext::class,
            new AuthenticationContext($this->actor->getId(), RefreshSessionId::generate(), 1)
        )->withAttribute(JsonBody::class, [
            'name' => 'ROLE_RENAMED', 'expected_revision' => $this->revision->toString(), 'confirm' => $confirmed
        ])->withAttribute(QueryString::class, [])->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
    }

    /**
     * Grants just the exact installation permission needed by the Action
     */
    private function admission(string $name): InstallationPermissionAdmission
    {
        $permission = $this->permission($name);
        $role = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString(Role::SUPER_ADMIN_NAME),
            [$permission->getId()],
            $this->at
        );
        $this->actor->assignRole($role->getId(), $this->at);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByName')->willReturn($role);
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($this->actor);
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByName')->willReturnCallback(
            static fn(PermissionName $requested): ?Permission => $requested->toString() === $name ? $permission : null
        );

        return new InstallationPermissionAdmission($users, $roles, $permissions);
    }

    /**
     * Defines a managed and protected Permission
     */
    private function permission(string $name): Permission
    {
        return Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString($name),
            PermissionTier::SUPER_ADMIN_ONLY,
            $this->at
        );
    }
}
