<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Adapter\Http\Api\Failure\PublicFailureProjector;
use App\Adapter\Http\Api\Failure\RedactedHttpDiagnostics;
use App\Adapter\Http\Middleware\Api\ApiResponseBoundary;
use App\Application\Failure\IncompleteInvitation;
use App\Application\Invitation\InvitationIssuance;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Throwable;

/**
 * Exercises the owned API scope, projection and diagnostic sink through Common and actual Slim requests
 */
final class SafeErrorBoundaryTest extends TestCase
{
    /**
     * Supplies unknown faults whose messages or numeric codes must never select a public status
     *
     * @return iterable<array{Throwable}>
     */
    public static function unknownFailures(): iterable
    {
        yield [new RuntimeException(
            'SQL password=secret /private/provider.php',
            404,
            new RuntimeException('cause token=secret')
        )];
        yield [new \Fight\Common\Domain\Exception\LookupException('private missing identity', 404)];
        yield [new \Slim\Exception\HttpException(
            (new ServerRequestFactory())->createServerRequest('GET', '/private'),
            'private native detail',
            409
        )];
    }

    /**
     * Exercises Action/query failures through the real routing, admission and API boundary
     */
    #[DataProvider('unknownFailures')]
    public function testRealLifecycleRedactsBeforeFormatting(Throwable $failure): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $logs = new TestHandler();
        $logs->setFormatter(new JsonFormatter());
        $app->getContainer()?->get('http.failure.logger')->setHandlers([$logs]);
        $bus = new class ($failure) implements QueryBus {
            /**
             * Constructs FailingQueryBus
             */
            public function __construct(private Throwable $failure)
            {
            }

            /**
             * @inheritDoc
             */
            public function fetch(Query $query): mixed
            {
                throw $this->failure;
            }

            /**
             * @inheritDoc
             */
            public function dispatch(QueryMessage $queryMessage): mixed
            {
                throw $this->failure;
            }
        };
        $app->getContainer()?->set(QueryBus::class, static fn(): QueryBus => $bus);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('X-Correlation-ID', str_repeat('b', 32))
            ->withHeader('Authorization', 'Bearer secret')->withHeader('Cookie', 'session=secret');
        $response = $app->handle($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            ['status' => 'error', 'message' => 'Internal server error.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertSame(str_repeat('b', 32), $response->getHeaderLine('X-Correlation-ID'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertCount(1, $logs->getRecords());
        self::assertSame(
            ['http_status' => 500, 'exception_type' => $failure::class],
            $logs->getRecords()[0]->context
        );
        foreach (['secret', 'password', '/private', 'cause', 'Bearer', 'session='] as $private) {
            self::assertStringNotContainsString($private, (string) $logs->getRecords()[0]->formatted);
        }
    }

    /**
     * Supplies invalid or ambiguous client correlation identifiers
     *
     * @return iterable<array{string|list<string>}>
     */
    public static function invalidCorrelation(): iterable
    {
        yield [str_repeat('F', 32)];
        yield [str_repeat('a', 256)];
        yield ['private=secret'];
        yield [[str_repeat('a', 32), str_repeat('b', 32)]];
    }

    /**
     * Replaces untrusted correlation on an ordinary successful real request
     *
     * @param string|list<string> $identifier
     */
    #[DataProvider('invalidCorrelation')]
    public function testSuccessCorrelation(array|string $identifier): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('X-Correlation-ID', $identifier);
        $response = $app->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $response->getHeaderLine('X-Correlation-ID'));
        self::assertNotSame(
            is_array($identifier) ? $identifier[0] : $identifier,
            $response->getHeaderLine('X-Correlation-ID')
        );
    }

    /**
     * Leaves non-API routing to the sanitized outer transport without API correlation or JSend
     */
    public function testNonApiScope(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/apiary/missing'));
        self::assertSame(404, $response->getStatusCode());
        self::assertFalse($response->hasHeader('X-Correlation-ID'));
        self::assertNotSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringNotContainsString('/apiary/missing', (string) $response->getBody());
    }

    /**
     * Keeps direct cookie-clearing effects when the sole diagnostics owner fails
     */
    public function testDirectSessionReporterFailure(): void
    {
        $sink = new class implements SafeHttpDiagnostics {
            public int $calls = 0;
            /**
             * @inheritDoc
             */
            public function report(Throwable $failure): void
            {
                ++$this->calls;
                throw new RuntimeException('private reporter failure');
            }
        };
        $responses = new JSendResponseFactory(new ResponseFactory(), new StreamFactory());
        $clock = new \Tests\Support\AccessClock(new \DateTimeImmutable());
        $sessions = new \App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder(
            $responses,
            new \App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies($clock)
        );
        $lifecycle = new \App\Adapter\Http\Responder\Api\V1\Auth\SessionLifecycleResponder(
            $sessions,
            $responses,
            $sink,
            new SafeHttpErrorPolicy(true)
        );
        $response = $lifecycle->unavailable(new RuntimeException('private original failure'));
        self::assertSame(1, $sink->calls);
        self::assertSame(500, $response->getStatusCode());
        self::assertCount(2, $response->getHeader('Set-Cookie'));
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            self::assertStringContainsString('Max-Age=0', $cookie);
        }
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(
            ['status' => 'error', 'message' => 'Internal server error.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Preserves partial invitation facts under the server-error envelope, not an expected refusal
     */
    public function testPartialInvitationRetainsInspectedFacts(): void
    {
        $id = UserId::generate();
        $failure = new IncompleteInvitation(new InvitationIssuance($id, 'unconfirmed', false, null));
        $response = $this->boundary()->process($this->request(), $this->throws($failure));
        self::assertSame(503, $response->getStatusCode());
        self::assertSame([
            'status'  => 'error',
            'message' => 'Invitation completion is unconfirmed. Read invitation status before another operation.',
            'data'    => [
                'user_id'    => $id->toString(),
                'assignment' => 'unconfirmed',
                'audit'      => 'unconfirmed',
                'delivery'   => null
            ]
        ], json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Keeps permission refusal private while delivering it once to the configured diagnostics owner
     */
    public function testExpectedFailureOfferedOnce(): void
    {
        $sink = new class implements SafeHttpDiagnostics {
            public int $calls = 0;
            /**
             * @inheritDoc
             */
            public function report(Throwable $failure): void
            {
                ++$this->calls;
            }
        };
        $response = $this->boundary($sink)->process($this->request(), $this->throws(new PermissionDenied('secret')));
        self::assertSame(1, $sink->calls);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            ['status' => 'fail', 'data' => ['message' => 'Permission denied.']],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Supplies both failed reporting and failed public projection
     *
     * @return iterable<array{string}>
     */
    public static function brokenCollaborators(): iterable
    {
        yield ['reporter'];
        yield ['projector'];
        yield ['invalid-data'];
    }

    /**
     * Contains a failed collaborator without recursive reporting or lost correlation/cache guarantees
     */
    #[DataProvider('brokenCollaborators')]
    public function testSafeFallback(string $broken): void
    {
        $sink = new class ($broken) implements SafeHttpDiagnostics {
            public int $calls = 0;
            /**
             * Constructs DiagnosticsProbe
             */
            public function __construct(private string $broken)
            {
            }
            /**
             * @inheritDoc
             */
            public function report(Throwable $failure): void
            {
                ++$this->calls;
                if ($this->broken === 'reporter') {
                    throw new RuntimeException('private sink failure');
                }
            }
        };
        $projector = new class ($broken) implements SafeHttpProjector {
            /**
             * Constructs ProjectionProbe
             */
            public function __construct(private string $broken)
            {
            }
            /**
             * @inheritDoc
             */
            public function project(Throwable $failure, int $status): SafeHttpPresentation
            {
                if ($this->broken === 'invalid-data') {
                    return new SafeHttpPresentation(data: ['secret' => new \stdClass()]);
                }
                throw new RuntimeException('private projector failure');
            }
        };
        $response = $this->boundary($sink, $projector)->process(
            $this->request(),
            $this->throws(new RuntimeException('secret'))
        );
        self::assertSame(1, $sink->calls);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $response->getHeaderLine('X-Correlation-ID'));
        self::assertSame(
            ['status' => 'error', 'message' => 'Internal server error.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Does not claim a valid response when the actual response factory is broken
     */
    public function testBrokenResponseFactoryEscapesSanitized(): void
    {
        $factory = $this->createStub(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willThrowException(new RuntimeException('private factory failure'));
        $safe = new ApiResponseBoundary(new SafeJSendErrorMiddleware(
            new JSendResponseFactory($factory, new StreamFactory()),
            new SafeHttpErrorPolicy(),
            new RedactedHttpDiagnostics(new Logger('test'), new SafeHttpErrorPolicy())
        ));
        try {
            $safe->process($this->request(), $this->throws(new RuntimeException('private original')));
            self::fail('A broken factory cannot produce a response.');
        } catch (RuntimeException $failure) {
            self::assertSame('Unable to render safe HTTP error.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    /**
     * Composes the real consumer boundary without testing container wiring
     */
    private function boundary(
        ?SafeHttpDiagnostics $sink = null,
        ?SafeHttpProjector $projector = null
    ): ApiResponseBoundary {
        $policy = new SafeHttpErrorPolicy(true);

        return new ApiResponseBoundary(new SafeJSendErrorMiddleware(
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory()),
            $policy,
            $sink ?? new RedactedHttpDiagnostics(new Logger('test'), $policy),
            $projector ?? new PublicFailureProjector()
        ));
    }

    /**
     * Creates a covered request with deliberately invalid external correlation
     */
    private function request(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/auth/csrf')
            ->withHeader('X-Correlation-ID', 'private=secret');
    }

    /**
     * Creates a downstream failure probe without a production diagnostic route
     */
    private function throws(Throwable $failure): RequestHandlerInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($failure);

        return $handler;
    }
}
