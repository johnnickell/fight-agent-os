<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\PasswordResetGrant\PasswordResetGrantRecords;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Protects recovery and delivery state when a reset grant crosses the row boundary
 */
final class PasswordResetGrantRecordsTest extends TestCase
{
    /**
     * Restores the exact authority fence and active lease for a claimed delivery
     */
    public function testClaimedDeliverySurvivesReload(): void
    {
        $row = self::storedRow();
        $grant = PasswordResetGrantRecords::hydrate($row);
        $delivery = $grant->getDelivery();

        self::assertSame(3, $grant->getAuthority()->getAuthenticationVersion());
        self::assertSame(7, $grant->getAuthority()->getCanonicalEmailRevision());
        self::assertSame(CredentialDeliveryStatus::CLAIMED, $delivery->getStatus());
        self::assertSame($row['delivery_claim_token'], $delivery->getClaimToken()?->toString());
        self::assertSame(CredentialDeliveryFailure::RETRYABLE_PROVIDER, $delivery->getLastFailure());
        self::assertEquals($row, PasswordResetGrantRecords::fields($grant));
    }

    /**
     * Does not recreate erased ciphertext or lease state after consumption
     */
    public function testConsumedGrantKeepsErasedDeliveryState(): void
    {
        $row = array_replace(self::storedRow(), [
            'consumed_at'              => '2026-10-20 12:01:00.000000+00:00',
            'delivery_status'          => 'invalidated',
            'delivery_ciphertext'      => null,
            'delivery_claim_token'     => null,
            'delivery_claimed_at'      => null,
            'delivery_lease_until'     => null,
            'delivery_last_failure'    => null,
            'delivery_last_outcome_at' => '2026-10-20 12:01:00.000000+00:00'
        ]);
        $grant = PasswordResetGrantRecords::hydrate($row);

        self::assertSame(CredentialDeliveryStatus::INVALIDATED, $grant->getDelivery()->getStatus());
        self::assertNull($grant->getDelivery()->getEncryptedMaterial());
        self::assertNull($grant->getDelivery()->getLeaseUntil());
        self::assertEquals($row, PasswordResetGrantRecords::fields($grant));
    }

    /**
     * Supplies a stored grant with distinct revisions and microsecond timestamps
     *
     * @return array<string, int|string|null>
     */
    private static function storedRow(): array
    {
        return [
            'recovery_authentication_version'   => 3,
            'recovery_canonical_email_revision' => 7,
            'id'                                => PasswordResetGrantId::generate()->toString(),
            'user_id'                           => UserId::generate()->toString(),
            'credential_digest'                 => str_repeat('a', 64),
            'expires_at'                        => '2026-10-20 13:00:00.000000+00:00',
            'consumed_at'                       => null,
            'revoked_at'                        => null,
            'revision'                          => 9,
            'delivery_id'                       => PasswordResetDeliveryId::generate()->toString(),
            'delivery_email'                    => 'recovery@example.test',
            'delivery_ciphertext'               => 'fixture-ciphertext',
            'delivery_expires_at'               => '2026-10-20 13:00:00.000000+00:00',
            'delivery_due_at'                   => '2026-10-20 11:57:03.654321+00:00',
            'delivery_status'                   => 'claimed',
            'delivery_claim_token'              => CredentialDeliveryClaimToken::generate()->toString(),
            'delivery_claimed_at'               => '2026-10-20 12:00:00.123456+00:00',
            'delivery_lease_until'              => '2026-10-20 12:02:00.123456+00:00',
            'delivery_attempt_count'            => 2,
            'delivery_last_attempt_at'          => '2026-10-20 11:55:00.123456+00:00',
            'delivery_last_outcome_at'          => '2026-10-20 11:56:00.123456+00:00',
            'delivery_last_failure'             => 'retryable_provider'
        ];
    }
}
