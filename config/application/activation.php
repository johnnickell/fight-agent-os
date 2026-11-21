<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\ActivateInvitationAction;
use App\Adapter\Http\Api\V1\Auth\CaptureSessionPresentation;
use App\Adapter\Http\Middleware\Api\Auth\ActivationAdmission;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use App\Adapter\Security\Authentication\AuthenticationEvents;
use App\Application\Security\Activation\ActivateInvitation;
use App\Application\Security\Password\PasswordSetting;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Authentication\UserAuthentication;
use Fight\AccessControl\Application\AccessControl\User\PasswordManagement;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(PasswordManagement::class, static fn(Container $container): PasswordManagement =>
        new PasswordManagement(
            $container->get(UserRepository::class),
            $container->get(PasswordResetGrantRepository::class),
            $container->get(SessionRevocationService::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(Clock::class),
            $container->get(PasswordHasher::class),
            $container->get(PasswordValidator::class),
            $container->get(PasswordHash::class),
            $container->get(AuthenticationEvents::class),
            $container->get(AuditEvidenceRepository::class)
        ));
    $container->set(ActivateInvitation::class, static fn(Container $container): ActivateInvitation =>
        new ActivateInvitation($container->get(PasswordSetting::class), $container->get(UserAuthentication::class)));
    $container->set(ActivateInvitationAction::class, static fn(Container $container): ActivateInvitationAction =>
        new ActivateInvitationAction(
            $container->get(ActivateInvitation::class),
            $container->get(SessionResponder::class),
            $container->get(CaptureSessionPresentation::class)
        ));
    $container->set(ActivationAdmission::class, static fn(Container $container): ActivationAdmission =>
        new ActivationAdmission(
            $container->get(Connection::class),
            $container->get(Clock::class),
            getenv('APP_ACTIVATION_RATE_KEY') ?: ''
        ));
};
