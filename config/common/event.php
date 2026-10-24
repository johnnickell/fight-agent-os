<?php

declare(strict_types=1);

use Fight\AccessControl\Application\AccessControl\ActivationGrant\EventSubscriber\InvitationDeliverySubscriber;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\EventSubscriber\PasswordResetDeliverySubscriber;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $services = [
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
    ];
    foreach ($services as $id => $factory) {
        $container->set($id, $factory);
    }
    $subscribers = [
        // Reset requests deliberately leave delivery to the bounded recovery runner so SMTP cannot
        // create an eligible-only public timing path. Direct delivery commands remain available.
        InvitationDeliverySubscriber::class => InvitationDeliverySubscriber::class
    ];
    foreach ($subscribers as $subscriber => $id) {
        $container->get('messaging.event.dispatcher')->registerService($subscriber, $id);
    }
};
