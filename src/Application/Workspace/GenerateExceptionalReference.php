<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Domain\Workspace\RepositoryId;
use App\Domain\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Class GenerateExceptionalReference
 *
 * Generates a non-reserved COMB reference without persisting an aggregate or granting import authority
 */
final readonly class GenerateExceptionalReference
{
    /**
     * Constructs GenerateExceptionalReference
     */
    public function __construct(
        private RegistrationStore $store,
        private RegistrationFences $fences,
        private RegistrationAccess $access,
        private TransactionalUnitOfWork $transactions,
        private Clock $clock
    ) {
    }

    /**
     * Returns only a syntactically typed-compatible UUID after current authority and bounded admission
     */
    public function generate(AuthenticationContext $authentication, WorkspaceId $workspace): string
    {
        return $this->transactions->commitTransactional(function () use ($authentication, $workspace): string {
            $this->fences->holdAuthority($authentication);
            $actor = $this->access->require($authentication, $workspace, RegistrationAccess::GENERATE);
            $this->store->admitReference($actor, $this->clock->now());

            return RepositoryId::generate()->toString();
        });
    }
}
