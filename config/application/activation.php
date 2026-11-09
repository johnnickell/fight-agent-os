<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\ActivateInvitationAction;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\CaptureSessionPresentation;
use App\Adapter\Http\Middleware\Api\Auth\ActivationAdmission;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use App\Adapter\Security\Activation\RandomRefreshCredentials;
use App\Adapter\Security\Authentication\AuthenticationEvents;
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Security\Activation\ActivateInvitation;
use App\Application\Security\Csrf\Clock\CsrfClock;
use App\Application\Security\Password\PasswordSetting;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationTokenPolicy;
use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(AuthenticationTokenPolicy::class, static fn(): AuthenticationTokenPolicy =>
        new AuthenticationTokenPolicy(
            new DateInterval('PT15M'),
            new DateInterval('P1D'),
            new DateInterval('P2D'),
            new DateInterval('P30D'),
            new DateInterval('P1Y'),
            new DateInterval('PT5S')
        ));
    $container->set(RefreshCredentialGenerator::class, static fn(): RefreshCredentialGenerator =>
        new RandomRefreshCredentials());
    $container->set(SessionRevocationService::class, static fn(Container $container): SessionRevocationService =>
        new SessionRevocationService($container->get(RefreshSessionRepository::class)));
    $container->set(AuthenticationEvents::class, static fn(Container $container): AuthenticationEvents =>
        new AuthenticationEvents($container->get(EventDispatcher::class)));
    $container->set(AuthenticationService::class, static fn(Container $container): AuthenticationService =>
        new AuthenticationService(
            $container->get(UserRepository::class),
            $container->get(ActivationGrantRepository::class),
            $container->get(RefreshSessionRepository::class),
            $container->get(SessionRevocationService::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(Clock::class),
            $container->get(LoginThrottle::class),
            $container->get(RefreshCredentialGenerator::class),
            $container->get(PasswordHasher::class),
            $container->get(PasswordValidator::class),
            $container->get(TokenEncoder::class),
            $container->get(AuthenticationTokenPolicy::class),
            PasswordHash::fromString($container->get(PasswordHasher::class)->hash(bin2hex(random_bytes(32)))),
            $container->get(AuthenticationEvents::class),
            $container->get(PasswordResetGrantRepository::class),
            $container->get(AuditEvidenceRepository::class),
            $container->get(EmailChangeGrantRepository::class)
        ));
    $container->set(ActivateInvitation::class, static fn(Container $container): ActivateInvitation =>
        new ActivateInvitation($container->get(PasswordSetting::class), $container->get(AuthenticationService::class)));
    $container->set(SessionResponder::class, static fn(Container $container): SessionResponder =>
        new SessionResponder(
            $container->get(JSendResponseFactory::class),
            $container->get(BrowserSessionCookies::class)
        ));
    $container->set(ActivateInvitationAction::class, static fn(Container $container): ActivateInvitationAction =>
        new ActivateInvitationAction(
            $container->get(ActivateInvitation::class),
            $container->get(SessionResponder::class),
            $container->get(CaptureSessionPresentation::class)
        ));
    $container->set(BrowserMutationGuard::class, static fn(Container $container): BrowserMutationGuard =>
        new BrowserMutationGuard($container->get(HmacCsrfProofs::class), $container->get(CsrfClock::class)));
    $container->set(ActivationAdmission::class, static fn(Container $container): ActivationAdmission =>
        new ActivationAdmission(
            $container->get(Connection::class),
            $container->get(Clock::class),
            getenv('APP_ACTIVATION_RATE_KEY') ?: ''
        ));
};
