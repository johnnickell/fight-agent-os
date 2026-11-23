<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Users\DeleteUserAction;
use App\Adapter\Http\Action\Api\V1\Users\DisableUserAction;
use App\Adapter\Http\Action\Api\V1\Users\EnableUserAction;
use App\Adapter\Http\Action\Api\V1\Users\RestoreUserAction;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use App\Application\Authorization\PreventAdministratorLockout;
use App\Domain\Authorization\EffectiveAdministrators;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;

/**
 * Preserves the generic lifecycle conflict only for the supplier's known missing target refusal
 */
final class UserLifecycleRefusalTest extends TestCase
{
    /**
     * Distinguishes the package's absent-target refusal from unrelated absence after admission
     */
    #[DataProvider('refusals')]
    public function testLifecycle(string $operation, ?string $message, bool $known): void
    {
        $target = UserId::generate();
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturn($target->toString());
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/users')
            ->withAttribute(AuthenticationContext::class, new AuthenticationContext(
                UserId::generate(),
                RefreshSessionId::generate(),
                1
            ))
            ->withAttribute(JsonBody::class, ['confirm' => true, 'restoration_state' => 'active'])
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
        $commands = $this->createMock(CommandBus::class);
        $execute = $commands->expects(self::once())->method('execute');
        $failure = $message === null ? null : new ResourceNotFound($message);
        if ($failure !== null) {
            $execute->willThrowException($failure);
        }
        $lockout = new PreventAdministratorLockout($this->createStub(EffectiveAdministrators::class));
        $responder = new UserLifecycleResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));
        $action = match ($operation) {
            'delete' => new DeleteUserAction($commands, $responder),
            'disable' => new DisableUserAction($lockout, $commands, $responder),
            'enable' => new EnableUserAction($lockout, $commands, $responder),
            'restore' => new RestoreUserAction($commands, $responder),
            default => throw new \LogicException('Unknown operation.')
        };

        if ($failure !== null) {
            $this->expectExceptionObject($known ? new StateConflict() : $failure);
        }
        $response = $action->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertJsonStringEqualsJsonString('{"status":"success","data":null}', (string) $response->getBody());
    }

    /**
     * Refuses self-deletion without dispatching a lifecycle command
     */
    public function testDeleteRefusesCurrentUser(): void
    {
        $actor = UserId::generate();
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturn($actor->toString());
        $request = new ServerRequestFactory()->createServerRequest('DELETE', '/api/v1/users/'.$actor->toString())
            ->withAttribute(
                AuthenticationContext::class,
                new AuthenticationContext($actor, RefreshSessionId::generate(), 1)
            )
            ->withAttribute(JsonBody::class, ['confirm' => true])
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $responder = new UserLifecycleResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));

        $this->expectException(StateConflict::class);
        new DeleteUserAction($commands, $responder)->handle($request);
    }

    /**
     * Supplies successful changes, exact supplier refusals and unrelated absence
     *
     * @return iterable<string, array{string, ?string, bool}>
     */
    public static function refusals(): iterable
    {
        foreach (['delete', 'disable', 'enable', 'restore'] as $operation) {
            yield "$operation succeeds" => [$operation, null, false];
            yield "$operation missing target" => [$operation, "The user cannot be {$operation}d.", true];
            yield "$operation unrelated absence" => [$operation, 'Another resource was not found.', false];
        }
    }
}
