<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Sessions\SessionRevocationResponder;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\CurrentRefreshSessionRevocationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionConflictException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionRevocationReasonRequiredException;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Throwable;

/**
 * Protects the explicit non-enumerating public failure mapping including optimistic conflicts
 */
final class SessionRevocationResponderTest extends TestCase
{
    /**
     * Maps only known outcomes without copying private exception details
     *
     * @param string $class
     *
     * @phpstan-param class-string<Throwable> $class
     */
    #[DataProvider('failures')]
    public function testKnownFailure(string $class): void
    {
        $next = $this->createStub(\Psr\Http\Server\RequestHandlerInterface::class);
        $next->method('handle')->willReturnCallback(fn() => $this->responder()->respond(
            static function () use ($class): void {
                throw new $class('private-sql-token-session-marker');
            }
        ));
        $response = \Tests\Support\ApiErrors::boundary(new \Monolog\Logger('test'))->process(
            (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest(
                'DELETE',
                '/api/v1/me/sessions/target'
            ),
            $next
        );
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $expected = ['status' => 'fail', 'data' => ['message' => 'State conflict.']];
        if ($class === CurrentRefreshSessionRevocationException::class) {
            $expected = ['status' => 'fail', 'data' => [
                'message' => 'Use Logout for the current session.', 'intent' => 'use_logout'
            ]];
        }
        self::assertSame($expected, json_decode((string) $response->getBody(), true));
    }

    /**
     * Creates the actual public response mapper
     */
    private function responder(): SessionRevocationResponder
    {
        return new SessionRevocationResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));
    }

    /**
     * Supplies every allowlisted package failure
     *
     * @return iterable<array{class-string<Throwable>}>
     */
    public static function failures(): iterable
    {
        yield [CurrentRefreshSessionRevocationException::class];
        yield [RefreshSessionConflictException::class];
        yield [RefreshSessionNotFoundException::class];
        yield [SessionAdministrationAuthorizationException::class];
        yield [SessionRevocationReasonRequiredException::class];
    }

    /**
     * Rejects unknown failures for the sanitized system-error boundary rather than asserting a safe conflict
     */
    public function testUnknownFailure(): void
    {
        $this->expectException(LogicException::class);
        $this->responder()->respond(
            static function (): void {
                throw new LogicException('private-storage-marker');
            }
        );
    }
}
