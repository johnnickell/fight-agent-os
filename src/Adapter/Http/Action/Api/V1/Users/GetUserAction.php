<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Users;

use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Users\UserDetailResponder;
use App\Application\Users\ReadUserDetail;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class GetUserAction
 *
 * Maps a bounded target into an independently authorized installation read
 */
#[RequiresPermission('READ_USERS')]
final readonly class GetUserAction
{
    /**
     * Constructs GetUserAction
     */
    public function __construct(private ReadUserDetail $detail, private UserDetailResponder $responder)
    {
    }

    /**
     * Reads one safe user without query selectors, body input or self-service fallback
     */
    #[OA\Get(
        path: '/api/v1/users/{userId}',
        operationId: 'getUser',
        summary: 'Read one safe installation user',
        description: <<<'TEXT'
            Requires Bearer authentication and exact current protected READ_USERS in Application before
            package GetUserById dispatch, independently of the early route gate. Role names, JWT claims and
            ownership grant nothing. Includes pending_activation, active, disabled and deleted identities.
            Returns exactly the directory's safe fields and complete roles sorted by name. Missing or duplicate
            role references fail closed. No credentials, sessions, grants, permission memberships, revisions,
            pending email, audit or delivery internals. Unknown authorized target is 404; forbidden targets are
            never looked up. No query or body fields are accepted. Reads observe committed state, not a
            cross-request snapshot; a disappeared target is not found. All responses are no-store.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'userId',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid', maxLength: 36, minLength: 36)
        )],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Safe user detail; Cache-Control: no-store',
                headers: [new OA\Header(header: 'Cache-Control', schema: new OA\Schema(
                    type: 'string',
                    enum: ['no-store']
                ))],
                content: new OA\JsonContent(ref: '#/components/schemas/UserDetailSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 404,
                description: 'Authorized user not found',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = CatalogInput::authentication($request);
        $value = (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('userId');
        try {
            if (strlen($value) !== 36) {
                throw new \InvalidArgumentException();
            }
            $id = UserId::fromString($value);
        } catch (Throwable) {
            throw new ValidationException(['user_id' => ['Invalid value.']]);
        }

        return $this->responder->respond($this->detail->read($authentication, $id));
    }
}
