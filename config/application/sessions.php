<?php

declare(strict_types=1);

use App\Adapter\Console\PruneSessionPresentationCommand;
use App\Adapter\Http\Action\Api\V1\Sessions\ListOwnedSessionsAction;
use App\Adapter\Http\Action\Api\V1\Sessions\ListUserSessionsAction;
use App\Adapter\Http\Action\Api\V1\Sessions\RevokeOwnedSessionAction;
use App\Adapter\Http\Action\Api\V1\Sessions\RevokeUserSessionAction;
use App\Adapter\Http\Api\V1\Auth\CaptureSessionPresentation;
use App\Adapter\Http\Responder\Api\V1\Sessions\OwnedSessionsResponder;
use App\Adapter\Http\Responder\Api\V1\Sessions\SessionRevocationResponder;
use App\Adapter\Persistence\SessionPresentation\PostgresSessionPresentation;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\PermissionGuard;
use App\Application\Authorization\SessionAdministrationDecision;
use App\Application\Sessions\ReadOwnedSessions;
use App\Application\Sessions\RevokeOwnedSession;
use App\Application\Sessions\SessionPresentation;
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
    $container->set('sessions.authorization.read', static fn(Container $c) =>
        SessionAdministrationDecision::forRead($c->get(InstallationAuthority::class)));
    $container->set('sessions.authorization.revoke', static fn(Container $c) =>
        SessionAdministrationDecision::forRevocation($c->get(InstallationAuthority::class)));
    $container->set(ListUserSessionsAction::class, static fn(Container $c) => new ListUserSessionsAction(
        $c->get(QueryBus::class),
        $c->get(OwnedSessionsResponder::class)
    ));
    $container->set(RevokeUserSessionAction::class, static fn(Container $c) => new RevokeUserSessionAction(
        $c->get(RefreshSessionRepository::class),
        $c->get(CommandBus::class),
        $c->get(SessionRevocationResponder::class)
    ));
    $container->set(RevokeSessionHandler::class, static fn(Container $c) => new RevokeSessionHandler(
        $c->get(RefreshSessionRepository::class),
        $c->get(Clock::class),
        $c->get('sessions.authorization.revoke'),
        $c->get(AuditEvidenceRepository::class),
        $c->get(TransactionalUnitOfWork::class),
        $c->get(EventDispatcher::class)
    ));
    $container->get('messaging.command.router')->registerHandlers([
        RevokeSessionHandler::commandRegistration() => RevokeSessionHandler::class
    ]);
    $container->set(RevokeOwnedSession::class, static fn(Container $c) => new RevokeOwnedSession(
        $c->get(PermissionGuard::class),
        $c->get(CommandBus::class)
    ));
    $container->set(SessionRevocationResponder::class, static fn(Container $c) =>
        new SessionRevocationResponder($c->get(JSendResponseFactory::class)));
    $container->set(RevokeOwnedSessionAction::class, static fn(Container $c) => new RevokeOwnedSessionAction(
        $c->get(RevokeOwnedSession::class),
        $c->get(SessionRevocationResponder::class)
    ));
    $container->set(PruneSessionPresentationCommand::class, static fn(Container $c) =>
        new PruneSessionPresentationCommand($c->get(SessionPresentation::class)));
    $container->set(SessionPresentation::class, static fn(Container $c) =>
        new PostgresSessionPresentation($c->get(Connection::class)));
    $container->set(CaptureSessionPresentation::class, static fn(Container $c) =>
        new CaptureSessionPresentation($c->get(SessionPresentation::class)));
    $container->set(ListActiveSessionsHandler::class, static fn(Container $c) => new ListActiveSessionsHandler(
        $c->get(RefreshSessionRepository::class),
        $c->get(Clock::class),
        $c->get('sessions.authorization.read')
    ));
    $container->get('messaging.query.router')->registerHandlers([
        ListActiveSessionsHandler::queryRegistration() => ListActiveSessionsHandler::class
    ]);
    $container->set(ReadOwnedSessions::class, static fn(Container $c) => new ReadOwnedSessions(
        $c->get(PermissionGuard::class),
        $c->get(QueryBus::class),
        $c->get(SessionPresentation::class)
    ));
    $container->set(OwnedSessionsResponder::class, static fn(Container $c) =>
        new OwnedSessionsResponder($c->get(JSendResponseFactory::class)));
    $container->set(ListOwnedSessionsAction::class, static fn(Container $c) => new ListOwnedSessionsAction(
        $c->get(ReadOwnedSessions::class),
        $c->get(OwnedSessionsResponder::class)
    ));
};
