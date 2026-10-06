<?php

declare(strict_types=1);

use App\Application\Security\Csrf\Clock\CsrfClock;
use App\Application\Security\Csrf\QueryHandler\GetCsrfProofHandler;
use App\Application\Security\Csrf\Service\CsrfNonceGenerator;
use App\Application\Security\Csrf\Service\CsrfProofs;
use App\Domain\Security\Csrf\Query\GetCsrfProof;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\QueryHandler as DeliveryQuery;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\QueryHandler\FindCredentialDeliveryStatusHandler;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\QueryHandler\FindDueCredentialDeliveriesHandler;
use Fight\AccessControl\Application\AccessControl\Permission\QueryHandler\ListPermissionsHandler;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindCredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindExpiredCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\ListPermissions;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return [
    'services' => [
        'messaging.query.router'                                    => static function (
            Container $container
        ): ServiceAwareQueryRouter {
            return new ServiceAwareQueryRouter($container);
        },
        'messaging.query.routing'                                   => static function (
            Container $container
        ): RoutingQueryBus {
            return new RoutingQueryBus($container->get('messaging.query.router'));
        },
        'messaging.query.bus'                                       => static function (
            Container $container
        ): QueryPipeline {
            return new QueryPipeline($container->get('messaging.query.routing'));
        },
        QueryBus::class                                             => static function (
            Container $container
        ): QueryBus {
            return $container->get('messaging.query.bus');
        },
        GetCsrfProofHandler::class                                  => static function (
            Container $container
        ): GetCsrfProofHandler {
            return new GetCsrfProofHandler(
                $container->get(CsrfNonceGenerator::class),
                $container->get(CsrfClock::class),
                $container->get(CsrfProofs::class)
            );
        },
        FindDueCredentialDeliveriesHandler::class                   => static function (
            Container $container
        ): FindDueCredentialDeliveriesHandler {
            return new FindDueCredentialDeliveriesHandler(
                $container->get(ActivationGrantRepository::class),
                $container->get(PasswordResetGrantRepository::class),
                $container->get(EmailChangeGrantRepository::class)
            );
        },
        DeliveryQuery\FindExpiredCredentialDeliveriesHandler::class => static function (
            Container $container
        ): DeliveryQuery\FindExpiredCredentialDeliveriesHandler {
            return new DeliveryQuery\FindExpiredCredentialDeliveriesHandler(
                $container->get(ActivationGrantRepository::class),
                $container->get(PasswordResetGrantRepository::class),
                $container->get(EmailChangeGrantRepository::class)
            );
        },
        FindCredentialDeliveryStatusHandler::class                  => static function (
            Container $container
        ): FindCredentialDeliveryStatusHandler {
            return new FindCredentialDeliveryStatusHandler(
                $container->get(ActivationGrantRepository::class),
                $container->get(PasswordResetGrantRepository::class),
                $container->get(EmailChangeGrantRepository::class)
            );
        },
        ListPermissionsHandler::class                               => static function (
            Container $container
        ): ListPermissionsHandler {
            $permissionRepository = $container->get(PermissionRepository::class);
            assert($permissionRepository instanceof PermissionRepository);

            return new ListPermissionsHandler($permissionRepository);
        }
    ],
    'handlers' => [
        ListPermissions::class                 => ListPermissionsHandler::class,
        GetCsrfProof::class                    => GetCsrfProofHandler::class,
        FindDueCredentialDeliveries::class     => FindDueCredentialDeliveriesHandler::class,
        FindExpiredCredentialDeliveries::class => DeliveryQuery\FindExpiredCredentialDeliveriesHandler::class,
        FindCredentialDeliveryStatus::class    => FindCredentialDeliveryStatusHandler::class
    ],
    'filters'  => []
];
