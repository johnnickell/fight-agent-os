<?php

declare(strict_types=1);

use App\Adapter\CredentialDelivery\RandomPasswordResetCredentialGenerator;
use App\Adapter\Http\Action\Api\V1\Auth\RequestPasswordResetAction;
use App\Adapter\Http\Api\V1\Auth\ResetResponseTiming;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Middleware\Api\Auth\ResetRequestGuard;
use App\Adapter\Http\Responder\Api\V1\Auth\ResetRequestResponder;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Adapter\Security\Authentication\AuthenticationEvents;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\CommandHandler\RequestPasswordResetHandler;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\RequestPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(PasswordResetCredentialGenerator::class, static fn(): PasswordResetCredentialGenerator =>
        new RandomPasswordResetCredentialGenerator());
    $container->set(RequestPasswordResetHandler::class, static fn(Container $c): RequestPasswordResetHandler =>
        new RequestPasswordResetHandler(
            $c->get(UserRepository::class),
            $c->get(PasswordResetGrantRepository::class),
            $c->get(AuditEvidenceRepository::class),
            $c->get(TransactionalUnitOfWork::class),
            $c->get(PasswordResetCredentialGenerator::class),
            $c->get(PasswordResetDeliveryCipher::class),
            $c->get(Clock::class),
            $c->get(AuthenticationEvents::class)
        ));
    $container->get('messaging.command.router')->registerHandlers([
        RequestPasswordReset::class => RequestPasswordResetHandler::class
    ]);
    $container->set(ResetResponseTiming::class, static fn(): ResetResponseTiming => new ResetResponseTiming(
        static fn(): int => hrtime(true),
        static function (int $nanoseconds): void {
            // One wait only: interruption can shorten padding, never create an unbounded retry loop.
            time_nanosleep(0, $nanoseconds);
        }
    ));
    $container->set(ResetRequestResponder::class, static fn(Container $c): ResetRequestResponder =>
        new ResetRequestResponder($c->get(JSendResponseFactory::class)));
    $container->set(RequestPasswordResetAction::class, static fn(Container $c): RequestPasswordResetAction =>
        new RequestPasswordResetAction(
            $c->get(CommandBus::class),
            $c->get(ResetResponseTiming::class),
            $c->get(ResetRequestResponder::class)
        ));
    $container->set(ResetRequestGuard::class, static fn(Container $c): ResetRequestGuard =>
        new ResetRequestGuard($c->get(BrowserMutationGuard::class), $c->get(AuthAdmission::class)));
};
