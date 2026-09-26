<?php

declare(strict_types=1);

namespace App\Application\Security;

/**
 * Interface CsrfNonceGenerator
 *
 * Generates unpredictable CSRF nonces
 */
interface CsrfNonceGenerator
{
    /**
     * Returns a canonical 256-bit nonce
     */
    public function generate(): string;
}
