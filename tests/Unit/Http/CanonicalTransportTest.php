<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Middleware\CanonicalTransport;
use App\Adapter\Http\Middleware\SecurityHeaders;
use App\Adapter\Security\HmacCsrfProofs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Proves direct transport admission and safe browser policy independent of edge assertions
 */
final class CanonicalTransportTest extends TestCase
{
    /**
     * Supplies requests that cannot establish canonical direct authority
     *
     * @return iterable<string, array{string, array<string, string>, array<string, string>}>
     */
    public static function rejected(): iterable
    {
        yield 'HTTP navigation' => ['http://agent-os.test/login', [], []];
        yield 'foreign authority' => ['https://evil.test/login', [], []];
        yield 'wrong port' => ['https://agent-os.test:444/login', [], []];
        yield 'userinfo' => ['https://user@agent-os.test/login', [], []];
        yield 'raw host mismatch' => ['https://agent-os.test/login', [], ['HTTP_HOST' => 'evil.test']];
        yield 'forwarded' => ['https://agent-os.test/login', ['Forwarded' => 'proto=https'], []];
        yield 'forwarded host' => ['https://agent-os.test/login', ['X-Forwarded-Host' => 'evil.test'], []];
        yield 'forwarded proto' => ['https://agent-os.test/login', ['X-Forwarded-Proto' => 'https'], []];
        yield 'original authority' => ['https://agent-os.test/login', ['X-Original-Host' => 'evil.test'], []];
    }

    /**
     * Rejects unsafe authority without redirects, cookies or downstream dispatch
     *
     * @param string $uri
     * @param array<string, string> $headers
     * @param array<string, string> $server
     */
    #[DataProvider('rejected')]
    public function test_that_only_direct_canonical_authority_is_admitted(
        string $uri,
        array $headers,
        array $server
    ): void {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $uri, $server);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $response = $this->transport()->process($request, $handler);
        self::assertSame(421, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Location'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    /**
     * Admits canonical authority without changing URI or cookies and denies CORS response decoration
     */
    public function test_that_canonical_https_retains_response_semantics_inside_the_envelope(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/app');
        $handler = new class implements RequestHandlerInterface {
            /**
             * @inheritDoc
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(401)
                    ->withHeader('Set-Cookie', 'opaque; Secure; HttpOnly; SameSite=Strict')
                    ->withHeader('Access-Control-Allow-Origin', '*')
                    ->withHeader('Access-Control-Allow-Credentials', 'true');
            }
        };
        $transport = $this->transport();
        $inner = new class ($transport, $handler) implements RequestHandlerInterface {
            /**
             * Constructs TransportHandler
             */
            public function __construct(private CanonicalTransport $transport, private RequestHandlerInterface $handler)
            {
            }

            /**
             * @inheritDoc
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->transport->process($request, $this->handler);
            }
        };
        $response = (new SecurityHeaders(true))->process($request, $inner);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('opaque; Secure; HttpOnly; SameSite=Strict', $response->getHeaderLine('Set-Cookie'));
        self::assertSame('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
        $insecure = (new SecurityHeaders(true))->process(
            $request->withUri($request->getUri()->withScheme('http')),
            $handler
        );
        self::assertFalse($insecure->hasHeader('Strict-Transport-Security'));
        foreach (['localhost', 'fight.localhost'] as $host) {
            $local = (new SecurityHeaders(true))->process(
                $request->withUri($request->getUri()->withHost($host)),
                $handler
            );
            self::assertFalse($local->hasHeader('Strict-Transport-Security'));
        }
    }

    /**
     * Creates direct canonical transport with an ephemeral independent key
     */
    private function transport(): CanonicalTransport
    {
        return new CanonicalTransport(new HmacCsrfProofs(bin2hex(random_bytes(32)), 'https://agent-os.test'), true);
    }
}
