<?php

declare(strict_types=1);

use App\Adapter\Http\Api\Failure\PublicFailureProjector;
use App\Adapter\Http\Api\Failure\RedactedHttpDiagnostics;
use App\Adapter\Http\Middleware\Api\ApiResponseBoundary;
use App\Adapter\Http\Middleware\Api\NativeApiFailures;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(SafeHttpErrorPolicy::class, static fn(): SafeHttpErrorPolicy => new SafeHttpErrorPolicy(true));
    $container->set(SafeHttpProjector::class, static fn(): PublicFailureProjector => new PublicFailureProjector());
    $container->set(SafeHttpDiagnostics::class, static fn(Container $container): RedactedHttpDiagnostics =>
        new RedactedHttpDiagnostics(
            $container->get('http.failure.logger'),
            $container->get(SafeHttpErrorPolicy::class)
        ));
    $container->set(SafeJSendErrorMiddleware::class, static fn(Container $container): SafeJSendErrorMiddleware =>
        new SafeJSendErrorMiddleware(
            $container->get(JSendResponseFactory::class),
            $container->get(SafeHttpErrorPolicy::class),
            $container->get(SafeHttpDiagnostics::class),
            $container->get(SafeHttpProjector::class)
        ));
    $container->set(ApiResponseBoundary::class, static fn(Container $container): ApiResponseBoundary =>
        new ApiResponseBoundary($container->get(SafeJSendErrorMiddleware::class)));
    $container->set(NativeApiFailures::class, static fn(): NativeApiFailures => new NativeApiFailures());
};
