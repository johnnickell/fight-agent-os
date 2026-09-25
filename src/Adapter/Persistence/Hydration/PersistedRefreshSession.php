<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Reconstitutes a persisted Fight Access Control refresh session through the package's protected constructor.
 *
 * The locked package exposes no public full-state factory and prohibits reflection or serialization. The package
 * itself reconstitutes entities through this subclass constructor pattern, so the adapter forwards authoritative
 * stored state without reimplementing session lifecycle policy.
 */
final class PersistedRefreshSession extends RefreshSession
{
    /**
     * Reconstitutes a refresh session from authoritative stored state
     *
     * @param list<string> $usedCredentialDigests
     */
    public static function reconstitute(
        RefreshSessionId $id,
        UserId $userId,
        string $credentialDigest,
        array $usedCredentialDigests,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $lastActivityAt,
        ?DateTimeImmutable $rotatedAt,
        DateTimeImmutable $idleExpiresAt,
        DateTimeImmutable $absoluteExpiresAt,
        int $authenticationVersion,
        bool $remembered,
        int $revision,
        bool $revoked
    ): self {
        return new self(
            $id,
            $userId,
            $credentialDigest,
            $usedCredentialDigests,
            $createdAt,
            $lastActivityAt,
            $rotatedAt,
            $idleExpiresAt,
            $absoluteExpiresAt,
            $authenticationVersion,
            $remembered,
            $revision,
            $revoked
        );
    }
}
