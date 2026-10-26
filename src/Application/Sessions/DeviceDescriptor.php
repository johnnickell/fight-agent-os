<?php

declare(strict_types=1);

namespace App\Application\Sessions;

/**
 * Class DeviceDescriptor
 *
 * Carries closed presentation labels rather than arbitrary request or persistence strings
 */
final readonly class DeviceDescriptor
{
    /**
     * Constructs DeviceDescriptor
     */
    public function __construct(
        public BrowserFamily $browser = BrowserFamily::UNKNOWN,
        public DeviceClass $device = DeviceClass::UNKNOWN
    ) {
    }
}
