<?php

declare(strict_types=1);

use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Service\Container;

return [
    'services' => [
        'messaging.command.router' => static function (Container $container): ServiceAwareCommandRouter {
            return new ServiceAwareCommandRouter($container);
        },
        'messaging.command.routing' => static function (Container $container): RoutingCommandBus {
            return new RoutingCommandBus($container->get('messaging.command.router'));
        },
        'messaging.command.bus' => static function (Container $container): CommandPipeline {
            return new CommandPipeline($container->get('messaging.command.routing'));
        },
        CommandBus::class => static function (Container $container): CommandBus {
            return $container->get('messaging.command.bus');
        },
        SynchronousCommandBus::class => static function (Container $container): SynchronousCommandBus {
            return $container->get('messaging.command.bus');
        },
    ],
    'handlers' => [],
    'filters' => [],
];
