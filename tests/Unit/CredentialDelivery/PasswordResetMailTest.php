<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Adapter\CredentialDelivery\CanonicalCredentialLink;
use App\Adapter\CredentialDelivery\CredentialMailProvider;
use App\Adapter\CredentialDelivery\InvitationMailProvider;
use App\Adapter\CredentialDelivery\PasswordResetMailProvider;
use App\Adapter\CredentialDelivery\SodiumCredentialDeliveryCipher;
use App\Application\Mail\MailDelivery;
use App\Application\Mail\MailMessage;
use App\Application\Mail\MailOutcome;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Proves bounded routing, deterministic reset mail and fail-closed private failure handling
 */
final class PasswordResetMailTest extends TestCase
{
    /**
     * Encodes opaque bytes in exactly two fragment fields with a package-validated bounded UserId
     */
    public function testExactFragmentContractAndCredentialBound(): void
    {
        $id = UserId::generate();
        $link = new CanonicalCredentialLink('https://localhost:28453', '/reset-password');
        $url = $link->forPasswordReset($id, 'a&b<>" /?#%');
        $expected = 'https://localhost:28453/reset-password#user_id='.$id->toString();
        $expected .= '&credential=a%26b%3C%3E%22%20%2F%3F%23%25';
        self::assertTrue($url === $expected);
        self::assertNull(parse_url($url, PHP_URL_QUERY));
        self::assertSame('/reset-password', parse_url($url, PHP_URL_PATH));
        self::assertSame(36, strlen($id->toString()));
        self::assertTrue(str_ends_with($link->forPasswordReset($id, str_repeat('x', 4096)), str_repeat('x', 4096)));
    }

