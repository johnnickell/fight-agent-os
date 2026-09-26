<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api;

use App\Adapter\Http\Api\Failure\ApiFailureMapper;
use App\Adapter\Http\Api\Failure\MappedApiFailure;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class ApiFailureMiddleware
 *
 * Correlates API responses and contains downstream API failures
 */
final readonly class ApiFailureMiddleware implements MiddlewareInterface
{
    /**
     * Constructs ApiFailureMiddleware
     */
    public function __construct(
        private ApiFailureMapper $mapper,
        private JSendResponseFactory $responses,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path !== '/api' && !str_starts_with($path, '/api/')) {
            return $handler->handle($request);
        }

        $inbound = $request->getHeader('X-Correlation-ID');
        $accepted = count($inbound) === 1 && preg_match('/\A[a-f0-9]{32}\z/D', $inbound[0]) === 1;
        $correlationId = $accepted ? $inbound[0] : bin2hex(random_bytes(16));

        try {
            $response = $handler->handle($request->withAttribute('correlation_id', $correlationId));
        } catch (Throwable $exception) {
            $classification = $this->mapper->classify($exception);
            if ($classification === null) {
                // Only fixed keys and validated scalars enter logs. Never pass the throwable,
                // request, URI, headers, body or message to a formatter/handler.
                $method = $request->getMethod();
                $this->logger->error('Unhandled HTTP request failure.', [
                    'correlation_id'     => $correlationId,
                    'correlation_source' => $accepted ? 'client' : 'generated',
                    'request_method'     => preg_match('/\A[A-Z]{1,16}\z/D', $method) ? $method : 'UNKNOWN',
                    'exception_type'     => $exception::class
                ]);
                $classification = new MappedApiFailure(500, JSendEnvelope::error('Internal server error.'));
            }

            $response = $this->responses->fromEnvelope(
                $classification->envelope,
                $classification->status,
                ['Cache-Control' => 'no-store']
            );
        }

        return $response->withHeader('X-Correlation-ID', $correlationId);
    }
}
