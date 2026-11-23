<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Auth\LoginAction;
use App\Adapter\Http\Action\Api\V1\Users\GetUserAction;
use App\Adapter\Http\Api\Auth\BearerCredential;
use App\Adapter\Http\Middleware\Api\Auth\ApiActionSecurity;
use App\Adapter\Security\AccessToken\AuthenticateAccessToken;
use App\Application\Authorization\PermissionGuard;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
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
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Tests\Support\AccessTokens;

/**
 * Exercises protected Action admission without an HTTP server or a database
 */
final class ApiActionSecurityTest extends TestCase
{
    /**
     * A public action does not resolve current account authority or rewrite the request
     */
    public function testPublicActionDoesNotRequireAuthority(): void
    {
        [$middleware] = $this->fixture('public');
        $request = $this->request(LoginAction::class);
        $result = new ResponseFactory()->createResponse(204);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($result);
        self::assertSame($result, $middleware->process($request, $handler));
    }

    /**
     * Requires declared current permission and attaches one request-local principal to authorized calls
     *
     * @param string                        $case
     * @param string|null                   $error
     *
     * @phpstan-param class-string<\Throwable>|null $error
     */
    #[DataProvider('admissionCases')]
    public function testProtectedAdmission(string $case, ?string $error): void
    {
        [$middleware, $bearer, $actor] = $this->fixture($case);
        $request = $this->request(GetUserAction::class);
        if ($case !== 'missing bearer') {
            $request = $request->withHeader('Authorization', 'Bearer '.$bearer);
        }
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = new ResponseFactory()->createResponse(204);
        if ($error !== null) {
            $handler->expects(self::never())->method('handle');
            $this->expectException($error);
        } else {
            $handler->expects(self::once())->method('handle')->with(self::callback(
                static function (ServerRequestInterface $forwarded) use ($actor): bool {
                    $authentication = $forwarded->getAttribute(AuthenticationContext::class);
                    $context = $forwarded->getAttribute(SecurityContext::class);
                    $provider = $forwarded->getAttribute(CurrentPrincipalProvider::class);

                    return $authentication instanceof AuthenticationContext
                        && $authentication->getUserId()->equals($actor)
                        && $provider instanceof CurrentPrincipalProvider
                        && $context instanceof SecurityContext
                        && $context->hasPermission(PermissionName::fromString('READ_USERS'));
                }
            ))->willReturn($response);
        }
        self::assertSame($response, $middleware->process($request, $handler));
    }

    /**
     * Supplies distinct authority failure classes and a permitted request
     *
     * @return iterable<string, array{string, ?class-string<\Throwable>}>
     */
    public static function admissionCases(): iterable
    {
        yield 'permission unavailable' => ['undeclared', LogicException::class];
        yield 'credential required' => ['missing bearer', AuthenticationRequired::class];
        yield 'permission denied' => ['denied', PermissionDenied::class];
        yield 'authorized' => ['allowed', null];
    }

    /**
     * Builds real token and principal resolution over isolated authoritative facts
     *
     * @return array{ApiActionSecurity, string, UserId}
     */
    private function fixture(string $case): array
    {
        $tokens = new AccessTokens();
        $now = $tokens->clock->now();
        $user = User::invite(UserId::generate(), EmailAddress::fromString('owner@example.test'), $now);
        $user->activate(PasswordHash::fromString(password_hash('test-only', PASSWORD_BCRYPT, ['cost' => 4])), $now);
        $permission = Permission::define(PermissionId::generate(), PermissionName::fromString('READ_USERS'), $now);
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_READER'), [$permission->getId()], $now);
        if ($case === 'allowed') {
            $user->assignRole($role->getId(), $now);
        }
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 hour'),
            $now->modify('+2 hours'),
            $user->getAuthenticationVersion(),
            false
        );
        $claims = [
            'sub'          => $user->getId()->toString(),
            'sid'          => $session->getId()->toString(),
            'type'         => 'access',
            'auth_version' => $user->getAuthenticationVersion(),
            'iat'          => $now->getTimestamp()
        ];
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($session);
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->expects($case === 'public' ? self::never() : self::once())->method('getByName')
            ->with(PermissionName::fromString('READ_USERS'))->willReturn($case === 'undeclared' ? null : $permission);
        $permissions->method('getByIds')->willReturn($case === 'allowed' ? [$permission] : []);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturn($case === 'allowed' ? [$role] : []);

        return [
            new ApiActionSecurity(
                new BearerCredential(),
                new AuthenticateAccessToken($tokens->jwt, $users, $sessions, $tokens->clock),
                new AuthoritativePrincipalResolver($users, $sessions, $roles, $permissions, $tokens->clock),
                $permissions,
                new PermissionGuard()
            ),
            $tokens->jwt->encode($claims, $now->modify('+900 seconds')),
            $user->getId()
        ];
    }

    /**
     * Creates a routed request without resolving or constructing the action
     */
    private function request(string $action): ServerRequestInterface
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getCallable')->willReturn($action.':handle');

        return new ServerRequestFactory()->createServerRequest('GET', '/api/v1/users')
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
    }
}
