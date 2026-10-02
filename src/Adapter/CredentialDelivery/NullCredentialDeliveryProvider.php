<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;

/**
 * Class NullCredentialDeliveryProvider
 *
 * Refuses to acknowledge an unsent credential as delivered
 */
final readonly class NullCredentialDeliveryProvider implements CredentialDeliveryProvider
{
    /**
     * @inheritDoc
     */
    public function deliver(CredentialDeliveryInvocation $invocation): CredentialDeliveryOutcome
    {
        return CredentialDeliveryOutcome::RETRYABLE_FAILURE;
    }
}
