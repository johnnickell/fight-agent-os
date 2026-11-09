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
    $container->set(RequestPasswordResetHandler::class, static fn(Container $container): RequestPasswordResetHandler =>
        new RequestPasswordResetHandler(
            $container->get(UserRepository::class),
            $container->get(PasswordResetGrantRepository::class),
            $container->get(AuditEvidenceRepository::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(PasswordResetCredentialGenerator::class),
            $container->get(PasswordResetDeliveryCipher::class),
            $container->get(Clock::class),
            $container->get(AuthenticationEvents::class)
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
    $container->set(ResetRequestResponder::class, static fn(Container $container): ResetRequestResponder =>
        new ResetRequestResponder($container->get(JSendResponseFactory::class)));
    $container->set(RequestPasswordResetAction::class, static fn(Container $container): RequestPasswordResetAction =>
        new RequestPasswordResetAction(
            $container->get(CommandBus::class),
            $container->get(ResetResponseTiming::class),
            $container->get(ResetRequestResponder::class)
        ));
    $container->set(ResetRequestGuard::class, static fn(Container $container): ResetRequestGuard =>
        new ResetRequestGuard($container->get(BrowserMutationGuard::class), $container->get(AuthAdmission::class)));
};
