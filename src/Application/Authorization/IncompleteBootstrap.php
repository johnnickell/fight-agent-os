<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Bootstrap\BootstrapState;
use RuntimeException;

/**
 * Class IncompleteBootstrap
 *
 * Carries safe admission and retained-state facts without private failure details
 */
final class IncompleteBootstrap extends RuntimeException
{
    /**
     * Constructs IncompleteBootstrap
     */
    public function __construct(public readonly BootstrapState $state)
    {
        parent::__construct('Bootstrap did not establish all required facts.');
    }
}
