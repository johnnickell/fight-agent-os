<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use LogicException;

/**
 * Class ConsoleCommandIntent
 *
 * Retains intended payloads across filters without leaking invocation state into later commands
 */
final class ConsoleCommandIntent
{
    /** @var list<array{command: Command, routed: bool}> */
    private array $pending = [];

    /**
     * Dispatches one scoped intent and rejects silent vetoes
     */
    public function dispatch(CommandBus $bus, Command $command): void
    {
        $index = count($this->pending);
        $this->pending[] = ['command' => $command, 'routed' => false];
        try {
            $bus->execute($command);
            if (!$this->pending[$index]['routed']) {
                throw new LogicException('The required console command was withheld.');
            }
        } finally {
            array_pop($this->pending);
        }
    }

    /**
     * Rejects changed or repeated terminal payloads before mutation
     */
    public function authorize(Command $command): void
    {
        $index = count($this->pending) - 1;
        $expected = $this->pending[$index] ?? null;
        if (
            $expected === null || $expected['routed'] || $expected['command']::class !== $command::class
            || $expected['command']->toArray() !== $command->toArray()
        ) {
            throw new LogicException('The routed console payload changed.');
        }
        $this->pending[$index]['routed'] = true;
    }
}
