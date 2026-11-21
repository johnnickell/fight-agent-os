<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Adapter\CredentialDelivery\CredentialExpiryRunner;
use App\Adapter\CredentialDelivery\CredentialRecoveryRunner;
use Closure;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryAbsent;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindCredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDeliveryId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class CredentialDeliveryCommand
 *
 * Runs trusted internal credential maintenance and presents only safe results
 */
final class CredentialDeliveryCommand extends Command
{
    private const array BOUNDS = [
        'page-size'        => 1000,
        'capacity'         => 1000,
        'lease-seconds'    => 300,
        'expiry-page-size' => 100,
        'expiry-pages'     => 10
    ];

    /**
     * Constructs CredentialDeliveryCommand with a lazy delivery-only configuration check
     *
     * @phpstan-param Closure(): void $validateDeliveryConfiguration
     */
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly CredentialExpiryRunner $expiryRunner,
        private readonly CredentialRecoveryRunner $recoveryRunner,
        private readonly Closure $validateDeliveryConfiguration
    ) {
        parent::__construct('credential:delivery');
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setDescription('Run, expire, or inspect internal credential delivery work')
            ->addArgument('operation', InputArgument::REQUIRED, 'run, expire, or status')
            ->addArgument('purpose', InputArgument::OPTIONAL, 'Status purpose')
            ->addArgument('delivery-id', InputArgument::OPTIONAL, 'Status delivery ID')
            ->addOption('internal', null, InputOption::VALUE_NONE, 'Acknowledge trusted internal invocation');
        foreach (self::BOUNDS as $name => $maximum) {
            $range = $name === 'lease-seconds' ? 'fixed at 300' : "1–$maximum";
            $this->addOption($name, null, InputOption::VALUE_REQUIRED, "Operational bound ($range)");
        }
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $operation = $input->getArgument('operation');
            if (!in_array($operation, ['run', 'expire', 'status'], true) || !$input->getOption('internal')) {
                throw new InvalidArgumentException('Explicit internal operation is required.');
            }
            $options = $this->readBounds($input, $operation);
            $purpose = $input->getArgument('purpose');
            $deliveryId = $input->getArgument('delivery-id');
            $statusQuery = null;
            if ($operation === 'status' && is_string($purpose) && is_string($deliveryId)) {
                match ($purpose) {
                    'activation' => ActivationDeliveryId::fromString($deliveryId),
                    'password_reset' => PasswordResetDeliveryId::fromString($deliveryId),
                    'email_change' => EmailChangeDeliveryId::fromString($deliveryId),
                    default => throw new InvalidArgumentException('Unsupported status purpose.'),
                };
                $statusQuery = new FindCredentialDeliveryStatus($purpose, $deliveryId);
            } elseif ($operation === 'status' || $purpose !== null || $deliveryId !== null) {
                throw new InvalidArgumentException('Invalid operation arguments.');
            }
        } catch (Throwable) {
            $this->error($output, 'Invalid arguments; use ./bin/console help credential:delivery.');

            return self::INVALID;
        }

        try {
            if ($statusQuery !== null) {
                /** @var CredentialDeliveryStatusView|CredentialDeliveryAbsent $status */
                $status = $this->queryBus->fetch($statusQuery);
                if ($status instanceof CredentialDeliveryAbsent) {
                    $result = ['found' => false, 'delivery' => null];
                    $exit = 3;
                } else {
                    $result = ['found' => true, 'delivery' => $status->toArray()];
                    $exit = self::SUCCESS;
                }
            } else {
                if ($operation === 'run') {
                    ($this->validateDeliveryConfiguration)();
                }
                $expiry = $this->expiryRunner->run($options['expiry-page-size'] ?? 50, $options['expiry-pages'] ?? 10);
                $result = ['expiry' => $expiry];
                $exit = $expiry['stalled'] ? self::FAILURE : self::SUCCESS;
                if ($operation === 'run' && !$expiry['stalled']) {
                    $delivery = $this->recoveryRunner->run($options['page-size'] ?? 100, $options['capacity'] ?? 100);
                    $result['delivery'] = $delivery;
                    $exit = $delivery['unsupported'] > 0 ? self::FAILURE : self::SUCCESS;
                }
            }
            $output->writeln(
                json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                OutputInterface::OUTPUT_RAW
            );

            return $exit;
        } catch (Throwable) {
            $this->error(
                $output,
                'Credential delivery failed or its outcome is uncertain; inspect trusted persisted state.'
            );

            return self::FAILURE;
        }
    }

    /**
     * Rejects duplicate CLI options and validates every admitted bound before any work
     *
     * @return array<string, int>
     */
    private function readBounds(InputInterface $input, string $operation): array
    {
        $seen = [];
        if ($input instanceof ArgvInput) {
            foreach ($input->getRawTokens(true) as $token) {
                $name = explode('=', $token, 2)[0];
                if (!str_starts_with($name, '--')) {
                    continue;
                }
                $name = substr($name, 2);
                if ($name !== 'internal' && !isset(self::BOUNDS[$name])) {
                    continue;
                }
                if (isset($seen[$name])) {
                    throw new InvalidArgumentException('Repeated operational option.');
                }
                $seen[$name] = true;
            }
        }

        $bounds = [];
        foreach (self::BOUNDS as $name => $maximum) {
            $value = $input->getOption($name);
            if ($value === null) {
                continue;
            }
            if (
                $operation === 'status' || ($operation === 'expire' && !str_starts_with($name, 'expiry-'))
                || !is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1
            ) {
                throw new InvalidArgumentException('Invalid operational option.');
            }
            $number = filter_var($value, FILTER_VALIDATE_INT);
            if (
                $number === false || $number < 1 || $number > $maximum
                || ($name === 'lease-seconds' && $number !== 300)
            ) {
                throw new InvalidArgumentException('Invalid operational bound.');
            }
            $bounds[$name] = $number;
        }

        return $bounds;
    }

    /**
     * Writes only a caller-independent message to the error stream
     */
    private function error(OutputInterface $output, string $message): void
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->writeln($message, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }
}
