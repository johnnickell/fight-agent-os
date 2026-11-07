<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\References;

use App\Adapter\Http\Api\V1\Repositories\RegistrationInput;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\References\GenerateExceptionalReferenceResponder;
use App\Adapter\Reference\ExceptionalReferences;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Messaging\Query\QueryBus;
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
        private ExceptionalReferences $exceptionalReferences,
        private QueryBus $queryBus,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
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
            Admission checks current authentication, independent permission and Workspace scope before generation;
            an admitted request may finish after a concurrent permission revocation.
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
        // ApiActionSecurity has authenticated this request and admitted the independent operation permission.
        $authentication = RegistrationInput::authentication($request);
        $workspaceId = RegistrationInput::workspace($request);
        $initial = $this->queryBus->fetch(new FindInitialWorkspace());
        $roleId = RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID);
        $role = $this->roleRepository->getById($roleId);
        $user = $this->userRepository->getById($authentication->getUserId());
        if (
            $initial === null || !$initial->id->equals($workspaceId) || $user === null
            || $role === null || !$role->isManaged() || $role->getName()->toString() !== 'ROLE_SUPER_ADMIN'
            || !$user->hasRole($roleId)
        ) {
            throw new PermissionDenied();
        }

        return $this->responder->respond($this->exceptionalReferences->generate($authentication->getUserId()));
    }
}
