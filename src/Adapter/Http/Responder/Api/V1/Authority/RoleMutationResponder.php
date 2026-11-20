<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Authority;

use App\Adapter\Persistence\PersistenceConflict;
use Fight\AccessControl\Domain\AccessControl\Role\Exception\CustomRoleException;
use Fight\AccessControl\Domain\AccessControl\Role\Exception\RoleConflictException;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\PreconditionFailed;
use Fight\Common\Application\Error\Exception\RuleViolation;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class RoleMutationResponder
 */
#[OA\Schema(
    schema: 'RoleRemovalSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', type: 'object', nullable: true)
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class RoleMutationResponder
{
    /**
     * Constructs RoleMutationResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Maps only known atomic conflicts without rendering package or storage diagnostics
     *
     * @param callable(): ?RoleView $operation
     */
    public function respond(callable $operation, int $status = 200): ResponseInterface
    {
        try {
            $view = $operation();
        } catch (CustomRoleException | PersistenceConflict | RuleViolation $failure) {
            throw new StateConflict(previous: $failure);
        } catch (PreconditionFailed $failure) {
            // The expected Role revision is a JSON field with an existing 409 contract, not an If-Match header.
            if (!$failure->getPrevious() instanceof RoleConflictException) {
                throw $failure;
            }
            throw new StateConflict(previous: $failure);
        }

        return $this->responses->fromEnvelope(
            JSendEnvelope::success($view === null ? null : new CatalogData($view)),
            $status,
            ['Cache-Control' => 'no-store']
        );
    }
}
