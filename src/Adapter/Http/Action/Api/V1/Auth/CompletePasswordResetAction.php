<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\ResetBrowserSession;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\PublicAccess;
use App\Adapter\Http\Responder\Api\V1\Auth\ResetCompletionResponder;
use App\Application\Security\PasswordReset\CompletePasswordReset;
use App\Domain\Security\Password\PasswordStructure;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CompletePasswordResetAction
 *
 * Maps checked possession fields into shared policy and package reset orchestration
 */
#[PublicAccess]
final readonly class CompletePasswordResetAction
{
    /**
     * Constructs CompletePasswordResetAction
     */
    public function __construct(
        private CompletePasswordReset $reset,
        private ResetBrowserSession $browserSession,
        private ResetCompletionResponder $responder
    ) {
    }

    /**
     * Completes current reset authority without creating a session
     */
    #[OA\Post(
        path: '/api/v1/auth/password-reset/complete',
        operationId: 'completePasswordReset',
        summary: 'Complete one current one-hour reset and require fresh login',
        description: <<<'TEXT'
            Possession only: ambient refresh cookies and authenticated roles cannot authorize reset.
            Requires direct configured HTTPS, exact Origin, same-origin Fetch Metadata when present,
            JSON-only unique-key bounded object, nonce cookie and X-CSRF-Proof from /auth/csrf.
            No Authorization, query, forwarding or method-override alternatives.
            Twenty peer and 300 endpoint attempts per rolling 900 seconds commit atomically before reset;
            HMAC keys contain no credential, password or user identity. Storage failure denies admission.
            Shared untrimmed Unicode structural and local common-password policy is authoritative server-side.
            Package consumes the latest usable purpose-bound grant, replaces the Argon2id hash, advances
            authentication authority, revokes all target sessions and records audit atomically, then emits event.
            No access/refresh token is issued. Fresh login is required. Matching target refresh/CSRF cookies
            expire with their fixed Secure HttpOnly SameSite=Strict Path=/api/v1/auth identity; unrelated
            cookies are untouched. All invalid, unknown, used, expired, replaced and raced grants share 409.
            System failure can be post-commit: never automatically replay.
            TEXT,
        security: [],
        parameters: [
            new OA\Parameter(name: 'Origin', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'X-CSRF-Proof', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(
                name: 'Sec-Fetch-Site',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['same-origin'])
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'credential', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid', writeOnly: true),
                    new OA\Property(
                        property: 'credential',
                        description: 'Unchanged opaque material, at most 4096 UTF-8 bytes',
                        type: 'string',
                        minLength: 1,
                        maxLength: 4096,
                        writeOnly: true
                    ),
                    new OA\Property(
                        property: 'password',
                        type: 'string',
                        minLength: 8,
                        maxLength: 128,
                        writeOnly: true
                    ),
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
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Completed; no session issued, fresh login required',
                content: new OA\JsonContent(ref: '#/components/schemas/ResetCompletionSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'This link cannot be used',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(
                response: 422,
                description: 'Shared password policy rejected the candidate',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationFail')
            ),
            new OA\Response(ref: '#/components/responses/InvitationThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(formName: 'password_reset_completion', rules: [
        ['field' => 'user_id', 'label' => 'User', 'rules' => 'required|type[string]|uuid'],
        [
            'field' => 'credential',
            'label' => 'Credential',
            'rules' => 'required|type[string]|min_length[1]|max_length[4096]'
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
        /** @var array{user_id: string, credential: string, password: string, password_confirmation: string} $input */
        $input = $request->getAttribute(JsonBody::class);
        $target = UserId::fromString($input['user_id']);
        // Inspect cookie ownership before reset; it is cleanup context only and grants no authority.
        $clear = $this->browserSession->matches($request, $target);
        $this->reset->complete($target, $input['credential'], $input['password']);

        return $this->responder->respond($clear);
    }
}
