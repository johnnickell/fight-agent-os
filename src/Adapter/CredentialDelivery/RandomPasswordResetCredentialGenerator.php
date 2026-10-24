<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetCredentialGenerator;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;

/**
 * Class RandomPasswordResetCredentialGenerator
 *
 * Generates independent 256-bit reset credentials without diagnostic output
 */
final readonly class RandomPasswordResetCredentialGenerator implements PasswordResetCredentialGenerator
{
    /**
     * @inheritDoc
     */
    public function generate(): PasswordResetCredential
    {
        return PasswordResetCredential::fromString(bin2hex(random_bytes(32)));
    }
}
