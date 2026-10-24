<?php

declare(strict_types=1);

namespace App\Adapter\Security\Authentication;

use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\RequestPasswordReset;
use Fight\AccessControl\Domain\AccessControl\User\Event\RedactedCommandFailed;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\CommandFailedEvent;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;

/**
 * Class AuthenticationEvents
 *
 * Removes package refresh/logout exception messages before any shared event subscriber can observe them
 */
final readonly class AuthenticationEvents implements EventDispatcher
{
    /**
     * Constructs AuthenticationEvents
     */
    public function __construct(private EventDispatcher $events)
    {
    }

    /**
     * @inheritDoc
     */
    public function trigger(Event $event): void
    {
        $this->events->trigger($this->redact($event));
    }

    /**
     * @inheritDoc
     */
    public function dispatch(EventMessage $eventMessage): void
    {
        /** @var Event $event */
        $event = $eventMessage->payload();
        $this->events->dispatch(new EventMessage(
            $eventMessage->id(),
            $eventMessage->timestamp(),
            $this->redact($event),
            $eventMessage->meta()
        ));
    }

    /**
     * @inheritDoc
     */
    public function register(EventSubscriber $subscriber): void
    {
        $this->events->register($subscriber);
    }

    /**
     * @inheritDoc
     */
    public function unregister(EventSubscriber $subscriber): void
    {
        $this->events->unregister($subscriber);
    }

    /**
     * @inheritDoc
     */
    public function addHandler(string $eventType, callable $handler, int $priority = 0): void
    {
        $this->events->addHandler($eventType, $handler, $priority);
    }

    /**
     * @inheritDoc
     */
    public function getHandlers(?string $eventType = null): array
    {
        return $this->events->getHandlers($eventType);
    }

    /**
     * @inheritDoc
     */
    public function hasHandlers(?string $eventType = null): bool
    {
        return $this->events->hasHandlers($eventType);
    }

    /**
     * @inheritDoc
     */
    public function removeHandler(string $eventType, callable $handler): void
    {
        $this->events->removeHandler($eventType, $handler);
    }

    /**
     * Returns package outcome facts without infrastructure detail in sensitive failure events
     */
    private function redact(Event $event): Event
    {
        if ($event instanceof CommandFailedEvent && $event->getCommand() instanceof RequestPasswordReset) {
            return new RedactedCommandFailed(RequestPasswordReset::class, [], 'Password reset request failed.');
        }
        if (
            $event instanceof RedactedCommandFailed && in_array($event->getCommandClass(), [
            AuthenticationService::class.'::refresh', AuthenticationService::class.'::logout'
            ], true)
        ) {
            return new RedactedCommandFailed($event->getCommandClass(), [], 'Authentication operation failed.');
        }

        return $event;
    }
}
