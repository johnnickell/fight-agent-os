<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

require sprintf('%s/vendor/autoload.php', dirname(__DIR__));

/** @var ContainerInterface $container */
$container = require sprintf('%s/config/services.php', dirname(__DIR__));

AppFactory::setContainer($container);
AppFactory::setResponseFactory(new ResponseFactory());
$app = AppFactory::create();
$container->set(Slim\App::class, static fn (): Slim\App => $app);

require sprintf('%s/config/routes.php', dirname(__DIR__));
require sprintf('%s/config/middleware.php', dirname(__DIR__));

return $app;
