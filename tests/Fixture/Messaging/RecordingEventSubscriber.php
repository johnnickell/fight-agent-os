<?php

declare(strict_types=1);

namespace Tests\Fixture\Messaging;

use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\EventMessage;

final class RecordingEventSubscriber implements EventSubscriber
{
    /** @var list<string> */
    public array $received = [];

    public static function eventRegistration(): array
    {
        return [JourneyRecorded::class => 'record'];
    }

    public function record(EventMessage $eventMessage): void
    {
        /** @var JourneyRecorded $event */
        $event = $eventMessage->payload();
        $this->received[] = $event->value;
    }
}
