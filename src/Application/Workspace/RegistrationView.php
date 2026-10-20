<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Domain\Workspace\RepositoryRegistration;

/**
 * Class RegistrationView
 *
 * Exposes validated registration facts without persistence or physical claims
 */
final readonly class RegistrationView
{
    /**
     * Constructs RegistrationView
     *
     * @param array<string, mixed> $data
     */
    private function __construct(public array $data)
    {
    }

    /**
     * Projects the active baseline and its independent revision baseline
     */
    public static function fromRegistration(RepositoryRegistration $registration): self
    {
        return new self([
            'workspace_id'       => $registration->workspaceId->toString(),
            'repository_id'      => $registration->id->toString(),
            'display_name'       => $registration->metadata->displayName,
            'slug'               => $registration->metadata->slug,
            'lifecycle'          => 'active',
            'primary_source'     => $registration->sources->primary?->toArray(),
            'equivalent_sources' => array_map(
                static fn($s): array => $s->toArray(),
                $registration->sources->equivalent
            ),
            'metadata_revision'  => 1,
            'source_revision'    => 1,
            'lifecycle_revision' => 1,
            'actor_type'         => 'user',
            'created_by'         => $registration->actorId->toString(),
            'updated_by'         => $registration->actorId->toString(),
            'created_at'         => $registration->createdAt->format('Y-m-d\TH:i:s.uP'),
            'updated_at'         => $registration->createdAt->format('Y-m-d\TH:i:s.uP')
        ]);
    }
}
