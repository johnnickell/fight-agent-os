<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Application\Security\Csrf\Clock\CsrfClock;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\ApiContract;

/**
 * Proves the published bootstrap contract against real application interactions
 */
final class CsrfContractTest extends TestCase
{
    /**
     * Issues and reuses a nonce without returning it in the JSON proof representation
     */
    public function test_that_issuance_and_reuse_match_the_contract(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $clock = $this->createStub(CsrfClock::class);
        $clock->method('now')->willReturn(1800000000);
        $app->getContainer()?->set(CsrfClock::class, static fn(): CsrfClock => $clock);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('Origin', 'https://agent-os.test')
            ->withHeader('Sec-Fetch-Site', 'same-origin');
        $response = $app->handle($request);
        ApiContract::assertBootstrap($response, 200);
        self::assertCount(1, $response->getHeader('Set-Cookie'));
        $cookie = explode(';', $response->getHeaderLine('Set-Cookie'))[0];
        $nonce = explode('=', $cookie, 2)[1];
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1800000900, $body['data']['expires_at']);
        self::assertStringStartsWith($body['data']['expires_at'].'.', $body['data']['proof']);
        self::assertStringNotContainsString($nonce, (string) $response->getBody());

        $reused = $app->handle($request->withHeader('Cookie', $cookie)->withCookieParams([CsrfCookie::NAME => $nonce]));
        ApiContract::assertBootstrap($reused, 200);
        self::assertFalse($reused->hasHeader('Set-Cookie'));
    }

    /**
     * Replaces a malformed nonce rather than granting it authority
     */
    public function test_that_invalid_nonce_is_replaced(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('Cookie', CsrfCookie::NAME.'=invalid')
            ->withCookieParams([CsrfCookie::NAME => 'invalid']);
        $response = $app->handle($request);

        ApiContract::assertBootstrap($response, 200);
        self::assertCount(1, $response->getHeader('Set-Cookie'));
        self::assertStringNotContainsString('invalid', $response->getHeaderLine('Set-Cookie'));
    }

    /**
     * Supplies real rejected inputs rather than seeding schema mismatches
     *
     * @return iterable<string, array{string, array<string, string|list<string>>, string, int, string}>
     */
    public static function rejections(): iterable
    {
        yield 'body' => ['https://agent-os.test', [], 'not-json', 400, 'fail'];
        yield 'content type without body' => [
            'https://agent-os.test', ['Content-Type' => 'application/json'], '', 400, 'fail'
        ];
        yield 'duplicate cookie' => [
            'https://agent-os.test', ['Cookie' => CsrfCookie::NAME.'=a; '.CsrfCookie::NAME.'=b'], '', 400, 'fail'
        ];
        yield 'multiple cookie headers' => ['https://agent-os.test', ['Cookie' => ['a=b', 'c=d']], '', 400, 'fail'];
        yield 'oversize cookie' => ['https://agent-os.test', ['Cookie' => str_repeat('a', 4097)], '', 400, 'fail'];
        yield 'insecure URI' => ['http://agent-os.test', [], '', 403, 'error'];
        yield 'foreign URI' => ['https://other.test', [], '', 403, 'error'];
        yield 'foreign Origin' => ['https://agent-os.test', ['Origin' => 'https://other.test'], '', 403, 'error'];
        yield 'duplicate Origin' => [
            'https://agent-os.test', ['Origin' => ['https://agent-os.test', 'https://agent-os.test']], '', 403, 'error'
        ];
        yield 'cross-site Fetch' => ['https://agent-os.test', ['Sec-Fetch-Site' => 'cross-site'], '', 403, 'error'];
        yield 'duplicate Fetch' => [
            'https://agent-os.test', ['Sec-Fetch-Site' => ['same-origin', 'same-origin']], '', 403, 'error'
        ];
        yield 'preflight header' => [
            'https://agent-os.test', ['Access-Control-Request-Method' => 'GET'], '', 403, 'error'
        ];
    }

    /**
     * Matches each reachable rejection to the GET operation and safe envelope
     *
     * @param string                             $origin
     * @param array<string, string|list<string>>  $headers
     * @param string                             $body
     * @param integer                            $status
     * @param string                             $envelope
     */
    #[DataProvider('rejections')]
    public function test_that_rejections_match_the_contract(
        string $origin,
        array $headers,
        string $body,
        int $status,
        string $envelope
    ): void {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest('GET', $origin.'/api/v1/auth/csrf')
            ->withBody((new StreamFactory())->createStream($body));
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $response = $app->handle($request);

        ApiContract::assertBootstrap($response, $status);
        self::assertFalse($response->hasHeader('Set-Cookie'));
        self::assertSame(
            $envelope,
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)['status']
        );
    }

    /**
     * Rejects query input with the error variant rather than the validation fail variant
     */
    public function test_that_query_rejection_matches_the_contract(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'https://agent-os.test/api/v1/auth/csrf?proof=invalid'
        );
        $response = $app->handle($request);

        ApiContract::assertBootstrap($response, 400);
        self::assertSame(
            ['status' => 'error', 'message' => 'Bad request.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Contains an unavailable query dependency at the real HTTP boundary
     */
    public function test_that_unexpected_failure_matches_the_generic_contract(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $bus = new class implements QueryBus {
            /**
             * @inheritDoc
             */
            public function fetch(Query $query): mixed
            {
                throw new \RuntimeException('Synthetic private dependency detail');
            }

            /**
             * @inheritDoc
             */
            public function dispatch(QueryMessage $queryMessage): mixed
            {
                throw new \LogicException('Unexpected dispatch path');
            }
        };
        $app->getContainer()?->set(QueryBus::class, static fn (): QueryBus => $bus);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('X-Correlation-ID', str_repeat('b', 32));
        $response = $app->handle($request);

        ApiContract::assertBootstrap($response, 500);
        self::assertSame(str_repeat('b', 32), $response->getHeaderLine('X-Correlation-ID'));
        self::assertSame(
            ['status' => 'error', 'message' => 'Internal server error.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
