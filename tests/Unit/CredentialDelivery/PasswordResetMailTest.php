<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

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
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryAbsent;
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
use Twig\Loader\ArrayLoader;

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
     * Dispatches a reset using its invocation destination and both rendered alternatives
     */
    public function testDispatchesResetWithIsolatedRendering(): void
    {
        $grant = $this->grant();
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturn(CredentialDeliveryStatusView::fromDelivery(
            'password_reset',
            $grant->getDelivery(),
            0,
            null
        ));
        $templates = $this->createStub(TemplateEngine::class);
        $userId = $grant->getUserId()->toString();
        $url = "https://example.test/reset-password#user_id={$userId}&credential=synthetic%3C%26%22credential";
        $context = ['resetUrl' => $url];
        $templates->method('render')->willReturnMap([
            ['mail/password-reset.txt.twig', $context, 'plain reset'],
            ['mail/password-reset.html.twig', $context, '<p>HTML reset</p>']
        ]);
        $mail = $this->createMock(MailDelivery::class);
        $mail->expects(self::once())->method('deliver')->willReturnCallback(static function (
            MailMessage $message
        ): CredentialDeliveryOutcome {
            [$destination, $subject, $text, $html] = $message->content();
            self::assertSame('invocation-only@example.test', $destination->toString());
            self::assertSame('Reset your Fight Agent OS password', $subject);
            self::assertSame('plain reset', $text);
            self::assertSame('<p>HTML reset</p>', $html);

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $invitation = new InvitationMailProvider(
            new CanonicalCredentialLink('https://example.test', '/activate'),
            $templates,
            $mail,
            $this->createStub(ActivationGrantRepository::class)
        );
        $provider = new CredentialMailProvider($invitation, $this->provider($queries, $mail, $templates));

        self::assertSame(CredentialDeliveryOutcome::DELIVERED, $provider->deliver($this->invocation($grant)));
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
                'absent' => new CredentialDeliveryAbsent(),
                'wrong type' => new \stdClass(),
                'wrong purpose' => CredentialDeliveryStatusView::fromDelivery(
                    'activation',
                    ActivationGrant::issue(
                        \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin::PRIVATE_INVITATION,
                        $grant->getUserId(),
                        ActivationCredential::fromString('synthetic'),
                        $grant->getExpiresAt()->modify('-1 hour'),
                        $grant->getExpiresAt(),
                        EmailAddress::fromString('not-routing-authority@example.test'),
                        'synthetic-ciphertext'
                    )->getDelivery(),
                    0,
                    null
                ),
                default => CredentialDeliveryStatusView::fromDelivery(
                    'password_reset',
                    $this->grant()->getDelivery(),
                    0,
                    null
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
            0,
            null
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
     * Preserves invitation routing and refuses the unsupported email-change mail family
     */
    public function testFixedPurposeDispatchPreservesInvitationAndRefusesEmailChange(): void
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
        ) use ($activationGrant): CredentialDeliveryOutcome {
            [$destination, $subject, $text, $html] = $message->content();
            $url = 'https://example.test/activate#credential=synthetic&user_id=';
            $url .= $activationGrant->getUserId()->toString();
            self::assertTrue($destination->toString() === 'local@example.test');
            self::assertSame('Your Fight Agent OS invitation', $subject);
            self::assertTrue(str_contains($text, "\n".$url."\n"));
            self::assertTrue(str_contains($html, 'href="'.str_replace('&', '&amp;', $url).'"'));
            self::assertSame($activationGrant->getDelivery()->getId()->toString(), $message->idempotencyId);

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $invitations = new InvitationMailProvider(
            new CanonicalCredentialLink('https://example.test', '/activate'),
            $this->templates(),
            $mail,
            $activationGrants
        );
        $resets = $this->provider($queries, $mail);
        $provider = new CredentialMailProvider($invitations, $resets);
        $grant = $this->grant();
        $unsupported = new CredentialDeliveryInvocation(
            'email_change',
            $grant->getDelivery()->getId()->toString(),
            EmailAddress::fromString('local@example.test'),
            'synthetic',
            null
        );
        self::assertSame(CredentialDeliveryOutcome::RETRYABLE_FAILURE, $provider->deliver($unsupported));
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
     * Supplies in-memory alternatives for link and dispatch assertions
     */
    private function templates(): TemplateEngine
    {
        return new TwigEngine(new Environment(
            new ArrayLoader([
                'mail/invitation.txt.twig'  => "\n{{ activationUrl }}\n",
                'mail/invitation.html.twig' => '<a href="{{ activationUrl }}">Continue</a>'
            ]),
            ['autoescape' => 'name', 'strict_variables' => true]
        ));
    }
}
