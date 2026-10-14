<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Me;

use App\Adapter\Http\Attribute\Authenticated;
use App\Adapter\Http\Responder\Api\V1\Me\ReadCurrentPrincipalResponder;
use App\Application\Failure\AuthenticationRequired;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Query\QueryBus;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ReadCurrentPrincipalAction
 *
 * Queries the package safe identity for the current authoritative principal
 */
#[Authenticated]
final readonly class ReadCurrentPrincipalAction
{
    /**
     * Constructs ReadCurrentPrincipalAction
     */
    public function __construct(private QueryBus $queries, private ReadCurrentPrincipalResponder $responder)
    {
    }

    /**
     * Dispatches one safe identity query without requiring dashboard permission
     */
    #[OA\Get(
        path: '/api/v1/me',
        operationId: 'readCurrentPrincipal',
        summary: 'Read the current authoritative safe identity and permissions',
        security: [['AccessBearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Safe current identity and current authority; no credentials or session identifiers',
                headers: [
                    new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
                    new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/CurrentPrincipalSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute(CurrentPrincipalProvider::class);
        if (!$provider instanceof CurrentPrincipalProvider) {
            throw new AuthenticationRequired();
        }
        $principal = $provider->getCurrentPrincipal();
        $identity = $this->queries->fetch(new GetUserById($principal->getUserId()));
        if (
            !$identity instanceof UserView || !$identity->getUserId()->equals($principal->getUserId())
            || $identity->getState() !== UserState::ACTIVE
        ) {
            throw new AuthenticationRequired();
        }

        return $this->responder->respond($identity, $principal);
    }
}
