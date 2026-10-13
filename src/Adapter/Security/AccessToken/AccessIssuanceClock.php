<?php

declare(strict_types=1);

namespace App\Adapter\Security\AccessToken;

use DateTimeImmutable;
use DateTimeZone;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;

/**
 * Class AccessIssuanceClock
 *
 * Supplies whole-second time before package authentication constructs claims and expiry
 */
final readonly class AccessIssuanceClock implements Clock
{
    /**
     * Constructs AccessIssuanceClock
     */
    public function __construct(private Clock $clock)
    {
    }

    /**
     * @inheritDoc
     */
    public function now(): DateTimeImmutable
    {
        $now = $this->clock->now();

        return $now->setTimestamp($now->getTimestamp())->setTimezone(new DateTimeZone('UTC'));
    }
}
