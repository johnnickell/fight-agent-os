<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Middleware\Api\Validation\ApiInputValidation;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;

/**
 * Class ApiInputValidationTest
 *
 * Exercises matched-route rejection without dispatching an Action
 */
final class ApiInputValidationTest extends TestCase
{
    /**
     * Rejects a body on a no-body route before calling the next handler
     */
    public function test_that_bootstrap_body_is_rejected_before_action(): void
    {
        $request = $this->request()->withBody((new StreamFactory())->createStream('not-json'));
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException(new \LogicException('Invalid input reached the Action.'));

        $response = $this->validation()->process($request, $next);

        self::assertSame(
            ['status' => 'fail', 'data' => ['fields' => ['body' => ['Body is not allowed.']]]],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Rejects duplicate named cookies without passing credentials to the Action
     */
    public function test_that_duplicate_cookie_is_rejected_before_action(): void
    {
        $request = $this->request()->withHeader('Cookie', '__Secure-agent_os_csrf=x; __Secure-agent_os_csrf=y');
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException(new \LogicException('Invalid input reached the Action.'));

        $response = $this->validation()->process($request, $next);

        self::assertSame(
            ['status' => 'fail', 'data' => ['fields' => ['cookie' => ['Ambiguous cookie.']]]],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Lists disallowed cookie headers
     *
     * @return iterable<string, array{string|string[], string}>
     */
    public static function invalid_cookies(): iterable
    {
        yield 'multiple header lines' => [['a=b', 'c=d'], 'Invalid cookie.'];
        yield 'oversized header' => [str_repeat('a', 4097), 'Invalid cookie.'];
        yield 'missing nonce value' => ['__Secure-agent_os_csrf', 'Ambiguous cookie.'];
    }

    /**
     * Rejects malformed or oversized cookie input without echoing it
     *
     * @param string|string[] $cookie
     */
    #[DataProvider('invalid_cookies')]
    public function test_that_invalid_cookie_is_rejected(array|string $cookie, string $message): void
    {
        $request = $this->request()->withHeader('Cookie', $cookie);
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException(new \LogicException('Invalid input reached the Action.'));

        $response = $this->validation()->process($request, $next);

        self::assertSame(
            ['status' => 'fail', 'data' => ['fields' => ['cookie' => [$message]]]],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Forwards an allowed bootstrap request to the selected Action boundary
     */
    public function test_that_empty_bootstrap_reaches_next_handler(): void
    {
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willReturn((new ResponseFactory())->createResponse(204));

        $response = $this->validation()->process($this->request(), $next);

        self::assertSame(204, $response->getStatusCode());
    }

    /**
     * Supplies route metadata exactly as Slim does after routing
     */
    private function request(): ServerRequestInterface
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getCallable')->willReturn(CsrfBootstrapAction::class);

        return (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
    }

    /**
     * Creates the API boundary with concrete response factories
     */
    private function validation(): ApiInputValidation
    {
        return new ApiInputValidation(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));
    }
}
