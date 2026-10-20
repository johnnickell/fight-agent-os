<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Repositories;

use App\Adapter\Http\Api\V1\Repositories\RegistrationInput;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Repositories\ListRepositoryRegistrationsResponder;
use App\Application\Workspace\ListRepositoryRegistrations;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ListRepositoryRegistrationsAction
 */
#[RequiresPermission('READ_REPOSITORY_REGISTRATIONS')]
final readonly class ListRepositoryRegistrationsAction
{
    /**
     * Constructs ListRepositoryRegistrationsAction
     */
    public function __construct(
        private ListRepositoryRegistrations $registrations,
        private ListRepositoryRegistrationsResponder $responder
    ) {
    }

    /**
     * Maps explicit bounded query pagination into one scoped read
     */
    #[OA\Get(
        path: '/api/v1/workspaces/{workspace_id}/repositories',
        operationId: 'listRepositoryRegistrations',
        summary: 'List authorized registrations ordered by created_at then repository_id',
        description: <<<'TEXT'
            Offset pagination is deterministic for a committed state, not a cross-request snapshot.
            Missing offset and limit mean 0 and 25. Current initial-Workspace access and independent
            READ_REPOSITORY_REGISTRATIONS are required.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/RegistrationWorkspace'),
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                schema: new OA\Schema(type: 'integer', minimum: 0, maximum: 100000, default: 0)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 25)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bounded safe page',
                content: new OA\JsonContent(ref: '#/components/schemas/RepositoryRegistrationPageSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/RegistrationRejected', response: 422),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[QueryString]
    #[Validation(rules: [
        ['field' => 'offset', 'label' => 'Offset', 'rules' => 'digits|min_number[0]|max_number[100000]'],
        ['field' => 'limit', 'label' => 'Limit', 'rules' => 'digits|min_number[1]|max_number[100]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{offset?: string, limit?: string} $input */
        $input = $request->getAttribute(QueryString::class);
        $offset = (int) ($input['offset'] ?? '0');
        $limit = (int) ($input['limit'] ?? '25');

        return $this->responder->respond($this->registrations->list(
            RegistrationInput::authentication($request),
            RegistrationInput::workspace($request),
            $offset,
            $limit
        ), $offset, $limit);
    }
}
