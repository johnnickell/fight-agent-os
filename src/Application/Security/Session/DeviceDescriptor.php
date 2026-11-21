<?php

declare(strict_types=1);

namespace App\Application\Security\Session;

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
        public BrowserFamily $browserFamily = BrowserFamily::UNKNOWN,
        public DeviceClass $deviceClass = DeviceClass::UNKNOWN
    ) {
    }
}
