<?php

declare(strict_types=1);

use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Persistence\Workspace\Workspace\PostgresWorkspaceBootstrapAudit;
use App\Adapter\Persistence\Workspace\Workspace\PostgresWorkspaceRepository;
use App\Application\Workspace\Workspace\CommandHandler\BootstrapWorkspaceHandler;
use App\Application\Workspace\Workspace\QueryHandler\FindInitialWorkspaceHandler;
use App\Application\Workspace\Workspace\QueryHandler\GetWorkspaceByIdHandler;
use App\Application\Workspace\Workspace\WorkspaceBootstrapAudit;
use App\Domain\Workspace\Workspace\Command\BootstrapWorkspace;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\Query\GetWorkspaceById;
use App\Domain\Workspace\Workspace\WorkspaceRepository;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(WorkspaceRepository::class, static fn(Container $container): WorkspaceRepository =>
        new PostgresWorkspaceRepository($container->get(Connection::class)));
    $container->set(WorkspaceBootstrapAudit::class, static fn(Container $container): WorkspaceBootstrapAudit =>
        new PostgresWorkspaceBootstrapAudit($container->get(Connection::class)));
    $container->set(BootstrapWorkspaceHandler::class, static fn(Container $container): BootstrapWorkspaceHandler =>
        new BootstrapWorkspaceHandler(
            $container->get(WorkspaceRepository::class),
            $container->get(WorkspaceBootstrapAudit::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(Clock::class)
        ));
    $container->set(FindInitialWorkspaceHandler::class, static fn(Container $container): FindInitialWorkspaceHandler =>
        new FindInitialWorkspaceHandler($container->get(WorkspaceRepository::class)));
    $container->set(GetWorkspaceByIdHandler::class, static fn(Container $container): GetWorkspaceByIdHandler =>
        new GetWorkspaceByIdHandler($container->get(WorkspaceRepository::class)));
    $container->set(BootstrapWorkspaceCommand::class, static fn(Container $container): BootstrapWorkspaceCommand =>
        new BootstrapWorkspaceCommand($container->get(CommandBus::class), $container->get(QueryBus::class)));
    $container->get('messaging.command.router')->registerHandlers([
        BootstrapWorkspace::class => BootstrapWorkspaceHandler::class
    ]);
    $container->get('messaging.query.router')->registerHandlers([
        FindInitialWorkspace::class => FindInitialWorkspaceHandler::class,
        GetWorkspaceById::class     => GetWorkspaceByIdHandler::class
    ]);
};
