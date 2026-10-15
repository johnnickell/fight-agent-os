<?php

declare(strict_types=1);

namespace App\Application\Security\Password;

use App\Domain\Security\Password\PasswordRejection;
use RuntimeException;

/**
 * Class PasswordRejected
 *
 * Reports a safe rejection without candidate or lookup material
 */
final class PasswordRejected extends RuntimeException
{
    /**
     * Constructs PasswordRejected
     */
    public function __construct(public readonly PasswordRejection $reason)
    {
        parent::__construct('Password rejected: '.$reason->value);
    }
}
