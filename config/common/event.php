<?php

declare(strict_types=1);

use Fight\AccessControl\Application\AccessControl\ActivationGrant\EventSubscriber\InvitationDeliverySubscriber;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\EventSubscriber\PasswordResetDeliverySubscriber;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Service\Container;

return [
    'services'    => [
        'messaging.event.dispatcher'           => static function (Container $container): ServiceAwareEventDispatcher {
            return new ServiceAwareEventDispatcher($container);
        },
        EventDispatcher::class                 => static function (Container $container): EventDispatcher {
            return $container->get('messaging.event.dispatcher');
        },
        SynchronousEventDispatcher::class      => static function (Container $container): SynchronousEventDispatcher {
            return $container->get('messaging.event.dispatcher');
        },
        InvitationDeliverySubscriber::class    => static function (Container $container): InvitationDeliverySubscriber {
            return new InvitationDeliverySubscriber($container->get(CommandBus::class));
        },
        PasswordResetDeliverySubscriber::class => static function (
            Container $container
        ): PasswordResetDeliverySubscriber {
            return new PasswordResetDeliverySubscriber($container->get(CommandBus::class));
        }
    ],
    'subscribers' => [
        InvitationDeliverySubscriber::class    => InvitationDeliverySubscriber::class,
        PasswordResetDeliverySubscriber::class => PasswordResetDeliverySubscriber::class
    ]
];
