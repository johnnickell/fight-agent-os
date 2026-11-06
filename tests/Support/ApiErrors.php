<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Http\Api\Failure\PublicFailureProjector;
use App\Adapter\Http\Api\Failure\RedactedHttpDiagnostics;
use App\Adapter\Http\Middleware\Api\ApiResponseBoundary;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Class ApiErrors
 *
 * Composes the owned safe boundary for focused behavioral interactions
 */
final class ApiErrors
{
    /**
     * Creates a consumer boundary with the test's observable logger
     */
    public static function boundary(LoggerInterface $logger): ApiResponseBoundary
    {
        $policy = new SafeHttpErrorPolicy(true);

        return new ApiResponseBoundary(new SafeJSendErrorMiddleware(
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory()),
            $policy,
            new RedactedHttpDiagnostics($logger, $policy),
            new PublicFailureProjector()
        ));
    }
}
