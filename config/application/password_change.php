<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\ChangePasswordAction;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Middleware\Api\Auth\PasswordChangeGuard;
use App\Adapter\Http\Responder\Api\V1\Auth\PasswordChangeResponder;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Security\Password\PasswordSetting;
use App\Application\Security\PasswordChange\ChangePassword;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(ChangePassword::class, static fn(Container $c): ChangePassword =>
        new ChangePassword($c->get(PasswordSetting::class), $c->get(AuthenticationService::class)));
    $container->set(PasswordChangeResponder::class, static fn(Container $c): PasswordChangeResponder =>
        new PasswordChangeResponder($c->get(JSendResponseFactory::class), $c->get(BrowserSessionCookies::class)));
    $container->set(ChangePasswordAction::class, static fn(Container $c): ChangePasswordAction =>
        new ChangePasswordAction($c->get(ChangePassword::class), $c->get(PasswordChangeResponder::class)));
    $container->set(PasswordChangeGuard::class, static fn(Container $c): PasswordChangeGuard =>
        new PasswordChangeGuard($c->get(HmacCsrfProofs::class), $c->get(AuthAdmission::class)));
};
