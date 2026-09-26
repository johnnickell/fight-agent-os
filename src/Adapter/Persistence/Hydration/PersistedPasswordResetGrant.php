<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDelivery;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Reconstitutes the package-owned reset generation through its protected constructor
 */
final class PersistedPasswordResetGrant extends PasswordResetGrant
{
    /**
     * Reconstitutes all persisted aggregate fields without replaying transitions
     */
    public static function reconstitute(
        PasswordResetGrantId $id,
        UserId $userId,
        string $digest,
        DateTimeImmutable $expiresAt,
        PasswordResetDelivery $delivery,
        ?DateTimeImmutable $consumedAt,
        ?DateTimeImmutable $revokedAt,
        int $revision
    ): PasswordResetGrant {
        return new self($id, $userId, $digest, $expiresAt, $delivery, $consumedAt, $revokedAt, $revision);
    }
}
