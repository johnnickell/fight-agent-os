<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Responder\Api\V1\Auth\PasswordChangeResponder;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;

/**
 * Protects terminal password-change presentation without replacement authority
 */
final class PasswordChangeResponderTest extends TestCase
{
    /**
     * Returns safe completion and expires both browser credentials
     */
    public function testCompletionClearsCookies(): void
    {
        $responder = new PasswordChangeResponder(
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory()),
            new BrowserSessionCookies(new AccessClock())
        );

        $response = $responder->respond();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => ['changed' => true]], json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
        $attributes = '=; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict; ';
        $attributes .= 'Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';
        self::assertSame([
            '__Secure-agent_os_refresh'.$attributes,
            '__Secure-agent_os_csrf'.$attributes
        ], $response->getHeader('Set-Cookie'));
    }
}
