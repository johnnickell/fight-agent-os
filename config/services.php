<?php

declare(strict_types=1);

use Fight\Common\Application\Service\Container;

$container = new Container();

foreach (
    [
    'parameters/paths.php',
    'parameters/templating.php',
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
    'common/templating.php',
    'application/csrf.php',
    'application/access_tokens.php',
    'application/credential_delivery.php',
    'application/controller.php'
    ] as $manifestEntry
) {
    /** @var callable(Container): void $configure */
    $configure = require sprintf('%s/%s', __DIR__, $manifestEntry);
    $configure($container);
}

return $container;
