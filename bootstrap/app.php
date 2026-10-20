<?php

declare(strict_types=1);

use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Adapter\Security\Authentication\AuthAdmission;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\Common\Application\Service\Container;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

require sprintf('%s/vendor/autoload.php', dirname(__DIR__));

/** @var Container $container */
$container = require sprintf('%s/config/services.php', dirname(__DIR__));

// Only direct TLS/server authority is supported. Forwarded headers never promote HTTP to HTTPS.
$production = !in_array(getenv('APP_ENV'), ['test', 'development', 'local'], true);
$transport = getenv('APP_AUTH_TRANSPORT');
$proxies = getenv('APP_AUTH_TRUSTED_PROXIES');
if (
    ($production && $transport !== 'direct')
    || ($transport !== false && $transport !== 'direct')
    || ($proxies !== false && $proxies !== '[]')
) {
    throw new LogicException('Invalid browser authentication transport configuration.');
}
$container->get(AuthAdmission::class);
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
