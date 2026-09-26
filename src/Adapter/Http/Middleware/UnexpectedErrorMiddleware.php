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
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class UnexpectedErrorMiddleware
 */
final readonly class UnexpectedErrorMiddleware implements MiddlewareInterface
{
    /**
     * Constructs UnexpectedErrorMiddleware
     */
    public function __construct(
        private JSendResponseFactory $responseFactory,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $correlationId = bin2hex(random_bytes(16));

            $this->logger->error('Unhandled HTTP request failure.', [
                'correlation_id' => $correlationId,
                'request_method' => $request->getMethod(),
                'request_path'   => $request->getUri()->getPath(),
                'exception'      => $exception
            ]);

            return $this->responseFactory->fromEnvelope(
                JSendEnvelope::error('Internal server error.'),
                HttpStatus::INTERNAL_SERVER_ERROR,
                [
                    'Cache-Control'    => 'no-store',
                    'X-Correlation-ID' => $correlationId
                ]
            );
        }
    }
}
