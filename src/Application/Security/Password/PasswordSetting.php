<?php

declare(strict_types=1);

namespace App\Application\Security\Password;

use App\Domain\Security\Password\PasswordRejection;
use App\Domain\Security\Password\PasswordStructure;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Class PasswordSetting
 *
 * Shares acceptance and hashing across activation, reset and change
 */
final readonly class PasswordSetting implements PasswordHasher
{
    /**
     * Constructs PasswordSetting
     */
    public function __construct(
        private PasswordStructure $structure,
        private CompromisedPasswordChecker $compromisedPasswords,
        private PasswordHasher $hasher
    ) {
    }

    /**
     * Returns a safe rejection or accepts without retaining candidate material
     */
    public function rejection(#[SensitiveParameter] string $candidate): ?PasswordRejection
    {
        $rejection = $this->structure->rejection($candidate);
        if ($rejection !== null) {
            return $rejection;
        }

        try {
            return $this->compromisedPasswords->isCompromised($candidate) ? PasswordRejection::COMMON_PASSWORD : null;
        } catch (Throwable) {
            throw new RuntimeException('Password checking failed.');
        }
    }

    /**
     * Creates a hash only for an accepted password and returns sensitive persistence material
     *
     * @throws PasswordRejected
     * @throws RuntimeException
     */
    public function hash(#[SensitiveParameter] string $password): string
    {
        $rejection = $this->rejection($password);
        if ($rejection !== null) {
            throw new PasswordRejected($rejection);
        }

        try {
            return $this->hasher->hash($password);
        } catch (Throwable) {
            throw new RuntimeException('Password hashing failed.');
        }
    }
}
