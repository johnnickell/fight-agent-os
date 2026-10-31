<?php

declare(strict_types=1);

namespace Tooling\Build;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Class BuildCache
 */
final class BuildCache
{
    private const array EXCLUSIONS = [
        '.git', '.runs', 'var', '.idea', '.DS_Store', 'client/dist', 'public/build',
        'client/node_modules/.cache', 'client/node_modules/.vite-temp'
    ];

    /**
     * Constructs BuildCache
     *
     * Initializes the checkout-local receipt cache
     */
    public function __construct(private readonly string $root)
    {
    }

    /**
     * Calculates a fingerprint of installed files and effective runtime without retaining secret values
     */
    public function fingerprint(): string
    {
        $environment = getenv();
        // Shell bookkeeping does not reach the tools as an intentional input.
        unset($environment['_'], $environment['SHLVL'], $environment['OLDPWD']);
        ksort($environment);
        $extensions = [];
        foreach (get_loaded_extensions() as $extension) {
            $extensions[$extension] = phpversion($extension);
        }
        ksort($extensions);
        $runtime = [
            'schema'      => 1,
            'root'        => $this->root,
            'environment' => $environment,
            'os'          => php_uname(),
            'php'         => [PHP_VERSION, PHP_BINARY, PHP_INT_SIZE, $extensions, ini_get_all()],
            'project'     => $this->tree($this->root, true)
        ];
        $paths = [PHP_BINARY, PHP_EXTENSION_DIR];
        foreach (['var/openapi', 'var/validations', 'var/client/build'] as $path) {
            $runtime['generated'][$path] = $this->tree($this->root.'/'.$path);
        }
        foreach (array_filter(explode(',', (string) php_ini_scanned_files())) as $ini) {
            $paths[] = trim($ini);
        }
        if (php_ini_loaded_file() !== false) {
            $paths[] = php_ini_loaded_file();
        }
        foreach (['/etc/os-release', '/var/lib/dpkg/status', '/etc/ld.so.cache'] as $path) {
            if (is_file($path)) {
                $paths[] = $path;
            }
        }
        $finder = new ExecutableFinder();
        foreach (['php', 'composer', 'git', 'python3'] as $tool) {
            $path = $finder->find($tool);
            if ($path === null) {
                throw new RuntimeException('Build runtime tool unavailable.');
            }
            $paths[] = $path;
            $runtime[$tool] = $this->command([$path, '--version']);
        }
        $frontendEnvironment = $this->frontendEnvironment($this->root.'/.runs/cache/client/fingerprint-home');
        $toolPath = $frontendEnvironment['PATH'];
        foreach (['node', 'npm'] as $tool) {
            $path = null;
            foreach (explode(PATH_SEPARATOR, $toolPath) as $directory) {
                $candidate = $directory.'/'.$tool;
                if (is_file($candidate) && is_executable($candidate)) {
                    $path = $candidate;
                    break;
                }
            }
            if ($path === null) {
                throw new RuntimeException('Build frontend tool unavailable.');
            }
            $paths[] = $path;
            $runtime[$tool] = $this->command([$path, '--version'], $frontendEnvironment);
            if ($tool === 'npm') {
                $resolved = realpath($path);
                if ($resolved === false) {
                    throw new RuntimeException('Cannot resolve npm installation.');
                }
                $paths[] = dirname($resolved, 2);
            }
        }
        // Include both Chromium and its headless shell, not just a reported version.
        $browserPath = getenv('PLAYWRIGHT_BROWSERS_PATH') ?: '/ms-playwright';
        if (!is_dir($browserPath)) {
            throw new RuntimeException('Build browser installation unavailable.');
        }
        $paths[] = $browserPath;
        sort($paths);
        foreach (array_unique($paths) as $path) {
            $runtime['files'][$path] = $this->tree($path);
        }
        $runtime['database'] = $this->command(
            [PHP_BINARY, 'scripts/guarded_test_database.php', 'fingerprint'],
            ['APP_ENV' => 'test']
        );

        return hash('sha256', json_encode($runtime, JSON_THROW_ON_ERROR));
    }

