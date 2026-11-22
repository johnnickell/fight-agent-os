<?php

declare(strict_types=1);

namespace App\Application\Security\Session;

/**
 * Enum BrowserFamily
 *
 * Enumerates version-free browser presentation families
 */
enum BrowserFamily: string
{
    case CHROME = 'Chrome';
    case EDGE = 'Edge';
    case FIREFOX = 'Firefox';
    case SAFARI = 'Safari';
    case UNKNOWN = 'Unknown';
}
