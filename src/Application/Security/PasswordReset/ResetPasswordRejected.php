<?php

declare(strict_types=1);

namespace App\Application\Security\PasswordReset;

use Fight\Common\Application\Error\Exception\RuleViolation;

/**
 * Class ResetPasswordRejected
 *
 * Reports safe shared-policy feedback without retaining candidate material
 */
final class ResetPasswordRejected extends RuleViolation
{
    /**
     * Constructs ResetPasswordRejected
     */
    public function __construct()
    {
        parent::__construct('Password does not meet the shared acceptance policy.');
    }
}
