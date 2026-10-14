<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Me;

use Fight\AccessControl\Domain\AccessControl\Authorization\AuthenticatedUserPrincipal;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ReadCurrentPrincipalResponder
 *
 * Presents a no-store safe current-principal response without serializing authentication authority
 */
#[OA\Schema(
    schema: 'CurrentPrincipalSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            required: ['user_id', 'email', 'roles', 'permissions'],
            properties: [
                new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'))
            ],
            type: 'object',
            additionalProperties: false
        )
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class ReadCurrentPrincipalResponder
{
    /**
     * Constructs ReadCurrentPrincipalResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns safe identity and current authority with no cookie or credential material
     */
    public function respond(UserView $identity, AuthenticatedUserPrincipal $principal): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new CurrentPrincipalData($identity, $principal)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
