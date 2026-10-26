<?php

declare(strict_types=1);

namespace App\Application\Sessions;

/**
 * Enum DeviceClass
 *
 * Enumerates coarse device presentation classes
 */
enum DeviceClass: string
{
    case DESKTOP = 'desktop';
    case MOBILE = 'mobile';
    case TABLET = 'tablet';
    case UNKNOWN = 'unknown';
}
