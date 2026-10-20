<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Domain\Workspace\RegistrationConflict;
use App\Domain\Workspace\RegistrationIntent;
use App\Domain\Workspace\RepositoryRegistered;
use App\Domain\Workspace\RepositoryRegistration;
use App\Domain\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Class RegisterRepository
 *
 * Coordinates current package authority with atomic Workspace-owned state, claims, audit and results
 */
final readonly class RegisterRepository
{
    /**
     * Constructs RegisterRepository
     */
    public function __construct(
        private RegistrationStore $store,
        private RegistrationFences $fences,
        private RegistrationAccess $access,
        private TransactionalUnitOfWork $transactions,
        private Clock $clock,
        private EventDispatcher $events
    ) {
    }

    /**
     * Registers exactly once for a canonical actor-bound intent and dispatches only a committed fact
     */
    public function register(
        AuthenticationContext $authentication,
        WorkspaceId $workspace,
        RegistrationIntent $intent
    ): RegistrationView {
        /** @var array{RegistrationView, ?RepositoryRegistered} $outcome */
        $outcome = $this->transactions->commitTransactional(function () use (
            $authentication,
            $workspace,
            $intent
        ): array {
            $this->fences->holdAuthority($authentication);
            $this->store->holdInitialWorkspace();
            $actor = $this->access->require($authentication, $workspace, RegistrationAccess::REGISTER);
            $original = $this->store->original($workspace, $actor, $intent);
            if ($original !== null) {
                $this->access->require($authentication, $workspace, RegistrationAccess::VIEW);

                return [$original, null];
            }
            $conflicts = $this->store->conflicts($workspace, $intent->metadata->slug, $intent->sources);
            if ($conflicts !== []) {
                throw new RegistrationConflict($conflicts[0]);
            }
            $registration = RepositoryRegistration::register(
                $workspace,
                $intent->metadata,
                $intent->sources,
                $actor,
                $this->clock->now()
            );
            $view = $this->store->add($registration, $intent);

            return [$view, new RepositoryRegistered($workspace, $registration->id)];
        });
        [$view, $event] = $outcome;
        if ($event !== null) {
            // Delivery can fail after commit. The durable original result is recovery authority.
            $this->events->trigger($event);
        }

        return $view;
    }
}
