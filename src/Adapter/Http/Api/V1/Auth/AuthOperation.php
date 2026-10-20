<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

/**
 * Enum AuthOperation
 *
 * Defines the bounded browser authentication endpoint purposes
 */
enum AuthOperation: string
{
    case LOGIN = 'login';
    case REFRESH = 'refresh';
    case LOGOUT = 'logout';
}
