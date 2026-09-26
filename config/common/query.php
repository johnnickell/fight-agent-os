<?php

declare(strict_types=1);

use App\Application\Security\Csrf\Clock\CsrfClock;
use App\Application\Security\Csrf\QueryHandler\GetCsrfProofHandler;
use App\Application\Security\Csrf\Service\CsrfNonceGenerator;
use App\Application\Security\Csrf\Service\CsrfProofs;
use App\Domain\Security\Csrf\Query\GetCsrfProof;
use Fight\AccessControl\Application\AccessControl\Permission\QueryHandler\ListPermissionsHandler;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\ListPermissions;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return [
    'services' => [
        'messaging.query.router'      => static function (Container $container): ServiceAwareQueryRouter {
            return new ServiceAwareQueryRouter($container);
        },
        'messaging.query.routing'     => static function (Container $container): RoutingQueryBus {
            return new RoutingQueryBus($container->get('messaging.query.router'));
        },
        'messaging.query.bus'         => static function (Container $container): QueryPipeline {
            return new QueryPipeline($container->get('messaging.query.routing'));
        },
        QueryBus::class               => static function (Container $container): QueryBus {
            return $container->get('messaging.query.bus');
        },
        GetCsrfProofHandler::class    => static function (Container $container): GetCsrfProofHandler {
            return new GetCsrfProofHandler(
                $container->get(CsrfNonceGenerator::class),
                $container->get(CsrfClock::class),
                $container->get(CsrfProofs::class)
            );
        },
        ListPermissionsHandler::class => static function (Container $container): ListPermissionsHandler {
            $permissionRepository = $container->get(PermissionRepository::class);
            assert($permissionRepository instanceof PermissionRepository);

            return new ListPermissionsHandler($permissionRepository);
        }
    ],
    'handlers' => [
        ListPermissions::class => ListPermissionsHandler::class,
        GetCsrfProof::class    => GetCsrfProofHandler::class
    ],
    'filters'  => []
];
