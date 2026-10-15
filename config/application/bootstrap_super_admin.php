<?php

declare(strict_types=1);

use App\Adapter\Console\BootstrapSuperAdminCommand;
use App\Adapter\Persistence\Bootstrap\PostgresBootstrapProgress;
use App\Application\Bootstrap\BootstrapOperation;
use App\Domain\Bootstrap\BootstrapProgress;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set('bootstrap.inspection.connection', static fn(Container $c): Connection =>
        DriverManager::getConnection($c->get(Connection::class)->getParams()));
    $container->set(BootstrapProgress::class, static fn(Container $c): BootstrapProgress =>
        new PostgresBootstrapProgress(
            $c->get(Connection::class),
            $c->get('bootstrap.inspection.connection'),
            $c->get(AuditEvidenceRepository::class)
        ));
    $container->set(BootstrapOperation::class, static fn(Container $c): BootstrapOperation => new BootstrapOperation(
        $c->get(CommandBus::class),
        $c->get(QueryBus::class),
        $c->get(UserRepository::class),
        $c->get(BootstrapProgress::class)
    ));
    $container->set(BootstrapSuperAdminCommand::class, static fn(Container $c): BootstrapSuperAdminCommand =>
        new BootstrapSuperAdminCommand($c->get(BootstrapOperation::class)));
};
