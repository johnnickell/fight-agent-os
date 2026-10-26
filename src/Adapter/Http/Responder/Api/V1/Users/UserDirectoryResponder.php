<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Users;

use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Repository\ResultSet;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class UserDirectoryResponder
 */
#[OA\Schema(
    schema: 'DirectoryRole',
    required: ['name', 'managed'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'managed', description: 'Policy ownership, not edit authority', type: 'boolean')
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'DirectoryUser',
    required: ['user_id', 'email', 'state', 'roles', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(
            property: 'state',
            type: 'string',
            enum: ['pending_activation', 'active', 'disabled', 'deleted']
        ),
        new OA\Property(
            property: 'roles',
            description: 'Exact current assignments, unique and ordered by name; empty means no assignments',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/DirectoryRole')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'UserDirectorySuccess',
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
                    items: new OA\Items(ref: '#/components/schemas/DirectoryUser'),
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
final readonly class UserDirectoryResponder
{
    /**
     * Constructs UserDirectoryResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns only approved safe directory fields without storage or shared-cache reuse
     *
     * @param array{users: ResultSet<UserView>, roles: array<string, list<array{name: string, managed: bool}>>} $page
     */
    public function respond(array $page): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new UserDirectoryData($page)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
