<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Application\Mail\MailMessage;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves invocation-lifetime mail content cannot enter ordinary diagnostic or serialized records
 */
final class MailMessageTest extends TestCase
{
    /**
     * Reveals content explicitly while redacting ordinary inspection
     */
    public function testProtectsPrivateMailAndRefusesSerialization(): void
    {
        $id = ActivationDeliveryId::generate()->toString();
        $destination = EmailAddress::fromString('private@example.test');
        $message = new MailMessage($id, $destination, 'Private subject', 'Private text', '<p>Private HTML</p>');
        self::assertTrue(
            $message->content() === [$destination, 'Private subject', 'Private text', '<p>Private HTML</p>']
        );
        self::assertSame(['idempotencyId' => $id, 'content' => '[REDACTED]'], $message->__debugInfo());
        foreach ([print_r($message, true), var_export($message, true)] as $dump) {
            foreach (['private@example.test', 'Private subject', 'Private text', 'Private HTML'] as $private) {
                self::assertStringNotContainsString($private, $dump);
            }
        }
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Mail messages cannot be serialized.');
        serialize($message);
    }

    /**
     * Rejects header injection or incomplete content with a fixed diagnostic
     */
    #[DataProvider('invalidContent')]
    public function testRejectsInvalidMail(string $id, string $subject, string $text, string $html): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MailMessage($id, EmailAddress::fromString('private@example.test'), $subject, $text, $html);
    }

    /**
     * Supplies invalid generic message identities and content
     *
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalidContent(): iterable
    {
        yield 'missing identity' => ['', 'Subject', 'Text', '<p>HTML</p>'];
        yield 'unsafe identity' => ["id\r\nBcc: other@example.test", 'Subject', 'Text', '<p>HTML</p>'];
        yield 'missing subject' => ['stable-id', '', 'Text', '<p>HTML</p>'];
        yield 'header injection' => ['stable-id', "Subject\r\nBcc: other@example.test", 'Text', '<p>HTML</p>'];
        yield 'missing text' => ['stable-id', 'Subject', '', '<p>HTML</p>'];
        yield 'missing html' => ['stable-id', 'Subject', 'Text', ''];
    }
}
