<?php

declare(strict_types=1);

namespace App\Adapter\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class IndexAction
{
    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write('Fight Agent OS is ready.');

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
