<?php

declare(strict_types=1);

namespace App\Application\Security\Activation;

use App\Domain\Security\Password\PasswordRejection;
use RuntimeException;

/**
 * Class ActivationPasswordRejected
 *
 * Carries only a safe password rejection reason
 */
final class ActivationPasswordRejected extends RuntimeException
{
    /**
     * Constructs ActivationPasswordRejected
     */
    public function __construct(public readonly PasswordRejection $reason)
    {
        parent::__construct('Choose a different password.');
    }
}
