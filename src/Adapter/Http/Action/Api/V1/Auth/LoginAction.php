<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Auth;

use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\PublicAccess;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class LoginAction
 *
 * Maps checked browser input directly to the package-owned authentication lifecycle
 */
#[PublicAccess]
final readonly class LoginAction
{
    /**
     * Constructs LoginAction
     */
    public function __construct(private AuthenticationService $authentication, private SessionResponder $responder)
    {
    }

    /**
     * Creates an ordinary or explicitly remembered session without exposing refresh material
     */
    #[OA\Post(
        path: '/api/v1/auth/login',
        operationId: 'login',
        summary: 'Log in with canonical email and an existing password',
        description: <<<'TEXT'
            Browser-only HTTPS POST with exact configured Origin, same-origin Fetch Metadata when supplied,
            JSON and nonce-bound X-CSRF-Proof. No Authorization or alternate credential source.
            Five attempts per canonical email per rolling 900 seconds, independently committed regardless of
            identity or result. Nonexistent, pending, disabled, deleted, wrong-password and email-throttled
            identities all return identical 401 without cookies or identity-dependent retry metadata.
            Network/endpoint admission uses separate rolling limits of at most 20/300 per 900 seconds and
            returns bounded 429; storage failure denies. Passwords are verified, not subject to setting policy.
            Success returns only memory access authority for 900 seconds; refresh is only a host-only Secure,
            HttpOnly, SameSite=Strict cookie at /api/v1/auth. Ordinary sessions have one-day idle/two-day
            absolute limits and no cookie Expires/Max-Age. Remembered sessions have 30-day idle/one-year
            absolute limits and cookie Expires no later than absolute expiry. CSRF nonce rotates on success.
            No roles, permissions, refresh credential or identity lifecycle is returned. No automatic replay.
            TEXT,
        security: [],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/BrowserAuthOrigin'),
            new OA\Parameter(ref: '#/components/parameters/BrowserAuthCsrf'),
            new OA\Parameter(
                name: 'Sec-Fetch-Site',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['same-origin'])
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/BrowserAuthLoginInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Session established; refresh only in HttpOnly cookie',
                content: new OA\JsonContent(ref: '#/components/schemas/SessionAccessSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/BrowserAuthThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(formName: 'login', rules: [
        [
            'field' => 'email',
            'label' => 'Email',
            'rules' => 'required|type[string]|min_length[1]|max_length[254]|email'
        ],
        [
            'field' => 'password',
            'label' => 'Password',
            'rules' => 'required|type[string]|min_length[1]|max_length[128]'
        ],
        ['field' => 'remember', 'label' => 'Remember', 'rules' => 'required|type[bool]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{email: string, password: string, remember: bool} $input */
        $input = $request->getAttribute(JsonBody::class);

        return $this->responder->respond($this->authentication->login(
            $input['email'],
            $input['password'],
            $input['remember']
        ));
    }
}
