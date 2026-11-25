#!/usr/bin/env php
<?php

declare(strict_types=1);

const HARNESS = '@fight/agent-os-pi';

function existing(string $path): string
{
    return realpath($path) ?: throw new RuntimeException('Required native package path is unavailable');
}

function manifest(string $path): stdClass
{
    $data = json_decode((string) @file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    return $data instanceof stdClass ? $data : throw new RuntimeException('Invalid configured package manifest');
}

function harnessOwner(string $path): ?string
{
    for ($directory = $path; ; $directory = dirname($directory)) {
        $file = $directory . '/package.json';
        if (is_file($file) && (manifest($file)->name ?? null) === HARNESS) {
            return $directory;
        }
        if ($directory === '/') {
            return null;
        }
    }
}

function nativePi(string $extension, string $failure): string
{
    $env = getenv();
    $env['NO_COLOR'] = $env['PI_OFFLINE'] = '1';
    unset($env['FORCE_COLOR']);
    $process = @proc_open(['pi', '--offline', '-e', $extension, '--help'],
        [['file', '/dev/null', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $env);
    if (!is_resource($process)) {
        throw new RuntimeException($failure);
    }
    $deadline = hrtime(true) + 15_000_000_000;
    $output = $errors = '';
    $exit = null;
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }
    try {
        do {
            $output .= stream_get_contents($pipes[1]);
            $errors .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exit ??= $status['exitcode'];
            }
            if ($exit !== null && feof($pipes[1]) && feof($pipes[2])) {
                if ($exit !== 0 || trim($errors) !== '') {
                    throw new RuntimeException($failure);
                }
                return $output;
            }
            if (hrtime(true) >= $deadline || strlen($output) + strlen($errors) > 16 * 1024 * 1024) {
                throw new RuntimeException($failure);
            }
            usleep(10_000);
        } while (true);
    } finally {
        if ($status['running']) {
            proc_terminate($process, 9);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
}

function nativePath(mixed $path): string
{
    if (!is_string($path) || !str_starts_with($path, '/')) {
        throw new RuntimeException('Pi package inventory lacks an absolute installed path');
    }
    return existing($path);
}

function configuredHarness(): ?string
{
    $output = nativePi(__DIR__ . '/harness-inventory.ts', 'Pi resource inventory unavailable; inspect native Pi settings');
    $prefix = 'FIGHT_HARNESS_INVENTORY=';
    $records = array_values(array_filter(explode("\n", $output), static fn ($line) => str_starts_with($line, $prefix)));
    if (count($records) !== 1) {
        throw new RuntimeException('Unsupported Pi resource inventory; expected one native inventory record');
    }
    $inventory = json_decode(substr($records[0], strlen($prefix)), false, 512, JSON_THROW_ON_ERROR);
    if (!is_array($inventory->packages ?? null) || !is_array($inventory->extensions ?? null) || !is_bool($inventory->projectTrusted ?? null)) {
        throw new RuntimeException('Unsupported Pi resource inventory');
    }
    $packages = $resources = [];
    $checkScope = static function (mixed $scope) use ($inventory): void {
        if (!is_string($scope) || ($scope === 'project' && !$inventory->projectTrusted)) {
            throw new RuntimeException('Project Harness configuration is not natively trusted; inspect native project trust');
        }
    };
    foreach ($inventory->packages as $entry) {
        $path = nativePath($entry->installedPath ?? null);
        if (harnessOwner($path) !== $path) {
            continue;
        }
        $checkScope($entry->scope ?? null);
        if (($entry->filtered ?? null) !== false) {
            throw new RuntimeException('Configured Harness is filtered; qualify its resource selection before dispatch');
        }
        $packages[$path] = true;
    }
    foreach ($inventory->extensions as $entry) {
        if (!is_bool($entry->enabled ?? null) || !is_string($entry->path ?? null)) {
            throw new RuntimeException('Unsupported native resource state');
        }
        if (str_starts_with($entry->path, 'builtin:')) {
            continue;
        }
        $path = nativePath($entry->path);
        $owner = harnessOwner($path);
        if ($owner !== null) {
            $checkScope($entry->metadata->scope ?? null);
            $packages[$owner] = true;
            if ($entry->enabled) {
                $resources[$path] = true;
            }
        }
    }
    if (count($packages) > 1) {
        throw new RuntimeException('Multiple configured Harness packages or resource owners; reconcile native Pi settings');
    }
    $selected = array_key_first($packages);
    if ($selected !== null) {
        $extensions = manifest($selected . '/package.json')->pi->extensions ?? null;
        if (!is_array($extensions) || array_filter($extensions, static fn ($path) => !is_string($path))) {
            throw new RuntimeException('Configured Harness extension manifest unavailable');
        }
        $expected = array_fill_keys(array_map(static fn ($path) => existing($selected . '/' . $path), $extensions), true);
        if ($resources != $expected) {
            throw new RuntimeException('Configured Harness resources are incomplete or disabled; inspect native Pi settings');
        }
    }
    return $selected;
}

try {
    if (!function_exists('pcntl_exec')) {
        throw new RuntimeException('PHP CLI with pcntl is required');
    }
    $forwarded = array_slice($argv, 1);
    $explicit = null;
    foreach ($forwarded as $index => $argument) {
        if ($argument === '--') {
            break;
        }
        if (str_starts_with($argument, '--harness-package=')) {
            $explicit = substr($argument, strlen('--harness-package='));
        } elseif ($argument === '--harness-package') {
            $explicit = $forwarded[$index + 1] ?? '';
            if ($explicit === '' || str_starts_with($explicit, '--')) {
                throw new RuntimeException('Missing Harness package path');
            }
        }
    }
    $configured = configuredHarness();
    $package = existing($explicit ?? $configured ?? dirname(__DIR__, 4) . '/harness/pi');
    if ($configured !== null && $package !== $configured) {
        throw new RuntimeException('Explicit Harness conflicts with the configured package; inspect native Pi settings');
    }
    if ((manifest($package . '/package.json')->name ?? null) !== HARNESS) {
        throw new RuntimeException('Selected package is not the Fight Harness');
    }
    $monitor = existing($package . '/src/observation/run-stage.php');
    if (dirname($monitor) !== $package . '/src/observation' || !is_file($monitor)) {
        throw new RuntimeException('monitor outside package');
    }
    nativePi($package, 'Native Harness registration unavailable or conflicting; inspect native Pi extension settings');
    // Keep the selected package's fixed process identity and ordinary ancestry.
    @pcntl_exec(PHP_BINARY, [$monitor, ...$forwarded]);
    throw new RuntimeException('PHP monitor could not start');
} catch (Throwable $error) {
    fwrite(STDERR, 'Stage monitor unavailable: ' . $error->getMessage() . ". No child started; inspect the configured Harness and its monitor support\n");
    exit(1);
}
