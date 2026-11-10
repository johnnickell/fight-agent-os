<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Adapter\Http\Action\ClientShellAction;
use App\Adapter\Http\Web\ClientAssetManifest;
use App\Adapter\Http\Web\ClientShellResponder;
use Fight\Common\Application\Service\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Proves documentation and diagnostics requests reveal no internal application state
 */
final class DocumentationExposureTest extends TestCase
{
    /**
     * Supplies unavailable documentation and diagnostic URLs in bootable local environments
     *
     * @return iterable<string, array{string, string}>
     */
    public static function unavailable_urls(): iterable
    {
        // Production HTTP boot now refuses the absent real mail provider before routes are available.
        // Its fail-closed composition is validated directly, not by this product HTTP suite.
        foreach (['local', 'development', 'test'] as $environment) {
            $paths = [
                '/swagger', '/openapi.yaml', '/docs/api/openapi.yaml', '/openapi.json',
                '/.runs/openapi/openapi.json', '/var/openapi/openapi.json', '/var/validations/catalog.json',
                '/var/client/build/manifest.json', '/var/https/certs/localhost-key.pem',
                '/diagnostics', '/api/v1/diagnostics'
            ];
            foreach ($paths as $path) {
                yield $environment.$path => [$environment, $path];
            }
        }
    }

    /**
     * Keeps private documents unavailable and browser-shell failure free of diagnostics
     */
    #[DataProvider('unavailable_urls')]
    public function test_that_documentation_and_diagnostics_are_not_served(string $environment, string $path): void
    {
        $previous = getenv('APP_ENV');
        putenv('APP_ENV='.$environment);
        try {
            $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
            $container = $app->getContainer();
            self::assertInstanceOf(Container::class, $container);
            // Eligible browser navigation may receive only the public shell, never a private document.
            // Force unavailable assets independently of the developer's current client deployment.
            $absentAssets = dirname(__DIR__, 3).'/.runs/build/tmp/absent-client-'.bin2hex(random_bytes(8));
            $container->set(ClientShellAction::class, static fn(Container $container) => new ClientShellAction(
                new ClientAssetManifest($absentAssets),
                $container->get(ClientShellResponder::class)
            ));
            $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));

            $browser = in_array($path, ['/swagger', '/diagnostics'], true);
            self::assertSame($browser ? 503 : 404, $response->getStatusCode());
            self::assertStringContainsString(
                $browser ? 'application unavailable' : 'not found',
                strtolower((string) $response->getBody())
            );
            $privateValues = [
                'Stack trace', '/app/', 'APP_ENV', 'APP_CSRF_MAC_KEY', 'Slim\\', 'openapi:', 'Swagger UI'
            ];
            foreach ($privateValues as $private) {
                self::assertStringNotContainsString($private, (string) $response->getBody());
            }
            self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
            self::assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
        } finally {
            putenv($previous === false ? 'APP_ENV' : 'APP_ENV='.$previous);
        }
    }
}
