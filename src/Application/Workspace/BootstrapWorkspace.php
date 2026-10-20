<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Domain\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Class BootstrapWorkspace
 *
 * Owns the explicitly invoked trusted installation bootstrap, never request or startup initialization
 */
final readonly class BootstrapWorkspace
{
    /**
     * Constructs BootstrapWorkspace
     */
    public function __construct(
        private RegistrationStore $store,
        private TransactionalUnitOfWork $transactions,
        private Clock $clock
    ) {
    }

    /**
     * Returns the original committed Workspace on every retry without enrolling a Host
     */
    public function bootstrap(UserId $operator): WorkspaceView
    {
        return $this->transactions->commitTransactional(function () use ($operator): WorkspaceView {
            $this->store->holdInitialWorkspace();
            $original = $this->store->initialWorkspace();
            if ($original !== null) {
                return $original;
            }
            $workspace = new WorkspaceView(WorkspaceId::generate(), $this->clock->now());
            $this->store->bootstrap($workspace, $operator);

            return $workspace;
        });
    }
}
