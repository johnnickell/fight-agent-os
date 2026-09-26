<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use App\Application\Security\CsrfProof;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class CsrfProofView
 *
 * Exposes only the approved memory-held CSRF proof and deadline
 */
final readonly class CsrfProofView implements Arrayable
{
    /**
     * Constructs CsrfProofView
     */
    public function __construct(private CsrfProof $result)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['proof' => $this->result->proof, 'expires_at' => $this->result->expiresAt];
    }
}
