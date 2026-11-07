<?php

declare(strict_types=1);

namespace App\Application\Security\PasswordChange;

use Fight\Common\Application\Error\Exception\RuleViolation;

/**
 * Class PasswordChangePolicyRejected
 *
 * Reports replacement rejection without retaining candidate material
 */
final class PasswordChangePolicyRejected extends RuleViolation
{
    /**
     * Constructs PasswordChangePolicyRejected
     */
    public function __construct()
    {
        parent::__construct('Password policy rejected.');
    }
}
