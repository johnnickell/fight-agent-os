<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\ResetResponseTiming;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\PublicAccess;
use App\Adapter\Http\Responder\Api\V1\Auth\ResetRequestResponder;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\RequestPasswordReset;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RequestPasswordResetAction
 *
 * Dispatches the package reset request without pre-querying or interpreting identity
 */
#[PublicAccess]
final readonly class RequestPasswordResetAction
{
    /**
     * Constructs RequestPasswordResetAction
     */
    public function __construct(
        private CommandBus $commands,
        private ResetResponseTiming $timing,
        private ResetRequestResponder $responder
    ) {
    }

    /**
     * Requests recovery with a generic account-independent acknowledgement
     */
    #[OA\Post(
        path: '/api/v1/auth/password-reset',
        operationId: 'requestPasswordReset',
        summary: 'Request a password reset without account discovery',
        description: <<<'TEXT'
            Browser-only HTTPS POST with exact configured Origin, same-origin Fetch Metadata when supplied,
            bounded unique-key JSON and nonce-bound X-CSRF-Proof. Accepts only canonical email.
            Atomic rolling 900-second budgets: 3 per HMAC email, at most 20 per server peer and 300 per endpoint.
            429 and bounded Retry-After depend only on attempts, never identity; storage failure denies.
            Eligible, nonexistent, pending, disabled, deleted and repeated requests share HTTP 200, the exact
            generic acknowledgement, no-store headers and no cookies or IDs. No public registration or delivery
            guarantee. Package-owned eligible grants last one hour and atomically replace predecessors.
            Reset delivery is recovered by the internal runner, never SMTP in this public request.
            Admitted dispatch is padded to 250ms using monotonic time, with at most one bounded wait; slower
            work is not delayed further. This reduces obvious fast missing-user paths, not network equality.
            TEXT,
        security: [],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/BrowserAuthOrigin'),
            new OA\Parameter(ref: '#/components/parameters/BrowserAuthCsrf')
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [new OA\Property(
                property: 'email',
                type: 'string',
                format: 'email',
                minLength: 1,
                maxLength: 254
            )],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Generic acknowledgement; not delivery confirmation',
                content: new OA\JsonContent(ref: '#/components/schemas/ResetRequestAccepted')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/BrowserAuthThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(formName: 'password_reset_request', rules: [[
        'field' => 'email',
        'label' => 'Email',
        'rules' => 'required|type[string]|min_length[1]|max_length[254]|email'
    ]])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{email: string} $input */
        $input = $request->getAttribute(JsonBody::class);
        $this->timing->normalize(fn() => $this->commands->execute(
            new RequestPasswordReset(EmailAddress::fromString($input['email']))
        ));

        return $this->responder->respond();
    }
}
