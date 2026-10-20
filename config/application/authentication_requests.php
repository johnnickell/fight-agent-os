<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\LoginAction;
use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Middleware\Api\Auth\AuthEndpointGuard;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Adapter\Security\Authentication\AuthAdmission;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(LoginAction::class, static fn(Container $c): LoginAction =>
        new LoginAction($c->get(AuthenticationService::class), $c->get(SessionResponder::class)));
    $container->set(BrowserSessionCookies::class, static fn(Container $c): BrowserSessionCookies =>
        new BrowserSessionCookies($c->get(Clock::class)));
    // A dedicated connection prevents a package login transaction from rolling back consumed attempts.
    $container->set('auth.admission.connection', static fn(Container $c): Connection =>
        DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($c['app.database_url'])));
    $container->set(PostgresAuthAttempts::class, static fn(Container $c): PostgresAuthAttempts =>
        new PostgresAuthAttempts($c->get('auth.admission.connection'), $c->get(Clock::class)));
    $container->set(AuthAdmission::class, static function (Container $c): AuthAdmission {
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

        return new AuthAdmission($c->get(PostgresAuthAttempts::class), $key, ...$limits);
    });
    $container->set(LoginThrottle::class, static fn(Container $c): LoginThrottle => $c->get(AuthAdmission::class));
    foreach (AuthOperation::cases() as $operation) {
        $container->set('auth.guard.'.$operation->value, static fn(Container $c): AuthEndpointGuard =>
            new AuthEndpointGuard($c->get(BrowserMutationGuard::class), $c->get(AuthAdmission::class), $operation));
    }
};
