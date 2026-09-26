<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Application\Security\CsrfProof;
use App\Application\Security\GetCsrfProof;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Exercises the real public CSRF interaction through Slim
 */
final class CsrfBootstrapTest extends TestCase
{
    /**
     * Verifies fresh and reused nonce proofs without leaking the cookie to JSON
     */
    public function testBootstrapIssuesAndReusesNonce(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('Sec-Fetch-Site', 'same-origin');
        $start = time();
        $first = $app->handle($request);
        $cookie = $first->getHeaderLine('Set-Cookie');
        self::assertSame(200, $first->getStatusCode());
        self::assertSame('application/json', $first->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $first->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression(
            '/\A__Secure-agent_os_csrf=([0-9a-f]{64}); Path=\/api\/v1\/auth; Secure; HttpOnly; SameSite=Strict\z/',
            $cookie
        );
        self::assertStringNotContainsString('Domain=', $cookie);
        self::assertStringNotContainsString('Max-Age=', $cookie);
        self::assertStringNotContainsString('Expires=', $cookie);
        self::assertSame('', $first->getHeaderLine('Access-Control-Allow-Origin'));
        $nonce = explode(';', explode('=', $cookie, 2)[1], 2)[0];
        $data = json_decode((string) $first->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('success', $data['status']);
        self::assertSame(['proof', 'expires_at'], array_keys($data['data']));
        self::assertGreaterThanOrEqual($start + 900, $data['data']['expires_at']);
        self::assertLessThanOrEqual(time() + 900, $data['data']['expires_at']);
        self::assertStringNotContainsString($nonce, (string) $first->getBody());

        $again = $app->handle($request->withHeader('Cookie', '__Secure-agent_os_csrf='.$nonce));
        self::assertSame(200, $again->getStatusCode());
        self::assertSame([], $again->getHeader('Set-Cookie'));
        self::assertSame('no-store', $again->getHeaderLine('Cache-Control'));
        $againData = json_decode((string) $again->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('success', $againData['status']);
        self::assertSame(['proof', 'expires_at'], array_keys($againData['data']));

        $proofs = $app->getContainer()?->get(\App\Application\Security\CsrfProofs::class);
        self::assertTrue($proofs->verify($nonce, $data['data']['proof'], time()));
        self::assertTrue($proofs->verify($nonce, $againData['data']['proof'], time()));
    }

    /**
     * Verifies that invalid cookies cannot be reused or returned as credentials
     */
    public function testBadCookieIsReplacedAndAmbiguousCookiesAreRejected(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf');
        $fresh = $app->handle($request->withHeader('Cookie', '__Secure-agent_os_csrf=unsafe'));
        self::assertSame(200, $fresh->getStatusCode());
        self::assertStringStartsWith('__Secure-agent_os_csrf=', $fresh->getHeaderLine('Set-Cookie'));
        $nonce = bin2hex(random_bytes(32));
        $ambiguous = $app->handle($request->withHeader(
            'Cookie',
            '__Secure-agent_os_csrf='.$nonce.'; __Secure-agent_os_csrf='.$nonce
        ));
        self::assertSame(400, $ambiguous->getStatusCode());
        self::assertSame(['status' => 'fail', 'data' => ['fields' => ['cookie' => ['Ambiguous cookie.']]]], json_decode(
            (string) $ambiguous->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
        self::assertSame('no-store', $ambiguous->getHeaderLine('Cache-Control'));
        self::assertSame('', $ambiguous->getHeaderLine('Set-Cookie'));
    }

    /**
     * Verifies that the final FQCN Action dispatches exactly one transport-free query
     */
    public function testActionDispatchesOnceWithoutPassingHttpToTheQuery(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $nonce = bin2hex(random_bytes(32));
        $bus = new class implements QueryBus {
            public int $calls = 0;
            public ?Query $query = null;

            /**
             * @inheritDoc
             */
            public function fetch(Query $query): mixed
            {
                ++$this->calls;
                $this->query = $query;

                return new CsrfProof(bin2hex(random_bytes(32)), 'safe-proof', time() + 900, false);
            }

            /**
             * @inheritDoc
             */
            public function dispatch(QueryMessage $queryMessage): mixed
            {
                throw new \LogicException('The Action must dispatch through fetch.');
            }
        };
        $app->getContainer()?->set(QueryBus::class, static fn (): QueryBus => $bus);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('Cookie', '__Secure-agent_os_csrf='.$nonce);
        $response = $app->handle($request);
        self::assertSame(1, $bus->calls);
        self::assertInstanceOf(GetCsrfProof::class, $bus->query);
        self::assertSame($nonce, $bus->query->nonce);
        self::assertSame(['nonce' => $nonce], $bus->query->toArray());
        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('success', $body['status']);
        self::assertSame('safe-proof', $body['data']['proof']);
        self::assertSame(['proof', 'expires_at'], array_keys($body['data']));
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
    }

    /**
     * Verifies rejected bootstrap transport input never reaches the query bus
     */
    public function testRejectedTransportInputDoesNotDispatch(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $bus = new class implements QueryBus {
            public int $calls = 0;

            /**
             * @inheritDoc
             */
            public function fetch(Query $query): mixed
            {
                ++$this->calls;

                return new CsrfProof(bin2hex(random_bytes(32)), 'safe-proof', time() + 900, false);
            }

            /**
             * @inheritDoc
             */
            public function dispatch(QueryMessage $queryMessage): mixed
            {
                throw new \LogicException('The Action must dispatch through fetch.');
            }
        };
        $app->getContainer()?->set(QueryBus::class, static fn (): QueryBus => $bus);
        $base = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf');
        $cases = [
            [$base->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStream('{"secret":"do-not-echo"}')),
                'body', 'Body is not allowed.'],
            [$base->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStream('{bad-json')),
                'body', 'Body is not allowed.'],
            [$base->withHeader('Content-Type', 'application/json'), 'body', 'Body is not allowed.'],
            [$base->withHeader('Cookie', ['a=b', 'c=d']), 'cookie', 'Invalid cookie.'],
            [$base->withHeader('Cookie', str_repeat('a', 4097)), 'cookie', 'Invalid cookie.'],
            [$base->withHeader('Cookie', '__Secure-agent_os_csrf'), 'cookie', 'Ambiguous cookie.'],
            [$base->withHeader('Cookie', '__Secure-agent_os_csrf=x; __Secure-agent_os_csrf=y'),
                'cookie', 'Ambiguous cookie.']
        ];
        foreach ($cases as [$request, $field, $message]) {
            $response = $app->handle($request);
            self::assertSame(400, $response->getStatusCode());
            self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('', $response->getHeaderLine('Set-Cookie'));
            self::assertSame(
                ['status' => 'fail', 'data' => ['fields' => [$field => [$message]]]],
                json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
            self::assertStringNotContainsString('do-not-echo', (string) $response->getBody());
        }
        self::assertSame(0, $bus->calls);
        self::assertSame(405, $app->handle($base->withMethod('POST'))->getStatusCode());
        self::assertSame(0, $bus->calls);
        self::assertSame(200, $app->handle($base)->getStatusCode());
        self::assertSame(1, $bus->calls);
    }

    /**
     * Verifies HTTPS, exact origin, Fetch Metadata and input restrictions before dispatch
     */
    public function testUnsafeBootstrapRequestsFailClosed(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $bus = new class implements QueryBus {
            public int $calls = 0;

            /**
             * @inheritDoc
             */
            public function fetch(Query $query): mixed
            {
                ++$this->calls;

                return new CsrfProof(bin2hex(random_bytes(32)), 'safe-proof', time() + 900, false);
            }

            /**
             * @inheritDoc
             */
            public function dispatch(QueryMessage $queryMessage): mixed
            {
                throw new \LogicException('The Action must dispatch through fetch.');
            }
        };
        $app->getContainer()?->set(QueryBus::class, static fn (): QueryBus => $bus);
        $factory = new ServerRequestFactory();
        $base = $factory->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf');
        $cases = [
            $factory->createServerRequest('GET', 'http://agent-os.test/api/v1/auth/csrf'),
            $factory->createServerRequest('GET', 'https://other.test/api/v1/auth/csrf'),
            $base->withHeader('Origin', 'https://other.test'),
            $base->withHeader('Origin', 'null'),
            $base->withHeader('Sec-Fetch-Site', 'same-site'),
            $base->withHeader('Sec-Fetch-Site', 'cross-site'),
            $base->withHeader('Sec-Fetch-Site', 'none'),
            $base->withHeader('Access-Control-Request-Method', 'GET'),
            $factory->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf?proof=secret'),
            $base->withHeader('Content-Type', 'text/plain'),
            $base->withHeader('Content-Type', 'application/json'),
            $base->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStream('not-json'))
        ];
        foreach ($cases as $case) {
            $response = $app->handle($case);
            self::assertContains($response->getStatusCode(), [400, 403]);
            self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('', $response->getHeaderLine('Set-Cookie'));
            self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
        }
        self::assertSame(0, $bus->calls);
        $allowed = $app->handle($base->withHeader('Origin', 'https://agent-os.test'));
        self::assertSame(200, $allowed->getStatusCode());
        self::assertSame(1, $bus->calls);
        $preflight = $app->handle($factory->createServerRequest('OPTIONS', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('Origin', 'https://other.test')
            ->withHeader('Access-Control-Request-Method', 'GET'));
        self::assertSame('', $preflight->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame(405, $preflight->getStatusCode());
        $unknown = $app->handle($factory->createServerRequest('GET', 'https://agent-os.test/api/v1/missing'));
        self::assertSame(404, $unknown->getStatusCode());
    }
}
