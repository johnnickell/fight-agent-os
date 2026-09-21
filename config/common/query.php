<?php

declare(strict_types=1);

use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return [
    'services' => [
        'messaging.query.router' => static function (Container $container): ServiceAwareQueryRouter {
            return new ServiceAwareQueryRouter($container);
        },
        'messaging.query.routing' => static function (Container $container): RoutingQueryBus {
            return new RoutingQueryBus($container->get('messaging.query.router'));
        },
        'messaging.query.bus' => static function (Container $container): QueryPipeline {
            return new QueryPipeline($container->get('messaging.query.routing'));
        },
        QueryBus::class => static function (Container $container): QueryBus {
            return $container->get('messaging.query.bus');
        },
    ],
    'handlers' => [],
    'filters' => [],
];
