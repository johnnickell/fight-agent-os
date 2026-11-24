<?php

declare(strict_types=1);

namespace App\Adapter\Installation;

/**
 * Class NativeApprovedRootFilesystem
 *
 * Observes the current process filesystem without modifying it
 */
final readonly class NativeApprovedRootFilesystem implements ApprovedRootFilesystem
{
    /**
     * @inheritDoc
     */
    public function hasConfigurationDirectory(string $directory): bool
    {
        clearstatcache(true);

        return is_dir($directory);
    }

    /**
     * @inheritDoc
     */
    public function read(string $path, int $length): string|false
    {
        return @file_get_contents($path, length: $length);
    }

    /**
     * @inheritDoc
     */
    public function permissions(string $directory, bool $writeGranted): array
    {
        $searchable = is_dir($directory) && is_executable($directory);

        return [
            'readable' => $searchable && is_readable($directory),
            'writable' => $searchable && $writeGranted && is_writable($directory)
        ];
    }
}
