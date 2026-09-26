<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Application\HttpFoundation\HttpStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpGoneException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpTooManyRequestsException;
use Slim\Exception\HttpUnauthorizedException;

/**
 * Class HttpExceptionMiddleware
 */
final readonly class HttpExceptionMiddleware implements MiddlewareInterface
{
    /**
     * Constructs HttpExceptionMiddleware
     */
    public function __construct(private JSendResponseFactory $responseFactory)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpException $exception) {
            $publicError = match (true) {
                $exception instanceof HttpBadRequestException => ['Bad request.', HttpStatus::BAD_REQUEST],
                $exception instanceof HttpUnauthorizedException => ['Unauthorized.', HttpStatus::UNAUTHORIZED],
                $exception instanceof HttpForbiddenException => ['Forbidden.', HttpStatus::FORBIDDEN],
                $exception instanceof HttpNotFoundException => ['Not found.', HttpStatus::NOT_FOUND],
                $exception instanceof HttpMethodNotAllowedException => [
                    'Method not allowed.',
                    HttpStatus::METHOD_NOT_ALLOWED
                ],
                $exception instanceof HttpGoneException => ['Gone.', HttpStatus::GONE],
                $exception instanceof HttpTooManyRequestsException => [
                    'Too many requests.',
                    HttpStatus::TOO_MANY_REQUESTS
                ],
                default => null,
            };

            if ($publicError === null) {
                throw $exception;
            }

            return $this->responseFactory->fromEnvelope(
                JSendEnvelope::error($publicError[0]),
                $publicError[1]
            );
        }
    }
}
