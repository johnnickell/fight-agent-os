<?php

declare(strict_types=1);

use App\Adapter\Http\Middleware\Api\Auth\ActivationAdmission;
use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Adapter\Security\HttpSecurityConfiguration;
use App\Domain\Invitation\InvitationRatePolicy;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\Common\Application\Service\Container;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

require sprintf('%s/vendor/autoload.php', dirname(__DIR__));

/** @var Container $container */
$container = require sprintf('%s/config/services.php', dirname(__DIR__));

// Validate the complete HTTP security graph before any route can serve a request.
$production = $container->get(HttpSecurityConfiguration::class)->production;
$container->get(AuthAdmission::class);
$container->get(ActivationAdmission::class);
$container->get(InvitationRatePolicy::class);
if ($production) {
    $container->get(PostgresAuthAttempts::class)->check();
}

// HTTP startup validates delivery-enabled composition; key-free CLI status/expiry remain available.
if ($production || getenv('APP_CREDENTIAL_DELIVERY_ADAPTER') === 'catcher') {
    $container->get(CredentialDeliveryProvider::class);
}

AppFactory::setContainer($container);
AppFactory::setResponseFactory(new ResponseFactory());
$app = AppFactory::create();
$container->set(Slim\App::class, static fn (): Slim\App => $app);

require sprintf('%s/config/routes.php', dirname(__DIR__));
require sprintf('%s/config/middleware.php', dirname(__DIR__));

return $app;
