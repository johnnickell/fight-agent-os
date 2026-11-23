<?php

declare(strict_types=1);

namespace Tests\Integration\CredentialDelivery;

use App\Adapter\CredentialDelivery\CanonicalCredentialLink;
use App\Adapter\CredentialDelivery\InvitationMailProvider;
use App\Adapter\Mail\MailDelivery;
use App\Adapter\Mail\MailMessage;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Templating\TwigEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Proves canonical, deterministic and transient invitation mail mapping without network access
 */
final class InvitationMailTest extends TestCase
{
    private UserId $routingUser;

    /**
     * Creates the package-owned routing identity without an invented UUID
     */
    protected function setUp(): void
    {
        $this->routingUser = UserId::generate();
    }

    /**
     * Delivers byte-compatible version-one alternatives through real Twig without retaining message bodies
     */
    public function testRendersVersionOneWithoutPrivateOrRemoteContent(): void
    {
        $observations = [];
        $userId = $this->routingUser->toString();
        $mail = $this->createMock(MailDelivery::class);
        $mail->method('deliver')->willReturnCallback(static function (MailMessage $message) use (
            &$observations,
            $userId
        ): CredentialDeliveryOutcome {
            [$destination, $subject, $text, $html] = $message->content();
            $expectedUrl = 'https://example.test/activate#credential=synthetic%3C%26%22credential&user_id='.$userId;
            $expectedHtmlUrl = str_replace('&', '&amp;', $expectedUrl);
            $expectedText = <<<TEXT
You have been invited to Fight Agent OS.

Activate your account:
{$expectedUrl}

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
<p><a href="{$expectedHtmlUrl}">Activate your account</a></p>
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

            return CredentialDeliveryOutcome::DELIVERED;
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
     * Constructs the provider with configured canonical links and no network transport
     */
    private function provider(MailDelivery $mail, ?TemplateEngine $templates = null): InvitationMailProvider
    {
        $grants = $this->createStub(ActivationGrantRepository::class);
        $now = new DateTimeImmutable();
        $grants->method('getByDeliveryId')->willReturn(ActivationGrant::issue(
            \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin::PRIVATE_INVITATION,
            $this->routingUser,
            ActivationCredential::fromString('synthetic-credential'),
            $now,
            $now->modify('+7 days'),
            EmailAddress::fromString('private@example.test'),
            'synthetic-ciphertext'
        ));

        return new InvitationMailProvider(
            new CanonicalCredentialLink('https://example.test', '/activate'),
            $templates ?? $this->templates(),
            $mail,
            $grants
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
            $credential,
            $purpose === 'activation' ? ActivationOrigin::PRIVATE_INVITATION : null
        );
    }
}
