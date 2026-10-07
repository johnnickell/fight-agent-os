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
            '{"script":"/build/main-AAAAAAAA.js","stylesheet":"/build/main-BBBBBBBB.css"}'
        );
        $action = new ClientShellAction(new ClientAssetManifest($this->directory), new ClientShellResponder());
        $response = $action->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/app'),
            (new ResponseFactory())->createResponse()
        );
        self::assertSame(200, $response->getStatusCode());
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
        $action = new ClientShellAction(new ClientAssetManifest($this->directory), new ClientShellResponder());
        $response = $action->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/app'),
            (new ResponseFactory())->createResponse()
        );
        self::assertSame(503, $response->getStatusCode());
        self::assertStringNotContainsString('<script', (string) $response->getBody());
        self::assertStringNotContainsString($this->directory, (string) $response->getBody());
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
        yield 'missing asset' => ['{"script":"/build/main-CCCCCCCC.js","stylesheet":"/build/main-BBBBBBBB.css"}'];
    }
}
