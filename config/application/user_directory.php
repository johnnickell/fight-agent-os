<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Users\GetUserAction;
use App\Adapter\Http\Action\Api\V1\Users\ListUsersAction;
use App\Adapter\Http\Responder\Api\V1\Users\UserDetailResponder;
use App\Adapter\Http\Responder\Api\V1\Users\UserDirectoryResponder;
use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use Fight\AccessControl\Application\AccessControl\User\QueryHandler\ListUsersHandler;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(ListUsersHandler::class, static fn(Container $c) =>
        new ListUsersHandler($c->get(UserRepository::class)));
    $container->get('messaging.query.router')->registerHandlers([
        ListUsersHandler::queryRegistration() => ListUsersHandler::class
    ]);
    $container->set(UserDetailResponder::class, static fn(Container $c) =>
        new UserDetailResponder($c->get(JSendResponseFactory::class)));
    $container->set(GetUserAction::class, static fn(Container $c) => new GetUserAction(
        $c->get(AuthenticatedPermissions::class),
        $c->get(InstallationAuthority::class),
        $c->get(QueryBus::class),
        $c->get(UserDetailResponder::class)
    ));
    $container->set(UserDirectoryResponder::class, static fn(Container $c) =>
        new UserDirectoryResponder($c->get(JSendResponseFactory::class)));
    $container->set(ListUsersAction::class, static fn(Container $c) => new ListUsersAction(
        $c->get(AuthenticatedPermissions::class),
        $c->get(InstallationAuthority::class),
        $c->get(QueryBus::class),
        $c->get(UserDirectoryResponder::class)
    ));
};
