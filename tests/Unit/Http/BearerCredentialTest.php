<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\Auth\BearerCredential;
use App\Application\Failure\AuthenticationRequired;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessTokens;

/**
 * Proves single-header extraction and alternative-transport rejection
 */
final class BearerCredentialTest extends TestCase
{
    /**
     * Preserves ordinary request input and the body cursor
     */
    public function testApprovedHeader(): void
    {
        $body = (new StreamFactory())->createStream('{"name":"Safe"}');
        $body->seek(3);
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/owned?name=safe')
            ->withHeader('Authorization', 'Bearer abc.def.ghi')
            ->withCookieParams(['__Secure-csrf' => 'opaque'])
            ->withBody($body);
        self::assertSame('abc.def.ghi', new BearerCredential()->extract($request));
        self::assertSame(3, $body->tell());
        self::assertSame('{"name":"Safe"}', (string) $body);
    }

    /**
     * Rejects raw whitespace that a PSR implementation normalizes
     */
    public function testRawHeaderAmbiguity(): void
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', '/api/v1/owned', [
            'HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi '
        ])->withHeader('Authorization', 'Bearer abc.def.ghi ');
        $this->expectException(AuthenticationRequired::class);
        new BearerCredential()->extract($request);
    }

    /**
     * Rejects actual compact tokens in unnamed alternative sources
     */
    public function testUnnamedAlternativeTokens(): void
    {
        $fixture = new AccessTokens();
        $token = $fixture->signed(json_encode($fixture->claims(), JSON_THROW_ON_ERROR));
        $base = new ServerRequestFactory()->createServerRequest('POST', '/api/v1/owned')
            ->withHeader('Authorization', 'Bearer '.$token);
        $requests = [
            $base->withCookieParams(['unrelated' => $token]),
            $base->withQueryParams(['unrelated' => $token]),
            $base->withBody(new StreamFactory()->createStream(
                json_encode(['unrelated' => $token], JSON_THROW_ON_ERROR)
            )),
            $base->withBody(new StreamFactory()->createStream('{"\\u0061ccess_token":"SECRET"}'))
        ];
        foreach ($requests as $request) {
            try {
                new BearerCredential()->extract($request);
                self::fail('Alternative token accepted.');
            } catch (AuthenticationRequired $error) {
                self::assertSame('Unauthorized.', $error->getMessage());
            }
        }
    }

    /**
     * Fails closed on unreadable transport without retaining its diagnostic
     */
    public function testBodyReadFailure(): void
    {
        $body = $this->createStub(StreamInterface::class);
        $body->method('isSeekable')->willReturn(true);
        $body->method('tell')->willThrowException(new RuntimeException('PRIVATE_STREAM_MARKER'));
        $request = new ServerRequestFactory()->createServerRequest('POST', '/api/v1/owned')
            ->withHeader('Authorization', 'Bearer abc.def.ghi')->withBody($body);
        $this->expectException(AuthenticationRequired::class);
        $this->expectExceptionMessage('Unauthorized.');
        new BearerCredential()->extract($request);
    }

    /**
     * Rejects ambiguous and alternative credentials before any authority query
     *
     * @param array<string> $headers
     * @param string $query
     * @param string $body
     * @param string $cookie
     * @param array<string, mixed> $parsed
     *
     * @phpstan-param list<string> $headers
     */
    #[DataProvider('invalidSources')]
    public function testSourceMatrix(array $headers, string $query, string $body, string $cookie, array $parsed): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/owned'.$query)
            ->withHeader('Cookie', $cookie)
            ->withBody((new StreamFactory())->createStream($body))
            ->withParsedBody($parsed);
        // Exercise unnormalized transport input that Slim's header value object otherwise trims/rejects.
        $transport = $this->createStub(ServerRequestInterface::class);
        $transport->method('getHeader')->willReturn($headers);
        $transport->method('getHeaderLine')->willReturn($cookie);
        $transport->method('getUri')->willReturn($request->getUri());
        $transport->method('getBody')->willReturn($request->getBody());
        $transport->method('getQueryParams')->willReturn($request->getQueryParams());
        $transport->method('getCookieParams')->willReturn($request->getCookieParams());
        $transport->method('getParsedBody')->willReturn($parsed);
        $transport->method('getServerParams')->willReturn([]);
        $this->expectException(AuthenticationRequired::class);
        new BearerCredential()->extract($transport);
    }

    /**
     * Enumerates transport attacks without committing a credential
     *
     * @return iterable<string, array{list<string>, string, string, string, array<string, mixed>}>
     */
    public static function invalidSources(): iterable
    {
        foreach (
            [[], ['Bearer abc.def.ghi', 'Bearer abc.def.ghi'], ['Bearer abc.def.ghi,Bearer abc.def.ghi'],
            ['Basic abc.def.ghi'], ['bearer abc.def.ghi'], [' Bearer abc.def.ghi'], ['Bearer abc.def.ghi '],
            ['Bearer  abc.def.ghi'], ["Bearer\tabc.def.ghi"], ["Bearer abc.def.ghi\n"],
            ['Bearer abc.def.ghi extra'], ['Bearer abc.def.ghi='], ['Bearer '.str_repeat('a', 2049).'.b.c']
            ] as $i => $headers
        ) {
            yield 'header-'.$i => [$headers, '', '', '', []];
        }

        $queries = ['?access_token=secret', '?authorization=secret', '?token=secret', '?access%5Ftoken=secret'];
        foreach ($queries as $i => $query) {
            yield 'query-'.$i => [['Bearer abc.def.ghi'], $query, '', '', []];
        }

        $bodies = ['access_token=secret', '{"access_token":"secret"}', '{"Authorization":"secret"}', 'token=secret'];
        foreach ($bodies as $i => $body) {
            yield 'body-'.$i => [['Bearer abc.def.ghi'], '', $body, '', []];
        }

        yield 'cookie' => [['Bearer abc.def.ghi'], '', '', 'access_token=secret', []];
        yield 'parsed-form' => [['Bearer abc.def.ghi'], '', '', '', ['access_token' => 'secret']];
        yield 'oversized-body' => [['Bearer abc.def.ghi'], '', str_repeat('a', 16385), '', []];
    }
}
