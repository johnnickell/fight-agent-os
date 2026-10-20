<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\ActivateInvitationAction;
use App\Adapter\Http\Middleware\Api\Auth\ActivationAdmission;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use App\Adapter\Security\Activation\RandomRefreshCredentials;
use App\Adapter\Security\Activation\UnavailableLoginThrottle;
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
    $container->set(LoginThrottle::class, static fn(): LoginThrottle => new UnavailableLoginThrottle());
    $container->set(RefreshCredentialGenerator::class, static fn(): RefreshCredentialGenerator =>
        new RandomRefreshCredentials());
    $container->set(SessionRevocationService::class, static fn(Container $c): SessionRevocationService =>
        new SessionRevocationService($c->get(RefreshSessionRepository::class)));
    $container->set(AuthenticationService::class, static fn(Container $c): AuthenticationService =>
        new AuthenticationService(
            $c->get(UserRepository::class),
            $c->get(ActivationGrantRepository::class),
            $c->get(RefreshSessionRepository::class),
            $c->get(SessionRevocationService::class),
            $c->get(TransactionalUnitOfWork::class),
            $c->get(Clock::class),
            $c->get(LoginThrottle::class),
            $c->get(RefreshCredentialGenerator::class),
            $c->get(PasswordHasher::class),
            $c->get(PasswordValidator::class),
            $c->get(TokenEncoder::class),
            $c->get(AuthenticationTokenPolicy::class),
            PasswordHash::fromString($c->get(PasswordHasher::class)->hash(bin2hex(random_bytes(32)))),
            $c->get(EventDispatcher::class),
            $c->get(PasswordResetGrantRepository::class),
            $c->get(AuditEvidenceRepository::class),
            $c->get(EmailChangeGrantRepository::class)
        ));
    $container->set(ActivateInvitation::class, static fn(Container $c): ActivateInvitation =>
        new ActivateInvitation($c->get(PasswordSetting::class), $c->get(AuthenticationService::class)));
    $container->set(SessionResponder::class, static fn(Container $c): SessionResponder =>
        new SessionResponder($c->get(JSendResponseFactory::class)));
    $container->set(ActivateInvitationAction::class, static fn(Container $c): ActivateInvitationAction =>
        new ActivateInvitationAction($c->get(ActivateInvitation::class), $c->get(SessionResponder::class)));
    $container->set(BrowserMutationGuard::class, static fn(Container $c): BrowserMutationGuard =>
        new BrowserMutationGuard($c->get(HmacCsrfProofs::class), $c->get(CsrfClock::class)));
    $container->set(ActivationAdmission::class, static fn(Container $c): ActivationAdmission =>
        new ActivationAdmission(
            $c->get(Connection::class),
            $c->get(Clock::class),
            getenv('APP_ACTIVATION_RATE_KEY') ?: ''
        ));
};
