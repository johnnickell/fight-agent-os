<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Middleware\Api\NativeApiFailures;
use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpGoneException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpTooManyRequestsException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Throwable;

/**
 * Exercises only the explicit API failure translation and leaves unrelated exceptions alone
 */
final class NativeApiFailuresTest extends TestCase
{
    /**
     * Lists native API failures and their public status codes
     *
     * @return iterable<string, array{class-string<HttpException>, int}>
     */
    public static function native_failures(): iterable
    {
        yield 'invalid request' => [HttpBadRequestException::class, 400];
        yield 'authentication' => [HttpUnauthorizedException::class, 401];
        yield 'permission' => [HttpForbiddenException::class, 403];
        yield 'missing route' => [HttpNotFoundException::class, 404];
        yield 'wrong method' => [HttpMethodNotAllowedException::class, 405];
        yield 'removed route' => [HttpGoneException::class, 410];
        yield 'rate limit' => [HttpTooManyRequestsException::class, 429];
    }

    /**
     * Translates only approved status and protocol metadata, never framework messages
     *
     * @param string $type
     * @param integer $status
     *
     * @phpstan-param class-string<HttpException> $type
     */
    #[DataProvider('native_failures')]
    public function test_that_native_failure_becomes_safe_http_failure(string $type, int $status): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/example');
        $failure = new $type($request, 'internal description');
        if ($failure instanceof HttpMethodNotAllowedException) {
            $failure->setAllowedMethods(['GET', 'HEAD']);
        }
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($failure);

        try {
            (new NativeApiFailures())->process($request, $next);
            self::fail('The middleware swallowed the native failure.');
        } catch (SafeHttpFailure $safe) {
            self::assertSame($status, $safe->status());
            self::assertSame('', $safe->getMessage());
            self::assertSame($status === 401 ? 'Bearer' : null, $safe->metadata()?->challenge);
            self::assertSame($status === 405 ? 'GET, HEAD' : null, $safe->metadata()?->allow);
        }
    }

    /**
     * Preserves the handler's success response without adapting it
     */
    public function test_that_success_is_preserved(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api');
        $response = (new ResponseFactory())->createResponse(204);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);

        self::assertSame($response, (new NativeApiFailures())->process($request, $next));
    }

    /**
     * Does not infer public outcomes from arbitrary numeric exception codes
     */
    public function test_that_unrecognized_api_fault_is_not_translated(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/example');
        $this->assertUnchanged($request, new RuntimeException('internal failure', 404));
    }

    /**
     * Leaves a non-API route to its own error renderer
     */
    public function test_that_non_api_failure_is_not_translated(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api-help');
        $this->assertUnchanged($request, new HttpNotFoundException($request));
    }

    /**
     * Checks the exact exception identity at the caller boundary
     */
    private function assertUnchanged(\Psr\Http\Message\ServerRequestInterface $request, Throwable $failure): void
    {
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($failure);
        try {
            (new NativeApiFailures())->process($request, $next);
        } catch (Throwable $observed) {
            self::assertSame($failure, $observed);

            return;
        }
        self::fail('The middleware swallowed the original failure.');
    }
}
