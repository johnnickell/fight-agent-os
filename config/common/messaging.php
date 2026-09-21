<?php

declare(strict_types=1);

use Fight\Common\Adapter\ServiceContainer\Fight\ContainerCapabilityRegistrar;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $command = require sprintf('%s/command.php', __DIR__);
    $query = require sprintf('%s/query.php', __DIR__);
    $event = require sprintf('%s/event.php', __DIR__);

    ContainerCapabilityRegistrar::registerMessaging(
        $container,
        [...$command['services'], ...$query['services'], ...$event['services']],
        [], $command['handlers'], $query['handlers'], $event['subscribers'],
        [...$command['filters'], ...$query['filters']],
        ['command.router' => 'messaging.command.router', 'query.router' => 'messaging.query.router', 'event.dispatcher' => 'messaging.event.dispatcher']
    );
};
