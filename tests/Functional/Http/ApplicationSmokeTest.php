<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Adapter\Http\Action\ClientShellAction;
use App\Adapter\Http\Web\ClientAssetManifest;
use App\Adapter\Http\Web\ClientShellResponder;
use Fight\Common\Application\Service\Container;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ApiContract;

/**
 * Exercises public routes through the real kernel
 */
final class ApplicationSmokeTest extends TestCase
{
    /**
     * Delivers the public client root without embedding credentials or principal authority
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('browserDestinations')]
    public function test_that_agent_os_root_remains_available(string $path): void
    {
        $directory = dirname(__DIR__, 3).'/.runs/build/tmp/test-client-root/'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        foreach (['prepaint-CCCCCCCC.js', 'main-AAAAAAAA.js', 'main-BBBBBBBB.css'] as $asset) {
            file_put_contents($directory.'/'.$asset, '');
        }
        file_put_contents($directory.'/manifest.json', json_encode([
            'prepaint'   => '/build/prepaint-CCCCCCCC.js',
            'script'     => '/build/main-AAAAAAAA.js',
            'stylesheet' => '/build/main-BBBBBBBB.css'
        ], JSON_THROW_ON_ERROR));
        try {
            $app = (static fn() => require dirname(__DIR__, 3).'/bootstrap/app.php')();
            $container = $app->getContainer();
            self::assertInstanceOf(Container::class, $container);
            $container->set(ClientShellAction::class, static fn(Container $container) => new ClientShellAction(
                new ClientAssetManifest($directory),
                $container->get(ClientShellResponder::class)
            ));
            $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
            $body = (string) $response->getBody();

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertStringContainsString("default-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
            self::assertStringContainsString('id="app"', $body);
            self::assertStringContainsString('src="/build/main-AAAAAAAA.js"', $body);
            self::assertStringNotContainsString('access_token', $body);
            self::assertStringNotContainsString('permissions', $body);
            self::assertStringNotContainsString('roles', $body);
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /**
     * Supplies direct browser destinations including meaningful client-side Not Found
     *
     * @return iterable<string, array{string}>
     */
    public static function browserDestinations(): iterable
    {
        foreach (
            ['/', '/login', '/activate', '/reset-password', '/reset-password/complete',
                '/account/password', '/account/sessions', '/admin/users', '/swagger', '/unknown-browser-page'] as $path
        ) {
            yield $path => [$path];
        }
    }

    /**
     * Supplies real routing misses without echoing the route or method detail
     *
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function misses(): iterable
    {
        yield 'encoded API namespace' => ['GET', '/%61pi/v1/unknown', 404, 'Resource not found.'];
        yield 'encoded missing asset' => ['GET', '/missing%2ejs', 404, 'Resource not found.'];
        yield 'reserved MCP root' => ['GET', '/mcp', 404, 'Resource not found.'];
        yield 'reserved MCP v1 miss' => ['GET', '/mcp/v1/unknown', 404, 'Resource not found.'];
        yield 'missing build asset' => ['GET', '/build/missing.js', 404, 'Resource not found.'];
        yield 'missing public asset' => ['GET', '/favicon.ico', 404, 'Resource not found.'];
        yield 'legacy entry' => ['GET', '/app', 404, 'Resource not found.'];
        yield 'legacy journey' => ['GET', '/app/login', 404, 'Resource not found.'];
        yield 'unsupported browser method' => ['POST', '/login', 405, 'Method not allowed.'];
        yield 'API prefix without route' => ['GET', '/api', 404, 'Resource not found.'];
        yield 'unknown API route' => ['GET', '/api/v1/not-an-agent-os-route', 404, 'Resource not found.'];
        yield 'unsupported method' => ['POST', '/api/v1/auth/csrf', 405, 'Method not allowed.'];
        yield 'unsupported preflight' => ['OPTIONS', '/api/v1/auth/csrf', 405, 'Method not allowed.'];
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

        if (!str_starts_with($path, '/api')) {
            self::assertSame($status, $response->getStatusCode());
            self::assertFalse($response->hasHeader('Location'));
            self::assertStringNotContainsString('id="app"', (string) $response->getBody());
            self::assertStringNotContainsString('/build/main-', (string) $response->getBody());

            return;
        }
        ApiContract::assertRoutingFailure($response, $status, $status === 404 ? 'NotFound' : 'MethodNotAllowed');
        if ($status === 405) {
            self::assertSame('GET', $response->getHeaderLine('Allow'));
        }
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        self::assertSame(
            ['status' => 'fail', 'data' => ['message' => $message]],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
