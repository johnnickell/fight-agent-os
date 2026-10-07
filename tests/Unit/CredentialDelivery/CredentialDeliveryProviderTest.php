<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Adapter\CredentialDelivery\InMemoryCredentialDeliveryProvider;
use App\Adapter\CredentialDelivery\NullCredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Class CredentialDeliveryProviderTest
 *
 * Exercises safe, network-free credential attempts through the package capability
 */
final class CredentialDeliveryProviderTest extends TestCase
{
    /**
     * Keeps an unsent credential pending rather than reporting false delivery
     */
    public function test_that_null_provider_does_not_acknowledge_delivery(): void
    {
        $provider = new NullCredentialDeliveryProvider();

        self::assertSame(
            CredentialDeliveryOutcome::RETRYABLE_FAILURE,
            $provider->deliver($this->invocation('activation', ActivationDeliveryId::generate()->toString()))
        );
    }

    /**
     * Preserves the package delivery identity across repeated invitation attempts
     */
    public function test_that_invitation_retries_keep_the_same_secret_free_identity(): void
    {
        $id = ActivationDeliveryId::generate()->toString();
        $provider = new InMemoryCredentialDeliveryProvider([
            'activation' => CredentialDeliveryOutcome::DELIVERED
        ]);
        $invocation = $this->invocation('activation', $id);

        self::assertSame(CredentialDeliveryOutcome::DELIVERED, $provider->deliver($invocation));
        $provider->deliver($invocation);
        self::assertSame([
            ['purpose' => 'activation', 'idempotency_id' => $id, 'outcome' => 'DELIVERED'],
            ['purpose' => 'activation', 'idempotency_id' => $id, 'outcome' => 'DELIVERED']
        ], $provider->attempts());
    }

    /**
     * Maps reset attempts to a typed permanent outcome without a secret snapshot
     */
    public function test_that_reset_outcome_is_typed_and_inspection_is_redacted(): void
    {
        $provider = new InMemoryCredentialDeliveryProvider([
            'password_reset' => CredentialDeliveryOutcome::PERMANENT_FAILURE
        ]);
        $id = PasswordResetDeliveryId::generate()->toString();

        self::assertSame(
            CredentialDeliveryOutcome::PERMANENT_FAILURE,
            $provider->deliver($this->invocation('password_reset', $id))
        );
        self::assertSame([
            ['purpose' => 'password_reset', 'idempotency_id' => $id, 'outcome' => 'PERMANENT_FAILURE']
        ], $provider->attempts());
        foreach (
            [serialize($provider), print_r($provider, true), var_export($provider->attempts(), true)] as $snapshot
        ) {
            self::assertStringNotContainsString('fixture-credential-secret', $snapshot);
            self::assertStringNotContainsString('private@example.test', $snapshot);
        }
    }

    /**
     * Refuses unknown purposes and missing invocation material without echoing it
     */
    public function test_that_invalid_invocation_is_rejected_without_recording_an_attempt(): void
    {
        $provider = new InMemoryCredentialDeliveryProvider();
        foreach (
            [
                $this->invocation('email_change', PasswordResetDeliveryId::generate()->toString()),
                $this->invocation('activation', ''),
                $this->invocation('activation', ActivationDeliveryId::generate()->toString(), '')
            ] as $invocation
        ) {
            try {
                $provider->deliver($invocation);
                self::fail('The invalid invocation was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Unsupported credential delivery invocation.', $exception->getMessage());
            }
        }
        self::assertSame([], $provider->attempts());
    }

    /**
     * Defaults to retryable rather than discarding an unconfigured simulated effect
     */
    public function test_that_unconfigured_in_memory_outcome_is_retryable(): void
    {
        $provider = new InMemoryCredentialDeliveryProvider();

        self::assertSame(
            CredentialDeliveryOutcome::RETRYABLE_FAILURE,
            $provider->deliver($this->invocation('password_reset', PasswordResetDeliveryId::generate()->toString()))
        );
    }

    /**
     * Rejects unsupported simulated outcomes rather than acknowledging an undefined effect
     */
    public function testRejectsUnsupportedOutcomes(): void
    {
        $outcomes = ['activation' => 'DELIVERED', 'email_change' => CredentialDeliveryOutcome::DELIVERED];
        foreach ($outcomes as $purpose => $outcome) {
            try {
                new InMemoryCredentialDeliveryProvider([$purpose => $outcome]);
                self::fail('An unsupported outcome must not be accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Unsupported credential delivery outcome configuration.', $exception->getMessage());
            }
        }
    }

    /**
     * Builds package invocation material for one synthetic provider call
     */
    private function invocation(
        string $purpose,
        string $id,
        string $credential = 'fixture-credential-secret'
    ): CredentialDeliveryInvocation {
        return new CredentialDeliveryInvocation(
            $purpose,
            $id,
            EmailAddress::fromString('private@example.test'),
            $credential
        );
    }
}
