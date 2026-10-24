<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Documentation;

use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Documentation\PrivateOpenApi;
use App\Adapter\Http\Documentation\PrivateOpenApiResponder;
use App\Adapter\Http\Web\PublicRuntimeConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Throwable;

/**
 * Class ReadPrivateOpenApiAction
 *
 * Reads the private contract only after current permission and environment checks
 */
#[RequiresPermission('READ_SWAGGER')]
final readonly class ReadPrivateOpenApiAction
{
    /**
     * Constructs ReadPrivateOpenApiAction
     */
    public function __construct(
        private PublicRuntimeConfiguration $configuration,
        private PrivateOpenApi $document,
        private PrivateOpenApiResponder $responder
    ) {
    }

    /**
     * Rejects unavailable documents without exposing paths or parsing diagnostics
     */
    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->configuration->swaggerAvailable) {
            throw new HttpNotFoundException($request);
        }
        try {
            $document = $this->document->read();
        } catch (Throwable) {
            return $this->responder->unavailable();
        }

        return $this->responder->respond($response, $document);
    }
}
