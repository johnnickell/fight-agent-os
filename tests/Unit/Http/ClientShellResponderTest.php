<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Web\ClientShellResponder;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Application\Templating\TemplateEngine;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Exercises public shell presentation and safe deployment failure
 */
final class ClientShellResponderTest extends TestCase
{
    /**
     * Gives the shell renderer only the approved configuration and selected assets
     */
    public function testRendersWithPublicConfiguration(): void
    {
        $assets = [
            'prepaint'   => '/build/prepaint-CCCCCCCC.js',
            'script'     => '/build/main-AAAAAAAA.js',
            'stylesheet' => '/build/main-BBBBBBBB.css'
        ];
        $templates = $this->createMock(TemplateEngine::class);
        $templates->expects(self::once())->method('render')->with('@client/shell.html.twig', [
            'assets' => $assets,
            'config' => [
                'schema_version'    => 2,
                'api_base_path'     => '/api/v1',
                'app_debug'         => false,
                'swagger_available' => false
            ]
        ])->willReturn('<main>Rendered shell</main>');
        $response = (new ClientShellResponder($templates))->respond((new ResponseFactory())->createResponse(), $assets);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<main>Rendered shell</main>', (string) $response->getBody());
    }

    /**
     * Serves the unavailable presentation when no assets can be selected
     */
    public function testRendersUnavailablePresentation(): void
    {
        $templates = $this->createMock(TemplateEngine::class);
        $templates->expects(self::once())->method('render')->with('@client/unavailable.html.twig')
            ->willReturn('<h1>Application unavailable</h1>');
        $response = (new ClientShellResponder($templates))->respond((new ResponseFactory())->createResponse(), null);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('<h1>Application unavailable</h1>', (string) $response->getBody());
    }

    /**
     * Contains renderer failures without exposing engine diagnostics
     */
    public function test_that_engine_failures_return_safe_plain_text(): void
    {
        $templates = $this->createStub(TemplateEngine::class);
        $templates->method('render')->willThrowException(new TemplatingException('private renderer detail'));
        $response = (new ClientShellResponder($templates))->respond((new ResponseFactory())->createResponse(), [
            'prepaint'   => '/build/prepaint-CCCCCCCC.js',
            'script'     => '/build/main-AAAAAAAA.js',
            'stylesheet' => '/build/main-BBBBBBBB.css'
        ]);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString("default-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame(
            'Application unavailable. Please contact the installation operator.',
            (string) $response->getBody()
        );
    }
}
