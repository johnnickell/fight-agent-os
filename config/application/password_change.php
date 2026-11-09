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
    $container->set(ChangePassword::class, static fn(Container $container): ChangePassword =>
        new ChangePassword($container->get(PasswordSetting::class), $container->get(AuthenticationService::class)));
    $container->set(PasswordChangeResponder::class, static fn(Container $container): PasswordChangeResponder =>
        new PasswordChangeResponder(
            $container->get(JSendResponseFactory::class),
            $container->get(BrowserSessionCookies::class)
        ));
    $container->set(ChangePasswordAction::class, static fn(Container $container): ChangePasswordAction =>
        new ChangePasswordAction(
            $container->get(ChangePassword::class),
            $container->get(PasswordChangeResponder::class)
        ));
    $container->set(PasswordChangeGuard::class, static fn(Container $container): PasswordChangeGuard =>
        new PasswordChangeGuard($container->get(HmacCsrfProofs::class), $container->get(AuthAdmission::class)));
};
