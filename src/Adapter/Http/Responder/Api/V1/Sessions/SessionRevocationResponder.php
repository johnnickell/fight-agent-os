<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Sessions;

use App\Adapter\Http\Api\Failure\CurrentSessionRevocation;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\CurrentRefreshSessionRevocationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionConflictException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class SessionRevocationResponder
 *
 * Collapses unsafe target outcomes without copying package or persistence diagnostics
 */
#[OA\Schema(
    schema: 'SessionRevocationSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', type: 'object', nullable: true, enum: [null])
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'CurrentSessionRevocationConflict',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['fail']),
        new OA\Property(property: 'data', required: ['message', 'intent'], properties: [
            new OA\Property(property: 'message', type: 'string', enum: ['Use Logout for the current session.']),
            new OA\Property(property: 'intent', type: 'string', enum: ['use_logout'])
        ], type: 'object', additionalProperties: false)
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class SessionRevocationResponder
{
    /**
     * Constructs SessionRevocationResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Maps only observed commit or allowlisted conflicts and leaves unexpected failures sanitized upstream
     *
     * @param callable(): void $operation
     */
    public function respond(callable $operation): ResponseInterface
    {
        try {
            $operation();
        } catch (CurrentRefreshSessionRevocationException $failure) {
            throw new CurrentSessionRevocation(previous: $failure);
        } catch (Throwable $failure) {
            if (
                !$failure instanceof RefreshSessionNotFoundException
                && !$failure instanceof RefreshSessionConflictException
                && !$failure instanceof SessionAdministrationAuthorizationException
            ) {
                throw $failure;
            }

            throw new StateConflict(previous: $failure);
        }

        return $this->responses->fromEnvelope(JSendEnvelope::success(), 200, ['Cache-Control' => 'no-store']);
    }
}
