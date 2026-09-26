<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Middleware\Api\Auth\CsrfBootstrapGuard;
use App\Adapter\Security\HmacCsrfProofs;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Class CsrfBootstrapGuardTest
 *
 * Exercises the public bootstrap's origin and browser-request policy
 */
final class CsrfBootstrapGuardTest extends TestCase
{
    /**
     * Lists untrusted browser request conditions
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function untrusted_requests(): iterable
    {
        yield 'insecure scheme' => ['http://agent-os.test/api/v1/auth/csrf', '', ''];
        yield 'wrong host' => ['https://other.test/api/v1/auth/csrf', '', ''];
        yield 'cross site' => ['https://agent-os.test/api/v1/auth/csrf', 'Sec-Fetch-Site', 'cross-site'];
        yield 'untrusted origin' => ['https://agent-os.test/api/v1/auth/csrf', 'Origin', 'https://other.test'];
    }

    /**
     * Rejects unsafe requests before the next handler
     */
    #[DataProvider('untrusted_requests')]
    public function test_that_untrusted_request_is_forbidden(string $url, string $header, string $value): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $url);
        if ($header !== '') {
            $request = $request->withHeader($header, $value);
        }
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException(new \LogicException('Untrusted input reached the Action.'));

        $response = $this->guard()->process($request, $next);

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * Rejects query strings on the bootstrap route
     */
    public function test_that_query_string_is_rejected(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'https://agent-os.test/api/v1/auth/csrf?x=1'
        );
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException(new \LogicException('Untrusted input reached the Action.'));

        $response = $this->guard()->process($request, $next);

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * Allows same-origin HTTPS browser requests
     */
    public function test_that_trusted_request_reaches_next_handler(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf');
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willReturn((new ResponseFactory())->createResponse(204));

        $response = $this->guard()->process($request, $next);

        self::assertSame(204, $response->getStatusCode());
    }

    /**
     * Builds the guard with a configured exact origin
     */
    private function guard(): CsrfBootstrapGuard
    {
        return new CsrfBootstrapGuard(
            new HmacCsrfProofs(bin2hex(random_bytes(32)), 'https://agent-os.test'),
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory())
        );
    }
}
