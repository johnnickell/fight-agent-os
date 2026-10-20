<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Domain\Workspace\RegistrationInputRejected;
use App\Domain\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Class ListRepositoryRegistrations
 */
final readonly class ListRepositoryRegistrations
{
    /**
     * Constructs ListRepositoryRegistrations
     */
    public function __construct(
        private RegistrationStore $store,
        private RegistrationFences $fences,
        private RegistrationAccess $access,
        private TransactionalUnitOfWork $transactions
    ) {
    }

    /**
     * Returns a stable bounded page without inventing a multi-workspace membership model
     *
     * @return array{items: list<RegistrationView>, has_more: bool}
     */
    public function list(AuthenticationContext $authentication, WorkspaceId $workspace, int $offset, int $limit): array
    {
        if ($offset < 0 || $offset > 100000 || $limit < 1 || $limit > 100) {
            throw new RegistrationInputRejected();
        }

        return $this->transactions->commitTransactional(function () use (
            $authentication,
            $workspace,
            $offset,
            $limit
        ): array {
            $this->fences->holdAuthority($authentication);
            $this->access->require($authentication, $workspace, RegistrationAccess::VIEW);

            return $this->store->page($workspace, $offset, $limit);
        });
    }
}
