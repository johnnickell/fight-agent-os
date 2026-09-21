<?php

declare(strict_types=1);

namespace App\Adapter\Http;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Application\HttpFoundation\HttpStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;

final readonly class SlimHttpExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(private JSendResponseFactory $responseFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpException $exception) {
            $statusCode = $exception->getCode();

            return $this->responseFactory->fromEnvelope(
                JSendEnvelope::error($exception->getMessage()),
                $statusCode >= 400 && $statusCode <= 599 ? $statusCode : HttpStatus::INTERNAL_SERVER_ERROR
            );
        }
    }
}
