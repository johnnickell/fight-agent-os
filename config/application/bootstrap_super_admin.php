<?php

declare(strict_types=1);

use App\Adapter\Console\BootstrapSuperAdminCommand;
use App\Adapter\Persistence\Bootstrap\PostgresBootstrapProgress;
use App\Application\Authorization\CommandHandler\BootstrapSuperAdminHandler;
use App\Domain\Authorization\ManagedPolicyCatalog;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Bootstrap\BootstrapProgress;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(BootstrapProgress::class, static fn(Container $container): BootstrapProgress =>
        new PostgresBootstrapProgress($container->get('authorization.inspection.connection')));
    $container->set(BootstrapSuperAdminHandler::class, static fn(Container $container): BootstrapSuperAdminHandler =>
        new BootstrapSuperAdminHandler(
            $container->get(CommandBus::class),
            $container->get(QueryBus::class),
            $container->get(UserRepository::class),
            $container->get(BootstrapProgress::class),
            $container->get(ManagedPolicyCatalog::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(MembershipEvidenceRepository::class)
        ));
    $container->get('messaging.command.router')->registerHandlers([
        BootstrapSuperAdminHandler::commandRegistration() => BootstrapSuperAdminHandler::class
    ]);
    $container->set(BootstrapSuperAdminCommand::class, static fn(Container $container): BootstrapSuperAdminCommand =>
        new BootstrapSuperAdminCommand(
            $container->get(CommandBus::class),
            $container->get(BootstrapProgress::class)
        ));
};
