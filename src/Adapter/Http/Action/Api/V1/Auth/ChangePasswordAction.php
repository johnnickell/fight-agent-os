<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Auth;

use App\Adapter\Http\Attribute\Authenticated;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Responder\Api\V1\Auth\PasswordChangeResponder;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Security\PasswordChange\ChangePassword;
use App\Domain\Security\Password\PasswordStructure;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ChangePasswordAction
 *
 * Derives the sole password target from request-scoped authenticated ownership
 */
#[Authenticated]
final readonly class ChangePasswordAction
{
    /**
     * Constructs ChangePasswordAction
     */
    public function __construct(private ChangePassword $change, private PasswordChangeResponder $responder)
    {
    }

    /**
     * Changes the current owner's password and ends every session
     */
    #[OA\Post(
        path: '/api/v1/auth/password/change',
        operationId: 'changePassword',
        summary: 'Change the authenticated owner password and require fresh login',
        description: <<<'TEXT'
            Requires valid Bearer authority and the request-scoped current principal; no body target,
            role, permission or ambient cookie can select or authorize an account. Direct configured HTTPS,
            exact Origin, same-origin Fetch Metadata when present and bounded unique-key JSON only.
            Twenty peer and 300 endpoint attempts per rolling 900 seconds, independently committed.
            Password bytes are never trimmed or normalized. Replacement uses shared Unicode structural
            and local common-password policy. Current proof occurs only in package changePassword.
            Wrong current proof and safe package rejection are generic 409; no password-history rule.
            Package atomically replaces Argon2id hash, advances authentication authority/revision,
            revokes all active sessions and records audit, then emits PasswordChanged.
            Success expires refresh and nonce cookies (Secure HttpOnly SameSite=Strict Path=/api/v1/auth),
            returns no replacement token and requires fresh login. Old access and refresh authority fails.
            Before-commit failure leaves authoritative sessions unchanged; system failure may be post-commit,
            so never automatically replay. Current-browser and peer memory must be terminally cleared.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(name: 'Origin', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(
                name: 'Sec-Fetch-Site',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['same-origin'])
            )
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['current_password', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(
                    property: 'current_password',
                    type: 'string',
                    minLength: 1,
                    maxLength: 128,
                    writeOnly: true
                ),
                new OA\Property(property: 'password', type: 'string', minLength: 8, maxLength: 128, writeOnly: true),
                new OA\Property(
                    property: 'password_confirmation',
                    type: 'string',
                    minLength: 8,
                    maxLength: 128,
                    writeOnly: true
                )
            ],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Changed; all sessions ended; fresh login required',
                content: new OA\JsonContent(ref: '#/components/schemas/PasswordChangeSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Password change rejected',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(
                response: 422,
                description: 'Replacement policy rejection',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationFail')
            ),
            new OA\Response(ref: '#/components/responses/InvitationThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(formName: 'password_change', rules: [
        [
            'field' => 'current_password',
            'label' => 'Current password',
            'rules' => 'required|type[string]|min_length[1]|max_length[128]'
        ],
        [
            'field'  => 'password',
            'label'  => 'Password',
            'rules'  => 'required|type[string]|min_length[8]|max_length[128]',
            'errors' => ['min_length' => PasswordStructure::GUIDANCE]
        ],
        [
            'field' => 'password_confirmation',
            'label' => 'Confirmation',
            'rules' => 'required|type[string]|min_length[8]|max_length[128]|same[password]'
        ]
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute(CurrentPrincipalProvider::class);
        if (!$provider instanceof CurrentPrincipalProvider) {
            throw new AuthenticationRequired();
        }
        /** @var array{current_password: string, password: string, password_confirmation: string} $input */
        $input = $request->getAttribute(JsonBody::class);
        $this->change->change(
            $provider->getCurrentPrincipal()->getUserId(),
            $input['current_password'],
            $input['password']
        );

        return $this->responder->respond();
    }
}
