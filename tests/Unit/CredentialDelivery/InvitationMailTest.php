<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Adapter\CredentialDelivery\CanonicalActivationLink;
use App\Adapter\CredentialDelivery\InvitationMailProvider;
use App\Adapter\CredentialDelivery\InvitationMailTemplate;
use App\Application\Mail\MailDelivery;
use App\Application\Mail\MailMessage;
use App\Application\Mail\MailOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Proves canonical, deterministic and transient invitation mail mapping without network access
 */
final class InvitationMailTest extends TestCase
{
    /**
     * Rejects ambiguous, request-derived or insecure link configuration before materialization
     */
    #[DataProvider('invalidUrls')]
    public function testRejectsUnsafeCanonicalConfiguration(string $origin, string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid canonical activation URL configuration.');
        new CanonicalActivationLink($origin, $path);
    }

    /**
     * Supplies unsafe origins and paths outside the deliberate canonical grammar
     *
     * @return iterable<string, array{string, string}>
     */
    public static function invalidUrls(): iterable
    {
        foreach (
            [
            '', 'http://example.test', 'https://user:pass@example.test', 'https://example.test/',
            'https://example.test?x=1', 'https://example.test#x', 'https://EXAMPLE.test',
            'https://example.test.', 'https://127.1', 'https://2130706433', 'https://0x7f000001',
            'https://127.0.0.1', 'https://[::1]', 'https://exämple.test', 'https://-bad.test',
            'https://bad_.test', 'https://example.test:0', 'https://example.test:65536',
            'https://example.test:0443', "https://example.test\n", 'https://example.test\\@evil.test'
            ] as $index => $origin
        ) {
            yield 'origin '.$index => [$origin, '/activate'];
        }
        foreach (
            ['', 'activate', '//evil.test', '/a/../activate', '/a%2factivate', '/activate?x=1',
                '/activate#x', '/a\\b', "/activate\n", '/activate/', '/a//b'] as $index => $path
        ) {
            yield 'path '.$index => ['https://example.test', $path];
        }
    }

    /**
     * Places encoded material exclusively in a fragment without identity or analytics parameters
     */
    public function testBuildsEncodedFragmentFromOnlyConfiguredOriginAndPath(): void
    {
        $link = new CanonicalActivationLink('https://localhost:28443', '/auth/activate');
        $url = $link->forCredential('a&b<>" /?#%');
        self::assertTrue($url === 'https://localhost:28443/auth/activate#credential=a%26b%3C%3E%22%20%2F%3F%23%25');
        self::assertSame('/auth/activate', parse_url($url, PHP_URL_PATH));
        $this->expectException(InvalidArgumentException::class);
        $link->forCredential('');
    }

    /**
     * Produces deterministic safe alternatives with explicit issuance-relative expiry and recovery copy
     */
    public function testRendersVersionOneWithoutPrivateOrRemoteContent(): void
    {
        $template = $this->template();
        $invocation = $this->invocation('activation', 'synthetic<&"credential');
        $first = $template->render($invocation);
        [$destination, $subject, $text, $html] = $first->content();
        self::assertSame($invocation->getIdempotencyId(), $first->idempotencyId);
        self::assertTrue($destination->toString() === 'private@example.test');
        self::assertSame('Your Fight Agent OS invitation', $subject);
        self::assertTrue($first->content() === $template->render($invocation)->content());
        self::assertTrue(str_contains($text, 'https://example.test/activate#credential=synthetic%3C%26%22credential'));
        self::assertTrue(str_contains(
            $html,
            'href="https://example.test/activate#credential=synthetic%3C%26%22credential"'
        ));
        foreach ([$text, $html] as $body) {
            self::assertTrue(str_contains($body, 'seven days after the invitation was issued'));
            self::assertTrue(str_contains($body, 'ask your administrator for a new invitation'));
            self::assertTrue(str_contains($body, 'Do not forward or share this link'));
            $forbidden = [
                'private@example.test', '<img', '<script', '<style', '<iframe', 'utm_', 'src=', 'synthetic<&'
            ];
            foreach ($forbidden as $bad) {
                self::assertFalse(str_contains($body, $bad));
            }
        }
        self::assertTrue(str_contains($html, 'name="referrer" content="no-referrer"'));
        self::assertTrue(str_contains($html, 'default-src &apos;none&apos;'));
    }

    /**
     * Maps all mail outcomes and preserves one generation identity across duplicate attempts
     */
    #[DataProvider('outcomes')]
    public function testMapsTypedOutcomesWithoutRetainingInvocation(
        MailOutcome $mailOutcome,
        CredentialDeliveryOutcome $expected
    ): void {
        $ids = [];
        $mail = $this->createMock(MailDelivery::class);
        $mail->method('deliver')->willReturnCallback(static function (MailMessage $message) use (
            &$ids,
            $mailOutcome
        ): MailOutcome {
            $ids[] = $message->idempotencyId;

            return $mailOutcome;
        });
        $provider = new InvitationMailProvider($this->template(), $mail);
        $invocation = $this->invocation();
        $outcomes = [$provider->deliver($invocation), $provider->deliver($invocation)];
        self::assertSame([$expected, $expected], $outcomes);
        self::assertSame(array_fill(0, 2, $invocation->getIdempotencyId()), $ids);
        self::assertStringNotContainsString($invocation->getCredential(), print_r($ids, true));
    }

    /**
     * Supplies transport outcomes without introducing a consumer retry policy
     *
     * @return iterable<string, array{MailOutcome, CredentialDeliveryOutcome}>
     */
    public static function outcomes(): iterable
    {
        yield 'delivered' => [MailOutcome::DELIVERED, CredentialDeliveryOutcome::DELIVERED];
        yield 'retryable' => [MailOutcome::RETRYABLE_FAILURE, CredentialDeliveryOutcome::RETRYABLE_FAILURE];
        yield 'permanent' => [MailOutcome::PERMANENT_FAILURE, CredentialDeliveryOutcome::PERMANENT_FAILURE];
    }

    /**
     * Keeps unexpected mail failures retryable without surfacing private diagnostics
     */
    public function testDiscardsPrivateTransportException(): void
    {
        $mail = $this->createMock(MailDelivery::class);
        $mail->method('deliver')->willThrowException(new RuntimeException('synthetic-private-link'));
        $provider = new InvitationMailProvider($this->template(), $mail);
        self::assertSame(CredentialDeliveryOutcome::RETRYABLE_FAILURE, $provider->deliver($this->invocation()));
    }

    /**
     * Refuses to render another credential purpose as an activation invitation
     */
    public function testDoesNotSendUnimplementedCredentialFamilies(): void
    {
        $mail = $this->createMock(MailDelivery::class);
        $mail->expects(self::never())->method('deliver');
        $provider = new InvitationMailProvider($this->template(), $mail);
        self::assertSame(
            CredentialDeliveryOutcome::RETRYABLE_FAILURE,
            $provider->deliver($this->invocation('password_reset'))
        );
    }

    /**
     * Constructs the configured fixed template
     */
    private function template(): InvitationMailTemplate
    {
        return new InvitationMailTemplate(new CanonicalActivationLink('https://example.test', '/activate'));
    }

    /**
     * Constructs synthetic transient package invocation material
     */
    private function invocation(
        string $purpose = 'activation',
        string $credential = 'synthetic-secret'
    ): CredentialDeliveryInvocation {
        return new CredentialDeliveryInvocation(
            $purpose,
            ActivationDeliveryId::generate()->toString(),
            EmailAddress::fromString('private@example.test'),
            $credential
        );
    }
}
