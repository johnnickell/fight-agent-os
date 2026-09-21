<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Messaging\JourneyRecorded;
use Tests\Fixture\Messaging\ReadJourneyQuery;
use Tests\Fixture\Messaging\RecordJourneyCommand;
use Tests\Fixture\Messaging\RecordingCommandFilter;
use Tests\Fixture\Messaging\RecordingCommandHandler;
use Tests\Fixture\Messaging\RecordingEventSubscriber;
use Tests\Fixture\Messaging\RecordingQueryFilter;
use Tests\Fixture\Messaging\RecordingQueryHandler;

final class MessagingJourneyTest extends TestCase
{
    public function test_the_booted_container_routes_synchronous_fight_messages_through_handlers_subscribers_and_filters(): void
    {
        $boot = require sprintf('%s/Fixture/Messaging/boot.php', dirname(__DIR__));
        $app = $boot();
        $container = $app->getContainer();

        $commandBus = $container->get(CommandBus::class);
        self::assertInstanceOf(SynchronousCommandBus::class, $commandBus);
        $commandBus->execute(new RecordJourneyCommand('command-ready'));
        self::assertSame(['command-ready'], $container->get(RecordingCommandHandler::class)->handled);
        self::assertSame([RecordJourneyCommand::class], $container->get(RecordingCommandFilter::class)->processed);

        $queryBus = $container->get(QueryBus::class);
        self::assertSame('answer:query-ready', $queryBus->fetch(new ReadJourneyQuery('query-ready')));
        self::assertSame([ReadJourneyQuery::class], $container->get(RecordingQueryFilter::class)->processed);

        $eventDispatcher = $container->get(EventDispatcher::class);
        self::assertInstanceOf(SynchronousEventDispatcher::class, $eventDispatcher);
        $eventDispatcher->trigger(new JourneyRecorded('event-ready'));
        self::assertSame(['event-ready'], $container->get(RecordingEventSubscriber::class)->received);
    }
}