    /**
     * Creates the same credential-free frontend environment for probes and phases
     *
     * @phpstan-return array<string, string|false>&array{PATH: string}
     */
    public function frontendEnvironment(string $home): array
    {
        return array_replace(array_fill_keys(array_keys(getenv()), false), [
            'PATH'                        => getenv('FIGHT_BUILD_TOOL_PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'HOME'                        => $home,
            'CI'                          => '1',
            'TZ'                          => 'UTC',
            'STORYBOOK_DISABLE_TELEMETRY' => '1',
            'PLAYWRIGHT_BROWSERS_PATH'    => getenv('PLAYWRIGHT_BROWSERS_PATH') ?: '/ms-playwright',
            'npm_config_cache'            => $this->root.'/.runs/cache/client/npm',
            'npm_config_update_notifier'  => 'false',
            'npm_config_offline'          => 'true'
        ]);
    }

    /**
     * Verifies the sole latest success and its original evidence
     */
    public function reusable(string $fingerprint): ?string
    {
        try {
            $cache = $this->json($this->root.'/var/build.json');
            if (
                ($cache['schema_version'] ?? null) !== 1 || ($cache['exit'] ?? null) !== 0 ||
                ($cache['fingerprint'] ?? null) !== $fingerprint ||
                !is_string($cache['receipt'] ?? null) ||
                preg_match('#^\.runs/build/[0-9]{8}T[0-9]{6}Z-[0-9]+/receipt\.json$#D', $cache['receipt']) !== 1
            ) {
                return null;
            }
            $path = $this->root.'/'.$cache['receipt'];
            if ($this->tree($path) !== ($cache['receipt_sha256'] ?? null)) {
                return null;
            }
            $receipt = $this->json($path);
            if (
                ($receipt['result'] ?? null) !== 'pass' || ($receipt['exit'] ?? null) !== 0 ||
                ($receipt['source_unchanged'] ?? null) !== true ||
                ($receipt['input_fingerprint'] ?? null) !== $fingerprint ||
                ($receipt['inputs_unchanged'] ?? null) !== true || empty($receipt['phases'])
            ) {
                return null;
            }
            foreach ($receipt['phases'] as $phase) {
                if (($phase['exit'] ?? null) !== 0) {
                    return null;
                }
            }
            if ($this->evidence(dirname($path)) !== ($cache['evidence_sha256'] ?? null)) {
                return null;
            }

            $this->command(
                [PHP_BINARY, 'scripts/guarded_test_database.php', 'reuse-check'],
                ['APP_ENV' => 'test']
            );

            return $cache['receipt'];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Invalidates previous success before any fresh attempt can fail or be interrupted
     */
    public function invalidate(): void
    {
        $path = $this->root.'/var/build.json';
        if ((file_exists($path) || is_link($path)) && !unlink($path)) {
            throw new RuntimeException('Cannot invalidate the previous build cache.');
        }
    }

    /**
     * Writes a success only after the existing receipt has been written
     */
    public function publish(string $fingerprint, string $run): void
    {
        $path = $this->root.'/var/build.json';
        $temporary = $path.'.tmp';
        $data = [
            'schema_version'  => 1,
            'fingerprint'     => $fingerprint,
            'exit'            => 0,
            'receipt'         => substr($run, strlen($this->root) + 1).'/receipt.json',
            'receipt_sha256'  => $this->tree($run.'/receipt.json'),
            'evidence_sha256' => $this->evidence($run)
        ];
        if (
            file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n") === false ||
            !chmod($temporary, 0644) || !rename($temporary, $path)
        ) {
            throw new RuntimeException('Cannot publish the build cache.');
        }
    }

    /**
     * Calculates a digest of sorted paths, bytes, permissions and resolved symlink contents
     *
     * @param string        $path
     * @param boolean          $project
     * @param array<string> $parents
     */
    public function tree(string $path, bool $project = false, array $parents = []): string
    {
        clearstatcache(true, $path);
        if (!file_exists($path) && !is_link($path)) {
            throw new RuntimeException('Missing build input.');
        }
        $stat = lstat($path);
        if ($stat === false) {
            throw new RuntimeException('Cannot inspect build input.');
        }
        $kind = $stat['mode'] & 0170000;
        $record = ['mode' => $stat['mode'] & 0777];
        if ($kind === 0120000) {
            $real = realpath($path);
            if ($real === false) {
                throw new RuntimeException('Missing or cyclic symlink target.');
            }
            $record['link'] = readlink($path);
            $record['target'] = $this->tree($real, false, $parents);
        } elseif ($kind === 0100000) {
            $record['bytes'] = hash_file('sha256', $path);
            if ($record['bytes'] === false) {
                throw new RuntimeException('Cannot fingerprint build input bytes.');
            }
        } elseif ($kind === 0040000) {
            $real = realpath($path);
            if ($real === false || in_array($real, $parents, true)) {
                throw new RuntimeException('Cyclic directory in build inputs.');
            }
            $entries = scandir($path);
            if ($entries === false) {
                throw new RuntimeException('Cannot enumerate build inputs.');
            }
            $parents[] = $real;
            $record['entries'] = [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $child = $path.'/'.$entry;
                $relative = substr($child, strlen($this->root) + 1);
                if ($project && in_array($relative, self::EXCLUSIONS, true)) {
                    continue;
                }
                $record['entries'][$entry] = $this->tree($child, $project, $parents);
            }
        } else {
            throw new RuntimeException('Unsupported special file in build inputs.');
        }

        return hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));
    }

    /**
     * Validates retained logs, snapshots, reports and required generated application inputs
     */
    private function evidence(string $run): string
    {
        $files = [];
        foreach (['before.json', 'after.json'] as $file) {
            $files[$file] = $this->tree($run.'/'.$file);
        }
        $receipt = $this->json($run.'/receipt.json');
        foreach ($receipt['phases'] as $index => $phase) {
            $file = sprintf('%02d.log', $index + 1);
            $files[$file] = $this->tree($run.'/'.$file);
        }
        foreach (
            [
            '.runs/build/reports', 'var/openapi', 'var/validations', 'var/client/build', 'var/client/check/production'
            ] as $path
        ) {
            $files[$path] = $this->tree($this->root.'/'.$path);
        }

        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }

    /**
     * Reads a structured cache or existing receipt
     *
     * @return array<string, mixed>
     */
    private function json(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Missing build evidence.');
        }
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new RuntimeException('Malformed build evidence.');
        }

        return $value;
    }

    /**
     * Runs a bounded runtime command without exposing its output on failure
     *
     * @param array<string>               $command
     * @param array<string, string|false> $environment
     */
    private function command(array $command, array $environment = []): string
    {
        $process = new Process($command, $this->root, $environment);
        $process->setTimeout(30);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Cannot qualify build runtime.');
        }

        return $process->getOutput();
    }
}
