<?php

declare(strict_types=1);

namespace Tests\Integration\CredentialDelivery;

use App\Adapter\CredentialDelivery\CanonicalCredentialLink;
use App\Adapter\CredentialDelivery\CredentialMailProvider;
use App\Adapter\CredentialDelivery\InvitationMailProvider;
use App\Adapter\CredentialDelivery\PasswordResetMailProvider;
use App\Adapter\CredentialDelivery\SodiumCredentialDeliveryCipher;
use App\Adapter\Mail\MailDelivery;
use App\Adapter\Mail\MailMessage;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindCredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Templating\TwigEngine;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Proves bounded routing, deterministic reset mail and fail-closed private failure handling
 */
final class PasswordResetMailTest extends TestCase
{
    /**
     * Maps every transport outcome with stable generation identity and reviewed deterministic alternatives
     */
    #[DataProvider('outcomes')]
    public function testRoutingAndVersionOneMail(CredentialDeliveryOutcome $outcome): void
    {
        $grant = $this->grant();
        $view = CredentialDeliveryStatusView::fromDelivery('password_reset', $grant->getDelivery(), 0, null);
        $queries = $this->createMock(QueryBus::class);
        $seenQueries = [];
        $queries->method('fetch')->willReturnCallback(static function (FindCredentialDeliveryStatus $query) use (
            $view,
            &$seenQueries
        ): CredentialDeliveryStatusView {
            $seenQueries[] = $query->toArray();

            return $view;
        });
        $checks = [];
        $ids = [];
        $digests = [];
        $mail = $this->createMock(MailDelivery::class);
        $mail->method('deliver')->willReturnCallback(static function (MailMessage $message) use (
            $grant,
            $outcome,
            &$checks,
            &$ids,
            &$digests
        ): CredentialDeliveryOutcome {
            [$destination, $subject, $text, $html] = $message->content();
            $userId = $grant->getUserId()->toString();
            $expectedText = <<<TEXT
Reset your Fight Agent OS password.

Choose a new password:
https://example.test/reset-password#user_id={$userId}&credential=synthetic%3C%26%22credential

This single-use link expires one hour after the reset was requested, not after this email arrives.
If it has expired or no longer works, request a new password-reset link.
If you did not request this reset, ignore it. Your password has not changed.
Do not forward or share this link.
TEXT;
            $htmlUrl = 'https://example.test/reset-password#user_id='.$userId;
            $htmlUrl .= '&amp;credential=synthetic%3C%26%22credential';
            $expectedHtml = <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="referrer" content="no-referrer">
<meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;">
<title>Reset your Fight Agent OS password</title></head><body>
<h1>Reset your Fight Agent OS password</h1>
<p><a href="{$htmlUrl}">Choose a new password</a></p>
<p>This single-use link expires one hour after the reset was requested, not after this email arrives.</p>
<p>If it has expired or no longer works, request a new password-reset link.</p>
<p>If you did not request this reset, ignore it. Your password has not changed.</p>
<p>Do not forward or share this link.</p>
</body></html>
HTML;
            $checks[] = [
                $destination->toString() === 'invocation-only@example.test',
                $subject === 'Reset your Fight Agent OS password',
                $text === $expectedText,
                $html === $expectedHtml
            ];
            $ids[] = $message->idempotencyId;
            $digests[] = hash('sha256', $text.$html);

            return $outcome;
        });
        $activationGrants = $this->createMock(ActivationGrantRepository::class);
        $activationGrants->expects(self::never())->method('getByDeliveryId');
        $provider = new CredentialMailProvider(
            new InvitationMailProvider(
                new CanonicalCredentialLink('https://example.test', '/activate'),
                $this->templates(),
                $mail,
                $activationGrants
            ),
            $this->provider($queries, $mail)
        );
        $invocation = $this->invocation($grant);
        $outcomes = [$provider->deliver($invocation), $provider->deliver($invocation)];
        self::assertSame([$outcome, $outcome], $outcomes);
        self::assertSame(array_fill(0, 2, [true, true, true, true]), $checks);
        self::assertSame(array_fill(0, 2, $invocation->getIdempotencyId()), $ids);
        self::assertSame(array_fill(0, 2, [
            'purpose' => 'password_reset', 'delivery_id' => $invocation->getIdempotencyId()
        ]), $seenQueries);
        self::assertSame($digests[0], $digests[1]);
    }

    /**
     * Supplies typed mail outcomes without owning package retry policy
     *
     * @return iterable<string, array{CredentialDeliveryOutcome}>
     */
    public static function outcomes(): iterable
    {
        yield 'delivered' => [CredentialDeliveryOutcome::DELIVERED];
        yield 'retryable' => [CredentialDeliveryOutcome::RETRYABLE_FAILURE];
        yield 'permanent' => [CredentialDeliveryOutcome::PERMANENT_FAILURE];
    }

    /**
     * Preserves HTML escaping independently of the plain text link
     */
    public function testEscapesUntrustedTemplateContext(): void
    {
        $templates = $this->templates();
        $context = ['resetUrl' => 'https://example.test/reset-password#user_id=synthetic&credential=<>"\''];
        $text = $templates->render('mail/password-reset.txt.twig', $context);
        $html = $templates->render('mail/password-reset.html.twig', $context);
        self::assertTrue(str_contains($text, $context['resetUrl']));
        self::assertTrue(str_contains($html, 'synthetic&amp;credential=&lt;&gt;&quot;&#039;'));
        self::assertFalse(str_contains($html, $context['resetUrl']));
    }

    /**
     * Builds a synthetic grant using package factories rather than invented identifiers
     */
    private function grant(): PasswordResetGrant
    {
        $at = new DateTimeImmutable('2026-10-02T12:00:00Z');

        return PasswordResetGrant::issue(
            UserId::generate(),
            PasswordResetCredential::fromString('synthetic'),
            $at,
            $at->modify('+1 hour'),
            EmailAddress::fromString('not-routing-authority@example.test'),
            (new SodiumCredentialDeliveryCipher(bin2hex(random_bytes(32)), 'password_reset'))->encrypt('synthetic'),
            new \Fight\AccessControl\Domain\AccessControl\User\RecoveryAuthority(1, 0)
        );
    }

    /**
     * Builds an immutable invocation whose destination and secret remain authoritative
     */
    private function invocation(PasswordResetGrant $grant): CredentialDeliveryInvocation
    {
        return new CredentialDeliveryInvocation(
            'password_reset',
            $grant->getDelivery()->getId()->toString(),
            EmailAddress::fromString('invocation-only@example.test'),
            'synthetic<&"credential',
            null
        );
    }

    /**
     * Builds the provider with isolated collaborators
     */
    private function provider(
        QueryBus $queries,
        MailDelivery $mail,
        ?TemplateEngine $templates = null
    ): PasswordResetMailProvider {
        return new PasswordResetMailProvider(
            $queries,
            new CanonicalCredentialLink('https://example.test', '/reset-password'),
            $templates ?? $this->templates(),
            $mail
        );
    }

    /**
     * Builds real Twig presentation for the shipped version-one alternatives
     */
    private function templates(): TemplateEngine
    {
        return new TwigEngine(new Environment(
            new FilesystemLoader(dirname(__DIR__, 3).'/templates'),
            ['autoescape' => 'name', 'strict_variables' => true]
        ));
    }
}
