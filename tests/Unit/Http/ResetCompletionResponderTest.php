<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Responder\Api\V1\Auth\ResetCompletionResponder;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;

/**
 * Protects safe reset completion and target-scoped browser clearing
 */
final class ResetCompletionResponderTest extends TestCase
{
    /**
     * Supplies independently specified matching and unrelated browser effects
     *
     * @return iterable<string, array{bool, list<string>}>
     */
    public static function browserCases(): iterable
    {
        $attributes = '=; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict; ';
        $attributes .= 'Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';
        yield 'matching session' => [true, [
            '__Secure-agent_os_refresh'.$attributes,
            '__Secure-agent_os_csrf'.$attributes
        ]];
        yield 'unrelated session' => [false, []];
    }

    /**
     * Exposes completion only and leaves unrelated browser authority untouched
     *
     * @param boolean $clearTargetSession
     * @param array $expectedCookies
     *
     * @phpstan-param list<string> $expectedCookies
     */
    #[DataProvider('browserCases')]
    public function testCompletion(bool $clearTargetSession, array $expectedCookies): void
    {
        $responder = new ResetCompletionResponder(
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory()),
            new BrowserSessionCookies(new AccessClock())
        );

        $response = $responder->respond($clearTargetSession);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => ['completed' => true]], json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
        self::assertSame($expectedCookies, $response->getHeader('Set-Cookie'));
    }
}
