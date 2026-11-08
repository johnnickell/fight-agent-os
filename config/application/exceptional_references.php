<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\References\GenerateExceptionalReferenceAction;
use App\Adapter\Http\Responder\Api\V1\References\GenerateExceptionalReferenceResponder;
use App\Adapter\Reference\ExceptionalReferences;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(ExceptionalReferences::class, static fn(Container $c): ExceptionalReferences =>
        new ExceptionalReferences($c->get(Connection::class), $c->get(Clock::class)));
    $container->set(GenerateExceptionalReferenceResponder::class, static fn(
        Container $c
    ): GenerateExceptionalReferenceResponder =>
        new GenerateExceptionalReferenceResponder($c->get(JSendResponseFactory::class)));
    $container->set(GenerateExceptionalReferenceAction::class, static fn(
        Container $c
    ): GenerateExceptionalReferenceAction =>
        new GenerateExceptionalReferenceAction(
            $c->get(ExceptionalReferences::class),
            $c->get(QueryBus::class),
            $c->get(UserRepository::class),
            $c->get(RoleRepository::class),
            $c->get(GenerateExceptionalReferenceResponder::class)
        ));
};
