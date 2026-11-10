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

// Exclude reserved namespaces while matching so GET cannot mask an API's POST-only 405.
// Decoded-path checks below also reject encoded namespace/asset spellings.
$app->get(
    '/{path:(?!(?:api|mcp|build|app)(?:/|$)).*}',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($container): ResponseInterface {
        $path = rawurldecode($request->getUri()->getPath());
        if (preg_match('~\A/(?:api|mcp|build|app)(?:/|\z)~', $path) === 1 || str_contains($path, '.')) {
            throw new HttpNotFoundException($request);
        }

        return $container->get(ClientShellAction::class)->handle($request, $response);
    }
)->setName('app.shell');
