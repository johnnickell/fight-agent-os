<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use App\Domain\Security\Csrf\Query\CsrfProofView;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class CsrfBootstrapData
 *
 * Maps a query view to the approved public JSON fields
 */
final readonly class CsrfBootstrapData implements Arrayable
{
    /**
     * Constructs CsrfBootstrapData
     */
    public function __construct(private CsrfProofView $result)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['proof' => $this->result->proof->value, 'expires_at' => $this->result->proof->expiresAt];
    }
}
