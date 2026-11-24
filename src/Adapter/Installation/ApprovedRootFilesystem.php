<?php

declare(strict_types=1);

namespace App\Adapter\Installation;

/**
 * Interface ApprovedRootFilesystem
 *
 * Observes bounded files and directory access as the current process
 */
interface ApprovedRootFilesystem
{
    /**
     * Checks whether the configuration directory exists
     */
    public function hasConfigurationDirectory(string $directory): bool;

    /**
     * Reads at most the requested number of bytes
     *
     * @param string          $path
     * @param non-negative-int $length
     */
    public function read(string $path, int $length): string|false;

    /**
     * Checks access to the directory itself without opening children
     *
     * @return array{readable: bool, writable: bool}
     */
    public function permissions(string $directory, bool $writeGranted): array;
}
