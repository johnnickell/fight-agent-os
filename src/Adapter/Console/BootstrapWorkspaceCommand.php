<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Application\Workspace\BootstrapWorkspace;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class BootstrapWorkspaceCommand
 *
 * Exposes only explicit trusted installation bootstrap with safe uncertain-outcome reporting
 */
#[AsCommand(name: 'workspace:bootstrap', description: 'Explicitly bootstrap the initial durable Workspace')]
final class BootstrapWorkspaceCommand extends Command
{
    /**
     * Constructs BootstrapWorkspaceCommand
     */
    public function __construct(private readonly BootstrapWorkspace $bootstrap)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->addOption('internal', null, InputOption::VALUE_NONE, 'Acknowledge trusted local operation');
        $this->addOption('confirm-bootstrap', null, InputOption::VALUE_NONE, 'Confirm initial Workspace creation');
        $this->addOption('operator', null, InputOption::VALUE_REQUIRED, 'Trusted operator attribution UUID');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        try {
            $seen = [];
            if ($input instanceof ArgvInput) {
                foreach ($input->getRawTokens() as $token) {
                    $option = explode('=', $token, 2)[0];
                    if (in_array($option, ['--internal', '--confirm-bootstrap', '--operator'], true)) {
                        if (isset($seen[$option])) {
                            throw new \InvalidArgumentException();
                        }
                        $seen[$option] = true;
                    }
                }
            }
            $operator = $input->getOption('operator');
            if (
                !$input->getOption('internal') || !$input->getOption('confirm-bootstrap') || !is_string($operator)
                || !preg_match(
                    '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
                    $operator
                )
            ) {
                throw new \InvalidArgumentException();
            }
            $actor = UserId::fromString($operator);
        } catch (Throwable) {
            $error->writeln('Required: --internal --confirm-bootstrap --operator=UUID', OutputInterface::OUTPUT_RAW);

            return self::INVALID;
        }
        try {
            $workspace = $this->bootstrap->bootstrap($actor);
            $output->writeln(json_encode([
                'workspace_id' => $workspace->id->toString(),
                'created_at'   => $workspace->createdAt->format('Y-m-d\TH:i:s.uP')
            ], JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (Throwable) {
            $error->writeln(
                'Workspace bootstrap outcome is uncertain; retry the same explicit bootstrap for the original result.',
                OutputInterface::OUTPUT_RAW
            );

            return self::FAILURE;
        }
    }
}
