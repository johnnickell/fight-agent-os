<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Domain\Workspace\RegistrationIntent;
use App\Domain\Workspace\RegistrationSources;
use App\Domain\Workspace\RepositoryId;
use App\Domain\Workspace\RepositoryRegistration;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Interface RegistrationStore
 *
 * Participates in the shared transaction without owning its completion
 */
interface RegistrationStore
{
    /**
     * Serializes registration claims under READ COMMITTED
     */
    public function holdRegistrationClaims(): void;

    /**
     * Returns a payload-bound original result or rejects conflicting key reuse
     */
    public function original(WorkspaceId $workspace, UserId $actor, RegistrationIntent $intent): ?RegistrationView;

    /**
     * Returns bounded conflict kinds without exposing the owning registrations
     *
     * @return list<string>
     */
    public function conflicts(WorkspaceId $workspace, string $slug, RegistrationSources $sources): array;

    /**
     * Records current state, all source claims, audit, event fact and original result atomically
     */
    public function add(RepositoryRegistration $registration, RegistrationIntent $intent): RegistrationView;

    /**
     * Returns one scoped registration without a persistence model
     */
    public function get(WorkspaceId $workspace, RepositoryId $repository): ?RegistrationView;

    /**
     * Returns a bounded stable page ordered by creation time and identity
     *
     * @return array{items: list<RegistrationView>, has_more: bool}
     */
    public function page(WorkspaceId $workspace, int $offset, int $limit): array;
}
