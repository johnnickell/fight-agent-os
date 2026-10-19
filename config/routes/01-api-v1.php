<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Action\Api\V1\Invitations\CorrectInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\IssueInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\ReadInvitationStatusAction;
use App\Adapter\Http\Action\Api\V1\Invitations\ResendInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\RetryInvitationAction;
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
    $api->post('/invitations', IssueInvitationAction::class.':handle')->setName('api.v1.invitations.issue');
    $api->post('/invitations/{user_id}/retry', RetryInvitationAction::class.':handle')
        ->setName('api.v1.invitations.retry');
    $api->post('/invitations/{user_id}/resend', ResendInvitationAction::class.':handle')
        ->setName('api.v1.invitations.resend');
    $api->patch('/invitations/{user_id}/email', CorrectInvitationAction::class.':handle')
        ->setName('api.v1.invitations.correct');
    $api->get('/invitations/{user_id}', ReadInvitationStatusAction::class.':handle')
        ->setName('api.v1.invitations.status');
    $api->get('/me', ReadCurrentPrincipalAction::class.':handle')
        ->setName('api.v1.me');
    $api->get('/validations/{form_name}', ReadValidationAction::class.':handle')
        ->setName('api.v1.validations.read');
})->add($container->get(ApiInputValidation::class))
    ->add($container->get(ApiActionSecurity::class));
