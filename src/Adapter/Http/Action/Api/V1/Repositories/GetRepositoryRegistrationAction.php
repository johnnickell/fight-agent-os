<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Repositories;

use App\Adapter\Http\Api\V1\Repositories\RegistrationInput;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Repositories\GetRepositoryRegistrationResponder;
use App\Application\Workspace\GetRepositoryRegistration;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class GetRepositoryRegistrationAction
 */
#[RequiresPermission('READ_REPOSITORY_REGISTRATIONS')]
final readonly class GetRepositoryRegistrationAction
{
    /**
     * Constructs GetRepositoryRegistrationAction
     */
    public function __construct(
        private GetRepositoryRegistration $registrations,
        private GetRepositoryRegistrationResponder $responder
    ) {
    }

    /**
     * Maps one exact scoped read without accepting alternate input
     */
    #[OA\Get(
        path: '/api/v1/workspaces/{workspace_id}/repositories/{repository_id}',
        operationId: 'getRepositoryRegistration',
        summary: 'Inspect one registration under current independent view and Workspace access',
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/RegistrationWorkspace'),
            new OA\Parameter(
                name: 'repository_id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Safe registration facts, no physical claims',
                content: new OA\JsonContent(ref: '#/components/schemas/RepositoryRegistrationSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 404,
                description: 'No registration in authorized scope',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/RegistrationRejected', response: 422),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responder->respond($this->registrations->get(
            RegistrationInput::authentication($request),
            RegistrationInput::workspace($request),
            RegistrationInput::repository($request)
        ));
    }
}
