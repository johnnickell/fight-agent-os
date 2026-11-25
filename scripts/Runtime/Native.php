<?php

declare(strict_types=1);

namespace Fight\Runtime;

use RuntimeException;

/**
 * Class Native
 *
 * Runs local commands and handles private files without application dependencies
 */
final class Native
{
    /**
     * Enforces a native operation prerequisite
     *
     * @phpstan-assert true $condition
     */
    public static function require(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    /**
     * Runs a native command without shell expansion or disclosing captured diagnostics
     *
     * @phpstan-param list<string> $args
     * @phpstan-param array<string,string>|null $env
     * @return array{int, string, string}
     */
    public static function process(
        array $args,
        string $cwd,
        ?array $env = null,
        bool $capture = false,
        ?string $input = null,
        ?int $timeout = 1800
    ): array {
        $descriptors = [STDIN, STDOUT, STDERR];
        if ($input !== null) {
            $descriptors[0] = tmpfile();
            self::require(is_resource($descriptors[0]) && fwrite(
                $descriptors[0],
                $input
            ) === strlen($input), 'Cannot stage command input');
            rewind($descriptors[0]);
        }
        if ($capture) {
            // Files avoid pipe backpressure when a child writes both streams.
            $descriptors[1] = tmpfile();
            $descriptors[2] = tmpfile();
            self::require(
                is_resource($descriptors[1]) && is_resource($descriptors[2]),
                'Cannot capture native command output'
            );
        }
        try {
            $process = @proc_open($args, $descriptors, $pipes, $cwd, $env);
            self::require(is_resource($process), 'Cannot start native command: '.$args[0]);
            $deadline = $timeout === null ? null : hrtime(true) + $timeout * 1_000_000_000;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if ($deadline !== null && hrtime(true) >= $deadline) {
                    proc_terminate($process, 9);
                    proc_close($process);
                    throw new RuntimeException('Native command timed out: '.implode(' ', array_slice($args, 0, 3)));
                }
                usleep(10_000);
            } while (true);
            $exit = proc_close($process);
            if ($status['exitcode'] >= 0) {
                $exit = $status['exitcode'];
            } elseif ($status['signaled']) {
                $exit = 128 + $status['termsig'];
            }
            if (!$capture) {
                return [$exit, '', ''];
            }
            rewind($descriptors[1]);
            rewind($descriptors[2]);

            $output = stream_get_contents($descriptors[1]);
            $error = stream_get_contents($descriptors[2]);
            self::require(is_string($output) && is_string($error), 'Cannot read captured command output');

            return [$exit, $output, $error];
        } finally {
            foreach ($descriptors as $index => $stream) {
                if ($stream !== [STDIN, STDOUT, STDERR][$index]) {
                    fclose($stream);
                }
            }
        }
    }

    /**
     * Runs a required command and returns its captured output
     *
     * @phpstan-param list<string> $args
     * @phpstan-param array<string,string>|null $env
     */
    public static function run(
        array $args,
        string $cwd,
        ?array $env = null,
        bool $capture = false,
        ?string $input = null,
        int $timeout = 1800
    ): string {
        [$exit, $stdout] = self::process($args, $cwd, $env, $capture, $input, $timeout);
        self::require($exit === 0, 'Command failed (exit '.$exit.'): '.implode(' ', array_slice($args, 0, 3)));

        return trim($stdout);
    }

    /**
     * Reads the output of a required command
     *
     * @phpstan-param list<string> $args
     */
    public static function text(array $args, string $cwd): string
    {
        return self::run($args, $cwd, capture: true);
    }

    /**
     * Decodes JSON from a required command
     *
     * @phpstan-param list<string> $args
     * @phpstan-param array<string,string>|null $env
     * @return array<mixed>
     */
    public static function query(array $args, string $cwd, ?array $env = null): array
    {
        $result = json_decode(self::run($args, $cwd, $env, capture: true), true, flags: JSON_THROW_ON_ERROR);
        self::require(is_array($result), 'Expected structured output from '.$args[0]);

        return $result;
    }

    /**
     * Parses captured output without producing a row for empty output
     *
     * @return list<string>
     */
    public static function lines(string $text): array
    {
        return $text === '' ? [] : explode("\n", $text);
    }

