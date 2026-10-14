<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Action\Api\V1\Validations\ReadValidationAction;
use App\Adapter\Http\Middleware\Api\Auth\CsrfBootstrapGuard;
use App\Adapter\Http\Middleware\Api\Validation\ApiInputValidation;
use Fight\Common\Application\Service\Container;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/** @var App $app */
/** @var Container $container */
// Only CSRF bootstrap and the public-safe validation read are public before TASK-00117's JWT guard.
$app->group('/api/v1', function (RouteCollectorProxy $api) use ($container): void {
    $api->get('/auth/csrf', CsrfBootstrapAction::class.':handle')
        ->add($container->get(CsrfBootstrapGuard::class))
        ->setName('api.v1.auth.csrf');
    $api->get('/validations/{form_name}', ReadValidationAction::class.':handle')
        ->setName('api.v1.validations.read');
})->add($container->get(ApiInputValidation::class));
