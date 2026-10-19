<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use App\Application\Mail\MailDelivery;
use App\Application\Mail\MailMessage;
use App\Application\Mail\MailOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\Common\Application\Templating\TemplateEngine;
use SensitiveParameter;
use Throwable;

/**
 * Class InvitationMailProvider
 *
 * Maps the package invitation capability to generic mail without owning delivery state
 */
final readonly class InvitationMailProvider implements CredentialDeliveryProvider
{
    /**
     * Constructs InvitationMailProvider
     */
    public function __construct(
        private CanonicalCredentialLink $link,
        private TemplateEngine $templates,
        private MailDelivery $mail
    ) {
    }

    /**
     * @inheritDoc
     */
    public function deliver(#[SensitiveParameter] CredentialDeliveryInvocation $invocation): CredentialDeliveryOutcome
    {
        // Never send an activation link for another purpose.
        if ($invocation->getPurpose() !== 'activation') {
            return CredentialDeliveryOutcome::RETRYABLE_FAILURE;
        }

        try {
            $context = ['activationUrl' => $this->link->forCredential($invocation->getCredential())];
            $message = new MailMessage(
                $invocation->getIdempotencyId(),
                $invocation->getEmail(),
                'Your Fight Agent OS invitation',
                $this->templates->render('mail/invitation.txt.twig', $context),
                $this->templates->render('mail/invitation.html.twig', $context)
            );

            return match ($this->mail->deliver($message)) {
                MailOutcome::DELIVERED => CredentialDeliveryOutcome::DELIVERED,
                MailOutcome::RETRYABLE_FAILURE => CredentialDeliveryOutcome::RETRYABLE_FAILURE,
                MailOutcome::PERMANENT_FAILURE => CredentialDeliveryOutcome::PERMANENT_FAILURE
            };
        } catch (Throwable) {
            // Do not retain, chain, log or render an exception that may contain a complete message.
            return CredentialDeliveryOutcome::RETRYABLE_FAILURE;
        }
    }
}
