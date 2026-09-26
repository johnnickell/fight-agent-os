<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class PersistedActivationGrant
 *
 * Reconstitutes package-owned activation authority through its supported protected constructor
 */
final class PersistedActivationGrant extends ActivationGrant
{
    /**
     * Reconstitutes one complete authoritative aggregate generation
     */
    public static function reconstitute(
        ActivationGrantId $id,
        UserId $userId,
        string $digest,
        DateTimeImmutable $expiresAt,
        ActivationDelivery $delivery,
        ?DateTimeImmutable $consumedAt,
        ?DateTimeImmutable $revokedAt,
        int $revision
    ): self {
        return new self($id, $userId, $digest, $expiresAt, $delivery, $consumedAt, $revokedAt, $revision);
    }
}
