<?php

declare(strict_types=1);

namespace Tooling\Build;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Class VerificationWorkspace
 *
 * Lock and source identity shared by verification commands
 */
final class VerificationWorkspace
{
    /**
     * Acquires exclusive access to the shared gate reports
     *
     * @return resource
     */
    public static function lock(string $root)
    {
        $directory = $root.'/.runs/build';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create the ignored build report directory.');
        }
        // Host CI consumes only top-level JSON/log evidence, never the private tool HOME.
        if (!chmod($directory, 0755)) {
            throw new RuntimeException('Cannot make the build report directory traversable by its host consumer.');
        }
        $lock = fopen($directory.'/gate.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another build or PHP pass is running; wait for it to finish.');
        }

        return $lock;
    }

    /**
     * Reads index, status and actual tracked/unignored bytes including dirty starting work
     *
     * @return array<string, string|array<string, array<string, string|int|bool>>>
     */
    public static function snapshot(string $root): array
    {
        $git = static function (array $arguments) use ($root): string {
            $process = new Process(
                ['git', '-c', 'safe.directory='.$root, ...$arguments],
                $root,
                ['GIT_OPTIONAL_LOCKS' => '0']
            );
            $process->mustRun();

            return $process->getOutput();
        };
        clearstatcache();
        $files = [];
        $paths = explode("\0", $git(['ls-files', '-z', '--cached', '--others', '--exclude-standard']));
        foreach (array_unique($paths) as $path) {
            if ($path === '') {
                continue;
            }
            $full = $root.'/'.$path;
            if (is_link($full)) {
                $files[$path] = ['link' => readlink($full)];
            } elseif (is_file($full)) {
                $digest = hash_file('sha256', $full);
                $mode = fileperms($full);
                if ($digest === false || $mode === false) {
                    throw new RuntimeException('Cannot snapshot source bytes and mode.');
                }
                $files[$path] = ['sha256' => $digest, 'executable' => $mode & 0111];
            } else {
                $files[$path] = ['absent' => true];
            }
        }
        ksort($files);

        return [
            'head'          => trim($git(['rev-parse', 'HEAD'])),
            'branch'        => trim($git(['rev-parse', '--abbrev-ref', 'HEAD'])),
            'index_sha256'  => hash('sha256', $git(['ls-files', '--stage', '-z'])),
            'status_sha256' => hash('sha256', $git(['status', '--porcelain=v1', '-z', '--untracked-files=all'])),
            'files'         => $files
        ];
    }
}
