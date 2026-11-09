<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Users\DisableUserAction;
use App\Adapter\Http\Action\Api\V1\Users\EnableUserAction;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresEffectiveAdministrators;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use App\Application\Authorization\PreventAdministratorLockout;
use App\Domain\Authorization\EffectiveAdministrators;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\DeleteUserHandler;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\DisableUserHandler;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\EnableUserHandler;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    // A shared binding of the SAME adapter/connection; only these handlers load under the User fence.
    // No policy interception, transaction wrapper or changes to sibling package read coordination.
    $container->set('user_lifecycle.repository', static fn(Container $c) => new PostgresUserRepository(
        $c->get(Connection::class),
        $c->get(AuthorizationReferenceFences::class),
        $c->get(AuthenticationAuthorityFences::class),
        lockLifecycleSnapshot: true
    ));
    $container->set(EffectiveAdministrators::class, static fn(Container $c) =>
        new PostgresEffectiveAdministrators($c->get(Connection::class)));
    $container->set(PreventAdministratorLockout::class, static fn(Container $c) => new PreventAdministratorLockout(
        $c->get(EffectiveAdministrators::class)
    ));
    $container->set(UserLifecycleResponder::class, static fn(Container $c) =>
        new UserLifecycleResponder($c->get(JSendResponseFactory::class)));
    foreach ([DisableUserAction::class, EnableUserAction::class] as $action) {
        $container->set($action, static fn(Container $c) => new $action(
            $c->get(PreventAdministratorLockout::class),
            $c->get(CommandBus::class),
            $c->get(UserLifecycleResponder::class)
        ));
    }
    $container->set(EnableUserHandler::class, static fn(Container $c) => new EnableUserHandler(
        $c->get('user_lifecycle.repository'),
        $c->get(AuditEvidenceRepository::class),
        $c->get(TransactionalUnitOfWork::class),
        $c->get(EventDispatcher::class),
        $c->get(Clock::class)
    ));
    $container->get('messaging.command.router')->registerHandlers([
        EnableUserHandler::commandRegistration() => EnableUserHandler::class
    ]);
    foreach ([DisableUserHandler::class, DeleteUserHandler::class] as $handler) {
        $repository = UserRepository::class;
        if ($handler === DisableUserHandler::class) {
            $repository = 'user_lifecycle.repository';
        }
        $container->set($handler, static fn(Container $c) => new $handler(
            $c->get($repository),
            $c->get(SessionRevocationService::class),
            $c->get(Clock::class),
            $c->get(AuditEvidenceRepository::class),
            $c->get(TransactionalUnitOfWork::class),
            $c->get(EventDispatcher::class),
            $c->get(PasswordResetGrantRepository::class)
        ));
        $container->get('messaging.command.router')->registerHandlers([$handler::commandRegistration() => $handler]);
    }
};
