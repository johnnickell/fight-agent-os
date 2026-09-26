<?php

declare(strict_types=1);

use App\Adapter\Http\Middleware\HttpExceptionMiddleware;
use App\Adapter\Http\Middleware\UnexpectedErrorMiddleware;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
// Slim executes middleware last-in, first-out: generic error -> Slim HTTP error -> routing -> API validation -> route.
$app->addRoutingMiddleware();
$app->add(new HttpExceptionMiddleware($container->get(JSendResponseFactory::class)));
$app->add(new UnexpectedErrorMiddleware(
    $container->get(JSendResponseFactory::class),
    $container->get(LoggerInterface::class)
));
