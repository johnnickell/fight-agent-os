<?php

declare(strict_types=1);

use Fight\Common\Adapter\ServiceContainer\Fight\ContainerCapabilityRegistrar;
use Fight\Common\Application\Service\Container;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\Fixture\Messaging\JourneyRecorded;
use Tests\Fixture\Messaging\ReadJourneyQuery;
use Tests\Fixture\Messaging\RecordJourneyCommand;
use Tests\Fixture\Messaging\RecordingCommandFilter;
use Tests\Fixture\Messaging\RecordingCommandHandler;
use Tests\Fixture\Messaging\RecordingEventSubscriber;
use Tests\Fixture\Messaging\RecordingQueryFilter;
use Tests\Fixture\Messaging\RecordingQueryHandler;

/** @return \Slim\App<Container> */
return static function (): \Slim\App {
    /** @var Container $container */
    $container = require dirname(__DIR__, 3).'/config/services.php';

    ContainerCapabilityRegistrar::registerMessaging(
        $container,
        [
            RecordingCommandHandler::class => static fn (): RecordingCommandHandler => new RecordingCommandHandler(),
            RecordingQueryHandler::class => static fn (): RecordingQueryHandler => new RecordingQueryHandler(),
            RecordingEventSubscriber::class => static fn (): RecordingEventSubscriber => new RecordingEventSubscriber(),
            RecordingCommandFilter::class => static fn (): RecordingCommandFilter => new RecordingCommandFilter(),
            RecordingQueryFilter::class => static fn (): RecordingQueryFilter => new RecordingQueryFilter(),
        ],
        [],
        [RecordJourneyCommand::class => RecordingCommandHandler::class],
        [ReadJourneyQuery::class => RecordingQueryHandler::class],
        [RecordingEventSubscriber::class => RecordingEventSubscriber::class],
        ['messaging.command.bus' => [RecordingCommandFilter::class], 'messaging.query.bus' => [RecordingQueryFilter::class]],
        ['command.router' => 'messaging.command.router', 'query.router' => 'messaging.query.router', 'event.dispatcher' => 'messaging.event.dispatcher'],
    );

    AppFactory::setContainer($container);
    AppFactory::setResponseFactory(new ResponseFactory());
    $app = AppFactory::create();
    $container->set(\Slim\App::class, static fn (): \Slim\App => $app);
    require dirname(__DIR__, 3).'/config/routes.php';

    return $app;
};
