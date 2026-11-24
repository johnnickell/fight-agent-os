<?php

declare(strict_types=1);

namespace App\Adapter\Installation;

/**
 * Class ApprovedRootAccess
 *
 * Observes configured roots without reading their contents
 */
final readonly class ApprovedRootAccess
{
    /**
     * Constructs ApprovedRootAccess
     */
    public function __construct(
        private string $configuration = '/run/fight-root-configuration/roots.json',
        private string $rootDirectory = '/run/fight-approved-roots',
        private string $mountTable = '/proc/self/mountinfo'
    ) {
    }

    /**
     * Returns safe root locators and current access facts
     *
     * @return array{roots: list<ApprovedRootView>, reason: ?string}
     */
    public function observe(): array
    {
        clearstatcache(true);
        if (!is_dir(dirname($this->configuration))) {
            return ['roots' => [], 'reason' => 'no_configuration_mount'];
        }

        $configuration = @file_get_contents($this->configuration, length: 65537);
        $model = null;
        if ($configuration !== false && strlen($configuration) <= 65536) {
            $model = json_decode($configuration, true);
        }
        $settings = is_array($model) ? ($model['x-fight-approved-roots'] ?? null) : null;
        $roots = is_array($settings) ? ($settings['roots'] ?? null) : null;
        if (!is_array($settings) || ($settings['version'] ?? null) !== 1 || !is_array($roots) || count($roots) > 16) {
            return ['roots' => [], 'reason' => 'configuration_unavailable'];
        }

        if ($roots === []) {
            return ['roots' => [], 'reason' => null];
        }

        $mounts = $this->mounts();
        $views = [];
        foreach ($roots as $name => $root) {
            if (!is_string($name) || preg_match('/\A[a-z][a-z0-9-]{0,31}\z/', $name) !== 1 || !is_array($root)) {
                return ['roots' => [], 'reason' => 'configuration_unavailable'];
            }
            $access = $root['access'] ?? null;
            $target = $root['target'] ?? null;
            $prefix = $this->rootDirectory.'/'.$name.'-';
            if (
                ($access !== 'ro' && $access !== 'rw') || !is_string($target) || !str_starts_with($target, $prefix)
                || preg_match('/\A[a-f0-9]{64}\z/', substr($target, strlen($prefix))) !== 1
            ) {
                return ['roots' => [], 'reason' => 'configuration_unavailable'];
            }
            $views[] = $this->observeRoot('root:'.$name, $target, $access, $mounts);
        }

        return ['roots' => $views, 'reason' => $mounts === null ? 'mount_table_unavailable' : null];
    }

    /**
     * Returns bounded kernel mount metadata for the reserved root namespace
     *
     * @return array<string, list<string>>|null
     */
    private function mounts(): ?array
    {
        $table = @file_get_contents($this->mountTable, length: 262145);
        if ($table === false || $table === '' || strlen($table) > 262144 || !str_ends_with($table, "\n")) {
            return null;
        }
        $mounts = [];
        foreach (explode("\n", rtrim($table, "\n")) as $line) {
            $fields = explode(' ', $line, 7);
            if (count($fields) < 7 || !str_contains($fields[6], '- ')) {
                return null;
            }
            $target = stripcslashes($fields[4]);
            if (str_starts_with($target, $this->rootDirectory.'/')) {
                $mounts[$target][] = strtok($fields[5], ',') ?: '';
            }
        }

        return $mounts;
    }

    /**
     * Returns access to one exact mount without opening or creating a child
     *
     * @param string                           $locator
     * @param string                           $target
     * @param string                           $access
     * @param array<string, list<string>>|null $mounts
     */
    private function observeRoot(string $locator, string $target, string $access, ?array $mounts): ApprovedRootView
    {
        $writeGranted = $access === 'rw';
        $observed = $mounts[$target] ?? [];
        $mounted = $observed !== [];
        $reason = null;
        $readable = false;
        $writable = false;
        if ($mounts === null) {
            $reason = 'mount_table_unavailable';
        } elseif (!$mounted) {
            $reason = 'exact_mount_not_observed';
        } elseif ($observed !== [$access] || $this->hasNestedMount($target, $mounts)) {
            $reason = 'unexpected_mount';
        } else {
            $directory = is_dir($target);
            $readable = $directory && is_readable($target) && is_executable($target);
            $writable = $directory && $writeGranted && is_writable($target) && is_executable($target);
            if (!$readable || ($writeGranted && !$writable)) {
                $reason = 'access_unavailable';
            }
        }

        return new ApprovedRootView($locator, true, $mounted, $readable, $writeGranted, $writable, $reason);
    }

    /**
     * Checks for unexpected filesystem boundaries inside the exact root
     *
     * @param string                      $target
     * @param array<string, list<string>> $mounts
     */
    private function hasNestedMount(string $target, array $mounts): bool
    {
        foreach (array_keys($mounts) as $mount) {
            if (str_starts_with($mount, $target.'/')) {
                return true;
            }
        }

        return false;
    }
}
