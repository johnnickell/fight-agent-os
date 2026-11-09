<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Sessions;

use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Sessions\OwnedSessionsResponder;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\ListActiveSessions;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use LogicException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/**
 * Class ListUserSessionsAction
 *
 * Lists one other user's usable sessions after exact HTTP admission and package target authorization
 */
#[RequiresPermission('READ_SESSIONS')]
final readonly class ListUserSessionsAction
{
    /**
     * Constructs ListUserSessionsAction
     */
    public function __construct(private QueryBus $queryBus, private OwnedSessionsResponder $responder)
    {
    }

    /**
     * Maps bounded target and pagination without accepting actor or current-session claims
     */
    #[OA\Get(
        path: '/api/v1/users/{userId}/sessions',
        operationId: 'listUserSessions',
        summary: 'Inspect another installation user\'s active sessions',
        description: <<<'TEXT'
            Requires current READ_SESSIONS and package actor/target authorization. Self targets use /me/sessions.
            Unknown or forbidden users return generic 403. Credential-free bounded pages are ordered by creation
            then ID, not a snapshot. Device metadata is unavailable. All responses are no-store.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(ref: '#/components/parameters/CatalogPage'),
            new OA\Parameter(ref: '#/components/parameters/CatalogPerPage')
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Safe session page; Cache-Control: no-store',
                content: new OA\JsonContent(ref: '#/components/schemas/OwnedSessionsSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[QueryString]
    #[Validation(rules: [
        ['field' => 'page', 'label' => 'Page', 'rules' => 'digits|min_number[1]|max_number[1000]'],
        ['field' => 'per_page', 'label' => 'Per page', 'rules' => 'digits|min_number[1]|max_number[100]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute(CurrentPrincipalProvider::class);
        if (!$provider instanceof CurrentPrincipalProvider) {
            throw new AuthenticationRequired();
        }
        $value = (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('userId');
        if (!preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D', $value)) {
            throw new ValidationException(['user_id' => ['Invalid value.']]);
        }
        $target = UserId::fromString($value);
        $principal = $provider->getCurrentPrincipal();
        if ($target->equals($principal->getUserId())) {
            throw new PermissionDenied();
        }
        /** @var array{page?: string, per_page?: string} $input */
        $input = $request->getAttribute(QueryString::class);
        try {
            $sessions = $this->queryBus->fetch(new ListActiveSessions(
                $principal->getUserId(),
                $target,
                $principal->getRefreshSessionId(),
                new Pagination((int) ($input['page'] ?? '1'), (int) ($input['per_page'] ?? '25'))
            ));
        } catch (SessionAdministrationAuthorizationException $failure) {
            throw new PermissionDenied(previous: $failure);
        }
        if (!$sessions instanceof ResultSet || $sessions->itemType() !== SessionView::class) {
            throw new LogicException('Session listing is unavailable.');
        }
        foreach ($sessions->records() as $session) {
            if (!$session->getUserId()->equals($target)) {
                throw new LogicException('Session listing is unavailable.');
            }
        }

        return $this->responder->respond(['sessions' => $sessions, 'devices' => []]);
    }
}
