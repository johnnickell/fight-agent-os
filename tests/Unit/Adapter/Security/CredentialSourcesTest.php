<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Security;

use App\Adapter\CredentialDelivery\RandomPasswordResetCredentialGenerator;
use App\Adapter\CredentialDelivery\SystemDeliveryClock;
use App\Adapter\Security\AccessToken\SystemAccessClock;
use App\Adapter\Security\Activation\RandomRefreshCredentials;
use App\Adapter\Security\Clock\SystemCsrfClock;
use App\Adapter\Security\RandomCsrfNonceGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the formats and independence of production credential and time sources
 */
final class CredentialSourcesTest extends TestCase
{
    /**
     * Returns fresh 256-bit lowercase-hex material for each independent request
     */
    #[DataProvider('credentialSources')]
    public function testFreshCredentialFormat(string $source): void
    {
        $generate = match ($source) {
            'reset'    => static fn (): string => new RandomPasswordResetCredentialGenerator()->generate()->toString(),
            'refresh'  => static fn (): string => new RandomRefreshCredentials()->generate()->toString(),
            default    => new RandomCsrfNonceGenerator()->generate(...)
        };
        $first = $generate();
        $second = $generate();
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $first);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $second);
        self::assertNotSame($first, $second);
    }

    /**
     * Supplies one case per credential capability
     *
     * @return iterable<string, array{string}>
     */
    public static function credentialSources(): iterable
    {
        yield 'password reset' => ['reset'];
        yield 'refresh' => ['refresh'];
        yield 'CSRF' => ['csrf'];
    }

    /**
     * Keeps delivery and token instants in UTC and CSRF deadlines in Unix seconds
     */
    public function testClockUnits(): void
    {
        $before = time();
        $access = new SystemAccessClock()->now();
        $delivery = new SystemDeliveryClock()->now();
        $csrf = new SystemCsrfClock()->now();
        $after = time();

        self::assertSame('UTC', $access->getTimezone()->getName());
        self::assertSame('UTC', $delivery->getTimezone()->getName());
        $timestamps = [$access->getTimestamp(), $delivery->getTimestamp(), $csrf];
        self::assertGreaterThanOrEqual($before, min($timestamps));
        self::assertLessThanOrEqual($after, max($timestamps));
    }
}
