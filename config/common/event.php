<?php

declare(strict_types=1);

use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Service\Container;

return [
    'services' => [
        'messaging.event.dispatcher' => static function (Container $container): ServiceAwareEventDispatcher {
            return new ServiceAwareEventDispatcher($container);
        },
        EventDispatcher::class => static function (Container $container): EventDispatcher {
            return $container->get('messaging.event.dispatcher');
        },
        SynchronousEventDispatcher::class => static function (Container $container): SynchronousEventDispatcher {
            return $container->get('messaging.event.dispatcher');
        },
    ],
    'subscribers' => [],
];
