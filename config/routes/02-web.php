<?php

declare(strict_types=1);

use App\Adapter\Http\Action\ClientShellAction;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Exception\HttpNotFoundException;

/** @var App $app */
/** @var ContainerInterface $container */
// Application root shares the bounded browser restoration and routing decision.
$app->get(
    '/',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($container): ResponseInterface {
        return $container->get(ClientShellAction::class)->handle($request, $response);
    }
)->setName('app.index');

// Final browser GET fallback: explicit server routes win and server/asset misses stay failures.
$app->get(
    '/{path:.*}',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($container): ResponseInterface {
        $path = rawurldecode($request->getUri()->getPath());
        if (preg_match('~\A/(?:api|mcp|build|app)(?:/|\z)~', $path) === 1 || str_contains($path, '.')) {
            throw new HttpNotFoundException($request);
        }

        return $container->get(ClientShellAction::class)->handle($request, $response);
    }
)->setName('app.shell');
