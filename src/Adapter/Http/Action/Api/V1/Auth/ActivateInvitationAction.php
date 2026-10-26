<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\CaptureSessionPresentation;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\PublicAccess;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use App\Application\Security\Activation\ActivateInvitation;
use App\Domain\Security\Password\PasswordStructure;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ActivateInvitationAction
 *
 * Maps checked public fields into password acceptance and package activation
 */
#[PublicAccess]
final readonly class ActivateInvitationAction
{
    /**
     * Constructs ActivateInvitationAction
     */
    public function __construct(
        private ActivateInvitation $activation,
        private SessionResponder $responder,
        private CaptureSessionPresentation $presentation
    ) {
    }

    /**
     * Completes activation by possession without enabling registration or remembered sessions
     */
    #[OA\Post(
        path: '/api/v1/auth/activate',
        operationId: 'activateInvitation',
        summary: 'Activate an invited identity and establish an ordinary session',
        description: <<<'TEXT'
            No public registration. Requires exact configured HTTPS Origin, same-origin Fetch Metadata when present,
            JSON only, a nonce-bound X-CSRF-Proof from /auth/csrf and its browser-managed HttpOnly nonce cookie.
            No Authorization or alternate credential source. Unknown, used, expired, replaced and raced grants
            share generic 409 recovery. Ten network attempts and 300 endpoint attempts per fixed 900-second window;
            admission commits before activation and denies on storage outage. Network identity is REMOTE_ADDR only;
            trusted ingress enrollment is separate. Password policy is independently enforced server-side.
            Success returns a memory-only access JWT (900 seconds) and sets __Secure-agent_os_refresh, host-only,
            Path=/api/v1/auth, Secure, HttpOnly, SameSite=Strict, no Expires/Max-Age. Refresh is absent from JSON.
            Ordinary server session: one-day idle, two-day absolute. CSRF nonce rotates on success.
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
                        type: 'string',
                        minLength: 1,
                        maxLength: 512,
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
                description: 'Activated, ordinary refresh cookie set; access only in memory',
                content: new OA\JsonContent(ref: '#/components/schemas/SessionAccessSuccess')
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
                description: 'Choose a password meeting the shared acceptance policy',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationFail')
            ),
            new OA\Response(ref: '#/components/responses/InvitationThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(formName: 'invitation_activation', rules: [
        ['field' => 'user_id', 'label' => 'User', 'rules' => 'required|type[string]|uuid'],
        [
            'field' => 'credential',
            'label' => 'Credential',
            'rules' => 'required|type[string]|min_length[1]|max_length[512]'
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

        $tokens = $this->activation->activate(
            UserId::fromString($input['user_id']),
            $input['credential'],
            $input['password']
        );
        $this->presentation->capture($tokens, $request);

        return $this->responder->respond($tokens);
    }
}
