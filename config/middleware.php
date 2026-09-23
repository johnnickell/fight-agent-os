<?php

declare(strict_types=1);

use App\Adapter\Http\Middleware\SlimHttpExceptionMiddleware;
use App\Adapter\Http\Middleware\UnexpectedErrorMiddleware;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\JsonRequestMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
// Slim executes middleware last-in, first-out: generic error -> JSON -> Slim HTTP error -> routing -> route.
$app->addRoutingMiddleware();
$app->add(new SlimHttpExceptionMiddleware($container->get(JSendResponseFactory::class)));
$app->add(new JsonRequestMiddleware());
$app->add(new UnexpectedErrorMiddleware(
    $container->get(JSendResponseFactory::class),
    $container->get(LoggerInterface::class)
));
