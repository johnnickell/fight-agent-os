<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Invitations;

use App\Adapter\Http\Api\Invitation\InvitationOperation;
use App\Adapter\Http\Api\Invitation\InvitationRateLimiter;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Invitations\IssueInvitationResponder;
use App\Application\Authorization\InstallationAssignmentPolicy;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\IncompleteInvitation;
use App\Application\Failure\InvitationConfirmationRequired;
use App\Application\Invitation\InvitationIssuance;
use App\Domain\Authorization\InstallationPolicyRequiresReconciliation;
use App\Domain\Invitation\Command\IssueInvitation;
use App\Domain\Invitation\InvitationMode;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\FindInvitationDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryAbsent;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Class IssueInvitationAction
 *
 * Maps checked invitation fields and verified authentication into one Application operation
 */
#[RequiresPermission('INVITE_USERS')]
final readonly class IssueInvitationAction
{
    /**
     * Constructs IssueInvitationAction
     */
    public function __construct(
        private PermissionGuard $permissionGuard,
        private InvitationRateLimiter $invitationRateLimiter,
        private InstallationAssignmentPolicy $installationAssignmentPolicy,
        private UserRepository $userRepository,
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private IssueInvitationResponder $responder
    ) {
    }

    /**
     * Issues one invitation without accepting actor or arbitrary authority fields
     */
    #[OA\Post(
        path: '/api/v1/invitations',
        operationId: 'issueInvitation',
        summary: 'Issue an ordinary or explicitly confirmed Super Admin invitation',
        description: <<<'TEXT'
            Requires exact INVITE_USERS; super_admin additionally requires ASSIGN_SUPER_ADMIN and true confirmation.
            Invitation, assignment and audit commit separately; delivery can begin before assignment.
            201 confirms origin and membership evidence, not email receipt.
            A 503 fail retains inspected partial/uncertain state: never retry automatically.
            Actor/action fixed-window limits default to 10 issuances per 60 seconds; configurable server-side.
            TEXT,
        security: [['AccessBearer' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'mode'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 254, minLength: 3),
                new OA\Property(property: 'mode', type: 'string', enum: ['ordinary', 'super_admin']),
                new OA\Property(
                    property: 'confirm_super_admin',
                    type: 'boolean',
                    description: 'Must be literal true for super_admin; absent means false'
                )
            ],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pending identity, exact membership and separate audit established; not inbox receipt',
                content: new OA\JsonContent(ref: '#/components/schemas/InvitationIssued')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Canonical email conflict or installation policy requires reconciliation',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(
                response: 422,
                description: 'Explicit elevation confirmation missing or false',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationFail')
            ),
            new OA\Response(ref: '#/components/responses/InvitationThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500),
            new OA\Response(
                response: 503,
                description: <<<'TEXT'
                    Partial or uncertain origin; retained target when known. No rollback guarantee and no automatic
                    retry or reinvitation; trusted operator inspection required
                    TEXT,
                content: new OA\JsonContent(ref: '#/components/schemas/InvitationIncomplete')
            )
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        [
            'field' => 'email',
            'label' => 'Email',
            'rules' => 'required|type[string]|min_length[3]|max_length[254]|email'
        ],
        ['field' => 'mode', 'label' => 'Mode', 'rules' => 'required|type[string]|in_list[ordinary,super_admin]'],
        ['field' => 'confirm_super_admin', 'label' => 'Confirmation', 'rules' => 'type[bool]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = $request->getAttribute(AuthenticationContext::class);
        $security = $request->getAttribute(SecurityContext::class);
        if (!$authentication instanceof AuthenticationContext || !$security instanceof SecurityContext) {
            throw new AuthenticationRequired();
        }
        /** @var array{email: string, mode: string, confirm_super_admin?: bool} $input */
        $input = $request->getAttribute(JsonBody::class);
        $mode = InvitationMode::from($input['mode']);
        if ($mode === InvitationMode::SUPER_ADMIN) {
            $this->permissionGuard->assertAllowed($security, [PermissionName::fromString('ASSIGN_SUPER_ADMIN')]);
        }
        try {
            $mode->requireConfirmation($input['confirm_super_admin'] ?? false);
        } catch (ValidationException $failure) {
            throw new InvitationConfirmationRequired(previous: $failure);
        }
        $actorId = $authentication->getUserId();
        $this->invitationRateLimiter->consume($actorId, InvitationOperation::ISSUE);
        try {
            $this->installationAssignmentPolicy->requireInstallation();
        } catch (InstallationPolicyRequiresReconciliation) {
            throw new StateConflict();
        }
        $email = EmailAddress::fromString(EmailAddress::fromString($input['email'])->canonical());
        if ($this->userRepository->getByEmail($email) !== null) {
            throw new StateConflict();
        }

        $userId = UserId::generate();
        $this->commandBus->execute(new IssueInvitation($actorId, $userId, $email, $mode));
        try {
            /** @var InvitationDeliveryStatusView|CredentialDeliveryAbsent $status */
            $status = $this->queryBus->fetch(new FindInvitationDeliveryStatus($userId));
            $delivery = $status instanceof CredentialDeliveryAbsent ? null : $status;
        } catch (Throwable) {
            $delivery = null;
        }
        $outcome = new InvitationIssuance($userId, 'confirmed', true, $delivery);
        if ($delivery === null) {
            throw new IncompleteInvitation($outcome);
        }

        return $this->responder->respond($outcome);
    }
}
