<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Action\Api\V1\Me\ReadCurrentPrincipalAction;
use App\Adapter\Http\Action\Api\V1\Validations\ReadValidationAction;
use App\Adapter\Http\Middleware\Api\Auth\ApiActionSecurity;
use App\Adapter\Http\Middleware\Api\Auth\CsrfBootstrapGuard;
use App\Adapter\Http\Middleware\Api\Validation\ApiInputValidation;
use Fight\Common\Application\Service\Container;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/** @var App $app */
/** @var Container $container */
// Group middleware executes after routing: security -> validation -> endpoint controls -> Action.
$app->group('/api/v1', function (RouteCollectorProxy $api) use ($container): void {
    $api->get('/auth/csrf', CsrfBootstrapAction::class.':handle')
        ->add($container->get(CsrfBootstrapGuard::class))
        ->setName('api.v1.auth.csrf');
    $api->get('/me', ReadCurrentPrincipalAction::class.':handle')
        ->setName('api.v1.me');
    $api->get('/validations/{form_name}', ReadValidationAction::class.':handle')
        ->setName('api.v1.validations.read');
})->add($container->get(ApiInputValidation::class))
    ->add($container->get(ApiActionSecurity::class));
