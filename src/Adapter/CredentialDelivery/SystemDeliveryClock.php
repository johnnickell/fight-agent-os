<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use DateTimeImmutable;
use DateTimeZone;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;

/**
 * Class SystemDeliveryClock
 *
 * Supplies current UTC time to package delivery handlers
 */
final readonly class SystemDeliveryClock implements Clock
{
    /**
     * @inheritDoc
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
