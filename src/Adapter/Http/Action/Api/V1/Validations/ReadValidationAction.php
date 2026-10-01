<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Validations;

use App\Adapter\Http\Responder\Api\V1\Validations\ReadValidationResponder;
use App\Adapter\Validation\Catalog;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 * Class ReadValidationAction
 *
 * Reads only the explicitly published public schema for one bounded name
 */
final readonly class ReadValidationAction
{
    /**
     * Constructs ReadValidationAction
     */
    public function __construct(private Catalog $catalog, private ReadValidationResponder $responder)
    {
    }

    /**
     * Returns an approved form's metadata without authorizing its operation
     *
     * @param ServerRequestInterface  $request
     * @param ResponseInterface       $response
     * @param array<string, string>   $args
     */
    #[OA\Get(
        path: '/api/v1/validations/{form_name}',
        operationId: 'readPublicValidation',
        summary: 'Read explicitly published browser-safe validation metadata',
        description: <<<'TEXT'
            Public read only; no authentication, credentials, cookies, body,
            Content-Type or query input. Does not authorize a form operation.
            Complete private generation required.
            TEXT,
        security: [],
        parameters: [new OA\Parameter(
            name: 'form_name',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', pattern: '^[a-z][a-z0-9_]{0,63}$', maxLength: 64)
        )],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complete safe public form; no-store, no cookie',
                headers: [
                    new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
                    new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/PublicValidationSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $name = $args['form_name'] ?? '';
        if (!Catalog::identifier($name)) {
            throw new HttpBadRequestException($request);
        }
        $schema = $this->catalog->find($name);
        if ($schema === null) {
            throw new HttpNotFoundException($request);
        }

        return $this->responder->respond($schema);
    }
}
