<?php

declare(strict_types=1);

namespace App\Application\Security\PasswordReset;

use RuntimeException;

/**
 * Class ResetUnavailable
 *
 * Discards secret-bearing infrastructure causes and preserves outcome uncertainty
 */
final class ResetUnavailable extends RuntimeException
{
    /**
     * Constructs ResetUnavailable
     */
    public function __construct()
    {
        parent::__construct('Password reset unavailable.');
    }
}
