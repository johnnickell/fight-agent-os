<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Sessions;

use App\Application\Sessions\DeviceDescriptor;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Repository\ResultSet;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class OwnedSessionsResponder
 *
 * Presents only the approved session security projection
 */
#[OA\Schema(
    schema: 'OwnedSession',
    required: [
        'session_id', 'browser_family', 'device_class', 'created_at', 'last_activity_at',
        'idle_expires_at', 'absolute_expires_at', 'remembered', 'current'
    ],
    properties: [
        new OA\Property(property: 'session_id', type: 'string', format: 'uuid'),
        new OA\Property(
            property: 'browser_family',
            type: 'string',
            enum: ['Chrome', 'Edge', 'Firefox', 'Safari', 'Unknown']
        ),
        new OA\Property(property: 'device_class', type: 'string', enum: ['desktop', 'mobile', 'tablet', 'unknown']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(
            property: 'last_activity_at',
            description: 'Creation or last successful refresh rotation; not online presence',
            type: 'string',
            format: 'date-time'
        ),
        new OA\Property(property: 'idle_expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'absolute_expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'remembered', type: 'boolean'),
        new OA\Property(
            property: 'current',
            description: 'Matches the request principal, not browser input',
            type: 'boolean'
        )
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'OwnedSessionsSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', required: [
            'page', 'per_page', 'total_pages', 'total_records', 'records'
        ], properties: [
            new OA\Property(property: 'page', type: 'integer', minimum: 1, maximum: 1000),
            new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 100),
            new OA\Property(property: 'total_pages', type: 'integer', minimum: 1),
            new OA\Property(property: 'total_records', type: 'integer', minimum: 0),
            new OA\Property(
                property: 'records',
                type: 'array',
                items: new OA\Items(ref: '#/components/schemas/OwnedSession'),
                maxItems: 100
            )
        ], type: 'object', additionalProperties: false)
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class OwnedSessionsResponder
{
    /**
     * Constructs OwnedSessionsResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns explicit safe session data with no-store
     *
     * @param array{sessions: ResultSet<SessionView>, devices: array<string, DeviceDescriptor>} $page
     */
    public function respond(array $page): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new OwnedSessionsData($page['sessions'], $page['devices'])),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
