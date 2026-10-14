<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\ActivationCredentialGenerator;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;

/**
 * Class RandomActivationCredentialGenerator
 *
 * Generates independent 256-bit invitation credentials without diagnostic output
 */
final readonly class RandomActivationCredentialGenerator implements ActivationCredentialGenerator
{
    /**
     * @inheritDoc
     */
    public function generate(): ActivationCredential
    {
        return ActivationCredential::fromString(bin2hex(random_bytes(32)));
    }
}
