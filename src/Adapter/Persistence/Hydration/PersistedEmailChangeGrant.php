<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDelivery;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrant;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class PersistedEmailChangeGrant
 *
 * Reconstitutes a persisted package-owned email-change generation
 */
final class PersistedEmailChangeGrant extends EmailChangeGrant
{
    /**
     * Reconstitutes the complete generation without replaying transitions
     */
    public static function reconstitute(
        EmailChangeGrantId $id,
        UserId $userId,
        string $digest,
        DateTimeImmutable $expiresAt,
        EmailChangeDelivery $delivery,
        int $emailChangeReservationRevision,
        ?DateTimeImmutable $consumedAt,
        ?DateTimeImmutable $revokedAt,
        ?DateTimeImmutable $expiredAt,
        int $revision
    ): EmailChangeGrant {
        if ($emailChangeReservationRevision < 1) {
            throw new \UnexpectedValueException('Invalid persisted email reservation binding.');
        }

        return new self(
            $id,
            $userId,
            $digest,
            $expiresAt,
            $delivery,
            $emailChangeReservationRevision,
            $consumedAt,
            $revokedAt,
            $expiredAt,
            $revision
        );
    }
}
