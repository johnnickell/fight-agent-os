<?php

declare(strict_types=1);

namespace App\Application\Security\Csrf\Clock;

/**
 * Interface CsrfClock
 *
 * Supplies the current UTC second for CSRF proof deadlines
 */
interface CsrfClock
{
    /**
     * Returns the current UTC second
     */
    public function now(): int;
}
