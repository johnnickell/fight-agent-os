<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Users;

use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class UserDetailResponder
 *
 * Presents one credential-free user using the directory's exact safe schema
 */
#[OA\Schema(
    schema: 'UserDetailSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/DirectoryUser')
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class UserDetailResponder
{
    /**
     * Constructs UserDetailResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns only approved user fields without cache reuse
     *
     * @param array{user: UserView, roles: list<array{name: string, managed: bool}>} $detail
     */
    public function respond(array $detail): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new UserData($detail['user'], $detail['roles'])),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
