<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use App\Adapter\Mail\MailDelivery;
use App\Adapter\Mail\MailMessage;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryAbsent;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindCredentialDeliveryStatus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Templating\TemplateEngine;
use SensitiveParameter;
use Throwable;

/**
 * Class PasswordResetMailProvider
 *
 * Resolves immutable package routing identity and maps invocation-lifetime reset mail
 */
final readonly class PasswordResetMailProvider implements CredentialDeliveryProvider
{
    /**
     * Constructs PasswordResetMailProvider
     */
    public function __construct(
        private QueryBus $queryBus,
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
        if ($invocation->getPurpose() !== 'password_reset') {
            return CredentialDeliveryOutcome::RETRYABLE_FAILURE;
        }

        try {
            /** @var CredentialDeliveryStatusView|CredentialDeliveryAbsent $routing */
            $routing = $this->queryBus->fetch(new FindCredentialDeliveryStatus(
                $invocation->getPurpose(),
                $invocation->getIdempotencyId()
            ));
            if (
                $routing instanceof CredentialDeliveryAbsent
                || $routing->getPurpose() !== $invocation->getPurpose()
                || $routing->getDeliveryId() !== $invocation->getIdempotencyId()
            ) {
                return CredentialDeliveryOutcome::RETRYABLE_FAILURE;
            }

            // Status is advisory, not a claim fence. Only this generation's immutable UserId is consumed.
            $context = ['resetUrl' => $this->link->forPasswordReset(
                $routing->getUserId(),
                $invocation->getCredential()
            )];
            $message = new MailMessage(
                $invocation->getIdempotencyId(),
                $invocation->getEmail(),
                'Reset your Fight Agent OS password',
                $this->templates->render('mail/password-reset.txt.twig', $context),
                $this->templates->render('mail/password-reset.html.twig', $context)
            );

            return $this->mail->deliver($message);
        } catch (Throwable) {
            // Never retain or expose lookup, render or transport diagnostics containing private material.
            return CredentialDeliveryOutcome::RETRYABLE_FAILURE;
        }
    }

    /**
     * Hides private query-bus and transport graphs from ordinary object dumps
     *
     * @return array{mapping: string}
     */
    public function __debugInfo(): array
    {
        return ['mapping' => 'password_reset mail'];
    }
}
