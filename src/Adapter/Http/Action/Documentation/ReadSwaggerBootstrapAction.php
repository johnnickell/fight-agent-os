<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Documentation;

use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Documentation\SwaggerBootstrapResponder;
use App\Adapter\Http\Web\PublicRuntimeConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * Class ReadSwaggerBootstrapAction
 *
 * Reads protected viewer initialization independently of the public application shell
 */
#[RequiresPermission('READ_SWAGGER')]
final readonly class ReadSwaggerBootstrapAction
{
    /**
     * Constructs ReadSwaggerBootstrapAction
     */
    public function __construct(
        private PublicRuntimeConfiguration $configuration,
        private SwaggerBootstrapResponder $responder
    ) {
    }

    /**
     * Requires configured eligibility after current request authorization
     */
    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->configuration->swaggerAvailable) {
            throw new HttpNotFoundException($request);
        }

        return $this->responder->respond();
    }
}
