<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Users;

use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
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
     * @param UserView $user
     * @param array $roles
     *
     * @phpstan-param list<RoleView> $roles
     */
    public function respond(UserView $user, array $roles): ResponseInterface
    {
        $presentation = UserRoleData::map([$user], $roles);

        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new UserData($user, $presentation[$user->getUserId()->toString()])),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
