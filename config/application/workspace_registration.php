<?php

declare(strict_types=1);

use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Http\Action\Api\V1\Repositories\GenerateExceptionalReferenceAction;
use App\Adapter\Http\Action\Api\V1\Repositories\GetRepositoryRegistrationAction;
use App\Adapter\Http\Action\Api\V1\Repositories\ListRepositoryRegistrationsAction;
use App\Adapter\Http\Action\Api\V1\Repositories\PreviewRepositoryConflictsAction;
use App\Adapter\Http\Action\Api\V1\Repositories\RegisterRepositoryAction;
use App\Adapter\Http\Responder\Api\V1\Repositories\GenerateExceptionalReferenceResponder;
use App\Adapter\Http\Responder\Api\V1\Repositories\GetRepositoryRegistrationResponder;
use App\Adapter\Http\Responder\Api\V1\Repositories\ListRepositoryRegistrationsResponder;
use App\Adapter\Http\Responder\Api\V1\Repositories\PreviewRepositoryConflictsResponder;
use App\Adapter\Http\Responder\Api\V1\Repositories\RegisterRepositoryResponder;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Workspace\PostgresRegistrationFences;
use App\Adapter\Persistence\Workspace\PostgresRegistrationStore;
use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Workspace\BootstrapWorkspace;
use App\Application\Workspace\GenerateExceptionalReference;
use App\Application\Workspace\GetRepositoryRegistration;
use App\Application\Workspace\ListRepositoryRegistrations;
use App\Application\Workspace\PreviewRepositoryConflicts;
use App\Application\Workspace\RegisterRepository;
use App\Application\Workspace\RegistrationAccess;
use App\Application\Workspace\RegistrationFences;
use App\Application\Workspace\RegistrationStore;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(RegistrationStore::class, static fn(Container $c): RegistrationStore =>
        new PostgresRegistrationStore($c->get(Connection::class)));
    $container->set(RegistrationFences::class, static fn(Container $c): RegistrationFences =>
        new PostgresRegistrationFences(
            $c->get(Connection::class),
            $c->get(AuthorizationReferenceFences::class),
            $c->get(AuthenticationAuthorityFences::class)
        ));
    $container->set(RegistrationAccess::class, static fn(Container $c): RegistrationAccess => new RegistrationAccess(
        $c->get(AuthenticatedPermissions::class),
        $c->get(RegistrationStore::class),
        $c->get(UserRepository::class),
        $c->get(RoleRepository::class)
    ));
    $container->set(BootstrapWorkspace::class, static fn(Container $c): BootstrapWorkspace => new BootstrapWorkspace(
        $c->get(RegistrationStore::class),
        $c->get(TransactionalUnitOfWork::class),
        $c->get(Clock::class)
    ));
    $container->set(BootstrapWorkspaceCommand::class, static fn(Container $c): BootstrapWorkspaceCommand =>
        new BootstrapWorkspaceCommand($c->get(BootstrapWorkspace::class)));
    $container->set(RegisterRepository::class, static fn(Container $c): RegisterRepository => new RegisterRepository(
        $c->get(RegistrationStore::class),
        $c->get(RegistrationFences::class),
        $c->get(RegistrationAccess::class),
        $c->get(TransactionalUnitOfWork::class),
        $c->get(Clock::class),
        $c->get(EventDispatcher::class)
    ));
    foreach (
        [
        GetRepositoryRegistration::class, ListRepositoryRegistrations::class, PreviewRepositoryConflicts::class
        ] as $operation
    ) {
        $container->set($operation, static fn(Container $c) => new $operation(
            $c->get(RegistrationStore::class),
            $c->get(RegistrationFences::class),
            $c->get(RegistrationAccess::class),
            $c->get(TransactionalUnitOfWork::class)
        ));
    }
    $container->set(GenerateExceptionalReference::class, static fn(Container $c): GenerateExceptionalReference =>
        new GenerateExceptionalReference(
            $c->get(RegistrationStore::class),
            $c->get(RegistrationFences::class),
            $c->get(RegistrationAccess::class),
            $c->get(TransactionalUnitOfWork::class),
            $c->get(Clock::class)
        ));
    foreach (
        [
        RegisterRepositoryResponder::class,
        GetRepositoryRegistrationResponder::class,
        ListRepositoryRegistrationsResponder::class,
        PreviewRepositoryConflictsResponder::class,
        GenerateExceptionalReferenceResponder::class
        ] as $responder
    ) {
        $container->set($responder, static fn(Container $c) => new $responder($c->get(JSendResponseFactory::class)));
    }
    $interactions = [
        [RegisterRepositoryAction::class, RegisterRepository::class, RegisterRepositoryResponder::class],
        [GetRepositoryRegistrationAction::class, GetRepositoryRegistration::class,
            GetRepositoryRegistrationResponder::class],
        [ListRepositoryRegistrationsAction::class, ListRepositoryRegistrations::class,
            ListRepositoryRegistrationsResponder::class],
        [PreviewRepositoryConflictsAction::class, PreviewRepositoryConflicts::class,
            PreviewRepositoryConflictsResponder::class],
        [GenerateExceptionalReferenceAction::class, GenerateExceptionalReference::class,
            GenerateExceptionalReferenceResponder::class]
    ];
    foreach ($interactions as [$action, $operation, $responder]) {
        $container->set($action, static fn(Container $c) => new $action($c->get($operation), $c->get($responder)));
    }
};
