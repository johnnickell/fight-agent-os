<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Application\Sessions\SessionPresentation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class PruneSessionPresentationCommand
 *
 * Exposes explicit bounded presentation cleanup without modifying session authority
 */
#[AsCommand(name: 'session-presentation:prune', description: 'Delete expired session presentation in a bounded batch')]
final class PruneSessionPresentationCommand extends Command
{
    /**
     * Constructs PruneSessionPresentationCommand
     */
    public function __construct(private readonly SessionPresentation $presentation)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->addOption('internal', null, InputOption::VALUE_NONE, 'Acknowledge trusted local operation');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Batch size, 1 through 1000', '100');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption('limit');
        if (
            !$input->getOption('internal') || !is_string($limit)
            || !preg_match('/\A[1-9][0-9]{0,3}\z/D', $limit) || (int) $limit > 1000
        ) {
            $output->writeln('Required: --internal --limit=1..1000', OutputInterface::OUTPUT_RAW);

            return self::INVALID;
        }
        try {
            $count = $this->presentation->prune((int) $limit);
            $output->writeln('Removed presentation rows: '.$count, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (Throwable) {
            $output->writeln('Presentation cleanup unavailable; retry explicitly.', OutputInterface::OUTPUT_RAW);

            return self::FAILURE;
        }
    }
}
