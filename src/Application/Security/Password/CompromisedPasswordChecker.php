<?php

declare(strict_types=1);

namespace App\Application\Security\Password;

use SensitiveParameter;

/**
 * Interface CompromisedPasswordChecker
 *
 * Checks known-common passwords without exposing lookup material
 */
interface CompromisedPasswordChecker
{
    /**
     * Reports membership or fails closed when required local data is unavailable
     */
    public function isCompromised(#[SensitiveParameter] string $candidate): bool;
}
