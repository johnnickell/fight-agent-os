<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\EncryptedCredentialMaterial;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDelivery;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Class PersistedPasswordResetDelivery
 *
 * Reconstitutes the package-owned delivery through its protected constructor
 */
final class PersistedPasswordResetDelivery extends PasswordResetDelivery
{
    /**
     * Reconstitutes all persisted delivery fields without replaying provider effects
     */
    public static function reconstitute(
        PasswordResetDeliveryId $id,
        UserId $userId,
        EmailAddress $email,
        ?EncryptedCredentialMaterial $material,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $dueAt,
        CredentialDeliveryStatus $status,
        ?CredentialDeliveryClaimToken $claimToken,
        ?DateTimeImmutable $claimedAt,
        ?DateTimeImmutable $leaseUntil,
        int $attemptCount,
        ?DateTimeImmutable $lastAttemptAt,
        ?DateTimeImmutable $lastOutcomeAt,
        ?CredentialDeliveryFailure $lastFailure
    ): PasswordResetDelivery {
        return new self(
            $id,
            $userId,
            $email,
            $material,
            $expiresAt,
            $dueAt,
            $status,
            $claimToken,
            $claimedAt,
            $leaseUntil,
            $attemptCount,
            $lastAttemptAt,
            $lastOutcomeAt,
            $lastFailure
        );
    }
}
