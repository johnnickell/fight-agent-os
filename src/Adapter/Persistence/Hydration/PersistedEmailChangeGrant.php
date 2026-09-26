<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDelivery;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrant;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
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
        ?DateTimeImmutable $consumedAt,
        ?DateTimeImmutable $revokedAt,
        ?DateTimeImmutable $expiredAt,
        int $revision
    ): EmailChangeGrant {
        return new self($id, $userId, $digest, $expiresAt, $delivery, $consumedAt, $revokedAt, $expiredAt, $revision);
    }
}
