<?php

declare(strict_types=1);

use App\Adapter\Http\Api\Failure\ApiFailureMapper;
use App\Adapter\Http\Middleware\Api\ApiFailureMiddleware;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
// Slim executes middleware last-in, first-out: API failure middleware -> routing -> API validation -> route.
// The API middleware passes non-API paths through unchanged.
$app->addRoutingMiddleware();
$failureLog = new Logger('http-failure');
$failureHandler = new StreamHandler('php://stderr', Level::Error);
$failureHandler->setFormatter(new JsonFormatter());
$failureLog->pushHandler($failureHandler);
$app->add(new ApiFailureMiddleware(
    new ApiFailureMapper(),
    $container->get(JSendResponseFactory::class),
    $failureLog
));
