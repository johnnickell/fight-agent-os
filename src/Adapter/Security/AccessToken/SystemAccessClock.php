<?php

declare(strict_types=1);

namespace App\Adapter\Security\AccessToken;

use DateTimeImmutable;
use DateTimeZone;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;

/**
 * Class SystemAccessClock
 *
 * Supplies the UTC server clock for access authentication
 */
final readonly class SystemAccessClock implements Clock
{
    /**
     * @inheritDoc
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
