<?php

declare(strict_types=1);

use App\Adapter\Console\BootstrapSuperAdminCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\Persistence\Bootstrap\PostgresBootstrapClaims;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Application\Bootstrap\BootstrapClaimScope;
use App\Application\Bootstrap\BootstrapCommandGuard;
use App\Application\Bootstrap\BootstrapOperation;
use App\Application\Bootstrap\CommandHandler\BootstrapSuperAdminHandler;
use App\Application\Invitation\ConsoleAssignmentPolicy;
use App\Application\Invitation\ConsoleCommandIntent;
use App\Domain\Bootstrap\BootstrapClaims;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

// Loaded only for the exact dedicated console command, never by HTTP or ordinary console composition.
return static function (Container $container): void {
    $configure = require __DIR__.'/console_invitation.php';
    $configure($container);
    $container->set('bootstrap.inspection.connection', static fn(Container $c): Connection =>
        DriverManager::getConnection($c->get(Connection::class)->getParams()));
    $container->set(BootstrapClaims::class, static fn(Container $c): BootstrapClaims => new PostgresBootstrapClaims(
        $c->get(Connection::class),
        $c->get('bootstrap.inspection.connection'),
        $c->get(AuthorizationReferenceFences::class),
        $c->get(UserRepository::class),
        $c->get(AuditEvidenceRepository::class)
    ));
    $container->set(BootstrapClaimScope::class, static fn(): BootstrapClaimScope => new BootstrapClaimScope());
    $container->set(BootstrapOperation::class, static fn(Container $c): BootstrapOperation => new BootstrapOperation(
        $c->get(CommandBus::class),
        $c->get(QueryBus::class),
        $c->get(BootstrapClaims::class),
        $c->get(ConsoleCommandIntent::class),
        $c->get(BootstrapClaimScope::class)
    ));
    $container->set(BootstrapCommandGuard::class, static fn(Container $c) => new BootstrapCommandGuard(
        $c->get(ConsoleCommandIntent::class),
        $c->get(ConsoleAssignmentPolicy::class),
        $c->get(BootstrapClaimScope::class)
    ));
    $container->set(BootstrapSuperAdminHandler::class, static fn(Container $c): BootstrapSuperAdminHandler =>
        new BootstrapSuperAdminHandler($c->get(BootstrapOperation::class)));
    $container->get('messaging.command.router')->registerHandlers([
        BootstrapSuperAdmin::class => BootstrapSuperAdminHandler::class
    ]);
    $container->set(BootstrapSuperAdminCommand::class, static fn(Container $c): BootstrapSuperAdminCommand =>
        new BootstrapSuperAdminCommand($c->get(BootstrapOperation::class)));
    $container->set(SafeConsoleApplication::class, static function (Container $c): SafeConsoleApplication {
        $application = new SafeConsoleApplication('Fight Agent OS bootstrap');
        $application->addCommand($c->get(BootstrapSuperAdminCommand::class));
        $application->setAutoExit(false);

        return $application;
    });
    $container->set('bootstrap.command.routing', static function (Container $c): CommandPipeline {
        $routing = new CommandPipeline($c->get('messaging.command.routing'));
        $routing->addFilter($c->get(BootstrapCommandGuard::class));

        return $routing;
    });
    $container->set('messaging.command.bus', static fn(Container $c): CommandPipeline =>
        new CommandPipeline($c->get('bootstrap.command.routing')));
};
