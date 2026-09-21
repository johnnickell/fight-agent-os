<?php

declare(strict_types=1);

use Fight\Common\Application\Service\Container;

$container = new Container();

/** @var callable(Container): void $configure */
foreach ([
    'parameters/paths.php',
    'common/security.php',
    'common/observability.php',
    'common/cache.php',
    'common/persistence.php',
    'common/messaging.php',
    'common/files.php',
    'common/http.php',
    'common/system.php',
    'common/communication.php',
    'common/presentation.php',
    'application/controller.php',
] as $manifestEntry) {
    $configure = require sprintf('%s/%s', __DIR__, $manifestEntry);
    $configure($container);
}

return $container;
