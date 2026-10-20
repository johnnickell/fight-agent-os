<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Application\Failure\ResourceNotFound;
use App\Domain\Workspace\RepositoryId;
use App\Domain\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Class GetRepositoryRegistration
 */
final readonly class GetRepositoryRegistration
{
    /**
     * Constructs GetRepositoryRegistration
     */
    public function __construct(
        private RegistrationStore $store,
        private RegistrationFences $fences,
        private RegistrationAccess $access,
        private TransactionalUnitOfWork $transactions
    ) {
    }

    /**
     * Returns one safe registration under current scoped disclosure authority
     */
    public function get(
        AuthenticationContext $authentication,
        WorkspaceId $workspace,
        RepositoryId $repository
    ): RegistrationView {
        return $this->transactions->commitTransactional(function () use (
            $authentication,
            $workspace,
            $repository
        ): RegistrationView {
            $this->fences->holdAuthority($authentication);
            $this->access->require($authentication, $workspace, RegistrationAccess::VIEW);

            return $this->store->get($workspace, $repository) ?? throw new ResourceNotFound();
        });
    }
}
