<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Auth\CaptureSessionPresentation;
use App\Application\Sessions\SessionPresentation;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\User\Security\AccessToken;
use Fight\AccessControl\Application\AccessControl\User\Security\TokenSet;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Proves bounded closed presentation parsing and metadata-failure independence
 */
final class CaptureSessionPresentationTest extends TestCase
{
    /**
     * Discards raw versions and unrecognized input rather than reflecting request content
     */
    #[DataProvider('headers')]
    public function testParser(string $header, string $browser, string $device): void
    {
        $descriptor = CaptureSessionPresentation::parse($header);
        self::assertSame($browser, $descriptor->browser->value);
        self::assertSame($device, $descriptor->device->value);
    }

    /**
     * Supplies representative desktop, mobile, tablet, spoofed and oversized input
     *
     * @return iterable<array{string, string, string}>
     */
    public static function headers(): iterable
    {
        yield ['Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36', 'Chrome', 'desktop'];
        yield ['Mozilla/5.0 (X11; Linux) Chrome/130.0 Edg/130.0', 'Edge', 'desktop'];
        yield ['Mozilla/5.0 (Macintosh) Firefox/130.0', 'Firefox', 'desktop'];
        yield ['Mozilla/5.0 (iPhone; Mobile) Version/18.0 Safari/600.0', 'Safari', 'mobile'];
        yield ['Mozilla/5.0 (iPad) Version/18.0 Safari/600.0', 'Safari', 'tablet'];
        yield ['Mozilla/5.0 (Android 14) Chrome/130.0', 'Chrome', 'tablet'];
        yield ['Mozilla/5.0 (Android 14; Mobile) Chrome/130.0', 'Chrome', 'mobile'];
        yield ['Mozilla/5.0 (iPhone) FxiOS/130.0', 'Firefox', 'mobile'];
        yield ['Mozilla/5.0 (iPhone) EdgiOS/130.0', 'Edge', 'mobile'];
        yield ['Mozilla/5.0 (iPhone) CriOS/130.0', 'Chrome', 'mobile'];
        yield ['', 'Unknown', 'unknown'];
        yield ['<script>private-marker</script>', 'Unknown', 'unknown'];
        yield ["Chrome/130\r\nprivate-marker", 'Unknown', 'unknown'];
        yield [str_repeat('x', 513).'Chrome/130', 'Unknown', 'unknown'];
        yield [str_repeat('x', 512), 'Unknown', 'unknown'];
        yield ['unknown é', 'Unknown', 'unknown'];
    }

    /**
     * Captures only the original stable identity and coarse descriptor even when persistence fails
     */
    public function testCaptureFailureDoesNotAffectTokenSet(): void
    {
        $now = new DateTimeImmutable();
        $tokens = new TokenSet(
            UserId::generate(),
            RefreshSessionId::generate(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now->modify('+2 days'),
            false,
            AccessToken::fromString('synthetic.access.only'),
            $now->modify('+15 minutes')
        );
        $store = $this->createMock(SessionPresentation::class);
        $store->expects(self::exactly(2))->method('capture')->willReturnCallback(
            function ($id, $device, $expiry) use ($tokens) {
                self::assertTrue($id->equals($tokens->getRefreshSessionId()));
                self::assertSame($tokens->getRefreshExpiresAt(), $expiry);
                self::assertSame('Unknown', $device->browser->value);
                throw new RuntimeException('private-metadata-marker');
            }
        );
        $capture = new CaptureSessionPresentation($store);
        $request = new ServerRequestFactory()->createServerRequest('POST', '/');
        $capture->capture($tokens, $request);
        $capture->capture($tokens, $request->withHeader('User-Agent', ['Chrome/130', 'Firefox/130']));
        self::assertSame('synthetic.access.only', $tokens->getAccessToken()->toString());
    }
}
