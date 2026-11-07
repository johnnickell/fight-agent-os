<?php

declare(strict_types=1);

namespace App\Application\Security\Activation;

use App\Domain\Security\Password\PasswordRejection;
use Fight\Common\Application\Error\Exception\RuleViolation;

/**
 * Class ActivationPasswordRejected
 *
 * Carries only a safe password rejection reason
 */
final class ActivationPasswordRejected extends RuleViolation
{
    /**
     * Constructs ActivationPasswordRejected
     */
    public function __construct(public readonly PasswordRejection $reason)
    {
        parent::__construct('Choose a different password.');
    }
}
