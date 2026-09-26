<?php

declare(strict_types=1);

use App\Adapter\Http\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Middleware\Api\Auth\CsrfBootstrapGuard;
use Fight\Common\Application\Service\Container;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/** @var App $app */
/** @var Container $container */
// Only the explicit CSRF bootstrap is public. Do not add a protected route before TASK-00117's JWT guard.
$app->group('/api/v1', function (RouteCollectorProxy $api) use ($container): void {
    $api->get('/auth/csrf', CsrfBootstrapAction::class)
        ->add($container->get(CsrfBootstrapGuard::class))
        ->setName('api.v1.auth.csrf');
});
