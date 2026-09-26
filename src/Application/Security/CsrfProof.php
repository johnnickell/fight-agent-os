<?php

declare(strict_types=1);

namespace App\Application\Security;

/**
 * Class CsrfProof
 *
 * Carries the transport-free bootstrap result
 */
final readonly class CsrfProof
{
    /**
     * Constructs CsrfProof
     */
    public function __construct(
        public string $nonce,
        public string $proof,
        public int $expiresAt,
        public bool $newNonce
    ) {
    }
}
