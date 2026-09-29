<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Proves documentation and diagnostics requests reveal no internal application state
 */
final class DocumentationExposureTest extends TestCase
{
    /**
     * Supplies unavailable documentation and diagnostic URLs in each environment
     *
     * @return iterable<string, array{string, string}>
     */
    public static function unavailable_urls(): iterable
    {
        foreach (['development', 'test', 'production'] as $environment) {
            $paths = ['/swagger', '/openapi.yaml', '/docs/api/openapi.yaml', '/diagnostics', '/api/v1/diagnostics'];
            foreach ($paths as $path) {
                yield $environment.$path => [$environment, $path];
            }
        }
    }

    /**
     * Returns a safe miss instead of documentation or exception diagnostics
     */
    #[DataProvider('unavailable_urls')]
    public function test_that_documentation_and_diagnostics_are_not_served(string $environment, string $path): void
    {
        $previous = getenv('APP_ENV');
        putenv('APP_ENV='.$environment);
        try {
            $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
            $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));

            self::assertSame(404, $response->getStatusCode());
            self::assertStringContainsString('not found', strtolower((string) $response->getBody()));
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
