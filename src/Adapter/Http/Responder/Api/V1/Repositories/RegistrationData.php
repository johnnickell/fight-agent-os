<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Repositories;

use App\Application\Workspace\RegistrationView;
use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class RegistrationData
 *
 * Presents only the explicit safe registration projection
 */
#[OA\Schema(
    schema: 'RepositorySourceInput',
    type: 'object',
    required: ['type', 'locator'],
    additionalProperties: false,
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['git_network_v1', 'github_repository_v1']),
        new OA\Property(
            property: 'locator',
            type: 'string',
            minLength: 1,
            maxLength: 2048,
            description: <<<'TEXT'
                Closed HTTPS, SSH URL or scp-like grammar; credentials and local/provider-native authority are refused
                TEXT
        )
    ]
)]
#[OA\Schema(
    schema: 'RepositorySource',
    oneOf: [
        new OA\Schema(
            type: 'object',
            additionalProperties: false,
            required: ['type', 'authority', 'owner', 'repository'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['github_repository_v1']),
                new OA\Property(property: 'authority', type: 'string', enum: ['github.com']),
                new OA\Property(property: 'owner', type: 'string'),
                new OA\Property(property: 'repository', type: 'string')
            ]
        ),
        new OA\Schema(
            type: 'object',
            additionalProperties: false,
            required: ['type', 'transport', 'host', 'port', 'username', 'path_mode', 'path'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['git_network_v1']),
                new OA\Property(property: 'transport', type: 'string', enum: ['https', 'ssh']),
                new OA\Property(property: 'host', type: 'string'),
                new OA\Property(property: 'port', type: 'integer', minimum: 1, maximum: 65535),
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'path_mode', type: 'string', enum: ['absolute', 'home_relative']),
                new OA\Property(property: 'path', type: 'string')
            ]
        )
    ]
)]
#[OA\Schema(
    schema: 'RepositoryRegistrationInput',
    type: 'object',
    additionalProperties: false,
    required: ['display_name', 'slug', 'idempotency_key'],
    properties: [
        new OA\Property(property: 'display_name', type: 'string', minLength: 1, maxLength: 120),
        new OA\Property(
            property: 'slug',
            type: 'string',
            minLength: 1,
            maxLength: 80,
            pattern: '^[a-z0-9]+(-[a-z0-9]+)*$'
        ),
        new OA\Property(
            property: 'idempotency_key',
            type: 'string',
            minLength: 8,
            maxLength: 128,
            pattern: '^[A-Za-z0-9_-]+$'
        ),
        new OA\Property(
            property: 'primary_source',
            type: 'object',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/RepositorySourceInput')]
        ),
        new OA\Property(
            property: 'equivalent_sources',
            type: 'array',
            maxItems: 7,
            items: new OA\Items(ref: '#/components/schemas/RepositorySourceInput')
        )
    ]
)]
#[OA\Schema(
    schema: 'RepositoryRegistration',
    type: 'object',
    additionalProperties: false,
    required: [
        'workspace_id', 'repository_id', 'display_name', 'slug', 'lifecycle', 'primary_source', 'equivalent_sources',
        'metadata_revision', 'source_revision', 'lifecycle_revision', 'actor_type', 'created_by', 'updated_by',
        'created_at', 'updated_at'
    ],
    properties: [
        new OA\Property(property: 'workspace_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'repository_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'display_name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'lifecycle', type: 'string', enum: ['active']),
        new OA\Property(
            property: 'primary_source',
            type: 'object',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/RepositorySource')]
        ),
        new OA\Property(
            property: 'equivalent_sources',
            type: 'array',
            maxItems: 7,
            items: new OA\Items(ref: '#/components/schemas/RepositorySource')
        ),
        new OA\Property(property: 'metadata_revision', type: 'integer', enum: [1]),
        new OA\Property(property: 'source_revision', type: 'integer', enum: [1]),
        new OA\Property(property: 'lifecycle_revision', type: 'integer', enum: [1]),
        new OA\Property(property: 'actor_type', type: 'string', enum: ['user']),
        new OA\Property(property: 'created_by', type: 'string', format: 'uuid'),
        new OA\Property(property: 'updated_by', type: 'string', format: 'uuid'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
    ]
)]
#[OA\Schema(
    schema: 'RepositoryRegistrationSuccess',
    type: 'object',
    additionalProperties: false,
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/RepositoryRegistration')
    ]
)]
#[OA\Response(
    response: 'RegistrationRejected',
    description: 'Invalid metadata, reserved slug, unsupported/unsafe descriptor, duplicate equivalence or identity',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationFail')
)]
#[OA\Response(
    response: 'RegistrationConflict',
    description: 'Known active slug/source claim or changed idempotent intent; no other registration or SQL details',
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final readonly class RegistrationData implements Arrayable
{
    /**
     * Constructs RegistrationData
     */
    public function __construct(private RegistrationView $view)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->view->data;
    }
}
