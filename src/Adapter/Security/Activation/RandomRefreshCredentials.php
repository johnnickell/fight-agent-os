<?php

declare(strict_types=1);

namespace App\Adapter\Security\Activation;

use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;

/**
 * Class RandomRefreshCredentials
 *
 * Supplies independent 256-bit opaque refresh credentials
 */
final readonly class RandomRefreshCredentials implements RefreshCredentialGenerator
{
    /**
     * @inheritDoc
     */
    public function generate(): RefreshCredential
    {
        return RefreshCredential::fromString(bin2hex(random_bytes(32)));
    }
}
