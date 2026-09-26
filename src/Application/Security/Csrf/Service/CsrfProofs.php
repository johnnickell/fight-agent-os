<?php

declare(strict_types=1);

namespace App\Application\Security\Csrf\Service;

/**
 * Interface CsrfProofs
 *
 * Authenticates short-lived nonce-bound CSRF proofs
 */
interface CsrfProofs
{
    /**
     * Creates a MAC-bound proof for a nonce and expiry at the configured browser origin
     */
    public function sign(string $nonce, int $expiresAt): string;

    /**
     * Verifies a proof against its cookie nonce and current UTC second
     */
    public function verify(string $nonce, string $proof, int $now): bool;
}
