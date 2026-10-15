<?php

declare(strict_types=1);

namespace App\Adapter\Mail;

use App\Application\Mail\MailDelivery;
use App\Application\Mail\MailMessage;
use App\Application\Mail\MailOutcome;
use InvalidArgumentException;
use SensitiveParameter;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Class CatcherMailDelivery
 *
 * Submits private local mail and clears the invocation-scoped SMTP session on every outcome
 */
final readonly class CatcherMailDelivery implements MailDelivery
{
    /**
     * Constructs CatcherMailDelivery
     */
    public function __construct(private SmtpTransport $transport, string $environment)
    {
        if (!in_array($environment, ['test', 'development', 'local'], true)) {
            throw new InvalidArgumentException('The mail catcher is restricted to local environments.');
        }
    }

    /**
     * @inheritDoc
     */
    public function deliver(#[SensitiveParameter] MailMessage $message): MailOutcome
    {
        try {
            [$destination, $subject, $text, $html] = $message->content();
            $email = (new Email())
                ->from('invitations@example.test')
                ->to($destination->toString())
                ->subject($subject)
                ->text($text, 'utf-8')
                ->html($html, 'utf-8');
            $headers = $email->getHeaders();
            $headers->addIdHeader('Message-ID', $message->idempotencyId.'@mail.agent-os.invalid');
            $headers->addTextHeader('Auto-Submitted', 'auto-generated');
            $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
            $headers->addTextHeader('Referrer-Policy', 'no-referrer');

            return $this->transport->send($email) === null ? MailOutcome::RETRYABLE_FAILURE : MailOutcome::DELIVERED;
        } catch (Throwable) {
            return MailOutcome::RETRYABLE_FAILURE;
        } finally {
            try {
                $this->transport->stop();
            } catch (Throwable) {
                // Cleanup must not surface private SMTP diagnostics or replace the accepted result.
            } finally {
                $this->transport->getStream()->terminate();
                $this->transport->getStream()->getDebug();
            }
        }
    }

    /**
     * Hides mutable SMTP diagnostics from ordinary object dumps
     *
     * @return array{transport: string}
     */
    public function __debugInfo(): array
    {
        return ['transport' => 'disposable local catcher'];
    }
}
