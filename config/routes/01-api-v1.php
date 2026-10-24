<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\ActivateInvitationAction;
use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Action\Api\V1\Auth\LoginAction;
use App\Adapter\Http\Action\Api\V1\Auth\LogoutAction;
use App\Adapter\Http\Action\Api\V1\Auth\RefreshAction;
use App\Adapter\Http\Action\Api\V1\Auth\RequestPasswordResetAction;
use App\Adapter\Http\Action\Api\V1\Authority\CreateRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\GetPermissionAction;
use App\Adapter\Http\Action\Api\V1\Authority\GetRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListPermissionsAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListRolesAction;
use App\Adapter\Http\Action\Api\V1\Authority\RemoveRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\RenameRoleAction;
use App\Adapter\Http\Action\Api\V1\Invitations\CorrectInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\IssueInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\ReadInvitationStatusAction;
use App\Adapter\Http\Action\Api\V1\Invitations\ResendInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\RetryInvitationAction;
use App\Adapter\Http\Action\Api\V1\Me\ReadCurrentPrincipalAction;
use App\Adapter\Http\Action\Api\V1\Repositories\GenerateExceptionalReferenceAction;
use App\Adapter\Http\Action\Api\V1\Repositories\GetRepositoryRegistrationAction;
use App\Adapter\Http\Action\Api\V1\Repositories\ListRepositoryRegistrationsAction;
use App\Adapter\Http\Action\Api\V1\Repositories\PreviewRepositoryConflictsAction;
use App\Adapter\Http\Action\Api\V1\Repositories\RegisterRepositoryAction;
use App\Adapter\Http\Action\Api\V1\Validations\ReadValidationAction;
use App\Adapter\Http\Action\Documentation\ReadPrivateOpenApiAction;
use App\Adapter\Http\Action\Documentation\ReadSwaggerBootstrapAction;
use App\Adapter\Http\Middleware\Api\Auth\ActivationAdmission;
use App\Adapter\Http\Middleware\Api\Auth\ApiActionSecurity;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Middleware\Api\Auth\CsrfBootstrapGuard;
use App\Adapter\Http\Middleware\Api\Auth\ResetRequestGuard;
use App\Adapter\Http\Middleware\Api\Validation\ApiInputValidation;
use Fight\Common\Application\Service\Container;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/** @var App $app */
/** @var Container $container */
// Group middleware executes after routing: security -> validation -> endpoint controls -> Action.
$app->group('/api/v1', function (RouteCollectorProxy $api) use ($container): void {
    $api->get('/documentation/bootstrap', ReadSwaggerBootstrapAction::class.':handle');
    $api->get('/documentation/openapi', ReadPrivateOpenApiAction::class.':handle');
    $api->get('/auth/csrf', CsrfBootstrapAction::class.':handle')
        ->add($container->get(CsrfBootstrapGuard::class))
        ->setName('api.v1.auth.csrf');
    $api->post('/auth/login', LoginAction::class.':handle')
        ->add($container->get('auth.guard.login'))
        ->setName('api.v1.auth.login');
    $api->post('/auth/password-reset', RequestPasswordResetAction::class.':handle')
        ->add($container->get(ResetRequestGuard::class))
        ->setName('api.v1.auth.password-reset');
    $api->post('/auth/refresh', RefreshAction::class.':handle')
        ->add($container->get('auth.guard.refresh'))
        ->setName('api.v1.auth.refresh');
    $api->post('/auth/logout', LogoutAction::class.':handle')
        ->add($container->get('auth.guard.logout'))
        ->setName('api.v1.auth.logout');
    $api->post('/auth/activate', ActivateInvitationAction::class.':handle')
        ->add($container->get(ActivationAdmission::class))
        ->add($container->get(BrowserMutationGuard::class))
        ->setName('api.v1.auth.activate');
    $api->post('/invitations', IssueInvitationAction::class.':handle')->setName('api.v1.invitations.issue');
    $api->post('/invitations/{user_id}/retry', RetryInvitationAction::class.':handle')
        ->setName('api.v1.invitations.retry');
    $api->post('/invitations/{user_id}/resend', ResendInvitationAction::class.':handle')
        ->setName('api.v1.invitations.resend');
    $api->patch('/invitations/{user_id}/email', CorrectInvitationAction::class.':handle')
        ->setName('api.v1.invitations.correct');
    $api->get('/invitations/{user_id}', ReadInvitationStatusAction::class.':handle')
        ->setName('api.v1.invitations.status');
    $api->post('/workspaces/{workspace_id}/repositories', RegisterRepositoryAction::class.':handle')
        ->setName('api.v1.repositories.register');
    $api->post(
        '/workspaces/{workspace_id}/repositories/conflict-preview',
        PreviewRepositoryConflictsAction::class.':handle'
    )
        ->setName('api.v1.repositories.preview');
    $api->get(
        '/workspaces/{workspace_id}/repositories/{repository_id}',
        GetRepositoryRegistrationAction::class.':handle'
    )
        ->setName('api.v1.repositories.get');
    $api->get('/workspaces/{workspace_id}/repositories', ListRepositoryRegistrationsAction::class.':handle')
        ->setName('api.v1.repositories.list');
    $api->post('/workspaces/{workspace_id}/references', GenerateExceptionalReferenceAction::class.':handle')
        ->setName('api.v1.references.generate');
    $api->post('/roles', CreateRoleAction::class.':handle')->setName('api.v1.roles.create');
    $api->patch('/roles/{roleId}', RenameRoleAction::class.':handle')->setName('api.v1.roles.rename');
    $api->delete('/roles/{roleId}', RemoveRoleAction::class.':handle')->setName('api.v1.roles.remove');
    $api->get('/roles', ListRolesAction::class.':handle')->setName('api.v1.roles.list');
    $api->get('/roles/{roleId}', GetRoleAction::class.':handle')->setName('api.v1.roles.get');
    $api->get('/permissions', ListPermissionsAction::class.':handle')->setName('api.v1.permissions.list');
    $api->get('/permissions/{permissionId}', GetPermissionAction::class.':handle')->setName('api.v1.permissions.get');
    $api->get('/me', ReadCurrentPrincipalAction::class.':handle')
        ->setName('api.v1.me');
    $api->get('/validations/{form_name}', ReadValidationAction::class.':handle')
        ->setName('api.v1.validations.read');
})->add($container->get(ApiInputValidation::class))
    ->add($container->get(ApiActionSecurity::class));
