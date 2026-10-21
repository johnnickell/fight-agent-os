<?php

declare(strict_types=1);

use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\DeleteUserHandler;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\DisableUserHandler;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    foreach ([DisableUserHandler::class, DeleteUserHandler::class] as $handler) {
        $container->set($handler, static fn(Container $c) => new $handler(
            $c->get(UserRepository::class),
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
