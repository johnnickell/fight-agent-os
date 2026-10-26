<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Sessions;

use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Sessions\OwnedSessionsResponder;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Sessions\ReadOwnedSessions;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ListOwnedSessionsAction
 *
 * Maps checked pagination and the sole current principal into an owned-session read
 */
#[RequiresPermission('READ_OWN_SESSIONS')]
final readonly class ListOwnedSessionsAction
{
    /**
     * Constructs ListOwnedSessionsAction
     */
    public function __construct(private ReadOwnedSessions $sessions, private OwnedSessionsResponder $responder)
    {
    }

    /**
     * Lists only usable owned sessions without accepting identity, sort or administrative input
     */
    #[OA\Get(
        path: '/api/v1/me/sessions',
        operationId: 'listOwnedSessions',
        summary: 'List owned active refresh sessions ordered by created_at then id ascending',
        description: <<<'TEXT'
            Requires Bearer and exact current READ_OWN_SESSIONS. Actor, owner and current session derive only
            from one CurrentPrincipalProvider. Package ListActiveSessions owns usable selection and ownership.
            Offset pages are deterministic for committed state, not a cross-request snapshot; the current
            session may be on another page. Activity means creation or successful refresh, never online presence.
            Original coarse presentation is untrusted and non-authoritative; missing metadata means Unknown device.
            No raw User-Agent, IP, versions, fingerprints, credentials, cookies, owner ID, audit or internal state.
            Responses are no-store. No revocation operation.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/CatalogPage'),
            new OA\Parameter(ref: '#/components/parameters/CatalogPerPage')
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Safe owned session page',
                headers: [new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control')],
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
        /** @var array{page?: string, per_page?: string} $input */
        $input = $request->getAttribute(QueryString::class);

        return $this->responder->respond($this->sessions->read(
            $provider,
            (int) ($input['page'] ?? '1'),
            (int) ($input['per_page'] ?? '25')
        ));
    }
}
