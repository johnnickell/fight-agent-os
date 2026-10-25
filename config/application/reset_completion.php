<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\CompletePasswordResetAction;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\RefreshCookie;
use App\Adapter\Http\Api\V1\Auth\ResetBrowserSession;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Middleware\Api\Auth\ResetCompletionGuard;
use App\Adapter\Http\Responder\Api\V1\Auth\ResetCompletionResponder;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Application\Security\Password\PasswordSetting;
use App\Application\Security\PasswordReset\CompletePasswordReset;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(CompletePasswordReset::class, static fn(Container $c): CompletePasswordReset =>
        new CompletePasswordReset($c->get(PasswordSetting::class), $c->get(AuthenticationService::class)));
    $container->set(ResetBrowserSession::class, static fn(Container $c): ResetBrowserSession =>
        new ResetBrowserSession($c->get(RefreshCookie::class), $c->get(RefreshSessionRepository::class)));
    $container->set(ResetCompletionResponder::class, static fn(Container $c): ResetCompletionResponder =>
        new ResetCompletionResponder($c->get(JSendResponseFactory::class), $c->get(BrowserSessionCookies::class)));
    $container->set(CompletePasswordResetAction::class, static fn(Container $c): CompletePasswordResetAction =>
        new CompletePasswordResetAction(
            $c->get(CompletePasswordReset::class),
            $c->get(ResetBrowserSession::class),
            $c->get(ResetCompletionResponder::class)
        ));
    $container->set(ResetCompletionGuard::class, static fn(Container $c): ResetCompletionGuard =>
        new ResetCompletionGuard($c->get(BrowserMutationGuard::class), $c->get(AuthAdmission::class)));
};
