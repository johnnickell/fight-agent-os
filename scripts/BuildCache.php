<?php

declare(strict_types=1);

namespace Tooling\Build;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Class BuildCache
 */
final class BuildCache
{
    private const array EXCLUSIONS = [
        '.git', '.runs', 'var', '.idea', '.DS_Store', 'client/dist', 'public/build', 'planning',
        'client/node_modules/.cache', 'client/node_modules/.vite-temp'
    ];

    /**
     * @var array<string, string>
     */
    private array $components = [];
    private string $missReason = 'No successful cache pointer';

    /**
     * Constructs BuildCache
     *
     * Initializes the checkout-local receipt cache
     */
    public function __construct(private readonly string $root)
    {
    }

    /**
     * Calculates application identity, retaining governing instructions within planning
     */
    public function projectFingerprint(): string
    {
        $instructions = [];
        $directory = $this->root.'/planning';
        if (is_link($directory)) {
            $instructions['planning'] = $this->tree($directory);
        } elseif (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $directory,
                RecursiveDirectoryIterator::SKIP_DOTS
            ));
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                $relative = substr($path, strlen($this->root) + 1);
                if (
                    $file->isLink() || $file->getExtension() !== 'md' ||
                    $relative === 'planning/CONVENTIONS.md' ||
                    str_starts_with($relative, 'planning/agents/') ||
                    str_starts_with($relative, 'planning/adr/') ||
                    str_ends_with($relative, '_TEMPLATE.md') ||
                    in_array($file->getFilename(), ['AGENTS.md', 'CLAUDE.md', 'SKILL.md'], true)
                ) {
                    $instructions[$relative] = $this->tree($path);
                }
            }
        }
        ksort($instructions);

        return hash('sha256', json_encode([$this->tree($this->root, true), $instructions], JSON_THROW_ON_ERROR));
    }

    /**
     * Reports a bounded cache decision without exposing input values
     */
    public function missReason(): string
    {
        return $this->missReason;
    }

    /**
     * Calculates a fingerprint of project files, dependencies and generated inputs
     */
    public function fingerprint(): string
    {
        $generated = [];
        foreach (['var/openapi', 'var/validations', 'var/client/build'] as $path) {
            $generated[$path] = $this->tree($this->root.'/'.$path);
        }
        $this->components = [
            'project'   => $this->projectFingerprint(),
            'generated' => hash('sha256', json_encode($generated, JSON_THROW_ON_ERROR))
        ];

        return hash('sha256', json_encode($this->components, JSON_THROW_ON_ERROR));
    }

    /**
     * Creates the credential-free frontend environment for build phases
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
            $this->missReason = 'Cache pointer missing or unreadable';
            $cache = $this->json($this->root.'/var/build.json');
            $this->missReason = 'Cache format changed; a fresh build is required';
            if (($cache['schema_version'] ?? null) !== 3) {
                return null;
            }
            $this->missReason = 'Cache pointer is invalid or does not record success';
            if (
                ($cache['exit'] ?? null) !== 0 ||
                !is_string($cache['receipt'] ?? null) ||
                preg_match('#^\.runs/build/[0-9]{8}T[0-9]{6}Z-[0-9]+/receipt\.json$#D', $cache['receipt']) !== 1
            ) {
                return null;
            }
            if (($cache['fingerprint'] ?? null) !== $fingerprint) {
                $changed = [];
                $labels = [
                    'project'   => 'project/dependency/instruction contents',
                    'generated' => 'generated inputs'
                ];
                foreach ($labels as $key => $label) {
                    if (($cache['components'][$key] ?? null) !== ($this->components[$key] ?? null)) {
                        $changed[] = $label;
                    }
                }
                $this->missReason = 'Changed '.($changed === [] ? 'input fingerprint' : implode(', ', $changed));

                return null;
            }
            $this->missReason = 'Original receipt missing, unreadable or modified';
            $path = $this->root.'/'.$cache['receipt'];
            if ($this->tree($path) !== ($cache['receipt_sha256'] ?? null)) {
                return null;
            }
            $receipt = $this->json($path);
            $this->missReason = 'Original receipt does not certify stable successful inputs';
            if (
                ($receipt['result'] ?? null) !== 'pass' || ($receipt['exit'] ?? null) !== 0 ||
                ($receipt['source_unchanged'] ?? null) !== true ||
                ($receipt['input_fingerprint'] ?? null) !== $fingerprint ||
                ($receipt['inputs_unchanged'] ?? null) !== true || empty($receipt['phases'])
            ) {
                return null;
            }
            $this->missReason = 'Original receipt contains an unsuccessful phase';
            foreach ($receipt['phases'] as $phase) {
                if (($phase['exit'] ?? null) !== 0) {
                    return null;
                }
            }
            $this->missReason = 'Retained logs, snapshots, reports or generated artifacts missing or changed';
            if ($this->evidence(dirname($path)) !== ($cache['evidence_sha256'] ?? null)) {
                return null;
            }

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
            'schema_version'  => 3,
            'fingerprint'     => $fingerprint,
            'components'      => $this->components,
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
}
