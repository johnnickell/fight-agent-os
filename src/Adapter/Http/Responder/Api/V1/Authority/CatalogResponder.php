<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Authority;

use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Repository\ResultSet;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CatalogResponder
 *
 * Explicitly redacts package role membership and all persistence data
 */
#[OA\Schema(
    schema: 'CatalogRole',
    required: ['role_id', 'name', 'managed', 'revision'],
    properties: [
        new OA\Property(property: 'role_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'revision', ref: '#/components/schemas/RoleRevision'),
        new OA\Property(
            property: 'managed',
            description: 'Managed roles are not custom-role mutation targets',
            type: 'boolean'
        )
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'RoleRevision',
    description: 'Opaque state expectation; never authority. Refresh and reconfirm after conflict.',
    type: 'string',
    maxLength: 46,
    pattern: '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}:[1-9][0-9]{0,8}$'
)]
#[OA\Schema(
    schema: 'CatalogPermission',
    required: ['permission_id', 'name', 'tier', 'managed'],
    properties: [
        new OA\Property(property: 'permission_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'tier', type: 'string', enum: ['ADMIN_SAFE', 'SUPER_ADMIN_ONLY']),
        new OA\Property(
            property: 'managed',
            description: 'Policy ownership marker, not permission CRUD authority',
            type: 'boolean'
        )
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'CatalogRoleSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/CatalogRole')
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'CatalogPermissionSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/CatalogPermission')
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'CatalogRolesSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            required: ['page', 'per_page', 'total_pages', 'total_records', 'records'],
            properties: [
                new OA\Property(property: 'page', type: 'integer', minimum: 1, maximum: 1000),
                new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 100),
                new OA\Property(property: 'total_pages', type: 'integer', minimum: 1),
                new OA\Property(property: 'total_records', type: 'integer', minimum: 0),
                new OA\Property(
                    property: 'records',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CatalogRole'),
                    maxItems: 100
                )
            ],
            type: 'object',
            additionalProperties: false
        )
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'CatalogPermissionsSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            required: ['page', 'per_page', 'total_pages', 'total_records', 'records'],
            properties: [
                new OA\Property(property: 'page', type: 'integer', minimum: 1, maximum: 1000),
                new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 100),
                new OA\Property(property: 'total_pages', type: 'integer', minimum: 1),
                new OA\Property(property: 'total_records', type: 'integer', minimum: 0),
                new OA\Property(
                    property: 'records',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CatalogPermission'),
                    maxItems: 100
                )
            ],
            type: 'object',
            additionalProperties: false
        )
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class CatalogResponder
{
    /**
     * Constructs CatalogResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns only explicit catalog fields with no-store on lists and single reads
     *
     * @param RoleView|PermissionView|ResultSet<RoleView>|ResultSet<PermissionView> $view
     */
    public function respond(RoleView|PermissionView|ResultSet $view): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new CatalogData($view)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
