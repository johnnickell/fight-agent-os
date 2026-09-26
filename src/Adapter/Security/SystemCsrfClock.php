<?php

declare(strict_types=1);

namespace App\Adapter\Security;

use App\Application\Security\CsrfClock;

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
