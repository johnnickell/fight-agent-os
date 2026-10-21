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
    public function test_that_agent_os_root_remains_available(): void
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
            $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/'));
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
     * Supplies real routing misses without echoing the route or method detail
     *
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function misses(): iterable
    {
        yield 'API prefix without route' => ['GET', '/api', 404, 'Not found.'];
        yield 'unknown API route' => ['GET', '/api/v1/not-an-agent-os-route', 404, 'Not found.'];
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

        ApiContract::assertRoutingFailure($response, $status, $status === 404 ? 'NotFound' : 'MethodNotAllowed');
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
