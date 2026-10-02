<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Middleware\Api\Validation\ApiInputValidation;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
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
        $next->method('handle')->willThrowException(new LogicException('Invalid input reached the Action.'));

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
        $next->method('handle')->willThrowException(new LogicException('Invalid input reached the Action.'));

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
        $next->method('handle')->willThrowException(new LogicException('Invalid input reached the Action.'));

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
     * Passes only checked JSON values to the Action boundary
     */
    public function test_that_declared_json_values_are_checked_without_hydration(): void
    {
        $request = $this->request(JsonInput::class, 'POST')->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream('{"page":2,"enabled":false}'));
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->willReturnCallback(static function (
            ServerRequestInterface $request
        ) {
            self::assertSame(['page' => 2, 'enabled' => false], $request->getAttribute(JsonBody::class));

            return (new ResponseFactory())->createResponse(204);
        });
        self::assertSame(204, $this->validation()->process($request, $next)->getStatusCode());
    }

    /**
     * Preserves string values, including false, in the checked GET map
     */
    public function test_that_query_values_remain_strings(): void
    {
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->willReturnCallback(static function (
            ServerRequestInterface $request
        ) {
            self::assertSame(['page' => '2', 'enabled' => 'false'], $request->getAttribute(QueryString::class));

            return (new ResponseFactory())->createResponse(204);
        });
        $request = $this->request(QueryInput::class)->withUri(
            $this->request()->getUri()->withQuery('page=2&enabled=false')
        );
        self::assertSame(204, $this->validation()->process($request, $next)->getStatusCode());
    }

    /**
     * Leaves absent optional fields absent rather than inserting defaults
     */
    public function test_that_optional_query_field_is_not_synthesized(): void
    {
        $request = $this->request(QueryInput::class)->withUri(
            $this->request()->getUri()->withQuery('page=1')
        );
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->willReturnCallback(static function (
            ServerRequestInterface $request
        ) {
            self::assertSame(['page' => '1'], $request->getAttribute(QueryString::class));

            return (new ResponseFactory())->createResponse(204);
        });
        self::assertSame(204, $this->validation()->process($request, $next)->getStatusCode());
    }

    /**
     * Rejects ambiguous input and package-rule failures before dispatch
     *
     * @param class-string $action
     */
    #[DataProvider('invalid_inputs')]
    public function test_that_invalid_declared_input_cannot_dispatch(
        string $action,
        string $method,
        string $query,
        string $body,
        string $path
    ): void {
        $request = $this->request($action, $method);
        if ($query === 'page=%GG') {
            $uri = $this->createStub(UriInterface::class);
            $uri->method('getQuery')->willReturn($query);
            $request = $request->withUri($uri);
        } else {
            $request = $request->withUri($request->getUri()->withQuery($query));
        }
        if ($body !== '') {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream($body));
        }
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $response = $this->validation()->process($request, $next);
        self::assertSame(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey($path, $data['data']['fields']);
        self::assertStringNotContainsString('secret', (string) $response->getBody());
    }

    /**
     * Supplies independently selected source and error cases
     *
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function invalid_inputs(): iterable
    {
        yield 'unknown JSON' => [JsonInput::class, 'POST', '', '{"secret":"secret"}', 'body'];
        yield 'duplicate JSON' => [JsonInput::class, 'POST', '', '{"page":1,"page":2}', 'body'];
        yield 'wrong JSON type' => [JsonInput::class, 'POST', '', '{"page":"2"}', 'body.page'];
        yield 'missing JSON' => [JsonInput::class, 'POST', '', '{}', 'body.page'];
        yield 'null JSON' => [JsonInput::class, 'POST', '', '{"page":null}', 'body.page'];
        yield 'range JSON' => [JsonInput::class, 'POST', '', '{"page":0}', 'body.page'];
        yield 'container JSON' => [JsonInput::class, 'POST', '', '{"page":[1]}', 'body.page'];
        yield 'wrong source' => [JsonInput::class, 'POST', 'page=1', '{"page":1}', 'query'];
        yield 'duplicate query' => [QueryInput::class, 'GET', 'page=1&%70age=2', '', 'query'];
        yield 'encoded bracket' => [QueryInput::class, 'GET', 'page%5B0%5D=1', '', 'query'];
        yield 'invalid encoding' => [QueryInput::class, 'GET', 'page=%GG', '', 'query'];
        yield 'unknown query' => [QueryInput::class, 'GET', 'secret=secret', '', 'query'];
        yield 'invalid boolean' => [QueryInput::class, 'GET', 'page=1&enabled=truthy', '', 'query.enabled'];
        yield 'empty query value' => [QueryInput::class, 'GET', 'page=', '', 'query.page'];
        yield 'missing query field' => [QueryInput::class, 'GET', 'enabled=false', '', 'query.page'];
        yield 'not a number' => [QueryInput::class, 'GET', 'page=false', '', 'query.page'];
        yield 'encoded collision' => [QueryInput::class, 'GET', 'page=1&pa%67e=2', '', 'query'];
        yield 'raw bracket' => [QueryInput::class, 'GET', 'page[]=1', '', 'query'];
        yield 'range query' => [QueryInput::class, 'GET', 'page=0', '', 'query.page'];
        yield 'body on query' => [QueryInput::class, 'GET', 'page=1', '{}', 'body'];
        yield 'oversized query' => [QueryInput::class, 'GET', 'page='.str_repeat('1', 4096), '', 'query'];
        yield 'too many query fields' => [
            QueryInput::class, 'GET', implode('&', array_fill(0, 33, 'page=1')), '', 'query'
        ];
        yield 'too large JSON' => [
            JsonInput::class, 'POST', '', '{"page":1,"enabled":"'.str_repeat('x', 65536).'"}', 'body'
        ];
    }

    /**
     * Checks original HTTP request-target encoding before PSR URI normalization loses it
     */
    public function test_that_malformed_original_query_cannot_dispatch(): void
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getCallable')->willReturn(QueryInput::class.':handle');
        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'https://agent-os.test/api/v1/auth/csrf?page=%GG',
            ['REQUEST_URI' => '/api/v1/auth/csrf?page=%GG']
        )->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $response = $this->validation()->process($request, $next);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            ['query' => ['Invalid query.']],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['fields']
        );
    }

    /**
     * Publishes only explicitly selected PHP rule failures on a named Action
     */
    public function test_that_named_input_rejection_exposes_public_messages_and_sanitizes_private_rules(): void
    {
        $registrations = [[
            'name' => 'sample_form',
            'action' => PublishedInput::class,
            'fields' => [
                'display_name' => [
                    'client_field' => 'displayName',
                    'rules' => [['index' => 0, 'message' => 'Use two characters.']]
                ]
            ]
        ]];
        $validation = new ApiInputValidation(
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory()),
            $registrations
        );
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $request = $this->request(PublishedInput::class, 'POST')->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream('{"display_name":"x"}'));
        $response = $validation->process($request, $next);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            ['body.display_name' => ['Use two characters.', 'Invalid value.']],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['fields']
        );
        self::assertStringNotContainsString('Private rule secret.', (string) $response->getBody());

        $request = $request->withBody((new StreamFactory())->createStream('{"display_name":"Z"}'));
        self::assertSame(
            ['body.display_name' => ['Use two characters.']],
            json_decode((string) $validation->process($request, $next)->getBody(), true, 512, JSON_THROW_ON_ERROR)
                ['data']['fields']
        );
    }

    /**
     * Fails closed on malformed declarations instead of accepting user input
     *
     * @param class-string $action
     */
    #[DataProvider('invalid_declarations')]
    public function test_that_invalid_declaration_is_a_server_failure(string $action, string $method): void
    {
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(LogicException::class);
        $this->validation()->process($this->request($action, $method), $next);
    }

    /**
     * Lists source and rule configuration failures
     *
     * @return iterable<string, array{class-string, string}>
     */
    public static function invalid_declarations(): iterable
    {
        yield 'missing rules' => [MissingValidation::class, 'POST'];
        yield 'conflicting sources' => [AmbiguousInput::class, 'POST'];
        yield 'unsupported rule' => [InvalidRulesInput::class, 'GET'];
        yield 'wrong method for query' => [QueryInput::class, 'POST'];
        yield 'wrong method for JSON' => [JsonInput::class, 'GET'];
    }

    /**
     * Rejects unsupported route callables before dispatch
     *
     * @param mixed $callable
     */
    #[DataProvider('unsupported_callables')]
    public function test_that_unsupported_route_callable_cannot_bypass_validation(mixed $callable): void
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getCallable')->willReturn($callable);
        $request = $this->request()->withAttribute(RouteContext::ROUTE, $route);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');

        $this->expectException(LogicException::class);
        $this->validation()->process($request, $next);
    }

    /**
     * Lists route forms that cannot identify the declared Action method
     *
     * @return iterable<string, array{mixed}>
     */
    public static function unsupported_callables(): iterable
    {
        yield 'implicit invocation' => [JsonInput::class];
        yield 'wrong method' => [JsonInput::class.':__invoke'];
        yield 'missing handle' => [self::class.':handle'];
        yield 'array callable' => [[JsonInput::class, 'handle']];
    }

    /**
     * Supplies route metadata exactly as Slim does after routing
     *
     * @param class-string $action
     */
    private function request(
        string $action = CsrfBootstrapAction::class,
        string $method = 'GET'
    ): ServerRequestInterface {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getCallable')->willReturn($action.':handle');

        return (new ServerRequestFactory())->createServerRequest($method, 'https://agent-os.test/api/v1/auth/csrf')
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
