<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\User\Security\AccessToken;
use Fight\AccessControl\Application\AccessControl\User\Security\TokenSet;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;

/**
 * Proves cookie transport identity, absolute expiry, rotation and CSRF invalidation without credential JSON
 */
final class BrowserSessionCookiesTest extends TestCase
{
    /**
     * Proves ordinary and remembered cookie persistence and matching deletion identities
     */
    public function testLifecycle(): void
    {
        $clock = new AccessClock();
        $cookies = new BrowserSessionCookies($clock);
        $responder = new SessionResponder(
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory()),
            $cookies
        );
        foreach ([false, true] as $remembered) {
            $tokens = $this->tokens($remembered, $clock->time->modify($remembered ? '+1 year' : '+2 days'));
            $response = $responder->respond($tokens);
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            $issued = $response->getHeader('Set-Cookie');
            self::assertCount(2, $issued);
            foreach ($issued as $cookie) {
                self::assertStringContainsString('; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict', $cookie);
                self::assertStringNotContainsString('Domain=', $cookie);
                self::assertStringNotContainsString('Max-Age=', $cookie);
            }
            $refresh = $tokens->getRefreshCredential()->toString();
            self::assertStringStartsWith(BrowserSessionCookies::REFRESH_NAME.'='.$refresh.';', $issued[0]);
            self::assertStringNotContainsString($refresh, (string) $response->getBody());
            $nonce = explode(';', explode('=', $issued[1], 2)[1], 2)[0];
            self::assertStringNotContainsString($nonce, (string) $response->getBody());
            if ($remembered) {
                $expiry = explode('Expires=', $issued[0], 2)[1];
                self::assertSame(
                    $tokens->getRefreshExpiresAt()->getTimestamp(),
                    new DateTimeImmutable($expiry)->getTimestamp()
                );
            } else {
                self::assertStringNotContainsString('Expires=', $issued[0]);
            }
            // Refresh rotates only the opaque refresh value; it must not invalidate other tabs' nonce.
            $replacement = $responder->refreshed($this->tokens($remembered, $tokens->getRefreshExpiresAt()));
            self::assertCount(1, $replacement->getHeader('Set-Cookie'));
            self::assertNotSame($issued[0], $replacement->getHeader('Set-Cookie')[0]);
        }
        $cleared = $responder->clear(new ResponseFactory()->createResponse(503));
        self::assertSame(503, $cleared->getStatusCode());
        self::assertSame('no-store', $cleared->getHeaderLine('Cache-Control'));
        $deleted = $cleared->getHeader('Set-Cookie');
        self::assertCount(2, $deleted);
        $attributes = '=; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict; ';
        $expired = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';
        foreach ([BrowserSessionCookies::REFRESH_NAME, CsrfCookie::NAME] as $index => $name) {
            self::assertSame(
                $name.$attributes.$expired,
                $deleted[$index]
            );
        }
        self::assertNotSame($cookies->rotateNonce(), $cookies->rotateNonce());
    }

    /**
     * Rejects an invalid package result instead of extending a deadline or transporting unsafe material
     */
    #[DataProvider('invalidDeadlines')]
    public function testDeadlineBounds(bool $remembered, string $offset): void
    {
        $clock = new AccessClock();
        $this->expectException(LogicException::class);
        new BrowserSessionCookies($clock)->refresh($this->tokens($remembered, $clock->time->modify($offset)));
    }

    /**
     * Supplies expired and out-of-policy absolute deadlines
     *
     * @return iterable<array{bool, string}>
     */
    public static function invalidDeadlines(): iterable
    {
        yield [false, '+0 seconds'];
        yield [false, '+2 days +1 microsecond'];
        yield [true, '+1 year +1 microsecond'];
    }

    /**
     * Builds opaque package results with the owning identifier factories
     */
    private function tokens(bool $remembered, DateTimeImmutable $deadline): TokenSet
    {
        return new TokenSet(
            UserId::generate(),
            RefreshSessionId::generate(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $deadline,
            $remembered,
            AccessToken::fromString('synthetic-access'),
            $deadline->modify('+900 seconds')
        );
    }
}
