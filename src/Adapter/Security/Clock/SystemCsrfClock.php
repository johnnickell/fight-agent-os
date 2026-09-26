<?php

declare(strict_types=1);

namespace App\Adapter\Security\Clock;

use App\Application\Security\Csrf\Clock\CsrfClock;

/**
 * Class SystemCsrfClock
 *
 * Reads the server UTC second
 */
final readonly class SystemCsrfClock implements CsrfClock
{
    /**
     * @inheritDoc
     */
    public function now(): int
    {
        return time();
    }
}
