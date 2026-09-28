<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Web\ClientShellResponder;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Exercises public shell presentation and safe deployment failure
 */
final class ClientShellResponderTest extends TestCase
{
    /**
     * Emits only the approved public configuration without authority hydration
     */
    public function test_that_shell_contains_only_the_approved_runtime_configuration(): void
    {
        $response = (new ClientShellResponder())->respond((new ResponseFactory())->createResponse(), [
            'script'     => '/build/main-AAAAAAAA.js',
            'stylesheet' => '/build/main-BBBBBBBB.css'
        ]);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString("script-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringNotContainsString('unsafe-inline', $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('<script type="module" src="/build/main-AAAAAAAA.js"></script>', $html);
        self::assertStringContainsString('<link rel="stylesheet" href="/build/main-BBBBBBBB.css">', $html);
        self::assertSame(
            1,
            preg_match('~<script id="runtime-config" type="application/json">(.*?)</script>~', $html, $match)
        );
        self::assertArrayHasKey(1, $match);
        self::assertSame(
            ['schema_version' => 1, 'api_base_path' => '/api/v1'],
            json_decode($match[1] ?? '', true, 16, JSON_THROW_ON_ERROR)
        );
        self::assertStringContainsString('<html lang="en"', $html);
        self::assertStringContainsString('<div id="app">', $html);
        self::assertStringContainsString('<noscript>', $html);
    }

    /**
     * Escapes asset attributes at the HTML boundary
     */
    public function test_that_asset_attributes_are_escaped(): void
    {
        $response = (new ClientShellResponder())->respond((new ResponseFactory())->createResponse(), [
            'script'     => '/build/"<script>',
            'stylesheet' => '/build/"<style>'
        ]);

        self::assertStringContainsString('src="/build/&quot;&lt;script&gt;"', (string) $response->getBody());
        self::assertStringContainsString('href="/build/&quot;&lt;style&gt;"', (string) $response->getBody());
    }

    /**
     * Reports unavailable deployment without emitting config or broken scripts
     */
    public function test_that_missing_assets_return_safe_unavailable_presentation(): void
    {
        $response = (new ClientShellResponder())->respond((new ResponseFactory())->createResponse(), null);
        $html = (string) $response->getBody();

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('<h1>Application unavailable</h1>', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('runtime-config', $html);
    }
}