    /**
     * Encodes JSON with exceptions instead of partial output
     */
    public static function json(mixed $value, int $flags = 0): string
    {
        return json_encode($value, $flags | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Reads a required file without printing its contents on failure
     */
    public static function read(string $path): string
    {
        $content = @file_get_contents($path);
        self::require(is_string($content), 'Cannot read '.$path);

        return $content;
    }

    /**
     * Checks for a filesystem entry including a dangling link
     */
    public static function exists(string $path): bool
    {
        clearstatcache(true, $path);

        return file_exists($path) || is_link($path);
    }

    /**
     * Creates a private directory and any missing parents
     */
    public static function mkdir(string $path): void
    {
        self::require(is_dir($path) || @mkdir($path, 0700, true) || is_dir($path), 'Cannot create directory '.$path);
    }

    /**
     * Writes configuration through an exclusive private temporary file
     */
    public static function writePrivate(string $path, string $content): void
    {
        self::require(!is_link($path), 'Refusing aliased runtime configuration: '.$path);
        self::mkdir(dirname($path));
        $tmp = $path.'.tmp';
        $stream = @fopen($tmp, 'xb');
        self::require(is_resource($stream), 'Cannot exclusively create runtime configuration: '.$tmp);
        try {
            self::require(chmod(
                $tmp,
                0600
            ) && fwrite(
                $stream,
                $content
            ) === strlen($content) && fflush($stream), 'Cannot write runtime configuration: '.$path);
            self::require(!is_link($path) && rename($tmp, $path), 'Cannot install runtime configuration: '.$path);
        } finally {
            fclose($stream);
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * Checks path containment with component boundaries
     */
    public static function beneath(string $path, string $parent): bool
    {
        return $path === $parent || str_starts_with($path, rtrim($parent, '/').'/');
    }

    /**
     * Resolves a path through its deepest existing ancestor
     */
    public static function canonical(string $path): string
    {
        clearstatcache(true, $path);
        $existing = realpath($path);
        if ($existing !== false) {
            return $existing;
        }
        $parent = dirname($path);
        if ($parent === $path || $parent === '.') {
            return $path;
        }
        $name = basename($path);
        $parent = self::canonical($parent);

        return match ($name) {
            '.' => $parent,
            '..' => dirname($parent),
            default => rtrim($parent, '/').'/'.$name,
        };
    }

    /**
     * Removes an owned directory without following symlinks
     */
    public static function removeDirectory(string $path): void
    {
        self::require(self::canonical($path) === $path && !is_link($path), 'Runtime state alias refused');
        foreach (new \FilesystemIterator($path) as $entry) {
            self::require($entry instanceof \SplFileInfo, 'Cannot inspect selected runtime output');
            if ($entry->isDir() && !$entry->isLink()) {
                self::removeDirectory($entry->getPathname());
            } else {
                self::require(unlink($entry->getPathname()), 'Cannot remove owned runtime output');
            }
        }
        self::require(rmdir($path), 'Cannot remove owned runtime directory');
    }

    /**
     * Reads Git registrations without guessing checkout or branch paths
     *
     * @return list<array{path: string, branch: string}>
     */
    public static function registrations(string $root): array
    {
        $result = [];
        foreach (explode("\0\0", self::text(['git', 'worktree', 'list', '--porcelain', '-z'], $root)) as $block) {
            $entry = [];
            foreach (explode("\0", $block) as $line) {
                [$key, $value] = array_pad(explode(' ', $line, 2), 2, '');
                $entry[$key] = $value;
            }
            if (isset($entry['worktree'])) {
                $branch = $entry['branch'] ?? '';
                $result[] = [
                    'path'   => self::canonical($entry['worktree']),
                    'branch' => str_starts_with($branch, 'refs/heads/') ? substr($branch, 11) : $branch
                ];
            }
        }
        self::require($result !== [], 'No registered Git worktrees');

        return $result;
    }

    /**
     * Lists native resources with the exact Compose project label
     *
     * @phpstan-param 'containers'|'networks'|'volumes' $kind
     * @return list<string>
     */
    public static function ids(string $root, string $kind, string $project): array
    {
        $args = match ($kind) {
            'containers' => ['ps', '-aq'],
            'networks' => ['network', 'ls', '-q'],
            'volumes' => ['volume', 'ls', '-q'],
        };

        return self::lines(self::text([
            'docker',
            ...$args,
            '--filter',
            'label=com.docker.compose.project='.$project
        ], $root));
    }
}
