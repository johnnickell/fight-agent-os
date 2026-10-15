<?php

declare(strict_types=1);

namespace App\Application\Mail;

use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * Class MailMessage
 *
 * Protects message content from ordinary diagnostics and persistence
 */
final readonly class MailMessage
{
    private SensitiveParameterValue $content;

    /**
     * Constructs MailMessage
     */
    public function __construct(
        public string $idempotencyId,
        #[SensitiveParameter] EmailAddress $destination,
        #[SensitiveParameter] string $subject,
        #[SensitiveParameter] string $text,
        #[SensitiveParameter] string $html
    ) {
        if (preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/D', $idempotencyId) !== 1) {
            throw new InvalidArgumentException('Invalid mail identity.');
        }
        if ($subject === '' || preg_match('/[\x00-\x1f\x7f]/', $subject) === 1 || $text === '' || $html === '') {
            throw new InvalidArgumentException('Invalid mail content.');
        }
        $this->content = new SensitiveParameterValue([$destination, $subject, $text, $html]);
    }

    /**
     * Reveals content only to the invocation-lifetime transport
     *
     * @return array{EmailAddress, string, string, string}
     */
    public function content(): array
    {
        return $this->content->getValue();
    }

    /**
     * Redacts all private message content
     *
     * @return array{idempotencyId: string, content: string}
     */
    public function __debugInfo(): array
    {
        return ['idempotencyId' => $this->idempotencyId, 'content' => '[REDACTED]'];
    }

    /**
     * Prevents durable copies of invocation material
     *
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('Mail messages cannot be serialized.');
    }
}
