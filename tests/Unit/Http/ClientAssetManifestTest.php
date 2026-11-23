<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\ClientShellAction;
use App\Adapter\Http\Web\ClientAssetManifest;
use App\Adapter\Http\Web\ClientShellResponder;
use Fight\Common\Application\Templating\TemplateEngine;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Verifies how the shell chooses local built assets without reading disk or rendering Twig
 */
final class ClientAssetManifestTest extends TestCase
{
    private const array ASSETS = [
        'prepaint'   => '/build/prepaint-CCCCCCCC.js',
        'script'     => '/build/main-AAAAAAAA.js',
        'stylesheet' => '/build/main-BBBBBBBB.css'
    ];

    /**
     * Sends only a complete local deployment to the shell renderer
     */
    public function testCompleteDeploymentRendersShell(): void
    {
        $files = $this->createMock(FilesystemReader::class);
        $files->expects(self::once())->method('read')->with('manifest.json')
            ->willReturn(json_encode(self::ASSETS, JSON_THROW_ON_ERROR));
        $files->method('fileExists')->willReturnCallback(static fn (string $path): bool => in_array($path, [
            'prepaint-CCCCCCCC.js', 'main-AAAAAAAA.js', 'main-BBBBBBBB.css'
        ], true));
        $renderer = $this->createMock(TemplateEngine::class);
        $renderer->expects(self::once())->method('render')->with(self::anything(), self::callback(
            static fn (array $context): bool => ($context['assets'] ?? null) === self::ASSETS
        ))->willReturn('<main>Client shell</main>');
        $action = new ClientShellAction(new ClientAssetManifest($files), new ClientShellResponder($renderer));
        $response = $action->handle(
            new ServerRequestFactory()->createServerRequest('GET', '/'),
            new ResponseFactory()->createResponse()
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('<main>Client shell</main>', (string) $response->getBody());
    }

    /**
     * Does not return a partial set of asset references
     */
    #[DataProvider('invalidManifests')]
    public function testUnavailableDeployment(?string $manifest): void
    {
        $files = $this->createStub(FilesystemReader::class);
        $read = $files->method('read');
        if ($manifest === null) {
            $read->willThrowException(UnableToReadFile::fromLocation('manifest.json', 'private path'));
        } else {
            $read->willReturn($manifest);
        }
        $files->method('fileExists')->willReturn(false);
        self::assertNull(new ClientAssetManifest($files)->read());
    }

    /**
     * Supplies deployed manifest failures without using the real filesystem
     *
     * @return iterable<string, array{?string}>
     */
    public static function invalidManifests(): iterable
    {
        yield 'missing manifest' => [null];
        yield 'malformed JSON' => ['{'];
        yield 'incomplete manifest' => ['{}'];
        yield 'missing asset' => [json_encode(self::ASSETS, JSON_THROW_ON_ERROR)];
    }

    /**
     * Rejects a foreign URL before looking for a corresponding local filename
     */
    public function testForeignPrepaintNeverReachesStorage(): void
    {
        $files = $this->createMock(FilesystemReader::class);
        $files->method('read')->willReturn(json_encode(array_replace(self::ASSETS, [
            'prepaint' => 'https://assets.example.test/prepaint-CCCCCCCC.js'
        ]), JSON_THROW_ON_ERROR));
        $files->expects(self::never())->method('fileExists');
        self::assertNull(new ClientAssetManifest($files)->read());
    }
}
