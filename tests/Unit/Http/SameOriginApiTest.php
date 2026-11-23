<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Middleware\Api\SameOriginApi;
use App\Adapter\Security\HmacCsrfProofs;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Proves cross-origin restrictions apply to API paths without changing unrelated routes
 */
final class SameOriginApiTest extends TestCase
{
    /**
     * Lists headers the API does not accept
     *
     * @return iterable<string, array{string, string|list<string>}>
     */
    public static function denied_headers(): iterable
    {
        yield 'different origin' => ['Origin', 'https://other.test'];
        yield 'multiple origin headers' => ['Origin', ['https://agent-os.test', 'https://agent-os.test']];
        yield 'method preflight' => ['Access-Control-Request-Method', 'POST'];
        yield 'header preflight' => ['Access-Control-Request-Headers', 'authorization'];
        yield 'same-site is not same-origin' => ['Sec-Fetch-Site', 'same-site'];
    }

    /**
     * Denies cross-origin API access before reaching any endpoint
     *
     * @param string $name
     * @param string|list<string> $value
     */
    #[DataProvider('denied_headers')]
    public function test_that_cross_origin_api_request_is_rejected(string $name, string|array $value): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api')
            ->withHeader($name, $value);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(PermissionDenied::class);

        $this->middleware()->process($request, $next);
    }

    /**
     * Passes same-origin and non-browser API requests without modifying them
     */
    #[DataProvider('permitted_requests')]
    public function test_that_permitted_request_is_unchanged(string $path, ?string $origin): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test'.$path);
        if ($origin !== null) {
            $request = $request->withHeader('Origin', $origin);
        }
        $response = (new ResponseFactory())->createResponse(204);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);

        self::assertSame($response, $this->middleware()->process($request, $next));
    }

    /**
     * Lists requests unaffected by the cross-origin API restriction
     *
     * @return iterable<string, array{string, ?string}>
     */
    public static function permitted_requests(): iterable
    {
        yield 'trusted origin' => ['/api/v1/me', 'https://agent-os.test'];
        yield 'no origin' => ['/api/v1/me', null];
        yield 'non-API route' => ['/api-help', 'https://other.test'];
    }

    /**
     * Uses the explicit trusted site origin
     */
    private function middleware(): SameOriginApi
    {
        return new SameOriginApi(new HmacCsrfProofs(bin2hex(random_bytes(32)), 'https://agent-os.test'));
    }
}
