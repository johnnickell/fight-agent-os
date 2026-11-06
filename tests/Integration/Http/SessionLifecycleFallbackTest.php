<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Adapter\Http\Api\Failure\PublicFailureProjector;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Middleware\Api\ApiResponseBoundary;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionLifecycleResponder;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\User\Security\RefreshResult;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use Fight\Common\Application\Error\Exception\ServiceUnavailable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;
use Throwable;

/**
 * Exercises required direct session effects across recovered rendering failures
 */
final class SessionLifecycleFallbackTest extends TestCase
{
    /**
     * Supplies direct and outer-boundary recovery with independent reporter failures
     *
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function recoveryCases(): iterable
    {
        foreach (['response', 'stream'] as $broken) {
            foreach ([false, true] as $outer) {
                foreach ([false, true] as $reporter) {
                    yield $broken.'-'.(int) $outer.'-'.(int) $reporter => [$broken, $outer, $reporter];
                }
            }
        }
    }

    /**
     * Retains exact cookie deletion when response or stream creation recovers once
     */
    #[DataProvider('recoveryCases')]
    public function testRecoveredRenderingClearsCookies(string $broken, bool $outer, bool $reporter): void
    {
        $response = $this->invoke($broken, $outer, $reporter, false);
        $this->assertCleared($response);
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Retains terminal and logout effects when their initial response cannot render
     */
    #[DataProvider('recoveryCases')]
    public function testTerminalAndLogoutRecovery(string $broken, bool $outer, bool $reporter): void
    {
        foreach (['terminal', 'loggedOut'] as $operation) {
            $this->assertCleared($this->invoke($broken, $outer, $reporter, false, operation: $operation));
        }
    }

    /**
     * Preserves the ordinary refresh conflict without clearing authority
     */
    public function testOrdinaryConflictDoesNotClearCookies(): void
    {
        $response = $this->invoke('none', true, false, false, operation: 'conflict');
        self::assertSame(409, $response->getStatusCode());
        self::assertSame([], $response->getHeader('Set-Cookie'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'fail', 'data' => ['message' => 'State conflict.']], json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Keeps ordinary availability intent and validated retry metadata while clearing cookies
     */
    public function testDeclaredUnavailable(): void
    {
        $response = $this->invoke('none', true, false, false, new ServiceUnavailable('private', retryDelaySeconds: 7));
        $this->assertCleared($response, 503);
        self::assertSame('7', $response->getHeaderLine('Retry-After'));
        self::assertSame(['status' => 'error', 'message' => 'Service unavailable.'], json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Does not promise a response from permanently incapable factories
     */
    #[DataProvider('recoveryCases')]
    public function testPermanentRenderingFailureIsSanitized(string $broken, bool $outer, bool $reporter): void
    {
        try {
            $this->invoke($broken, $outer, $reporter, true);
            self::fail('Permanently broken factories cannot render a response.');
        } catch (RuntimeException $failure) {
            self::assertSame('Unable to render safe HTTP error.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    /**
     * Composes the actual responder and optional production boundary without a diagnostic route
     */
    private function invoke(
        string $broken,
        bool $outer,
        bool $reporter,
        bool $permanent,
        ?Throwable $failure = null,
        string $operation = 'unavailable'
    ): ResponseInterface {
        $responseCalls = 0;
        $streamCalls = 0;
        $responseFactory = $this->createStub(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturnCallback(
            static function (int $code) use ($broken, $permanent, &$responseCalls): ResponseInterface {
                if ($broken === 'response' && (++$responseCalls === 1 || $permanent)) {
                    throw new RuntimeException('private response diagnostic');
                }

                return (new ResponseFactory())->createResponse($code);
            }
        );
        $streamFactory = $this->createStub(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(
            static function (string $content) use ($broken, $permanent, &$streamCalls): StreamInterface {
                if ($broken === 'stream' && (++$streamCalls === 1 || $permanent)) {
                    throw new RuntimeException('private stream diagnostic');
                }

                return (new StreamFactory())->createStream($content);
            }
        );
        $sink = $this->createMock(SafeHttpDiagnostics::class);
        // A permanent local escape can be caught once by the actual outer Common boundary.
        $offers = $operation === 'conflict' ? 0 : ($permanent && $outer ? 2 : 1);
        $sink->expects(self::exactly($offers))->method('report')->willReturnCallback(
            static function (Throwable $offered) use ($reporter): void {
                if ($reporter) {
                    throw new RuntimeException('private reporter diagnostic');
                }
            }
        );
        $responses = new JSendResponseFactory($responseFactory, $streamFactory);
        $policy = new SafeHttpErrorPolicy(true);
        $lifecycle = new SessionLifecycleResponder(
            new SessionResponder($responses, new BrowserSessionCookies(new AccessClock(new DateTimeImmutable()))),
            $responses,
            $sink,
            $policy
        );
        $failure ??= new RuntimeException('private original diagnostic', 404, new RuntimeException('private cause'));
        $render = static fn(): ResponseInterface => match ($operation) {
            'terminal' => $lifecycle->terminal(),
            'loggedOut' => $lifecycle->loggedOut(),
            'conflict' => $lifecycle->respond(RefreshResult::conflict()),
            default => $lifecycle->unavailable($failure)
        };
        if (!$outer) {
            return $render();
        }
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback($render);
        $boundary = new ApiResponseBoundary(new SafeJSendErrorMiddleware(
            $responses,
            $policy,
            $sink,
            new PublicFailureProjector()
        ));
        $response = $boundary->process(
            (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/auth/logout')
                ->withHeader('X-Correlation-ID', 'private=secret'),
            $handler
        );
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $response->getHeaderLine('X-Correlation-ID'));

        return $response;
    }

    /**
     * Checks the exact host-only credential identities and safe transport response
     */
    private function assertCleared(ResponseInterface $response, int $status = 500): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $attributes = '; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict';
        $attributes .= '; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';
        self::assertSame([
            '__Secure-agent_os_refresh='.$attributes,
            '__Secure-agent_os_csrf='.$attributes
        ], $response->getHeader('Set-Cookie'));
    }
}
