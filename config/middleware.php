<?php

declare(strict_types=1);

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\JSendErrorMiddleware;
use Fight\Common\Adapter\Middleware\Psr15\JsonRequestMiddleware;
use App\Adapter\Http\SlimHttpExceptionMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
// Slim executes middleware last-in, first-out: generic error -> JSON -> Slim HTTP error -> routing -> route.
$app->addRoutingMiddleware();
$app->add(new SlimHttpExceptionMiddleware($container->get(JSendResponseFactory::class)));
$app->add(new JsonRequestMiddleware());
$app->add(new JSendErrorMiddleware($container->get(JSendResponseFactory::class)));
