<?php

declare(strict_types=1);

use App\Adapter\Authorization\InstallationPermissionAdmission;
use App\Adapter\Authorization\InstallationSessionAdministration;
use App\Adapter\Console\PruneSessionPresentationCommand;
use App\Adapter\Http\Action\Api\V1\Sessions\ListOwnedSessionsAction;
use App\Adapter\Http\Action\Api\V1\Sessions\ListUserSessionsAction;
use App\Adapter\Http\Action\Api\V1\Sessions\RevokeOwnedSessionAction;
use App\Adapter\Http\Action\Api\V1\Sessions\RevokeUserSessionAction;
use App\Adapter\Http\Api\V1\Auth\CaptureSessionPresentation;
use App\Adapter\Http\Responder\Api\V1\Sessions\OwnedSessionsResponder;
use App\Adapter\Http\Responder\Api\V1\Sessions\SessionRevocationResponder;
use App\Adapter\Persistence\SessionPresentation\PostgresSessionPresentation;
use App\Application\Security\Session\QueryHandler\ListOwnedSessionsHandler;
use App\Application\Security\Session\SessionPresentation;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\RefreshSession\CommandHandler\RevokeSessionHandler;
use Fight\AccessControl\Application\AccessControl\RefreshSession\QueryHandler\ListActiveSessionsHandler;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set('sessions.authorization.read', static fn(Container $container) =>
        InstallationSessionAdministration::forRead($container->get(InstallationPermissionAdmission::class)));
    $container->set('sessions.authorization.revoke', static fn(Container $container) =>
        InstallationSessionAdministration::forRevocation($container->get(InstallationPermissionAdmission::class)));
    $container->set(ListUserSessionsAction::class, static fn(Container $container) => new ListUserSessionsAction(
        $container->get(QueryBus::class),
        $container->get(OwnedSessionsResponder::class)
    ));
    $container->set(RevokeUserSessionAction::class, static fn(Container $container) => new RevokeUserSessionAction(
        $container->get(RefreshSessionRepository::class),
        $container->get(CommandBus::class),
        $container->get(SessionRevocationResponder::class)
    ));
    $container->set(RevokeSessionHandler::class, static fn(Container $container) => new RevokeSessionHandler(
        $container->get(RefreshSessionRepository::class),
        $container->get(Clock::class),
        $container->get('sessions.authorization.revoke'),
        $container->get(TransactionalUnitOfWork::class),
        $container->get(EventDispatcher::class),
        $container->get(AuditEvidenceRepository::class)
    ));
    $container->get('messaging.command.router')->registerHandlers([
        RevokeSessionHandler::commandRegistration() => RevokeSessionHandler::class
    ]);
    $container->set(SessionRevocationResponder::class, static fn(Container $container) =>
        new SessionRevocationResponder($container->get(JSendResponseFactory::class)));
    $container->set(RevokeOwnedSessionAction::class, static fn(Container $container) => new RevokeOwnedSessionAction(
        $container->get(RefreshSessionRepository::class),
        $container->get(CommandBus::class),
        $container->get(SessionRevocationResponder::class)
    ));
    $container->set(PruneSessionPresentationCommand::class, static fn(Container $container) =>
        new PruneSessionPresentationCommand($container->get(SessionPresentation::class)));
    $container->set(SessionPresentation::class, static fn(Container $container) =>
        new PostgresSessionPresentation($container->get(Connection::class)));
    $container->set(CaptureSessionPresentation::class, static fn(Container $container) =>
        new CaptureSessionPresentation($container->get(SessionPresentation::class)));
    $container->set(ListActiveSessionsHandler::class, static fn(Container $container) => new ListActiveSessionsHandler(
        $container->get(RefreshSessionRepository::class),
        $container->get(Clock::class),
        $container->get('sessions.authorization.read')
    ));
    $container->get('messaging.query.router')->registerHandlers([
        ListActiveSessionsHandler::queryRegistration() => ListActiveSessionsHandler::class,
        ListOwnedSessionsHandler::queryRegistration()  => ListOwnedSessionsHandler::class
    ]);
    $container->set(ListOwnedSessionsHandler::class, static fn(Container $container) => new ListOwnedSessionsHandler(
        $container->get(QueryBus::class),
        $container->get(SessionPresentation::class)
    ));
    $container->set(OwnedSessionsResponder::class, static fn(Container $container) =>
        new OwnedSessionsResponder($container->get(JSendResponseFactory::class)));
    $container->set(ListOwnedSessionsAction::class, static fn(Container $container) => new ListOwnedSessionsAction(
        $container->get(QueryBus::class),
        $container->get(OwnedSessionsResponder::class)
    ));
};
