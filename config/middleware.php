<?php

declare(strict_types=1);

use App\Adapter\Http\Middleware\Api\ApiResponseBoundary;
use App\Adapter\Http\Middleware\Api\NativeApiFailures;
use Psr\Container\ContainerInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
// Slim is last-in, first-out: API scope/Common -> native translation -> routing -> admission -> validation -> Action.
// Only Common handles/reports covered failures; these adapter middlewares pass non-API paths through.
$app->addRoutingMiddleware();
$app->add($container->get(NativeApiFailures::class));
$app->add($container->get(ApiResponseBoundary::class));
// Outermost fallback contains non-API misses without exposing or logging raw diagnostics.
// API failures retain their correlated JSend handler above in every environment.
$app->addErrorMiddleware(false, false, false);
