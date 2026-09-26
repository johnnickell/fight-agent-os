<?php

declare(strict_types=1);

namespace App\Adapter\Security;

use App\Application\Security\CsrfNonceGenerator;

/**
 * Class RandomCsrfNonceGenerator
 *
 * Generates cryptographically random browser-session nonces
 */
final readonly class RandomCsrfNonceGenerator implements CsrfNonceGenerator
{
    /**
     * @inheritDoc
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
