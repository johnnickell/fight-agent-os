<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\Failure\ApiFailureMapper;
use App\Adapter\Http\Middleware\Api\ApiFailureMiddleware;
use App\Application\Failure\PermissionDenied;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Exercises correlation, redaction and public failure handling directly
 */
final class ApiFailureMiddlewareTest extends TestCase
{
    /**
     * Supplies malformed or untrusted external correlation values
     *
     * @return iterable<string, array{string|string[]}>
     */
    public static function invalid_ids(): iterable
    {
        yield 'oversized' => [str_repeat('f', 256)];
        yield 'uppercase' => [str_repeat('F', 32)];
        yield 'structured log injection' => ['credential=secret; path=/private'];
        yield 'multiple headers' => [[str_repeat('a', 32), str_repeat('b', 32)]];
    }

    /**
     * Replaces invalid external identifiers on successful requests
     *
     * @param string|string[] $id
     */
    #[DataProvider('invalid_ids')]
    public function test_that_invalid_correlation_is_replaced(array|string $id): void
    {
        $logger = new Logger('test');
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/auth/csrf')
            ->withHeader('X-Correlation-ID', $id);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse(204));

        $response = $this->boundary($logger)->process($request, $handler);

        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        self::assertNotSame(is_string($id) ? $id : $id[0], $response->getHeaderLine('X-Correlation-ID'));
    }

    /**
     * Leaves paths outside the API prefix to their own transport boundary
     */
    public function test_that_non_api_path_is_not_rendered_as_jsend(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/apiary/missing');
        $failure = new RuntimeException('Other transport decides its response.');
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        $this->boundary(new Logger('test'))->process($request, $next);
    }

    /**
     * Propagates a well-formed client value only as a diagnostic reference
     */
    public function test_that_valid_correlation_propagates_to_success(): void
    {
        $id = str_repeat('a', 32);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/auth/csrf')
            ->withHeader('X-Correlation-ID', $id);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse(204)
            ->withHeader('X-Correlation-ID', 'untrusted-downstream'));

        $response = $this->boundary(new Logger('test'))->process($request, $handler);

        self::assertSame($id, $response->getHeaderLine('X-Correlation-ID'));
    }

    /**
     * Never gives formatters an exception or untrusted request fields
     */
    public function test_that_unknown_failure_logs_only_safe_context_and_returns_generic_error(): void
    {
        $handler = new TestHandler(Level::Error);
        $handler->setFormatter(new JsonFormatter());
        $logger = new Logger('test', [$handler]);
        $id = str_repeat('b', 32);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/grant/private-id?token=secret')
            ->withHeader('X-Correlation-ID', $id)
            ->withHeader('Authorization', 'Bearer credential-secret')
            ->withHeader('Cookie', 'session=secret')
            ->withBody((new StreamFactory())->createStream('password=secret'));
        $failure = new RuntimeException("SQL credential-secret\n/srv/private/provider.php grant=secret");
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($failure);

        $response = $this->boundary($logger)->process($request, $next);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame($id, $response->getHeaderLine('X-Correlation-ID'));
        self::assertSame(
            ['status' => 'error', 'message' => 'Internal server error.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertCount(1, $handler->getRecords());
        self::assertSame([
            'correlation_id'     => $id,
            'correlation_source' => 'client',
            'request_method'     => 'GET',
            'exception_type'     => RuntimeException::class
        ], $handler->getRecords()[0]->context);
        $formatted = $handler->getRecords()[0]->formatted;
        self::assertIsString($formatted);
        foreach (['secret', 'SQL', '/srv/private', 'password=', 'Bearer', 'session=', 'grant='] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $formatted);
        }
    }

    /**
     * Treats a generic framework HTTP error as unknown despite its claimed status
     */
    public function test_that_generic_http_exception_is_not_automatically_public(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/auth/csrf');
        $logger = new Logger('test', [$records = new TestHandler(Level::Error)]);
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException(new HttpException($request, 'private SQL', 404));

        $response = $this->boundary($logger)->process($request, $next);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(1, count($records->getRecords()));
    }

    /**
     * Returns a known permission failure without logging sensitive details
     */
    public function test_that_known_failure_is_safe_without_exception_logging(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/auth/csrf');
        $logger = new Logger('test', [$records = new TestHandler(Level::Error)]);
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException(new PermissionDenied('credential=secret'));

        $response = $this->boundary($logger)->process($request, $next);

        self::assertSame(
            ['status' => 'error', 'message' => 'Forbidden.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertCount(0, $records->getRecords());
    }

    /**
     * Creates the real presentation boundary without application container wiring
     */
    private function boundary(Logger $logger): ApiFailureMiddleware
    {
        return new ApiFailureMiddleware(
            new ApiFailureMapper(),
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory()),
            $logger
        );
    }
}
