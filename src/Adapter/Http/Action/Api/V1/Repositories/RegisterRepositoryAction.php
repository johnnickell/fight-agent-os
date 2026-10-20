<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Repositories;

use App\Adapter\Http\Api\V1\Repositories\RegistrationInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Repositories\RegisterRepositoryResponder;
use App\Application\Workspace\RegisterRepository;
use App\Domain\Workspace\RegistrationIntent;
use App\Domain\Workspace\RegistrationMetadata;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RegisterRepositoryAction
 */
#[RequiresPermission('REGISTER_REPOSITORIES')]
final readonly class RegisterRepositoryAction
{
    /**
     * Constructs RegisterRepositoryAction
     */
    public function __construct(
        private RegisterRepository $registrations,
        private RegisterRepositoryResponder $responder
    ) {
    }

    /**
     * Maps validated fields into one atomic registration operation
     */
    #[OA\Post(
        path: '/api/v1/workspaces/{workspace_id}/repositories',
        operationId: 'registerRepository',
        summary: 'Register stable collaboration metadata without checkout or execution authority',
        description: <<<'TEXT'
            Requires current initial-Workspace administrator access and REGISTER_REPOSITORIES independently.
            IDs, actor, times, lifecycle and revisions are generated internally. Exact retries additionally require
            READ_REPOSITORY_REGISTRATIONS and return the immutable original result; changed intent conflicts.
            No network or filesystem effect. The authenticated transport body limit is 16 KiB.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/RegistrationWorkspace')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RepositoryRegistrationInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Committed registration or its original result; not an access grant or physical checkout',
                content: new OA\JsonContent(ref: '#/components/schemas/RepositoryRegistrationSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/RegistrationConflict', response: 409),
            new OA\Response(ref: '#/components/responses/RegistrationRejected', response: 422),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        [
            'field' => 'display_name',
            'label' => 'Display name',
            'rules' => 'required|type[string]|min_length[1]|max_length[120]'
        ],
        ['field' => 'slug', 'label' => 'Slug', 'rules' => 'required|type[string]|min_length[1]|max_length[80]'],
        [
            'field' => 'idempotency_key',
            'label' => 'Idempotency key',
            'rules' => 'required|type[string]|min_length[8]|max_length[128]'
        ],
        ['field' => 'primary_source', 'label' => 'Primary source', 'rules' => 'type[?object]'],
        ['field' => 'equivalent_sources', 'label' => 'Equivalent sources', 'rules' => 'type[array]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{display_name: string, slug: string, idempotency_key: string} $input */
        $input = $request->getAttribute(JsonBody::class);

        return $this->responder->respond($this->registrations->register(
            RegistrationInput::authentication($request),
            RegistrationInput::workspace($request),
            new RegistrationIntent(
                new RegistrationMetadata($input['display_name'], $input['slug']),
                RegistrationInput::sources($input),
                $input['idempotency_key']
            )
        ));
    }
}
