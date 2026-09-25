<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Hydration;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Reconstitutes a persisted Fight Access Control user through the package's protected constructor.
 *
 * The locked package exposes no public full-state factory and prohibits reflection or serialization. The package
 * itself reconstitutes entities through this subclass constructor pattern, so the adapter forwards authoritative
 * stored state without duplicating lifecycle policy.
 */
final class PersistedUser extends User
{
    /**
     * Reconstitutes an identity from authoritative stored state
     *
     * @param list<RoleId> $roleIds
     */
    public static function reconstitute(
        UserId $id,
        EmailAddress $email,
        UserState $state,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?PasswordHash $passwordHash,
        int $authenticationVersion,
        int $authenticationAuthorityRevision,
        array $roleIds,
        int $authorizationAssignmentRevision,
        ?EmailAddress $pendingEmailChange,
        int $emailChangeReservationRevision,
        int $canonicalEmailRevision
    ): self {
        return new self(
            $id,
            $email,
            $state,
            $createdAt,
            $updatedAt,
            $passwordHash,
            $authenticationVersion,
            $authenticationAuthorityRevision,
            $roleIds,
            $authorizationAssignmentRevision,
            $pendingEmailChange,
            $emailChangeReservationRevision,
            $canonicalEmailRevision
        );
    }
}
