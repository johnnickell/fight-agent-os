<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use Closure;

/**
 * Class ResetResponseTiming
 *
 * Pads admitted reset work to a fixed monotonic budget without extending slow work
 */
final readonly class ResetResponseTiming
{
    private const int BUDGET_NANOSECONDS = 250_000_000;

    /**
     * Constructs ResetResponseTiming
     *
     * @param Closure $now
     * @param Closure $wait
     *
     * @phpstan-param Closure(): int $now
     * @phpstan-param Closure(int): void $wait
     */
    public function __construct(private Closure $now, private Closure $wait)
    {
    }

    /**
     * Applies the same bounded remainder to successful, silent and exceptional dispatch paths
     */
    public function normalize(callable $work): void
    {
        $started = ($this->now)();
        try {
            $work();
        } finally {
            $elapsed = max(0, ($this->now)() - $started);
            $remaining = self::BUDGET_NANOSECONDS - $elapsed;
            if ($remaining > 0) {
                ($this->wait)($remaining);
            }
        }
    }
}
