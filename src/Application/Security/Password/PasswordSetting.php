<?php

declare(strict_types=1);

namespace App\Application\Security\Password;

use App\Domain\Security\Password\PasswordRejection;
use App\Domain\Security\Password\PasswordStructure;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Class PasswordSetting
 *
 * Enforces password acceptance across activation, reset and change
 */
final readonly class PasswordSetting
{
    /**
     * Constructs PasswordSetting
     */
    public function __construct(
        private PasswordStructure $passwordStructure,
        private CompromisedPasswordChecker $compromisedPasswordChecker
    ) {
    }

    /**
     * Requires an acceptable new password without retaining its bytes
     */
    public function requireAccepted(#[SensitiveParameter] string $candidate): void
    {
        $rejection = $this->passwordStructure->rejection($candidate);
        if ($rejection !== null) {
            throw new PasswordRejected($rejection);
        }

        try {
            $compromised = $this->compromisedPasswordChecker->isCompromised($candidate);
        } catch (Throwable) {
            throw new RuntimeException('Password checking failed.');
        }
        if ($compromised) {
            throw new PasswordRejected(PasswordRejection::COMMON_PASSWORD);
        }
    }
}
