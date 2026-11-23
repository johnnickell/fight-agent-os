<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\IndexAction;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Protects the plain-text readiness response
 */
final class IndexActionTest extends TestCase
{
    /**
     * Returns a successful and explicitly plain-text readiness message
     */
    public function testReadinessResponse(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/');
        $response = (new IndexAction())->handle($request, (new ResponseFactory())->createResponse());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('Fight Agent OS is ready.', (string) $response->getBody());
    }
}
