<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Security;

use App\Adapter\Security\AccessToken\AccessIssuanceClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Proves package-facing time precision without changing the raw clock
 */
final class AccessIssuanceClockTest extends TestCase
{
    /**
     * Floors raw time to its UTC second without rounding into the future
     */
    #[DataProvider('times')]
    public function testWholeSecondTime(string $rawTime, string $expectedTime): void
    {
        $raw = new AccessClock(new DateTimeImmutable($rawTime));
        $clock = new AccessIssuanceClock($raw);
        $issuedAt = $clock->now();
        self::assertSame($expectedTime, $issuedAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('UTC', $issuedAt->getTimezone()->getName());
        self::assertSame($raw->time->getTimestamp(), $issuedAt->getTimestamp());
        self::assertSame($rawTime, $raw->now()->format('Y-m-d\TH:i:s.uP'));

        $raw->time = $raw->time->modify('+1 second');
        self::assertSame($issuedAt->getTimestamp() + 1, $clock->now()->getTimestamp());
        self::assertSame('000000', $clock->now()->format('u'));
    }

    /**
     * Enumerates exact, ordinary fractional and second-boundary inputs
     *
     * @return iterable<string, array{string, string}>
     */
    public static function times(): iterable
    {
        $expected = '2026-09-28T12:00:00.000000+00:00';
        yield 'whole-second' => [$expected, $expected];
        yield 'ordinary-subsecond' => ['2026-09-28T12:00:00.123456+00:00', $expected];
        yield 'end-of-second' => ['2026-09-28T12:00:00.999999+00:00', $expected];
        yield 'offset-clock' => ['2026-09-28T17:30:00.123456+05:30', $expected];
    }
}
