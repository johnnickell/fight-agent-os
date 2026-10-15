<?php

declare(strict_types=1);

namespace App\Domain\Security\Password;

/**
 * Enum PasswordRejection
 *
 * Enumerates safe password rejection reasons without candidate material
 */
enum PasswordRejection: string
{
    case INVALID_UTF8 = 'invalid_utf8';
    case LENGTH = 'length';
    case UPPERCASE_REQUIRED = 'uppercase_required';
    case LOWERCASE_REQUIRED = 'lowercase_required';
    case NUMBER_REQUIRED = 'number_required';
    case SYMBOL_REQUIRED = 'symbol_required';
    case COMMON_PASSWORD = 'common_password';
}
