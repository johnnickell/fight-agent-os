<?php

declare(strict_types=1);

use App\Adapter\Http\Api\Failure\ApiFailureMapper;
use App\Adapter\Http\Middleware\Api\ApiFailureMiddleware;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Psr\Container\ContainerInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
// Slim executes middleware last-in, first-out: API failure middleware -> routing -> API validation -> route.
// The API middleware passes non-API paths through unchanged.
$app->addRoutingMiddleware();
$app->add(new ApiFailureMiddleware(
    new ApiFailureMapper(),
    $container->get(JSendResponseFactory::class),
    $container->get('http.failure.logger')
));
// Outermost fallback contains non-API misses without exposing or logging raw diagnostics.
// API failures retain their correlated JSend handler above in every environment.
$app->addErrorMiddleware(false, false, false);
