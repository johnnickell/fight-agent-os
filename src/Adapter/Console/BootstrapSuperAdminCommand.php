<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Application\Bootstrap\BootstrapOperation;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use App\Domain\Invitation\ConsoleInvitationInput;
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
 * Class BootstrapSuperAdminCommand
 *
 * Translates explicit trusted-console confirmation without exposing private diagnostics
 */
#[AsCommand(name: 'user:bootstrap-super-admin', description: 'Permanently claim and bootstrap the first Super Admin')]
final class BootstrapSuperAdminCommand extends Command
{
    public const string USAGE = 'Required: --internal --confirm-bootstrap --operator=UUID --email=EMAIL';

    /**
     * Constructs BootstrapSuperAdminCommand
     */
    public function __construct(private readonly BootstrapOperation $operation)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->addOption('internal', null, InputOption::VALUE_NONE, 'Acknowledge trusted local operation');
        $this->addOption(
            'confirm-bootstrap',
            null,
            InputOption::VALUE_NONE,
            'Confirm permanent claim even if later steps fail'
        );
        $this->addOption('operator', null, InputOption::VALUE_REQUIRED, 'Enrolled operator UUID');
        $this->addOption('email', null, InputOption::VALUE_REQUIRED, 'Pending Super Admin email');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        try {
            if (!$input->getOption('internal') || !$input->getOption('confirm-bootstrap') || $this->repeated($input)) {
                throw new \InvalidArgumentException('Explicit bootstrap confirmation is required.');
            }
            $invitation = new ConsoleInvitationInput(
                (string) $input->getOption('operator'),
                (string) $input->getOption('email')
            );
        } catch (Throwable) {
            $error->writeln(self::USAGE, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);

            return self::INVALID;
        }
        try {
            $command = new BootstrapSuperAdmin($invitation, UserId::generate());
            $state = $this->operation->run($command);
            $result = [
                'status'     => $state->isComplete() ? 'bootstrap_complete' : 'partial_or_uncertain',
                'claim'      => $state->claim,
                'invitation' => $state->invitation,
                'assignment' => $state->assignment,
                'audit'      => $state->audit
            ];
            if ($state->claim === 'claimed') {
                $result['user_id'] = $command->userId->toString();
            }
            if (!$state->isComplete()) {
                $result['recovery'] = 'Do not retry or reinvite; interrupted-claim recovery is separately planned.';
                if ($state->claim === 'absent') {
                    $result['recovery'] = 'No claim observed; verify pre-claim failure before a fresh invocation.';
                }
                $error->writeln(
                    json_encode($result, JSON_THROW_ON_ERROR),
                    OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET
                );

                return self::FAILURE;
            }
            $output->writeln(json_encode($result, JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (Throwable) {
            $error->writeln(
                'Bootstrap outcome is uncertain; inspect trusted state. Do not retry or reinvite.',
                OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET
            );

            return self::FAILURE;
        }
    }

    /**
     * Rejects ambiguous repeated confirmation and identity options
     */
    private function repeated(InputInterface $input): bool
    {
        if (!$input instanceof ArgvInput) {
            return false;
        }
        $seen = [];
        foreach ($input->getRawTokens() as $token) {
            $option = explode('=', $token, 2)[0];
            if (!in_array($option, ['--internal', '--confirm-bootstrap', '--operator', '--email'], true)) {
                continue;
            }
            if (isset($seen[$option])) {
                return true;
            }
            $seen[$option] = true;
        }

        return false;
    }
}
