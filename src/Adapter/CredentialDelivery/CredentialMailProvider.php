<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use SensitiveParameter;

/**
 * Class CredentialMailProvider
 *
 * Selects fixed mail mappings without resolving code from persisted work
 */
final readonly class CredentialMailProvider implements CredentialDeliveryProvider
{
    /**
     * Constructs CredentialMailProvider
     */
    public function __construct(
        private InvitationMailProvider $invitations,
        private PasswordResetMailProvider $passwordResets
    ) {
    }

    /**
     * @inheritDoc
     */
    public function deliver(#[SensitiveParameter] CredentialDeliveryInvocation $invocation): CredentialDeliveryOutcome
    {
        return match ($invocation->getPurpose()) {
            'activation' => $this->invitations->deliver($invocation),
            'password_reset' => $this->passwordResets->deliver($invocation),
            default => CredentialDeliveryOutcome::RETRYABLE_FAILURE
        };
    }
}
