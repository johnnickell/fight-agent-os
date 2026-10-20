<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Authority\GetPermissionAction;
use App\Adapter\Http\Action\Api\V1\Authority\GetRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListPermissionsAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListRolesAction;
use App\Adapter\Http\Responder\Api\V1\Authority\CatalogResponder;
use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\ReadAuthorityCatalogs;
use Fight\AccessControl\Application\AccessControl\Permission\QueryHandler\GetPermissionByIdHandler;
use Fight\AccessControl\Application\AccessControl\Role\QueryHandler\GetRoleByIdHandler;
use Fight\AccessControl\Application\AccessControl\Role\QueryHandler\ListRolesHandler;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    foreach ([ListRolesHandler::class, GetRoleByIdHandler::class] as $handler) {
        $container->set($handler, static fn(Container $c) => new $handler($c->get(RoleRepository::class)));
        $container->get('messaging.query.router')->registerHandlers([$handler::queryRegistration() => $handler]);
    }
    $container->set(GetPermissionByIdHandler::class, static fn(Container $c) =>
        new GetPermissionByIdHandler($c->get(PermissionRepository::class)));
    $container->get('messaging.query.router')->registerHandlers([
        GetPermissionByIdHandler::queryRegistration() => GetPermissionByIdHandler::class
    ]);
    $container->set(ReadAuthorityCatalogs::class, static fn(Container $c) => new ReadAuthorityCatalogs(
        $c->get(AuthenticatedPermissions::class),
        $c->get(InstallationAuthority::class),
        $c->get(QueryBus::class)
    ));
    $container->set(CatalogResponder::class, static fn(Container $c) =>
        new CatalogResponder($c->get(JSendResponseFactory::class)));
    $actions = [ListRolesAction::class, GetRoleAction::class, ListPermissionsAction::class, GetPermissionAction::class];
    foreach ($actions as $action) {
        $container->set($action, static fn(Container $c) => new $action(
            $c->get(ReadAuthorityCatalogs::class),
            $c->get(CatalogResponder::class)
        ));
    }
};
