<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class SafeConsoleApplication
 *
 * Prevents Symfony parsing and verbose exception rendering from exposing private input
 */
final class SafeConsoleApplication extends Application
{
    /**
     * @inheritDoc
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        // Unknown-command suggestions can echo input before renderThrowable is reached.
        $input->setInteractive(false);
        try {
            return parent::doRun($input, $output);
        } catch (ExceptionInterface) {
            // Do not retain the original input-bearing exception or its chain.
            throw new InvalidArgumentException('Invalid console input.', 2);
        }
    }

    /**
     * @inheritDoc
     */
    public function renderThrowable(Throwable $e, OutputInterface $output): void
    {
        $message = 'Console failed or its outcome is uncertain; inspect trusted persisted state.';
        if ($e instanceof ExceptionInterface) {
            $message = 'Invalid console input; use ./bin/console help user:invite.';
        }
        $output->writeln($message, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }
}
