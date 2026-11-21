<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Application\Authorization\IncompleteMembership;
use App\Domain\Authorization\Command\AddUserRole;
use App\Domain\Authorization\MembershipProgress;
use App\Domain\Authorization\OperatorMembershipEvidence;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class AddUserRoleCommand
 *
 * Translates trusted existing-role correction without exposing private failures
 */
#[AsCommand(name: 'user:role:add', description: 'Add an existing non-Super-Admin role from a trusted console')]
final class AddUserRoleCommand extends Command
{
    public const string USAGE = 'Usage: ./bin/console user:role:add --internal --operator=UUID --user=UUID --role=UUID';
    public const string FAILURE_MESSAGE = 'Role addition failed or is uncertain; inspect trusted persisted state.';

    /**
     * Constructs AddUserRoleCommand
     */
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly MembershipProgress $membershipProgress
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->addOption('internal', null, InputOption::VALUE_NONE, 'Acknowledge trusted local operation');
        $this->addOption('operator', null, InputOption::VALUE_REQUIRED, 'Enrolled operator UUID');
        $this->addOption('user', null, InputOption::VALUE_REQUIRED, 'Existing target user UUID');
        $this->addOption('role', null, InputOption::VALUE_REQUIRED, 'Existing eligible role UUID');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        try {
            if (!$input->getOption('internal') || $this->hasRepeatedOptions($input)) {
                throw new \InvalidArgumentException('Invalid role addition options.');
            }
            $operator = TrustedOperatorInput::operator((string) $input->getOption('operator'), 'console');
            $userId = UserId::fromString(TrustedOperatorInput::uuid((string) $input->getOption('user')));
            $roleId = RoleId::fromString(TrustedOperatorInput::uuid((string) $input->getOption('role')));
        } catch (Throwable) {
            $error->writeln(self::USAGE, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);

            return self::INVALID;
        }
        try {
            $invocationId = Uuid::comb();
            $this->commandBus->execute(new AddUserRole($operator, $userId, $roleId, $invocationId));
            $state = $this->membershipProgress->inspect(
                OperatorMembershipEvidence::membershipConfirmed($operator, $userId, $roleId, $invocationId)
            );
            if (!$state->isComplete()) {
                throw new IncompleteMembership($state);
            }
            $output->writeln(json_encode([
                'user_id' => $userId->toString(),
                'role_id' => $roleId->toString(),
                'status'  => 'role_present',
                'audit'   => 'confirmed'
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (IncompleteMembership $failure) {
            ConsoleUserOutput::failure(
                $error,
                $failure->state,
                $operator->operatorId->toString(),
                $roleId->toString(),
                self::FAILURE_MESSAGE,
                false
            );

            return self::FAILURE;
        } catch (Throwable) {
            $error->writeln(self::FAILURE_MESSAGE, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);

            return self::FAILURE;
        }
    }

    /**
     * Rejects ambiguous repeated authority and identity selectors
     */
    private function hasRepeatedOptions(InputInterface $input): bool
    {
        if (!$input instanceof ArgvInput) {
            return false;
        }
        $seen = [];
        foreach ($input->getRawTokens() as $token) {
            $option = explode('=', $token, 2)[0];
            if (!in_array($option, ['--internal', '--operator', '--user', '--role'], true)) {
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
