<?php

declare(strict_types=1);

namespace App\Adapter\Installation;

/**
 * Class ApprovedRootView
 *
 * Describes one configured root without exposing host paths or contents
 */
final readonly class ApprovedRootView
{
    /**
     * Constructs ApprovedRootView
     */
    public function __construct(
        public string $locator,
        public bool $configured,
        public bool $mounted,
        public bool $readable,
        public bool $writeGranted,
        public bool $writable,
        public ?string $reason
    ) {
    }
}
