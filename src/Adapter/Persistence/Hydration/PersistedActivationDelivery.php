<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\EncryptedCredentialMaterial;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Class PersistedActivationDelivery
 *
 * Reconstitutes package-owned encrypted delivery state through its supported protected constructor
 */
final class PersistedActivationDelivery extends ActivationDelivery
{
    /**
     * Reconstitutes the complete delivery generation without replaying transport effects
     */
    public static function reconstitute(
        ActivationDeliveryId $id,
        UserId $userId,
        EmailAddress $email,
        ?EncryptedCredentialMaterial $material,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $dueAt,
        CredentialDeliveryStatus $status,
        ?CredentialDeliveryClaimToken $token,
        ?DateTimeImmutable $claimedAt,
        ?DateTimeImmutable $leaseUntil,
        int $attemptCount,
        ?DateTimeImmutable $lastAttemptAt,
        ?DateTimeImmutable $lastOutcomeAt,
        ?CredentialDeliveryFailure $lastFailure
    ): self {
        return new self(
            $id,
            $userId,
            $email,
            $material,
            $expiresAt,
            $dueAt,
            $status,
            $token,
            $claimedAt,
            $leaseUntil,
            $attemptCount,
            $lastAttemptAt,
            $lastOutcomeAt,
            $lastFailure
        );
    }
}
