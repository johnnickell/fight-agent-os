<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

/**
 * Class CsrfCookie
 *
 * Defines the shared browser CSRF cookie name
 */
final class CsrfCookie
{
    public const string NAME = '__Secure-agent_os_csrf';
}
