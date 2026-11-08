<?php

declare(strict_types=1);

namespace App\Application\Workspace\Project\CommandHandler;

use App\Application\Workspace\Project\ProjectRegistrationAudit;
use App\Domain\Workspace\Project\Command\RegisterProject;
use App\Domain\Workspace\Project\Event\ProjectRegistered;
use App\Domain\Workspace\Project\Project;
use App\Domain\Workspace\Project\ProjectRepository;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class RegisterProjectHandler
 *
 * Coordinates atomic state, claims and required audit, then publishes the committed fact
 */
final readonly class RegisterProjectHandler implements CommandHandler
{
    /**
     * Constructs RegisterProjectHandler
     */
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectRegistrationAudit $projectRegistrationAudit,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private Clock $clock,
        private EventDispatcher $eventDispatcher
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RegisterProject::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RegisterProject) {
            throw new InvalidArgumentException('Unexpected Project command.');
        }
        $this->transactionalUnitOfWork->commitTransactional(function () use ($command): void {
            $project = Project::register(
                $command->projectId,
                $command->workspaceId,
                $command->metadata,
                $command->sources,
                $command->actorId,
                $this->clock->now()
            );
            $this->projectRepository->add($project);
            $this->projectRegistrationAudit->record($project);
        });
        // Failure here is post-commit uncertainty, never proof that creation rolled back.
        $this->eventDispatcher->trigger(new ProjectRegistered($command->workspaceId, $command->projectId));
    }
}
