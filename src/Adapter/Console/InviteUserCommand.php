<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Application\Invitation\ConsoleUserOperation;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
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
 * Class InviteUserCommand
 *
 * Translates trusted console input into the ordinary invitation use case
 */
#[AsCommand(name: 'user:invite', description: 'Invite one ordinary pending user from a trusted console')]
final class InviteUserCommand extends Command
{
    public const string USAGE = 'Usage: ./bin/console user:invite --internal --operator=UUID --email=EMAIL';
    public const FAILURE_MESSAGE = 'Invitation failed or its outcome is uncertain; inspect trusted persisted state.';

    /**
     * Constructs InviteUserCommand
     */
    public function __construct(private readonly ConsoleUserOperation $operation)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->addOption('internal', null, InputOption::VALUE_NONE, 'Acknowledge trusted local operation');
        $this->addOption('operator', null, InputOption::VALUE_REQUIRED, 'Enrolled operator UUID');
        $this->addOption('email', null, InputOption::VALUE_REQUIRED, 'Invitation email');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        try {
            if (!$input->getOption('internal') || $this->hasRepeatedOptions($input)) {
                throw new \InvalidArgumentException('Invalid invitation options.');
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
            $userId = UserId::generate();
            $outcome = $this->operation->run(new InviteOrdinaryUser($invitation, $userId));
            if (!$outcome->success) {
                ConsoleUserOutput::failure(
                    $error,
                    $outcome,
                    $invitation->operatorId->toString(),
                    InstallationManagedPolicy::USER_ROLE_ID,
                    self::FAILURE_MESSAGE
                );

                return self::FAILURE;
            }
            $output->writeln(json_encode([
                'email'   => $invitation->email->toString(),
                'user_id' => $userId->toString(),
                'status'  => 'invited'
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (Throwable) {
            $error->writeln(self::FAILURE_MESSAGE, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);

            return self::FAILURE;
        }
    }

    /**
     * Rejects ambiguous repeated authority and identity options in real CLI input
     */
    private function hasRepeatedOptions(InputInterface $input): bool
    {
        if (!$input instanceof ArgvInput) {
            return false;
        }
        $seen = [];
        foreach ($input->getRawTokens() as $token) {
            $option = explode('=', $token, 2)[0];
            if (!in_array($option, ['--internal', '--operator', '--email'], true)) {
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
