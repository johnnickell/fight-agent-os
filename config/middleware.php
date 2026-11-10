<?php

declare(strict_types=1);

use App\Adapter\Http\Middleware\Api\ApiResponseBoundary;
use App\Adapter\Http\Middleware\Api\NativeApiFailures;
use App\Adapter\Http\Middleware\Api\SameOriginApi;
use App\Adapter\Http\Middleware\CanonicalTransport;
use App\Adapter\Http\Middleware\SecurityHeaders;
use Psr\Container\ContainerInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
// Slim is last-in, first-out: headers -> transport -> fallback -> API/Common -> native -> origin -> routing.
// Matched routes then perform admission/validation/Action dispatch. Only Common reports covered API failures.
$app->addRoutingMiddleware();
$app->add($container->get(SameOriginApi::class));
$app->add($container->get(NativeApiFailures::class));
$app->add($container->get(ApiResponseBoundary::class));
// Outermost fallback contains non-API misses without exposing or logging raw diagnostics.
// API failures retain their correlated JSend handler above in every environment.
$app->addErrorMiddleware(false, false, false);
$app->add($container->get(CanonicalTransport::class));
// Wrap error responses too; no endpoint or developer tool can broaden this policy.
$app->add($container->get(SecurityHeaders::class));
