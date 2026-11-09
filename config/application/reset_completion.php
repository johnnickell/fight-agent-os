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
    $container->set(CompletePasswordReset::class, static fn(Container $container): CompletePasswordReset =>
        new CompletePasswordReset(
            $container->get(PasswordSetting::class),
            $container->get(AuthenticationService::class)
        ));
    $container->set(ResetBrowserSession::class, static fn(Container $container): ResetBrowserSession =>
        new ResetBrowserSession(
            $container->get(RefreshCookie::class),
            $container->get(RefreshSessionRepository::class)
        ));
    $container->set(ResetCompletionResponder::class, static fn(Container $container): ResetCompletionResponder =>
        new ResetCompletionResponder(
            $container->get(JSendResponseFactory::class),
            $container->get(BrowserSessionCookies::class)
        ));
    $container->set(CompletePasswordResetAction::class, static fn(Container $container): CompletePasswordResetAction =>
        new CompletePasswordResetAction(
            $container->get(CompletePasswordReset::class),
            $container->get(ResetBrowserSession::class),
            $container->get(ResetCompletionResponder::class)
        ));
    $container->set(ResetCompletionGuard::class, static fn(Container $container): ResetCompletionGuard =>
        new ResetCompletionGuard($container->get(BrowserMutationGuard::class), $container->get(AuthAdmission::class)));
};
