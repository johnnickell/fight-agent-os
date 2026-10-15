<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Adapter\Mail\CatcherMailDelivery;
use App\Application\Mail\MailMessage;
use App\Application\Mail\MailOutcome;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Proves MIME mapping, private failure handling and session cleanup without opening a socket
 */
final class CatcherMailDeliveryTest extends TestCase
{
    /**
     * Submits both alternatives with stable identity and privacy headers without retaining content
     */
    public function testMapsPrivateMailAndClosesSmtpSession(): void
    {
        $transport = $this->getMockBuilder(SmtpTransport::class)->onlyMethods(['send', 'stop'])->getMock();
        $id = ActivationDeliveryId::generate()->toString();
        $checks = [];
        $transport->method('send')->willReturnCallback(static function (RawMessage $raw) use (
            $id,
            &$checks
        ): SentMessage {
            if (!$raw instanceof Email) {
                throw new RuntimeException('Expected a MIME email.');
            }
            $checks = [
                $raw->getFrom()[0]->getAddress() === 'invitations@example.test',
                $raw->getTo()[0]->getAddress() === 'private@example.test',
                $raw->getSubject() === 'Invitation',
                $raw->getTextBody() === 'synthetic-secret-text',
                $raw->getHtmlBody() === '<p>synthetic-secret-html</p>',
                $raw->getHeaders()->get('Message-ID')?->getBodyAsString() === '<'.$id.'@mail.agent-os.invalid>',
                $raw->getHeaders()->get('Auto-Submitted')?->getBodyAsString() === 'auto-generated',
                $raw->getHeaders()->get('X-Auto-Response-Suppress')?->getBodyAsString() === 'All',
                $raw->getHeaders()->get('Referrer-Policy')?->getBodyAsString() === 'no-referrer'
            ];

            return new SentMessage($raw, Envelope::create($raw));
        });
        $stops = 0;
        $transport->method('stop')->willReturnCallback(static function () use (&$stops): void {
            ++$stops;
        });
        $adapter = new CatcherMailDelivery($transport, 'development');
        self::assertSame(MailOutcome::DELIVERED, $adapter->deliver($this->message($id)));
        self::assertSame(array_fill(0, 9, true), $checks);
        self::assertSame(1, $stops);
        self::assertSame('', $transport->getStream()->getDebug());
        self::assertSame(['transport' => 'disposable local catcher'], $adapter->__debugInfo());
        self::assertStringNotContainsString('synthetic-secret', print_r($adapter, true));
    }

    /**
     * Discards exceptions and rejected sends while still clearing the SMTP session
     */
    #[DataProvider('failures')]
    public function testFailsSafelyAndCleansSession(bool $throws, bool $cleanupThrows): void
    {
        $transport = $this->getMockBuilder(SmtpTransport::class)->onlyMethods(['send', 'stop'])->getMock();
        $transport->method('send')->willReturnCallback(static function () use ($throws): ?SentMessage {
            if ($throws) {
                throw new RuntimeException('synthetic-secret-provider-diagnostic');
            }

            return null;
        });
        $stops = 0;
        $transport->method('stop')->willReturnCallback(static function () use (&$stops, $cleanupThrows): void {
            ++$stops;
            if ($cleanupThrows) {
                throw new RuntimeException('synthetic-secret-cleanup-diagnostic');
            }
        });
        $adapter = new CatcherMailDelivery($transport, 'test');
        self::assertSame(MailOutcome::RETRYABLE_FAILURE, $adapter->deliver($this->message('stable-id')));
        self::assertSame(1, $stops);
        self::assertSame('', $transport->getStream()->getDebug());
        self::assertStringNotContainsString('synthetic-secret', print_r($adapter, true));
    }

    /**
     * Supplies rejection and failure outcomes including failed disconnect
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function failures(): iterable
    {
        yield 'rejected' => [false, false];
        yield 'send failed' => [true, false];
        yield 'disconnect failed' => [true, true];
    }

    /**
     * Refuses catcher use outside explicitly local environments
     */
    public function testRefusesProductionUse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CatcherMailDelivery(new SmtpTransport(), 'production');
    }

    /**
     * Constructs a synthetic transient message
     */
    private function message(string $id): MailMessage
    {
        return new MailMessage(
            $id,
            EmailAddress::fromString('private@example.test'),
            'Invitation',
            'synthetic-secret-text',
            '<p>synthetic-secret-html</p>'
        );
    }
}
