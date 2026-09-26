<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Exercises public routes through the real kernel
 */
final class ApplicationSmokeTest extends TestCase
{
    /**
     * Preserves the root response
     */
    public function test_that_agent_os_root_remains_available(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('Fight Agent OS is ready.', (string) $response->getBody());
    }

    /**
     * Supplies real routing misses without echoing the route or method detail
     *
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function misses(): iterable
    {
        yield 'API prefix without route' => ['GET', '/api', 404, 'Not found.'];
        yield 'unknown API route' => ['GET', '/api/v1/not-an-agent-os-route', 404, 'Not found.'];
        yield 'unsupported method' => ['POST', '/api/v1/auth/csrf', 405, 'Method not allowed.'];
    }

    /**
     * Returns sanitized correlated route failures for real routes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('misses')]
    public function test_that_routing_misses_are_sanitized(
        string $method,
        string $path,
        int $status,
        string $message
    ): void {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withHeader('X-Correlation-ID', str_repeat('a', 256));

        $response = $app->handle($request);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        self::assertSame(
            ['status' => 'error', 'message' => $message],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
