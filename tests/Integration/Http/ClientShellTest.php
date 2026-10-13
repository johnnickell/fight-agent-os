<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Adapter\Http\Action\ClientShellAction;
use App\Adapter\Http\Web\ClientAssetManifest;
use App\Adapter\Http\Web\ClientShellResponder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Proves safe shell delivery through the runtime asset reader, not the asset build tool
 */
final class ClientShellTest extends TestCase
{
    private string $directory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->directory = dirname(__DIR__, 3).'/.runs/test-client-assets/'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        file_put_contents($this->directory.'/prepaint-CCCCCCCC.js', '');
        file_put_contents($this->directory.'/main-AAAAAAAA.js', '');
        file_put_contents($this->directory.'/main-BBBBBBBB.css', '');
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /**
     * Emits existing same-origin assets through the Action and responder
     */
    public function testServesAvailableAssets(): void
    {
        file_put_contents(
            $this->directory.'/manifest.json',
            json_encode([
                'prepaint'   => '/build/prepaint-CCCCCCCC.js',
                'script'     => '/build/main-AAAAAAAA.js',
                'stylesheet' => '/build/main-BBBBBBBB.css'
            ], JSON_THROW_ON_ERROR)
        );
        $action = new ClientShellAction(new ClientAssetManifest($this->directory), $this->responder());
        $response = $action->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/app'),
            (new ResponseFactory())->createResponse()
        );
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            '<script src="/build/prepaint-CCCCCCCC.js"></script>',
            (string) $response->getBody()
        );
        self::assertStringContainsString('src="/build/main-AAAAAAAA.js"', (string) $response->getBody());
        self::assertStringContainsString('href="/build/main-BBBBBBBB.css"', (string) $response->getBody());
    }

    /**
     * Refuses missing, malformed, foreign or incomplete deployments without disclosing paths
     */
    #[DataProvider('unavailableAssets')]
    public function testUnavailableAssetsFailSafely(?string $manifest): void
    {
        if ($manifest !== null) {
            file_put_contents($this->directory.'/manifest.json', $manifest);
        }
        $action = new ClientShellAction(new ClientAssetManifest($this->directory), $this->responder());
        $response = $action->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/app'),
            (new ResponseFactory())->createResponse()
        );
        self::assertSame(503, $response->getStatusCode());
        self::assertStringNotContainsString('<script', (string) $response->getBody());
        self::assertStringNotContainsString($this->directory, (string) $response->getBody());
    }

    /**
     * Contains template deployment failures without exposing partial HTML or exception detail
     */
    #[DataProvider('unavailableTemplates')]
    public function testTemplateFailuresFailSafely(?string $shell, ?string $unavailable, bool $hasAssets): void
    {
        if ($shell !== null) {
            file_put_contents($this->directory.'/shell.html.twig', $shell);
        }
        if ($unavailable !== null) {
            file_put_contents($this->directory.'/unavailable.html.twig', $unavailable);
        }
        if ($hasAssets) {
            file_put_contents($this->directory.'/manifest.json', json_encode([
                'prepaint'   => '/build/prepaint-CCCCCCCC.js',
                'script'     => '/build/main-AAAAAAAA.js',
                'stylesheet' => '/build/main-BBBBBBBB.css'
            ], JSON_THROW_ON_ERROR));
        }
        $loader = new FilesystemLoader();
        $loader->addPath($this->directory, 'client');
        $action = new ClientShellAction(
            new ClientAssetManifest($this->directory),
            new ClientShellResponder(new Environment($loader, ['autoescape' => 'html', 'strict_variables' => true]))
        );
        $response = $action->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/app'),
            (new ResponseFactory())->createResponse()
        );
        $body = (string) $response->getBody();
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString("default-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringContainsString('Application unavailable', $body);
        self::assertStringNotContainsString($this->directory, $body);
        self::assertStringNotContainsString('private_partial', $body);
        self::assertStringNotContainsString('private_missing', $body);
        self::assertStringNotContainsString('.twig', $body);
        self::assertStringNotContainsString('<script', $body);
        self::assertStringNotContainsString('/build/', $body);
        self::assertStringNotContainsString('runtime-config', $body);
        $type = $unavailable === '<main>Application unavailable</main>' ? 'text/html' : 'text/plain';
        self::assertSame($type.'; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Supplies missing and corrupt template deployments
     *
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function unavailableTemplates(): iterable
    {
        $safe = '<main>Application unavailable</main>';
        yield 'missing shell' => [null, $safe, true];
        yield 'syntax error' => ['private_partial{% private_missing %}', $safe, true];
        yield 'render error after partial output' => ['private_partial{{ private_missing }}', $safe, true];
        yield 'both missing' => [null, null, true];
        yield 'missing assets and fallback' => [null, null, false];
        yield 'fallback render error' => [null, 'private_partial{{ private_missing }}', true];
        yield 'fallback syntax error' => [null, '{% private_missing %}', false];
    }

    /**
     * Creates presentation with the real public templates
     */
    private function responder(): ClientShellResponder
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 3).'/templates/client', 'client');

        return new ClientShellResponder(new Environment($loader, ['autoescape' => 'html', 'strict_variables' => true]));
    }

    /**
     * Supplies runtime deployment inputs without asserting generated-file contents
     *
     * @return iterable<string, array{?string}>
     */
    public static function unavailableAssets(): iterable
    {
        yield 'missing' => [null];
        yield 'invalid JSON' => ['{'];
        yield 'scalar' => ['null'];
        yield 'incomplete' => ['{}'];
        yield 'non-string' => ['{"script":1,"stylesheet":"/build/main-BBBBBBBB.css"}'];
        yield 'foreign origin' => ['{"script":"https://evil.test/x.js","stylesheet":"/build/main-BBBBBBBB.css"}'];
        yield 'traversal' => ['{"script":"/build/../x.js","stylesheet":"/build/main-BBBBBBBB.css"}'];
        yield 'missing asset' => [json_encode([
            'prepaint'   => '/build/prepaint-DDDDDDDD.js',
            'script'     => '/build/main-AAAAAAAA.js',
            'stylesheet' => '/build/main-BBBBBBBB.css'
        ], JSON_THROW_ON_ERROR)];
    }
}
