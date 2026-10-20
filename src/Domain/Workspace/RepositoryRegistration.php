<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class RepositoryRegistration
 *
 * Owns the active registration baseline without physical or execution authority
 */
final readonly class RepositoryRegistration
{
    /**
     * Constructs RepositoryRegistration
     */
    private function __construct(
        public RepositoryId $id,
        public WorkspaceId $workspaceId,
        public RegistrationMetadata $metadata,
        public RegistrationSources $sources,
        public UserId $actorId,
        public DateTimeImmutable $createdAt
    ) {
    }

    /**
     * Creates the registration with an identity generated only by its owner
     */
    public static function register(
        WorkspaceId $workspaceId,
        RegistrationMetadata $metadata,
        RegistrationSources $sources,
        UserId $actorId,
        DateTimeImmutable $now
    ): self {
        return new self(RepositoryId::generate(), $workspaceId, $metadata, $sources, $actorId, $now);
    }

    /**
     * Reconstitutes the persisted active baseline without minting a replacement identity
     */
    public static function reconstitute(
        RepositoryId $id,
        WorkspaceId $workspaceId,
        RegistrationMetadata $metadata,
        RegistrationSources $sources,
        UserId $actorId,
        DateTimeImmutable $createdAt
    ): self {
        return new self($id, $workspaceId, $metadata, $sources, $actorId, $createdAt);
    }
}
