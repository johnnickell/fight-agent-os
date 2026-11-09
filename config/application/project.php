<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Projects\GetProjectAction;
use App\Adapter\Http\Action\Api\V1\Projects\ListProjectsAction;
use App\Adapter\Http\Action\Api\V1\Projects\PreviewProjectConflictsAction;
use App\Adapter\Http\Action\Api\V1\Projects\RegisterProjectAction;
use App\Adapter\Http\Api\V1\Projects\ProjectWorkspaceAdmission;
use App\Adapter\Http\Responder\Api\V1\Projects\GetProjectResponder;
use App\Adapter\Http\Responder\Api\V1\Projects\ListProjectsResponder;
use App\Adapter\Http\Responder\Api\V1\Projects\PreviewProjectConflictsResponder;
use App\Adapter\Http\Responder\Api\V1\Projects\RegisterProjectResponder;
use App\Adapter\Persistence\Workspace\Project\PostgresProjectRegistrationAudit;
use App\Adapter\Persistence\Workspace\Project\PostgresProjectRepository;
use App\Application\Workspace\Project\CommandHandler\RegisterProjectHandler;
use App\Application\Workspace\Project\ProjectRegistrationAudit;
use App\Application\Workspace\Project\QueryHandler\GetProjectByIdHandler;
use App\Application\Workspace\Project\QueryHandler\ListProjectsHandler;
use App\Application\Workspace\Project\QueryHandler\PreviewProjectConflictsHandler;
use App\Domain\Workspace\Project\Command\RegisterProject;
use App\Domain\Workspace\Project\ProjectRepository;
use App\Domain\Workspace\Project\Query\GetProjectById;
use App\Domain\Workspace\Project\Query\ListProjects;
use App\Domain\Workspace\Project\Query\PreviewProjectConflicts;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(ProjectRepository::class, static fn(Container $container): ProjectRepository =>
        new PostgresProjectRepository($container->get(Connection::class)));
    $container->set(ProjectRegistrationAudit::class, static fn(Container $container): ProjectRegistrationAudit =>
        new PostgresProjectRegistrationAudit($container->get(Connection::class)));
    $container->set(RegisterProjectHandler::class, static fn(Container $container): RegisterProjectHandler =>
        new RegisterProjectHandler(
            $container->get(ProjectRepository::class),
            $container->get(ProjectRegistrationAudit::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(Clock::class),
            $container->get(EventDispatcher::class)
        ));
    foreach (
        [GetProjectByIdHandler::class, ListProjectsHandler::class, PreviewProjectConflictsHandler::class] as $handler
    ) {
        $container->set($handler, static fn(Container $container) =>
            new $handler($container->get(ProjectRepository::class)));
    }
    $container->get('messaging.command.router')->registerHandlers([
        RegisterProject::class => RegisterProjectHandler::class
    ]);
    $container->get('messaging.query.router')->registerHandlers([
        GetProjectById::class          => GetProjectByIdHandler::class,
        ListProjects::class            => ListProjectsHandler::class,
        PreviewProjectConflicts::class => PreviewProjectConflictsHandler::class
    ]);
    $container->set(ProjectWorkspaceAdmission::class, static fn(Container $container): ProjectWorkspaceAdmission =>
        new ProjectWorkspaceAdmission(
            $container->get(QueryBus::class),
            $container->get(UserRepository::class),
            $container->get(RoleRepository::class)
        ));
    foreach (
        [
        RegisterProjectResponder::class, GetProjectResponder::class,
        ListProjectsResponder::class, PreviewProjectConflictsResponder::class
        ] as $responder
    ) {
        $container->set($responder, static fn(Container $container) =>
            new $responder($container->get(JSendResponseFactory::class)));
    }
    $container->set(RegisterProjectAction::class, static fn(Container $container): RegisterProjectAction =>
        new RegisterProjectAction(
            $container->get(CommandBus::class),
            $container->get(ProjectWorkspaceAdmission::class),
            $container->get(RegisterProjectResponder::class)
        ));
    foreach (
        [
        GetProjectAction::class              => GetProjectResponder::class,
        ListProjectsAction::class            => ListProjectsResponder::class,
        PreviewProjectConflictsAction::class => PreviewProjectConflictsResponder::class
        ] as $action => $responder
    ) {
        $container->set($action, static fn(Container $container) => new $action(
            $container->get(QueryBus::class),
            $container->get(ProjectWorkspaceAdmission::class),
            $container->get($responder)
        ));
    }
};
