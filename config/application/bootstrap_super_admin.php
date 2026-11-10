<?php

declare(strict_types=1);

use App\Adapter\Console\BootstrapSuperAdminCommand;
use App\Adapter\Persistence\Bootstrap\PostgresBootstrapProgress;
use App\Application\Bootstrap\BootstrapOperation;
use App\Domain\Authorization\ManagedPolicyCatalog;
use App\Domain\Bootstrap\BootstrapProgress;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set('bootstrap.inspection.connection', static fn(Container $container): Connection =>
        DriverManager::getConnection($container->get(Connection::class)->getParams()));
    $container->set(BootstrapProgress::class, static fn(Container $container): BootstrapProgress =>
        new PostgresBootstrapProgress(
            $container->get(Connection::class),
            $container->get('bootstrap.inspection.connection'),
            $container->get(AuditEvidenceRepository::class)
        ));
    $container->set(BootstrapOperation::class, static fn(Container $container): BootstrapOperation =>
        new BootstrapOperation(
            $container->get(CommandBus::class),
            $container->get(QueryBus::class),
            $container->get(UserRepository::class),
            $container->get(BootstrapProgress::class),
            $container->get(ManagedPolicyCatalog::class)
        ));
    $container->set(BootstrapSuperAdminCommand::class, static fn(Container $container): BootstrapSuperAdminCommand =>
        new BootstrapSuperAdminCommand($container->get(BootstrapOperation::class)));
};
