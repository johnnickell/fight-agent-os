<?php

declare(strict_types=1);

use App\Adapter\Http\IndexAction;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
// app.index route
$app->get(
    '/',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($container): ResponseInterface {
        return $container->get(IndexAction::class)->handle($request, $response);
    }
)->setName('app.index');
