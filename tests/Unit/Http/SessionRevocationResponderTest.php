<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Sessions\SessionRevocationResponder;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\CurrentRefreshSessionRevocationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionConflictException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
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
        $response = $this->responder()->respond(
            static function () use ($class): void {
                throw new $class('private-sql-token-session-marker');
            }
        );
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $expected = ['status' => 'error', 'message' => 'Conflict.'];
        if ($class === CurrentRefreshSessionRevocationException::class) {
            $expected = ['status' => 'error', 'message' => 'Use Logout for the current session.', 'code' => 5801];
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
