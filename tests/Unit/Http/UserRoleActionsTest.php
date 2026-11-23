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
     * Enumerates unauthenticated, unconfirmed and malformed route admission
     *
     * @return iterable<array{bool, string, class-string<\Throwable>}>
     */
    public static function invalidRequests(): iterable
    {
        foreach ([false, true] as $remove) {
            yield [$remove, 'anonymous', \Fight\Common\Application\Error\Exception\AuthenticationRequired::class];
            yield [$remove, 'confirmation', \Fight\Common\Application\Validation\Exception\ValidationException::class];
            yield [$remove, 'userId', \Fight\Common\Application\Validation\Exception\ValidationException::class];
            yield [$remove, 'roleId', \Fight\Common\Application\Validation\Exception\ValidationException::class];
        }
    }

    /**
     * Refuses invalid entry requests before any command dispatch
     *
     * @phpstan-param class-string<\Throwable> $exception
     */
    #[DataProvider('invalidRequests')]
    public function testInvalidRequest(bool $remove, string $invalid, string $exception): void
    {
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_ORDINARY'), [], new DateTimeImmutable());
        $this->exercise(
            $remove,
            UserState::ACTIVE,
            UserId::generate(),
            UserId::generate(),
            $role,
            false,
            $invalid,
            $exception
        );
    }

    /**
     * Enumerates absent or mismatched authoritative identity projections
     *
     * @return iterable<array{bool, string}>
     */
    public static function invalidIdentities(): iterable
    {
        foreach ([false, true] as $remove) {
            foreach (['missing_user', 'wrong_user', 'missing_role', 'wrong_role'] as $invalid) {
                yield [$remove, $invalid];
            }
        }
    }

    /**
     * Refuses absent or aliased authoritative identities without dispatch
     */
    #[DataProvider('invalidIdentities')]
    public function testInvalidIdentity(bool $remove, string $invalid): void
    {
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_ORDINARY'), [], new DateTimeImmutable());
        $this->exercise(
            $remove,
            UserState::ACTIVE,
            UserId::generate(),
            UserId::generate(),
            $role,
            false,
            $invalid
        );
    }

    /**
     * Refuses elevation without authoritative security context despite claimed permission input
     */
    public function testElevationRequiresSecurityContext(): void
    {
        $role = Role::defineManaged(
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [],
            new DateTimeImmutable()
        );
        $this->exercise(
            false,
            UserState::ACTIVE,
            UserId::generate(),
            UserId::generate(),
            $role,
            false,
            '',
            \Fight\Common\Application\Error\Exception\AuthenticationRequired::class
        );
    }

    /**
     * Enumerates the independent elevation permission and confirmation decisions
     *
     * @return iterable<array{bool, bool}>
     */
    public static function elevations(): iterable
    {
        yield [false, false];
        yield [false, true];
        yield [true, false];
        yield [true, true];
    }

    /**
     * Requires both authoritative elevation permission and distinct explicit confirmation
     */
    #[DataProvider('elevations')]
    public function testElevation(bool $permission, bool $confirmation): void
    {
        $exception = \Fight\Common\Application\Error\Exception\PermissionDenied::class;
        if ($permission) {
            $exception = \Fight\Common\Application\Validation\Exception\ValidationException::class;
        }
        $role = Role::defineManaged(
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [],
            new DateTimeImmutable()
        );
        $this->exercise(
            false,
            UserState::ACTIVE,
            UserId::generate(),
            UserId::generate(),
            $role,
            $permission && $confirmation,
            '',
            $exception,
            $permission,
            $confirmation
        );
    }

    /**
     * Drives a real Action with safe package Views and a dispatch observer
     *
     * @phpstan-param class-string<\Throwable> $exception
     */
    private function exercise(
        bool $remove,
        UserState $state,
        UserId $actor,
        UserId $target,
        Role $role,
        bool $allowed,
        string $invalid = '',
        string $exception = StateConflict::class,
        ?bool $elevationPermission = null,
        bool $elevationConfirmation = true
    ): void {
        $now = new DateTimeImmutable();
        $resolvedTarget = $invalid === 'wrong_user' ? UserId::generate() : $target;
        $user = new UserView($resolvedTarget, EmailAddress::fromString('target@example.test'), $state, [], $now, $now);
        $resolvedRole = $role;
        if ($invalid === 'wrong_role') {
            $resolvedRole = Role::define(
                RoleId::generate(),
                RoleName::fromString('ROLE_OTHER'),
                [],
                $now
            );
        }
        $userView = $invalid === 'missing_user' ? null : $user;
        $roleView = $invalid === 'missing_role' ? null : RoleView::fromRole($resolvedRole);
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(static fn(Query $query) =>
            $query instanceof GetRoleById ? $roleView : $userView);
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
            $name === $invalid ? 'invalid' : ($name === 'userId' ? $target->toString() : $role->getId()->toString()));
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/')
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(\Slim\Interfaces\RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(\Slim\Routing\RoutingResults::class))
            ->withAttribute(JsonBody::class, [
                'confirm'             => $invalid !== 'confirmation',
                'actor_id'            => UserId::generate()->toString(),
                'permissions'         => ['ASSIGN_SUPER_ADMIN'],
                'confirm_super_admin' => $elevationConfirmation
            ])
            ->withAttribute(
                AuthenticationContext::class,
                new AuthenticationContext($actor, RefreshSessionId::generate(), 1)
            );
        if ($elevationPermission !== null) {
            $authority = $this->createStub(
                \Fight\AccessControl\Domain\AccessControl\Authorization\AuthenticatedAuthority::class
            );
            $authority->method('hasPermission')->willReturn($elevationPermission);
            $request = $request->withAttribute(
                \Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext::class,
                new \Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext($authority)
            );
        }
        if ($invalid === 'anonymous') {
            $request = $request->withoutAttribute(AuthenticationContext::class);
        }
        if (!$allowed) {
            $this->expectException($exception);
        }
        $response = $action->handle($request);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => null], json_decode((string) $response->getBody(), true));
    }
}
