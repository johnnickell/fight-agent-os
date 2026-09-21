<?php

declare(strict_types=1);

namespace Tests\Fixture\Messaging;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

final class RecordingCommandHandler implements CommandHandler
{
    /** @var list<string> */
    public array $handled = [];

    public static function commandRegistration(): string
    {
        return RecordJourneyCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void
    {
        /** @var RecordJourneyCommand $command */
        $command = $commandMessage->payload();
        $this->handled[] = $command->value;
    }
}
