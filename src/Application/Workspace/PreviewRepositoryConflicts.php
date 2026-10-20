<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Domain\Workspace\RegistrationMetadata;
use App\Domain\Workspace\RegistrationSources;
use App\Domain\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Class PreviewRepositoryConflicts
 */
final readonly class PreviewRepositoryConflicts
{
    /**
     * Constructs PreviewRepositoryConflicts
     */
    public function __construct(
        private RegistrationStore $store,
        private RegistrationFences $fences,
        private RegistrationAccess $access,
        private TransactionalUnitOfWork $transactions
    ) {
    }

    /**
     * Returns advisory conflict kinds without claiming, reserving or exposing another registration
     *
     * @return list<string>
     */
    public function preview(
        AuthenticationContext $authentication,
        WorkspaceId $workspace,
        RegistrationMetadata $metadata,
        RegistrationSources $sources
    ): array {
        return $this->transactions->commitTransactional(function () use (
            $authentication,
            $workspace,
            $metadata,
            $sources
        ): array {
            $this->fences->holdAuthority($authentication);
            $this->access->require($authentication, $workspace, RegistrationAccess::VIEW);

            return $this->store->conflicts($workspace, $metadata->slug, $sources);
        });
    }
}
