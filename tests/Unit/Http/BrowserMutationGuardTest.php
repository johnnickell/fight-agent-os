<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Security\Csrf\Clock\CsrfClock;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Exercises secure browser mutation admission before the next handler
 */
final class BrowserMutationGuardTest extends TestCase
{
    private const string ORIGIN = 'https://agent-os.test:8443';
    private const int NOW = 1789999200;

    /**
     * Accepts the exact nondefault ingress authority and a fresh nonce-bound proof
     */
    public function test_that_the_guard_passes_the_original_request(): void
    {
        [$guard, $request] = $this->fixture(self::ORIGIN, ['HTTP_HOST' => 'agent-os.test:8443']);
        $response = (new ResponseFactory())->createResponse(204);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);

        self::assertSame($response, $guard->process($request, $next));
    }

    /**
     * Lists disallowed ingress authorities
     *
     * @return iterable<string, array{mixed}>
     */
    public static function invalid_authorities(): iterable
    {
        yield 'different port' => ['agent-os.test:8444'];
        yield 'missing nondefault port' => ['agent-os.test'];
        yield 'non-string authority' => [8443];
    }

    /**
     * Rejects incongruent raw ingress authority before invoking the handler
     */
    #[DataProvider('invalid_authorities')]
    public function test_that_raw_ingress_authority_must_agree(mixed $rawHost): void
    {
        [$guard, $request] = $this->fixture(self::ORIGIN, ['HTTP_HOST' => $rawHost]);
        $this->assertDenied($guard, $request);
    }

    /**
     * Lists request headers that make the mutation inadmissible
     *
     * @return iterable<string, array{string, string|list<string>}>
     */
    public static function invalid_headers(): iterable
    {
        yield 'forwarded provenance' => ['Forwarded', 'proto=https'];
        yield 'navigation instead of fetch' => ['Sec-Fetch-Mode', 'navigate'];
        yield 'cross-origin fetch' => ['Sec-Fetch-Site', 'cross-site'];
        yield 'ambiguous origin' => ['Origin', [self::ORIGIN, self::ORIGIN]];
        yield 'nonce not bound to proof' => ['Cookie', CsrfCookie::NAME.'='.str_repeat('b', 64)];
        yield 'ineligible body format' => ['Content-Type', 'text/plain'];
        yield 'unsupported content encoding' => ['Content-Encoding', 'gzip'];
    }

    /**
     * Denies unsupported headers without starting endpoint work
     *
     * @param string $name
     * @param string|list<string> $value
     */
    #[DataProvider('invalid_headers')]
    public function test_that_unsafe_headers_do_not_reach_the_handler(string $name, string|array $value): void
    {
        [$guard, $request] = $this->fixture(self::ORIGIN, ['HTTP_HOST' => 'agent-os.test:8443']);
        $this->assertDenied($guard, $request->withHeader($name, $value));
    }

    /**
     * Rejects a proof at its exact expiry without relying on wall time
     */
    public function test_that_a_proof_at_its_expiry_is_denied(): void
    {
        [$guard, $request] = $this->fixture('https://agent-os.test', [], self::NOW);
        $this->assertDenied($guard, $request);
    }

    /**
     * Asserts denial before any work is delegated
     */
    private function assertDenied(BrowserMutationGuard $guard, ServerRequestInterface $request): void
    {
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(PermissionDenied::class);

        $guard->process($request, $next);
    }

    /**
     * Creates a deterministic proof and direct JSON request
     *
     * @param string $origin
     * @param array<string, mixed> $server
     * @param integer $expires
     *
     * @return array{BrowserMutationGuard, ServerRequestInterface}
     */
    private function fixture(string $origin, array $server, int $expires = self::NOW + 60): array
    {
        $proofs = new HmacCsrfProofs(bin2hex(random_bytes(32)), $origin);
        $clock = $this->createStub(CsrfClock::class);
        $clock->method('now')->willReturn(self::NOW);
        $nonce = str_repeat('a', 64);
        $request = (new ServerRequestFactory())->createServerRequest('POST', $origin.'/api/v1/auth/login', $server)
            ->withHeader('Origin', $origin)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cookie', CsrfCookie::NAME.'='.$nonce)
            ->withHeader('X-CSRF-Proof', $proofs->sign($nonce, $expires))
            ->withHeader('Sec-Fetch-Mode', 'cors')
            ->withHeader('Sec-Fetch-Dest', 'empty');

        return [new BrowserMutationGuard($proofs, $clock), $request];
    }
}
