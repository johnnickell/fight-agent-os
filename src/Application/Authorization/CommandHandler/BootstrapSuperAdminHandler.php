<?php

declare(strict_types=1);

namespace App\Application\Authorization\CommandHandler;

use App\Application\Authorization\IncompleteBootstrap;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\ManagedPolicyCatalog;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Bootstrap\BootstrapEvidence;
use App\Domain\Bootstrap\BootstrapProgress;
use App\Domain\Bootstrap\BootstrapState;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Fight\AccessControl\Domain\AccessControl\Audit\SystemId;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class BootstrapSuperAdminHandler
 *
 * Coordinates a trusted manual bootstrap with independent package commits and inspected outcomes
 */
final readonly class BootstrapSuperAdminHandler implements CommandHandler
{
    /**
     * Constructs BootstrapSuperAdminHandler
     */
    public function __construct(
        private CommandBus $commands,
        private QueryBus $queries,
        private UserRepository $users,
        private BootstrapProgress $progress,
        private ManagedPolicyCatalog $managedPolicyCatalog,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private MembershipEvidenceRepository $membershipEvidenceRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return BootstrapSuperAdmin::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof BootstrapSuperAdmin) {
            throw new InvalidArgumentException('Unexpected bootstrap command.');
        }
        $roleId = RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID);
        try {
            $hasSuperAdmin = $this->users->hasRoleAssignment($roleId);
        } catch (Throwable) {
            throw new IncompleteBootstrap(new BootstrapState('unknown'));
        }
        if ($hasSuperAdmin) {
            throw new IncompleteBootstrap(new BootstrapState('refused'));
        }
        try {
            $policy = $this->managedPolicyCatalog->load();
            $this->commands->execute(new ReconcileManagedPolicy($policy));
            InstallationManagedPolicy::requireReconciled($this->queries->fetch(
                new PreviewManagedPolicy($policy)
            ), $policy);
            $role = $this->queries->fetch(new GetRoleById($roleId));
            if (!$role instanceof RoleView || !$role->getRoleId()->equals($roleId) || !$role->isManaged()) {
                throw new LogicException('Managed bootstrap authority is unavailable.');
            }
            $systemId = SystemId::generate();
            $this->commands->execute(new InvitePendingUser($systemId, $command->userId, $command->email));
            $this->commands->execute(new AssignRoleToUser($systemId, $command->userId, $roleId));
            $state = $this->progress->inspect($command);
            if ($state->invitation !== 'confirmed' || $state->assignment !== 'confirmed') {
                throw new LogicException('Bootstrap completion is unavailable.');
            }
            $this->transactionalUnitOfWork->commitTransactional(
                fn() => $this->membershipEvidenceRepository->add(BootstrapEvidence::completed($command))
            );

            return;
        } catch (Throwable) {
            // An exception never proves rollback across independently committed package steps.
        }
        try {
            $state = $this->progress->inspect($command);
        } catch (Throwable) {
            $state = new BootstrapState('admitted');
        }
        if (!$state->isComplete()) {
            throw new IncompleteBootstrap($state);
        }
    }
}
