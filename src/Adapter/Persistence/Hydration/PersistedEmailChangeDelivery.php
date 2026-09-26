<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\EncryptedCredentialMaterial;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDelivery;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDeliveryId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Reconstitutes the package-owned email-change delivery state
 */
final class PersistedEmailChangeDelivery extends EmailChangeDelivery
{
    /**
     * Restores a complete delivery generation without invoking a provider
     */
    public static function reconstitute(
        EmailChangeDeliveryId $id,
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
    ): EmailChangeDelivery {
        return new self($id, $userId, $email, $material, $expiresAt, $dueAt, $status, $claimToken, $claimedAt,
            $leaseUntil, $attemptCount, $lastAttemptAt, $lastOutcomeAt, $lastFailure);
    }
}
