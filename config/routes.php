<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Slim\App;

/** @var App $app */
/** @var ContainerInterface $container */
require sprintf('%s/routes/02-web.php', __DIR__);
