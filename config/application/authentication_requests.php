<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\LoginAction;
use App\Adapter\Http\Action\Api\V1\Auth\LogoutAction;
use App\Adapter\Http\Action\Api\V1\Auth\RefreshAction;
use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\CaptureSessionPresentation;
use App\Adapter\Http\Api\V1\Auth\RefreshCookie;
use App\Adapter\Http\Middleware\Api\Auth\AuthEndpointGuard;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionLifecycleResponder;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Adapter\Security\Activation\RandomRefreshCredentials;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Adapter\Security\Authentication\AuthenticationEvents;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\RefreshSession\SessionLifecycle;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Authentication\TokenIssuer;
use Fight\AccessControl\Application\AccessControl\User\Authentication\UserAuthentication;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationTokenPolicy;
use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
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
    $container->set(PasswordHash::class, static fn(Container $container): PasswordHash =>
        PasswordHash::fromString($container->get(PasswordHasher::class)->hash(bin2hex(random_bytes(32)))));
    $container->set(TokenIssuer::class, static fn(Container $container): TokenIssuer => new TokenIssuer(
        $container->get(TokenEncoder::class),
        $container->get(AuthenticationTokenPolicy::class)
    ));
    $container->set(UserAuthentication::class, static fn(Container $container): UserAuthentication =>
        new UserAuthentication(
            $container->get(UserRepository::class),
            $container->get(ActivationGrantRepository::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(Clock::class),
            $container->get(LoginThrottle::class),
            $container->get(RefreshCredentialGenerator::class),
            $container->get(PasswordHasher::class),
            $container->get(PasswordValidator::class),
            $container->get(TokenIssuer::class),
            $container->get(PasswordHash::class),
            $container->get(AuthenticationEvents::class),
            $container->get(AuditEvidenceRepository::class)
        ));
    $container->set(SessionLifecycle::class, static fn(Container $container): SessionLifecycle => new SessionLifecycle(
        $container->get(UserRepository::class),
        $container->get(RefreshSessionRepository::class),
        $container->get(SessionRevocationService::class),
        $container->get(TransactionalUnitOfWork::class),
        $container->get(Clock::class),
        $container->get(RefreshCredentialGenerator::class),
        $container->get(TokenIssuer::class),
        $container->get(AuthenticationTokenPolicy::class),
        $container->get(AuthenticationEvents::class),
        $container->get(AuditEvidenceRepository::class)
    ));
    $container->set(SessionResponder::class, static fn(Container $container): SessionResponder =>
        new SessionResponder(
            $container->get(JSendResponseFactory::class),
            $container->get(BrowserSessionCookies::class)
        ));
    $container->set(LoginAction::class, static fn(Container $container): LoginAction =>
        new LoginAction(
            $container->get(UserAuthentication::class),
            $container->get(SessionResponder::class),
            $container->get(CaptureSessionPresentation::class)
        ));
    $container->set(RefreshCookie::class, static fn(): RefreshCookie => new RefreshCookie());
    $container->set(SessionLifecycleResponder::class, static fn(Container $container): SessionLifecycleResponder =>
        new SessionLifecycleResponder(
            $container->get(SessionResponder::class),
            $container->get(JSendResponseFactory::class),
            $container->get(SafeHttpDiagnostics::class),
            $container->get(SafeHttpErrorPolicy::class)
        ));
    foreach ([RefreshAction::class, LogoutAction::class] as $action) {
        $container->set($action, static fn(Container $container): RefreshAction|LogoutAction => new $action(
            $container->get(SessionLifecycle::class),
            $container->get(RefreshCookie::class),
            $container->get(SessionLifecycleResponder::class)
        ));
    }
    $container->set(BrowserSessionCookies::class, static fn(Container $container): BrowserSessionCookies =>
        new BrowserSessionCookies($container->get(Clock::class)));
    // A dedicated connection prevents a package login transaction from rolling back consumed attempts.
    $container->set('auth.admission.connection', static fn(Container $container): Connection =>
        DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($container['app.database_url'])));
    $container->set(PostgresAuthAttempts::class, static fn(Container $container): PostgresAuthAttempts =>
        new PostgresAuthAttempts($container->get('auth.admission.connection'), $container->get(Clock::class)));
    $container->set(AuthAdmission::class, static function (Container $container): AuthAdmission {
        $key = getenv('APP_AUTH_RATE_KEY') ?: '';
        $signing = json_decode(getenv('APP_ACCESS_TOKEN_CONFIG') ?: '{}', true);
        $otherKeys = [getenv('APP_CSRF_MAC_KEY'), getenv('APP_ACTIVATION_RATE_KEY')];
        foreach ($signing['keys'] ?? [] as $entry) {
            $otherKeys[] = $entry['secret_hex'] ?? '';
        }
        if (in_array($key, $otherKeys, true)) {
            throw new InvalidArgumentException('Authentication admission requires an independent key.');
        }
        $limits = [];
        foreach (['APP_AUTH_NETWORK_LIMIT' => 20, 'APP_AUTH_ENDPOINT_LIMIT' => 300] as $name => $default) {
            $value = getenv($name);
            if ($value !== false && preg_match('/\A[1-9][0-9]{0,2}\z/D', $value) !== 1) {
                throw new InvalidArgumentException('Invalid authentication admission configuration.');
            }
            $limits[] = $value === false ? $default : (int) $value;
        }

        return new AuthAdmission($container->get(PostgresAuthAttempts::class), $key, ...$limits);
    });
    $container->set(LoginThrottle::class, static fn(Container $container): LoginThrottle =>
        $container->get(AuthAdmission::class));
    foreach (AuthOperation::cases() as $operation) {
        $container->set('auth.guard.'.$operation->value, static fn(Container $container): AuthEndpointGuard =>
            new AuthEndpointGuard(
                $container->get(BrowserMutationGuard::class),
                $container->get(AuthAdmission::class),
                $operation
            ));
    }
};
