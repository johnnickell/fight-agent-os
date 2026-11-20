<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Security\Authentication\AuthenticationEvents;
use Fight\AccessControl\Application\AccessControl\RefreshSession\SessionLifecycle;
use Fight\AccessControl\Domain\AccessControl\User\Event\RedactedCommandFailed;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Messaging\Event\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps internal diagnostics out of published refresh and logout failures
 */
final class AuthenticationEventsTest extends TestCase
{
    /**
     * Publishes the operation but strips package diagnostics before subscribers see them
     */
    #[DataProvider('operations')]
    public function testSessionFailure(string $operation): void
    {
        $failure = new RedactedCommandFailed($operation, ['component' => 'private-marker'], 'private diagnostic');
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::once())->method('trigger')->with(self::callback(
            static fn(Event $event): bool => $event instanceof RedactedCommandFailed && $event->toArray() === [
                'command_class' => $operation,
                'command_data'  => [],
                'error_message' => 'Authentication operation failed.'
            ]
        ));

        (new AuthenticationEvents($dispatcher))->trigger($failure);
    }

    /**
     * Supplies the two sensitive session operations
     *
     * @return iterable<array{string}>
     */
    public static function operations(): iterable
    {
        yield [SessionLifecycle::class.'::refresh'];
        yield [SessionLifecycle::class.'::logout'];
    }
}
