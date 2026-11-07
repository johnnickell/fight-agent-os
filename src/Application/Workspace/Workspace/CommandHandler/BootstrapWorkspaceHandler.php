<?php

declare(strict_types=1);

namespace App\Application\Workspace\Workspace\CommandHandler;

use App\Application\Workspace\Workspace\WorkspaceBootstrapAudit;
use App\Domain\Workspace\Workspace\Command\BootstrapWorkspace;
use App\Domain\Workspace\Workspace\Workspace;
use App\Domain\Workspace\Workspace\WorkspaceRepository;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class BootstrapWorkspaceHandler
 *
 * Coordinates explicit initial Workspace persistence and required atomic audit
 */
final readonly class BootstrapWorkspaceHandler implements CommandHandler
{
    /**
     * Constructs BootstrapWorkspaceHandler
     */
    public function __construct(
        private WorkspaceRepository $workspaceRepository,
        private WorkspaceBootstrapAudit $workspaceBootstrapAudit,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private Clock $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return BootstrapWorkspace::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof BootstrapWorkspace) {
            throw new InvalidArgumentException('Unexpected Workspace command.');
        }
        $this->transactionalUnitOfWork->commitTransactional(function () use ($command): void {
            $workspace = new Workspace($command->workspaceId, $this->clock->now());
            $this->workspaceRepository->add($workspace);
            $this->workspaceBootstrapAudit->record($workspace, $command->operatorId);
        });
    }
}
