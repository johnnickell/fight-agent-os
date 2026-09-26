<?php

declare(strict_types=1);

namespace App\Domain\Security\Csrf\Query;

use App\Domain\Security\Csrf\CsrfProof;
use InvalidArgumentException;

/**
 * Class CsrfProofView
 *
 * Carries the transport-free query result for cookie and proof presentation
 */
final readonly class CsrfProofView
{
    /**
     * Constructs CsrfProofView
     */
    public function __construct(public string $nonce, public CsrfProof $proof, public bool $newNonce)
    {
        if (preg_match('/\A[0-9a-f]{64}\z/D', $nonce) !== 1) {
            throw new InvalidArgumentException('Invalid CSRF nonce.');
        }
    }
}
