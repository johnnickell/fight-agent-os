<?php

declare(strict_types=1);

use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\Common\Application\Service\Container;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

require sprintf('%s/vendor/autoload.php', dirname(__DIR__));

/** @var Container $container */
$container = require sprintf('%s/config/services.php', dirname(__DIR__));

// HTTP startup validates delivery-enabled composition; key-free CLI status/expiry remain available.
if (
    !in_array(getenv('APP_ENV'), ['test', 'development', 'local'], true)
    || getenv('APP_CREDENTIAL_DELIVERY_ADAPTER') === 'catcher'
) {
    $container->get(CredentialDeliveryProvider::class);
}

AppFactory::setContainer($container);
AppFactory::setResponseFactory(new ResponseFactory());
$app = AppFactory::create();
$container->set(Slim\App::class, static fn (): Slim\App => $app);

require sprintf('%s/config/routes.php', dirname(__DIR__));
require sprintf('%s/config/middleware.php', dirname(__DIR__));

return $app;
