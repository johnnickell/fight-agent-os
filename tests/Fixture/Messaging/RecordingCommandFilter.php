<?php

declare(strict_types=1);

namespace Tests\Fixture\Messaging;

use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

final class RecordingCommandFilter implements CommandFilter
{
    /** @var list<string> */
    public array $processed = [];

    public function process(CommandMessage $commandMessage, callable $next): void
    {
        $this->processed[] = $commandMessage->payload()::class;
        $next($commandMessage);
    }
}
