<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Application\Invitation\ConsoleUserOutcome;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConsoleUserOutput
 *
 * Renders only safe inspected state and bounded corrective guidance
 */
final class ConsoleUserOutput
{
    /**
     * Returns known membership separately from unconfirmed audit and execution outcome
     */
    public static function failure(
        OutputInterface $output,
        ConsoleUserOutcome $outcome,
        string $operatorId,
        string $roleId,
        string $fallback,
        bool $suggestCorrection = true
    ): void {
        $text = $fallback;
        if ($outcome->knownUserId !== null) {
            $correction = './bin/console user:role:add --internal --operator='.$operatorId;
            $correction .= ' --user='.$outcome->knownUserId->toString().' --role='.$roleId;
            $text = json_encode([
                'status'     => 'partial_or_uncertain',
                'user_id'    => $outcome->knownUserId->toString(),
                'role_id'    => $roleId,
                'assignment' => $outcome->assignment,
                'audit'      => $outcome->audit,
                ...($suggestCorrection ? [
                    'correction' => $correction
                ] : [])
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
        $output->writeln($text, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }
}
