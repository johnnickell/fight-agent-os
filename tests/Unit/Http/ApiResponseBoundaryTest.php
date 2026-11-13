<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Auth\SessionResponseFailure;
use App\Adapter\Http\Middleware\Api\ApiResponseBoundary;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Protects API transport scope and invocation-local cookie obligations in isolation
 */
final class ApiResponseBoundaryTest extends TestCase
{
    /**
     * Supplies exact API scope and accepted or rejected correlation headers
     *
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function correlationCases(): iterable
    {
        yield 'API root accepted' => ['/api', [str_repeat('a', 32)], true];
        yield 'API child missing' => ['/api/v1/control', [], false];
        yield 'uppercase rejected' => ['/api/v1/control', [str_repeat('A', 32)], false];
        yield 'ambiguous rejected' => ['/api/v1/control', [str_repeat('a', 32), str_repeat('b', 32)], false];
    }

    /**
     * Correlates downstream requests and responses without changing successful payloads or cookies
     *
     * @param string $path
     * @param array $inbound
     * @param boolean $accepted
     *
     * @phpstan-param list<string> $inbound
     */
    #[DataProvider('correlationCases')]
    public function testApiCorrelation(string $path, array $inbound, bool $accepted): void
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', $path);
        if ($inbound !== []) {
            $request = $request->withHeader('X-Correlation-ID', $inbound);
        }
        $response = new ResponseFactory()->createResponse(202)
            ->withBody(new StreamFactory()->createStream('safe result'))
            ->withHeader('Set-Cookie', 'unrelated=value; Path=/')
            ->withHeader('X-Correlation-ID', 'downstream')
            ->withHeader('Cache-Control', 'public');
        $observed = null;
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            static function (ServerRequestInterface $forwarded) use (&$observed, $response) {
                $observed = $forwarded->getAttribute('correlation_id');

                return $response;
            }
        );

        $result = $this->boundary()->process($request, $handler);

        self::assertSame(202, $result->getStatusCode());
        self::assertSame('safe result', (string) $result->getBody());
        self::assertSame(['unrelated=value; Path=/'], $result->getHeader('Set-Cookie'));
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $result->getHeaderLine('X-Correlation-ID'));
        self::assertSame($observed, $result->getHeaderLine('X-Correlation-ID'));
        if ($accepted) {
            self::assertSame($inbound[0], $observed);
        } else {
            self::assertNotContains($observed, $inbound);
        }
    }

    /**
     * Leaves similar non-API paths and their response policy entirely with the downstream owner
     */
    public function testNonApiPassThrough(): void
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', '/apiary');
        $response = new ResponseFactory()->createResponse(203)->withHeader('Cache-Control', 'private');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);

        self::assertSame($response, $this->boundary()->process($request, $handler));
    }

    /**
     * Supplies only the narrow session failure and an ordinary rendering fault
     *
     * @return iterable<string, array{bool}>
     */
    public static function failureCases(): iterable
    {
        yield 'session deletion required' => [true];
        yield 'ordinary fault has no deletion obligation' => [false];
    }

    /**
     * Clears only explicitly obligated failures and never leaks that obligation into the next request
     */
    #[DataProvider('failureCases')]
    public function testFailureCookieObligation(bool $sessionFailure): void
    {
        $boundary = $this->boundary();
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(
            $sessionFailure ? new SessionResponseFailure() : new RuntimeException('private diagnostic')
        );
        $request = new ServerRequestFactory()->createServerRequest('POST', '/api/v1/auth/logout')
            ->withHeader('X-Correlation-ID', str_repeat('b', 32));

        $response = $boundary->process($request, $handler);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(str_repeat('b', 32), $response->getHeaderLine('X-Correlation-ID'));
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
        $attributes = '=; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict; ';
        $attributes .= 'Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';
        self::assertSame($sessionFailure ? [
            '__Secure-agent_os_refresh'.$attributes,
            '__Secure-agent_os_csrf'.$attributes
        ] : [], $response->getHeader('Set-Cookie'));
        $this->assertNextRequestHasNoObligation($boundary);
    }

    /**
     * Discards an obligation even when exhausted rendering cannot return a response
     */
    public function testExhaustedRenderingDoesNotLeakObligation(): void
    {
        $factory = $this->createStub(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willThrowException(new RuntimeException('private factory detail'));
        $boundary = $this->boundary($factory);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new SessionResponseFailure());

        try {
            $boundary->process(
                new ServerRequestFactory()->createServerRequest('POST', '/api/v1/auth/logout'),
                $handler
            );
            self::fail('Exhausted rendering cannot return a response.');
        } catch (RuntimeException $failure) {
            self::assertSame('Unable to render safe HTTP error.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
        $this->assertNextRequestHasNoObligation($boundary);
    }

    /**
     * Composes only in-memory response machinery and isolated diagnostics
     */
    private function boundary(?ResponseFactoryInterface $factory = null): ApiResponseBoundary
    {
        return new ApiResponseBoundary(new SafeJSendErrorMiddleware(
            new JSendResponseFactory($factory ?? new ResponseFactory(), new StreamFactory()),
            new SafeHttpErrorPolicy(),
            $this->createStub(SafeHttpDiagnostics::class)
        ));
    }

    /**
     * Checks the observable result of reusing the same boundary after a failed invocation
     */
    private function assertNextRequestHasNoObligation(ApiResponseBoundary $boundary): void
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new ResponseFactory()->createResponse(204));

        $response = $boundary->process(
            new ServerRequestFactory()->createServerRequest('GET', '/api/v1/control'),
            $handler
        );

        self::assertSame(204, $response->getStatusCode());
        self::assertSame([], $response->getHeader('Set-Cookie'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }
}
