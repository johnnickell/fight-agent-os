<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Responder\Api\V1\Auth\CsrfBootstrapResponder;
use App\Adapter\Security\HmacCsrfProofs;
use App\Domain\Security\Csrf\Query\CsrfProofView;
use App\Domain\Security\Csrf\Query\GetCsrfProof;
use Fight\Common\Application\Messaging\Query\QueryBus;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

/**
 * Class CsrfBootstrapAction
 *
 * Maps the public CSRF cookie to one application query
 */
final readonly class CsrfBootstrapAction
{
    /**
     * Constructs CsrfBootstrapAction
     */
    public function __construct(private QueryBus $queries, private CsrfBootstrapResponder $responder)
    {
    }

    /**
     * Dispatches one CSRF bootstrap query and delegates presentation
     */
    #[OA\Get(
        path: '/api/v1/auth/csrf',
        operationId: 'getCsrfProof',
        description: <<<'TEXT'
            Sends no request body and no Content-Type header. Any body or Content-Type
            is rejected before dispatch. Query strings are forbidden. The URI must match
            the configured HTTPS origin exactly; forwarded headers are not trusted.
            Origin is optional on this GET, but if present must match the configured origin.
            Sec-Fetch-Site is optional; when present it must be exactly same-origin.
            Duplicate Origin/Fetch headers and Access-Control-Request-Method are rejected.
            A valid nonce cookie is reused without Set-Cookie; missing/invalid nonce is
            replaced. Duplicate nonce entries, multiple Cookie headers or a Cookie header
            over 4096 bytes are rejected. Other cookies do not grant authority.
            Issuance neither authenticates nor rotates refresh credentials. Keep the proof
            only in volatile memory. Mutation verification belongs to later operations.
            TEXT,
        summary: 'Bootstrap a short-lived non-authoritative CSRF proof',
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'Origin',
                description: 'If supplied, exactly the externally configured trusted HTTPS origin',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'Sec-Fetch-Site',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['same-origin'])
            ),
            new OA\Parameter(
                name: 'X-Correlation-ID',
                description: <<<'TEXT'
                    Exactly one 32-character lowercase hexadecimal value is preserved.
                    Invalid, duplicated or absent values are replaced, not rejected.
                    TEXT,
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: '__Secure-agent_os_csrf',
                description: <<<'TEXT'
                    Browser-managed HttpOnly nonce, never a refresh credential. Canonical
                    values are 64 lowercase hexadecimal characters; invalid values are replaced.
                    TEXT,
                in: 'cookie',
                required: false,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: <<<'TEXT'
                    MAC proof bound to the nonce, origin and expiry (900 seconds). Set-Cookie
                    appears only for a new nonce. The host-only browser-session cookie has
                    no Domain, Expires or Max-Age. No refresh credential is returned.
                    TEXT,
                headers: [
                    new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
                    new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID'),
                    new OA\Header(
                        header: 'Set-Cookie',
                        description: 'Only on nonce creation/replacement; inaccessible to JavaScript',
                        schema: new OA\Schema(
                            type: 'string',
                            pattern: <<<'PATTERN'
                            ^__Secure-agent_os_csrf=[a-f0-9]{64}; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict$
                            PATTERN
                        )
                    )
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/CsrfSuccess')
            ),
            new OA\Response(ref: '#/components/responses/BadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $nonce = null;
        $cookies = $request->getHeader('Cookie');
        if (count($cookies) > 1) {
            throw new HttpBadRequestException($request);
        }

        $found = false;
        foreach (explode(';', $cookies[0] ?? '') as $cookie) {
            $pair = explode('=', trim($cookie), 2);
            if ($pair[0] !== CsrfCookie::NAME) {
                continue;
            }

            if ($found || count($pair) !== 2) {
                throw new HttpBadRequestException($request);
            }

            $found = true;
            $nonce = HmacCsrfProofs::validNonce($pair[1]) ? $pair[1] : null;
        }

        $result = $this->queries->fetch(new GetCsrfProof($nonce));
        if (!$result instanceof CsrfProofView) {
            throw new \UnexpectedValueException('Unexpected CSRF query result.');
        }

        return $this->responder->respond($result);
    }
}
