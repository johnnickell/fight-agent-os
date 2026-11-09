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
    $container->set(ExceptionalReferences::class, static fn(Container $container): ExceptionalReferences =>
        new ExceptionalReferences($container->get(Connection::class), $container->get(Clock::class)));
    $container->set(GenerateExceptionalReferenceResponder::class, static fn(
        Container $container
    ): GenerateExceptionalReferenceResponder =>
        new GenerateExceptionalReferenceResponder($container->get(JSendResponseFactory::class)));
    $container->set(GenerateExceptionalReferenceAction::class, static fn(
        Container $container
    ): GenerateExceptionalReferenceAction =>
        new GenerateExceptionalReferenceAction(
            $container->get(ExceptionalReferences::class),
            $container->get(QueryBus::class),
            $container->get(UserRepository::class),
            $container->get(RoleRepository::class),
            $container->get(GenerateExceptionalReferenceResponder::class)
        ));
};
