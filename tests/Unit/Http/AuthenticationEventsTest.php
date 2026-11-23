<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Security\Authentication\AuthenticationEvents;
use Fight\AccessControl\Application\AccessControl\RefreshSession\SessionLifecycle;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\RequestPasswordReset;
use Fight\AccessControl\Domain\AccessControl\User\Event\RedactedCommandFailed;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\CommandFailedEvent;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Value\Internet\EmailAddress;
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
     * Keeps dispatch identity, time and metadata while redacting password reset diagnostics and input
     */
    public function testPasswordResetDispatchRedactsOnlyPayload(): void
    {
        $message = EventMessage::create(new CommandFailedEvent(
            new RequestPasswordReset(EmailAddress::fromString('private@example.test')),
            'Private failure details'
        ));
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
            static function (EventMessage $public) use ($message): bool {
                self::assertEquals($message->id(), $public->id());
                self::assertEquals($message->timestamp(), $public->timestamp());
                self::assertEquals($message->meta(), $public->meta());
                $event = $public->payload();
                self::assertInstanceOf(RedactedCommandFailed::class, $event);
                self::assertSame([
                    'command_class' => RequestPasswordReset::class,
                    'command_data'  => [],
                    'error_message' => 'Password reset request failed.'
                ], $event->toArray());

                return true;
            }
        ));

        new AuthenticationEvents($dispatcher)->dispatch($message);
    }

    /**
     * Preserves unrelated events and subscriber/handler control at the same boundary
     */
    public function testUnrelatedEventAndHandlerControlArePreserved(): void
    {
        $event = $this->createStub(Event::class);
        $subscriber = $this->createStub(EventSubscriber::class);
        $dispatcher = $this->createMock(EventDispatcher::class);
        $events = new AuthenticationEvents($dispatcher);
        $handler = static fn (): null => null;
        $dispatcher->expects(self::once())->method('trigger')->with(self::identicalTo($event));
        $dispatcher->expects(self::once())->method('register')->with($subscriber);
        $dispatcher->expects(self::once())->method('addHandler')->with(Event::class, $handler, 15);
        $dispatcher->expects(self::once())->method('hasHandlers')->with(Event::class)->willReturn(true);
        $handlers = [Event::class => [$handler]];
        $dispatcher->expects(self::once())->method('getHandlers')->with(Event::class)->willReturn($handlers);
        $dispatcher->expects(self::once())->method('removeHandler')->with(Event::class, $handler);
        $dispatcher->expects(self::once())->method('unregister')->with($subscriber);

        $events->register($subscriber);
        $events->addHandler(Event::class, $handler, 15);
        self::assertTrue($events->hasHandlers(Event::class));
        self::assertSame($handlers, $events->getHandlers(Event::class));
        $events->trigger($event);
        $events->removeHandler(Event::class, $handler);
        $events->unregister($subscriber);
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