    /**
     * Rejects empty and oversized credentials without echoing material
     */
    #[DataProvider('invalidCredentials')]
    public function testInvalidMaterialNeverBecomesALink(string $credential): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid password-reset material.');
        (new CanonicalCredentialLink('https://example.test', '/reset-password'))
            ->forPasswordReset(UserId::generate(), $credential);
    }

    /**
     * Supplies the fragment credential boundary failures
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidCredentials(): iterable
    {
        yield 'empty' => [''];
        yield 'oversized' => [str_repeat('x', 4097)];
    }

    /**
     * Maps every transport outcome with stable generation identity and reviewed deterministic alternatives
     */
    #[DataProvider('outcomes')]
    public function testRoutingAndVersionOneMail(MailOutcome $outcome, CredentialDeliveryOutcome $expected): void
    {
        $grant = $this->grant();
        $view = CredentialDeliveryStatusView::fromDelivery('password_reset', $grant->getDelivery(), 0);
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
        ): MailOutcome {
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
                new CanonicalCredentialLink('https://example.test', '/app/activate'),
                $this->templates(),
                $mail,
                $activationGrants
            ),
            $this->provider($queries, $mail)
        );
        $invocation = $this->invocation($grant);
        $outcomes = [$provider->deliver($invocation), $provider->deliver($invocation)];
        self::assertSame([$expected, $expected], $outcomes);
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
     * @return iterable<string, array{MailOutcome, CredentialDeliveryOutcome}>
     */
    public static function outcomes(): iterable
    {
        yield 'delivered' => [MailOutcome::DELIVERED, CredentialDeliveryOutcome::DELIVERED];
        yield 'retryable' => [MailOutcome::RETRYABLE_FAILURE, CredentialDeliveryOutcome::RETRYABLE_FAILURE];
        yield 'permanent' => [MailOutcome::PERMANENT_FAILURE, CredentialDeliveryOutcome::PERMANENT_FAILURE];
    }

    /**
     * Sends nothing when exact immutable routing cannot be established
     */
    #[DataProvider('routingFailures')]
    public function testRoutingFailureSendsNothing(string $failure): void
    {
        $grant = $this->grant();
        $queries = $this->createMock(QueryBus::class);
        if ($failure === 'exception') {
            $queries->method('fetch')->willThrowException(new RuntimeException('private-query-marker'));
        } else {
            $result = match ($failure) {
                'absent' => null,
                'wrong type' => new \stdClass(),
                'wrong purpose' => CredentialDeliveryStatusView::fromDelivery(
                    'activation',
                    $grant->getDelivery(),
                    0
                ),
                default => CredentialDeliveryStatusView::fromDelivery(
                    'password_reset',
                    $this->grant()->getDelivery(),
                    0
                )
            };
            $queries->method('fetch')->willReturn($result);
        }
        $templates = $this->createMock(TemplateEngine::class);
        $templates->expects(self::never())->method('render');
        $mail = $this->createMock(MailDelivery::class);
        $mail->expects(self::never())->method('deliver');
        self::assertSame(
            CredentialDeliveryOutcome::RETRYABLE_FAILURE,
            $this->provider($queries, $mail, $templates)->deliver($this->invocation($grant))
        );
    }

    /**
     * Supplies absent, failed and mismatched public lookup results
     *
     * @return iterable<string, array{string}>
     */
    public static function routingFailures(): iterable
    {
        foreach (['absent', 'wrong type', 'wrong purpose', 'wrong generation', 'exception'] as $failure) {
            yield $failure => [$failure];
        }
    }

    /**
     * Discards private rendering and mail errors without a partial send or private result
     */
    #[DataProvider('presentationFailures')]
    public function testPrivateFailuresAreRetryable(string $failure): void
    {
        $grant = $this->grant();
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willReturn(CredentialDeliveryStatusView::fromDelivery(
            'password_reset',
            $grant->getDelivery(),
            0
        ));
        $templates = $this->createMock(TemplateEngine::class);
        $templates->method('render')->willReturnCallback(static function (
            string $template,
            array $context
        ) use (
            $failure
        ): string {
            if ($template === $failure) {
                throw new RuntimeException($context['resetUrl']);
            }

            return 'transient alternative';
        });
        $mail = $this->createMock(MailDelivery::class);
        if ($failure === 'mail') {
            $mail->method('deliver')->willThrowException(new RuntimeException('private-mail-marker'));
        } else {
            $mail->expects(self::never())->method('deliver');
        }
        self::assertSame(
            CredentialDeliveryOutcome::RETRYABLE_FAILURE,
            $this->provider($queries, $mail, $templates)->deliver($this->invocation($grant))
        );
    }

    /**
     * Supplies both alternative failures and transport failure
     *
     * @return iterable<string, array{string}>
     */
    public static function presentationFailures(): iterable
    {
        yield 'text' => ['mail/password-reset.txt.twig'];
        yield 'html' => ['mail/password-reset.html.twig'];
        yield 'mail' => ['mail'];
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
     * Routes only the fixed supported mail families and never queries routing for an unsupported purpose
     */
    public function testFixedPurposeDispatchPreservesInvitationAndRefusesUnknownPurposes(): void
    {
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('fetch');
        $at = new DateTimeImmutable('2026-10-02T12:00:00Z');
        $activationGrant = ActivationGrant::issue(
            \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin::PRIVATE_INVITATION,
            UserId::generate(),
            ActivationCredential::fromString('synthetic'),
            $at,
            $at->modify('+7 days'),
            EmailAddress::fromString('not-routing-authority@example.test'),
            'synthetic-ciphertext'
        );
        $activationGrants = $this->createMock(ActivationGrantRepository::class);
        $activationGrants->expects(self::once())->method('getByDeliveryId')
            ->with(self::equalTo($activationGrant->getDelivery()->getId()))
            ->willReturn($activationGrant);
        $mail = $this->createMock(MailDelivery::class);
        $mail->expects(self::once())->method('deliver')->willReturnCallback(static function (
            MailMessage $message
        ) use ($activationGrant): MailOutcome {
            [$destination, $subject, $text, $html] = $message->content();
            $url = 'https://example.test/app/activate#credential=synthetic&user_id=';
            $url .= $activationGrant->getUserId()->toString();
            self::assertTrue($destination->toString() === 'local@example.test');
            self::assertSame('Your Fight Agent OS invitation', $subject);
            self::assertTrue(str_contains($text, "\n".$url."\n"));
            self::assertTrue(str_contains($html, 'href="'.str_replace('&', '&amp;', $url).'"'));
            self::assertSame($activationGrant->getDelivery()->getId()->toString(), $message->idempotencyId);

            return MailOutcome::DELIVERED;
        });
        $invitations = new InvitationMailProvider(
            new CanonicalCredentialLink('https://example.test', '/app/activate'),
            $this->templates(),
            $mail,
            $activationGrants
        );
        $resets = $this->provider($queries, $mail);
        $provider = new CredentialMailProvider($invitations, $resets);
        $grant = $this->grant();
        foreach (['email_change', 'unknown'] as $purpose) {
            $invocation = new CredentialDeliveryInvocation(
                $purpose,
                $grant->getDelivery()->getId()->toString(),
                EmailAddress::fromString('local@example.test'),
                'synthetic',
                null
            );
            self::assertSame(CredentialDeliveryOutcome::RETRYABLE_FAILURE, $provider->deliver($invocation));
        }
        $activation = new CredentialDeliveryInvocation(
            'activation',
            $activationGrant->getDelivery()->getId()->toString(),
            EmailAddress::fromString('local@example.test'),
            'synthetic',
            \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin::PRIVATE_INVITATION
        );
        self::assertSame(CredentialDeliveryOutcome::RETRYABLE_FAILURE, $resets->deliver($activation));
        self::assertSame(CredentialDeliveryOutcome::DELIVERED, $provider->deliver($activation));
    }

    /**
     * Prevents ordinary dumps from traversing private query-bus collaborator state
     */
    public function testDiagnosticsDoNotExposePrivateQueryGraph(): void
    {
        $marker = 'synthetic-private-query-diagnostic-marker';
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willThrowException(new RuntimeException($marker));
        $mail = $this->createMock(MailDelivery::class);
        $provider = $this->provider($queries, $mail);
        self::assertFalse(str_contains(print_r($provider, true), $marker));
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
