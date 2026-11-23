<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Users\AssignUserRoleAction;
use App\Adapter\Http\Action\Api\V1\Users\RemoveUserRoleAction;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use App\Application\Authorization\PermissionGuard;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Interfaces\RouteInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteContext;

/**
 * Protects pre-dispatch role target admission
 */
final class UserRoleActionsTest extends TestCase
{
    /**
     * Exercises both entries for every lifecycle state and self target
     *
     * @return iterable<array{bool, UserState, bool}>
     */
    public static function targets(): iterable
    {
        foreach ([false, true] as $remove) {
            foreach (UserState::cases() as $state) {
                yield [$remove, $state, false];
                yield [$remove, $state, true];
            }
        }
    }

    /**
     * Refuses inactive and self targets without dispatch and admits ordinary changes
     */
    #[DataProvider('targets')]
    public function testTargetAdmission(bool $remove, UserState $state, bool $self): void
    {
        $actor = UserId::generate();
        $target = $self ? $actor : UserId::generate();
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_ORDINARY'), [], new DateTimeImmutable());
        $this->exercise($remove, $state, $actor, $target, $role, !$self && $state === UserState::ACTIVE);
    }

    /**
     * Refuses managed Super Admin removal irrespective of application permissions
     */
    public function testSuperAdminRemoval(): void
    {
        $role = Role::defineManaged(
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [],
            new DateTimeImmutable()
        );
        $this->exercise(true, UserState::ACTIVE, UserId::generate(), UserId::generate(), $role, false);
    }

    /**
     * Drives a real Action with safe package Views and a dispatch observer
     */
    private function exercise(
        bool $remove,
        UserState $state,
        UserId $actor,
        UserId $target,
        Role $role,
        bool $allowed
    ): void {
        $now = new DateTimeImmutable();
        $user = new UserView($target, EmailAddress::fromString('target@example.test'), $state, [], $now, $now);
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(static fn(Query $query) =>
            $query instanceof GetRoleById ? RoleView::fromRole($role) : $user);
        $commands = $this->createMock(CommandBus::class);
        $commands->expects($allowed ? self::once() : self::never())->method('execute');
        $responder = new UserLifecycleResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));
        if ($remove) {
            $action = new RemoveUserRoleAction($queries, $commands, $responder);
        } else {
            $action = new AssignUserRoleAction($queries, $commands, new PermissionGuard(), $responder);
        }
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturnCallback(static fn(string $name) =>
            $name === 'userId' ? $target->toString() : $role->getId()->toString());
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/')
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(\Slim\Interfaces\RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(\Slim\Routing\RoutingResults::class))
            ->withAttribute(JsonBody::class, ['confirm' => true])
            ->withAttribute(
                AuthenticationContext::class,
                new AuthenticationContext($actor, RefreshSessionId::generate(), 1)
            );
        if (!$allowed) {
            $this->expectException(StateConflict::class);
        }
        $response = $action->handle($request);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => null], json_decode((string) $response->getBody(), true));
    }
}
