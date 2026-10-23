<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Auth\ResetResponseTiming;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Proves response normalization control paths without wall-clock assumptions
 */
final class ResetResponseTimingTest extends TestCase
{
    /**
     * Pads silent, eligible and exceptional work by the same remaining monotonic budget
     *
     * @param integer $elapsed
     * @param boolean $fail
     * @param array $expected
     *
     * @phpstan-param list<int> $expected
     */
    #[DataProvider('paths')]
    public function testBoundedRemainder(int $elapsed, bool $fail, array $expected): void
    {
        $now = 1000;
        $waits = [];
        $timing = new ResetResponseTiming(
            static function () use (&$now): int {
                return $now;
            },
            static function (int $wait) use (&$waits): void {
                $waits[] = $wait;
            }
        );
        $failure = new RuntimeException('Controlled failure.');
        try {
            $timing->normalize(static function () use (&$now, $elapsed, $fail, $failure): void {
                $now += $elapsed;
                if ($fail) {
                    throw $failure;
                }
            });
            self::assertFalse($fail);
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame($expected, $waits);
    }

    /**
     * Supplies short, exact-boundary, over-budget and exceptional work durations
     *
     * @return iterable<array{int, bool, list<int>}>
     */
    public static function paths(): iterable
    {
        yield [0, false, [250_000_000]];
        yield [20_000_000, false, [230_000_000]];
        yield [20_000_000, true, [230_000_000]];
        yield [250_000_000, false, []];
        yield [500_000_000, true, []];
        yield [-1, false, [250_000_000]];
    }
}
