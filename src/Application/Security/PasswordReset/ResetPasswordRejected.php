<?php

declare(strict_types=1);

namespace App\Application\Security\PasswordReset;

use RuntimeException;

/**
 * Class ResetPasswordRejected
 *
 * Reports safe shared-policy feedback without retaining candidate material
 */
final class ResetPasswordRejected extends RuntimeException
{
    /**
     * Constructs ResetPasswordRejected
     */
    public function __construct()
    {
        parent::__construct('Password does not meet the shared acceptance policy.');
    }
}
