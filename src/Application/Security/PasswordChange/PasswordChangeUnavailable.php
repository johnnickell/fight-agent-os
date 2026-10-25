<?php

declare(strict_types=1);

namespace App\Application\Security\PasswordChange;

use RuntimeException;

/**
 * Class PasswordChangeUnavailable
 *
 * Discards secret-bearing infrastructure causes and preserves outcome uncertainty
 */
final class PasswordChangeUnavailable extends RuntimeException
{
    /**
     * Constructs PasswordChangeUnavailable
     */
    public function __construct()
    {
        parent::__construct('Password change unavailable.');
    }
}
