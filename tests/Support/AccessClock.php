<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;

/**
 * Class AccessClock
 *
 * Supplies deterministic security deadlines
 */
final class AccessClock implements Clock
{
    /**
     * Constructs AccessClock
     */
    public function __construct(public DateTimeImmutable $time = new DateTimeImmutable('2026-09-28T12:00:00Z'))
    {
    }

    /**
     * @inheritDoc
     */
    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}
