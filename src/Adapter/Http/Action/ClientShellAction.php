<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action;

use App\Adapter\Http\Web\ClientAssetManifest;
use App\Adapter\Http\Web\ClientShellResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ClientShellAction
 *
 * Serves the non-product browser foundation
 */
final class ClientShellAction
{
    /**
     * Constructs ClientShellAction
     */
    public function __construct(
        private readonly ClientAssetManifest $manifest,
        private readonly ClientShellResponder $responder
    ) {
    }

    /**
     * Returns the shell independently of API and root smoke routes
     */
    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->responder->respond($response, $this->manifest->read());
    }
}
