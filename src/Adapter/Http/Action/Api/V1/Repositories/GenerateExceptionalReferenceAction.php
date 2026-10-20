<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Repositories;

use App\Adapter\Http\Api\V1\Repositories\RegistrationInput;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Repositories\GenerateExceptionalReferenceResponder;
use App\Application\Workspace\GenerateExceptionalReference;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class GenerateExceptionalReferenceAction
 */
#[RequiresPermission('GENERATE_REFERENCE_IDS')]
final readonly class GenerateExceptionalReferenceAction
{
    /**
     * Constructs GenerateExceptionalReferenceAction
     */
    public function __construct(
        private GenerateExceptionalReference $references,
        private GenerateExceptionalReferenceResponder $responder
    ) {
    }

    /**
     * Generates an exceptional reference without accepting a kind, identity, actor or timestamp
     */
    #[OA\Post(
        path: '/api/v1/workspaces/{workspace_id}/references',
        operationId: 'generateExceptionalReference',
        summary: 'Generate a non-reserved COMB UUID for exceptional manual preparation',
        description: <<<'TEXT'
            No body, Content-Type or query is accepted. Requires current Workspace access and independent
            GENERATE_REFERENCE_IDS. Fixed actor budget: 10 per UTC 60-second window, at most 10000 current buckets.
            Only limiter metadata persists; the UUID is neither persisted nor reserved and grants no import authority.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/RegistrationWorkspace')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Non-reserved typed-compatible COMB UUID',
                content: new OA\JsonContent(ref: '#/components/schemas/ExceptionalReferenceSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/RegistrationRejected', response: 422),
            new OA\Response(ref: '#/components/responses/ReferenceThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responder->respond($this->references->generate(
            RegistrationInput::authentication($request),
            RegistrationInput::workspace($request)
        ));
    }
}
