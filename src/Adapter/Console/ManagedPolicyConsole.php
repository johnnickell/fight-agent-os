<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use Closure;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Throwable;

/**
 * Class ManagedPolicyConsole
 *
 * Presents explicit trusted-console policy operations without private diagnostics
 */
final readonly class ManagedPolicyConsole
{
    public const string FAILURE = "Managed policy operation failed; no private diagnostics are emitted.\n";
    public const string USAGE = "Usage: ./bin/managed-policy preview|apply --internal\n";

    /**
     * Constructs ManagedPolicyConsole
     */
    public function __construct(
        private ManagedPolicy $policy,
        private QueryBus $queries,
        private SynchronousCommandBus $commands
    ) {
    }

    /**
     * Runs a non-interactive operation and emits only the safe result or fixed failure
     *
     * @phpstan-param list<string> $arguments
     * @phpstan-param Closure(string): void $output
     * @phpstan-param Closure(string): void $error
     */
    public function run(array $arguments, Closure $output, Closure $error): int
    {
        if ($arguments !== ['preview', '--internal'] && $arguments !== ['apply', '--internal']) {
            $error(self::USAGE);

            return 2;
        }

        try {
            $result = [
                'operation'     => $arguments[0],
                'policy_sha256' => hash('sha256', json_encode($this->policy->toArray(), JSON_THROW_ON_ERROR))
            ];
            if ($arguments[0] === 'preview') {
                $result['plan'] = $this->queries->fetch(new PreviewManagedPolicy($this->policy))->toArray();
            } else {
                $this->commands->execute(new ReconcileManagedPolicy($this->policy));
                $result['status'] = 'applied';
            }
            $output(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");

            return 0;
        } catch (Throwable) {
            $error(self::FAILURE);

            return 1;
        }
    }
}
