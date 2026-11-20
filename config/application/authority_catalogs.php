<?php

declare(strict_types=1);

use App\Adapter\Authorization\InstallationPermissionAdmission;
use App\Adapter\Http\Action\Api\V1\Authority\GetPermissionAction;
use App\Adapter\Http\Action\Api\V1\Authority\GetRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListPermissionsAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListRolesAction;
use App\Adapter\Http\Responder\Api\V1\Authority\CatalogResponder;
use Fight\AccessControl\Application\AccessControl\Permission\QueryHandler\GetPermissionByIdHandler;
use Fight\AccessControl\Application\AccessControl\Role\QueryHandler\GetRoleByIdHandler;
use Fight\AccessControl\Application\AccessControl\Role\QueryHandler\ListRolesHandler;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    foreach ([ListRolesHandler::class, GetRoleByIdHandler::class] as $handler) {
        $container->set($handler, static fn(Container $container) =>
            new $handler($container->get(RoleRepository::class)));
        /** @var string|QueryHandler $handler */
        $container->get('messaging.query.router')->registerHandlers([$handler::queryRegistration() => $handler]);
    }
    $container->set(GetPermissionByIdHandler::class, static fn(Container $container) =>
        new GetPermissionByIdHandler($container->get(PermissionRepository::class)));
    $container->get('messaging.query.router')->registerHandlers([
        GetPermissionByIdHandler::queryRegistration() => GetPermissionByIdHandler::class
    ]);
    $container->set(CatalogResponder::class, static fn(Container $container) =>
        new CatalogResponder($container->get(JSendResponseFactory::class)));
    $actions = [ListRolesAction::class, GetRoleAction::class, ListPermissionsAction::class, GetPermissionAction::class];
    foreach ($actions as $action) {
        $container->set($action, static fn(Container $container) => new $action(
            $container->get(QueryBus::class),
            $container->get(InstallationPermissionAdmission::class),
            $container->get(CatalogResponder::class)
        ));
    }
};
