<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Adapter\CredentialDelivery\CanonicalActivationLink;
use App\Adapter\CredentialDelivery\InvitationMailProvider;
use App\Application\Mail\MailDelivery;
use App\Application\Mail\MailMessage;
use App\Application\Mail\MailOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\Common\Adapter\Templating\TwigEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

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
     * Delivers byte-compatible version-one alternatives through real Twig without retaining message bodies
     */
    public function testRendersVersionOneWithoutPrivateOrRemoteContent(): void
    {
        $observations = [];
        $mail = $this->createMock(MailDelivery::class);
        $mail->method('deliver')->willReturnCallback(static function (MailMessage $message) use (
            &$observations
        ): MailOutcome {
            [$destination, $subject, $text, $html] = $message->content();
            $expectedText = <<<TEXT
You have been invited to Fight Agent OS.

Activate your account:
https://example.test/activate#credential=synthetic%3C%26%22credential

This single-use link expires seven days after the invitation was issued, not after this email arrives.
If it has expired or no longer works, ask your administrator for a new invitation.
If you did not expect this invitation, ignore it. Do not forward or share this link.
TEXT;
            $expectedHtml = <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="referrer" content="no-referrer">
<meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;">
<title>Fight Agent OS invitation</title></head><body>
<h1>You have been invited to Fight Agent OS</h1>
<p><a href="https://example.test/activate#credential=synthetic%3C%26%22credential">Activate your account</a></p>
<p>This single-use link expires seven days after the invitation was issued, not after this email arrives.</p>
<p>If it has expired or no longer works, ask your administrator for a new invitation.</p>
<p>If you did not expect this invitation, ignore it. Do not forward or share this link.</p>
</body></html>
HTML;
            $observations[] = [
                'id'          => $message->idempotencyId,
                'destination' => $destination->toString() === 'private@example.test',
                'subject'     => $subject === 'Your Fight Agent OS invitation',
                'text'        => $text === $expectedText,
                'html'        => $html === $expectedHtml,
                'digest'      => hash('sha256', $text.$html)
            ];

            return MailOutcome::DELIVERED;
        });
        $provider = $this->provider($mail);
        $invocation = $this->invocation('activation', 'synthetic<&"credential');
        $outcomes = [$provider->deliver($invocation), $provider->deliver($invocation)];
        self::assertSame(array_fill(0, 2, CredentialDeliveryOutcome::DELIVERED), $outcomes);
        self::assertCount(2, $observations);
        self::assertSame($observations[0], $observations[1]);
        self::assertSame($invocation->getIdempotencyId(), $observations[0]['id']);
        foreach (['destination', 'subject', 'text', 'html'] as $check) {
            self::assertTrue($observations[0][$check], $check);
        }
        $diagnostics = print_r($provider, true);
        self::assertFalse(str_contains($diagnostics, $invocation->getCredential()));
        self::assertFalse(str_contains($diagnostics, rawurlencode($invocation->getCredential())));
    }

    /**
     * Escapes HTML metacharacters without corrupting the plain-text alternative
     */
    public function testAlternativesUseTheirOwnEscapingRules(): void
    {
        $templates = $this->templates();
        $context = ['activationUrl' => 'https://example.test/activate#credential=a&b<>"\''];
        $text = $templates->render('mail/invitation.txt.twig', $context);
        $html = $templates->render('mail/invitation.html.twig', $context);
        self::assertTrue(str_contains($text, "\nhttps://example.test/activate#credential=a&b<>\"'\n"));
        self::assertTrue(str_contains(
            $html,
            'href="https://example.test/activate#credential=a&amp;b&lt;&gt;&quot;&#039;"'
        ));
        self::assertFalse(str_contains($html, $context['activationUrl']));
        self::assertFalse(str_contains($text, '&amp;'));
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
        $provider = $this->provider($mail);
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
        $provider = $this->provider($mail);
        self::assertSame(CredentialDeliveryOutcome::RETRYABLE_FAILURE, $provider->deliver($this->invocation()));
    }

    /**
     * Refuses partial mail when either rendered alternative fails with private diagnostics
     */
    #[DataProvider('failedAlternatives')]
    public function testRenderingFailureIsSafeAndRetryable(string $failedAlternative): void
    {
        $templates = $this->createMock(TemplateEngine::class);
        $templates->method('render')->willReturnCallback(static function (
            string $template,
            array $context
        ) use ($failedAlternative): string {
            if ($template === $failedAlternative) {
                throw new RuntimeException($context['activationUrl']);
            }

            return 'transient rendered alternative';
        });
        $mail = $this->createMock(MailDelivery::class);
        $mail->expects(self::never())->method('deliver');
        $provider = $this->provider($mail, $templates);
        self::assertSame(CredentialDeliveryOutcome::RETRYABLE_FAILURE, $provider->deliver($this->invocation()));
    }

    /**
     * Supplies each alternative whose failure must prevent any external effect
     *
     * @return iterable<string, array{string}>
     */
    public static function failedAlternatives(): iterable
    {
        yield 'text' => ['mail/invitation.txt.twig'];
        yield 'html after text' => ['mail/invitation.html.twig'];
    }

    /**
     * Handles a real Twig loader failure without sending or exposing templating diagnostics
     */
    public function testMissingTemplateIsRetryableWithoutMailEffect(): void
    {
        $templates = new TwigEngine(new Environment(new ArrayLoader(), ['strict_variables' => true]));
        $mail = $this->createMock(MailDelivery::class);
        $mail->expects(self::never())->method('deliver');
        self::assertSame(
            CredentialDeliveryOutcome::RETRYABLE_FAILURE,
            $this->provider($mail, $templates)->deliver($this->invocation())
        );
    }

    /**
     * Refuses to render another credential purpose as an activation invitation
     */
    public function testDoesNotSendUnimplementedCredentialFamilies(): void
    {
        $templates = $this->createMock(TemplateEngine::class);
        $templates->expects(self::never())->method('render');
        $mail = $this->createMock(MailDelivery::class);
        $mail->expects(self::never())->method('deliver');
        self::assertSame(
            CredentialDeliveryOutcome::RETRYABLE_FAILURE,
            $this->provider($mail, $templates)->deliver($this->invocation('password_reset'))
        );
    }

    /**
     * Constructs the provider with configured canonical links and no network transport
     */
    private function provider(MailDelivery $mail, ?TemplateEngine $templates = null): InvitationMailProvider
    {
        return new InvitationMailProvider(
            new CanonicalActivationLink('https://example.test', '/activate'),
            $templates ?? $this->templates(),
            $mail
        );
    }

    /**
     * Constructs real Twig presentation for the shipped alternatives
     */
    private function templates(): TemplateEngine
    {
        return new TwigEngine(new Environment(
            new FilesystemLoader(dirname(__DIR__, 3).'/templates'),
            ['autoescape' => 'name', 'strict_variables' => true]
        ));
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
