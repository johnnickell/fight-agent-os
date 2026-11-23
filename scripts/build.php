<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Symfony\Component\Process\Process;
use Tooling\Build\BuildCache;
use Tooling\Build\PhpPass;
use Tooling\Build\VerificationWorkspace;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/BuildCache.php';
require __DIR__.'/PhpPass.php';
require __DIR__.'/VerificationWorkspace.php';

if (array_slice($argv ?? [], 1) !== [] && array_slice($argv ?? [], 1) !== ['--force']) {
    fwrite(STDERR, "Usage: php scripts/build.php [--force]\n");
    exit(2);
}
$force = ($argv[1] ?? null) === '--force';

$root = dirname(__DIR__);
$directory = $root.'/.runs/build';
try {
    $lock = VerificationWorkspace::lock($root);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
$started = microtime(true);
$cache = new BuildCache($root);
$fingerprint = null;
// A deliberate fresh attempt retires prior success before interruptible fingerprint work.
if ($force) {
    $cache->invalidate();
}
$timings = [];
$timed = static function (string $name, callable $operation) use (&$timings): mixed {
    $start = microtime(true);
    try {
        return $operation();
    } finally {
        $timings[$name] = round(microtime(true) - $start, 3);
        fwrite(STDOUT, sprintf("%s: %.2fs\n", $name, $timings[$name]));
    }
};
// Planning is fresh on every invocation, including application-cache hits. A
// planning failure must not turn a previously verified application into a pass.
$planningCommand = [PHP_BINARY, '-n', 'scripts/planning_portfolio.php'];
try {
    $planningBefore = $cache->tree($root.'/planning');
    $planning = new Process($planningCommand, $root);
    $planning->setTimeout(null);
    $planningExit = $timed('Planning validation', static fn (): int => $planning->run(
        static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        }
    ));
    if ($planningExit !== 0 || $planningBefore !== $cache->tree($root.'/planning')) {
        fwrite(STDERR, "Build failed: planning is invalid or changed during validation. No application phases run.\n");
        exit(1);
    }
} catch (Throwable) {
    fwrite(STDERR, "Build failed: planning validation could not be completed. No application phases run.\n");
    exit(1);
}
$missReason = $force ? 'Explicit --force requested' : 'No successful cache pointer';
try {
    fwrite(STDOUT, "Checking build source inputs...\n");
    $fingerprint = $timed('Input fingerprint', static fn (): string => $cache->fingerprint());
    $reused = $force ? null : $timed(
        'Receipt/artifact validation',
        static fn (): ?string => $cache->reusable($fingerprint)
    );
    if (!$force) {
        $missReason = $cache->missReason();
    }
    if ($reused !== null) {
        $stable = $fingerprint === $timed('Input stability recheck', static fn (): string => $cache->fingerprint());
        if ($planningBefore !== $cache->tree($root.'/planning')) {
            fwrite(STDERR, "Build failed: planning changed after validation. Run the build again.\n");
            exit(1);
        }
        if (!$stable) {
            $reused = null;
            $missReason = 'Inputs changed during cache verification';
        }
    }
    if ($reused !== null) {
        fwrite(STDOUT, sprintf(
            "Reusing successful build in %.2fs. Original receipt: %s\n",
            microtime(true) - $started,
            $reused
        ));
        fwrite(STDOUT, "Planning was validated freshly. Application phases reused.\n");
        fwrite(STDOUT, "Original warnings remain with that receipt.\n");
        flock($lock, LOCK_UN);
        fclose($lock);
        exit(0);
    }
} catch (Throwable) {
    $fingerprint = null;
    fwrite(STDERR, "Build cache unavailable: source input identity is incomplete; running the full gate.\n");
}
// A failure or interruption from here must never revive an earlier success.
$cache->invalidate();
$missReason = $fingerprint === null ? 'Source input fingerprint unavailable' : $missReason;
fwrite(STDOUT, 'Build cache miss: '.$missReason.".\n");
fwrite(STDOUT, $force ? "Forced full build.\n" : "No reusable success; running the full build.\n");
$run = $directory.'/'.gmdate('Ymd\THis\Z').'-'.getmypid();
if (!mkdir($run, 0755) || !chmod($run, 0755) || !mkdir($run.'/home', 0700)) {
    throw new RuntimeException('Cannot create the build execution directory.');
}

/**
 * Writes selected host-readable evidence independently of the writer's umask
 */
$writeEvidence = static function (string $path, array $data): void {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    if (file_put_contents($path, $json) === false || !chmod($path, 0644)) {
        throw new RuntimeException('Cannot retain host-readable build evidence.');
    }
};
$receipt = [
    'schema_version'    => 2,
    'input_fingerprint' => $fingerprint,
    'result'            => 'incomplete',
    'php'               => PHP_VERSION,
    'xdebug'            => phpversion('xdebug'),
    'packages'          => [],
    'phases'            => [[
        'name'    => 'Planning (read-only)',
        'command' => $planningCommand,
        'exit'    => 0,
        'seconds' => $timings['Planning validation']
    ]],
    'cache_miss_reason' => $missReason,
    'reports'           => ['.runs/build/reports/backend/', '.runs/build/reports/client/'],
    'limits'            => 'Local acceptance, not independent review, QA, merge or deployment'
];
if (
    file_put_contents($run.'/01.log', $planning->getOutput().$planning->getErrorOutput()) === false ||
    !chmod($run.'/01.log', 0644)
) {
    throw new RuntimeException('Cannot retain fresh planning validation.');
}
foreach (InstalledVersions::getInstalledPackages() as $package) {
    $receipt['packages'][$package] = InstalledVersions::getPrettyVersion($package);
}
ksort($receipt['packages']);

/**
 * Runs one named phase and preserves both output streams without hiding warnings
 */
$execute = static function (
    string $name,
    array $command,
    string $cwd,
    array $environment = []
) use (
    &$receipt,
    $run
): int {
    fwrite(STDOUT, sprintf("\n==> %s\n", $name));
    $path = $run.'/'.sprintf('%02d', count($receipt['phases']) + 1).'.log';
    $log = fopen($path, 'w');
    if ($log === false) {
        throw new RuntimeException('Cannot open phase evidence.');
    }
    if (!chmod($path, 0644)) {
        fclose($log);
        throw new RuntimeException('Cannot make phase evidence readable by its host consumer.');
    }
    $environment += [
        'HOME'          => $run.'/home',
        'COMPOSER_HOME' => $run.'/home/composer'
    ];
    $process = new Process($command, $cwd, $environment);
    $process->setTimeout(null);
    $start = microtime(true);
    try {
        $exit = $process->run(static function (string $type, string $output) use ($log): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
            if (fwrite($log, $output) === false) {
                throw new RuntimeException('Cannot retain complete phase evidence.');
            }
        });
        $receipt['phases'][] = [
            'name'    => $name,
            'command' => $command,
            'exit'    => $exit,
            'seconds' => round(microtime(true) - $start, 3)
        ];
        fwrite(STDOUT, sprintf("Phase finished: %s in %.2fs (exit %d)\n", $name, microtime(true) - $start, $exit));

        return $exit;
    } finally {
        fclose($log);
    }
};

$exitCode = 1;
$before = null;
try {
    $before = VerificationWorkspace::snapshot($root);
    $writeEvidence($run.'/before.json', $before);
    if (!is_file($root.'/client/node_modules/.fight-install.json')) {
        throw new RuntimeException(
            'Missing frontend setup. Run ./bin/client setup before web; see docs/engineering/BUILD.md.'
        );
    }

    $exitCode = PhpPass::run($root, $execute);
    if ($exitCode !== 0) {
        throw new RuntimeException('Build failed during the PHP pass.');
    }

    // Frontend tools receive no application, database, provider or host credential environment.
    // Explicit runner setup may select tool/browser paths without inheriting its ambient PATH or HOME.
    $frontendEnvironment = $cache->frontendEnvironment($run.'/home');
    $exitCode = $execute('Client checks', ['npm', 'run', 'check'], $root.'/client', $frontendEnvironment);
    if ($exitCode !== 0) {
        throw new RuntimeException('Build failed during client checks.');
    }
    $exitCode = $execute('Critical browser smoke (HTTPS continuity)', ['php', 'scripts/browser_smoke.php'], $root);
    if ($exitCode !== 0) {
        throw new RuntimeException('Build failed during critical browser smoke.');
    }
    // Counts, coverage and explicit exclusions remain in the owning tools' reports.
    $reports = [
        'backend'  => '.runs/build/reports/backend/unit-coverage.json',
        'frontend' => '.runs/build/reports/client/coverage/coverage-summary.json',
        'catalog'  => '.runs/build/reports/client/catalog-evidence/receipt.json',
        'browser'  => '.runs/build/reports/browser-smoke/summary.json'
    ];
    foreach ($reports as $name => $path) {
        $bytes = file_get_contents($root.'/'.$path);
        if ($bytes === false) {
            throw new RuntimeException('Missing required final '.$name.' evidence.');
        }
        $receipt['report_sha256'][$path] = hash('sha256', $bytes);
    }
    $receipt['result'] = 'pass';
} catch (Throwable $error) {
    $exitCode = $exitCode === 0 ? 1 : $exitCode;
    $receipt['result'] = 'fail';
    fwrite(STDERR, $error->getMessage()."\n");
} finally {
    try {
        $after = VerificationWorkspace::snapshot($root);
        $writeEvidence($run.'/after.json', $after);
        $receipt['source_unchanged'] = $before !== null && $before === $after;
        if (!$receipt['source_unchanged']) {
            fwrite(STDERR, "Build failed: source state changed or is unverified. No automatic restoration.\n");
            $receipt['result'] = 'fail';
            $exitCode = 1;
        }
        $afterFingerprint = null;
        if ($fingerprint !== null) {
            try {
                $afterFingerprint = $timed('Final input fingerprint', static fn (): string => $cache->fingerprint());
            } catch (Throwable) {
                // Known inputs becoming unknown during execution cannot be certified.
            }
        }
        $receipt['inputs_unchanged'] = $fingerprint !== null && $fingerprint === $afterFingerprint;
        if ($planningBefore !== $cache->tree($root.'/planning')) {
            fwrite(STDERR, "Build failed: planning changed after validation.\n");
            $receipt['result'] = 'fail';
            $exitCode = 1;
        }
        if ($fingerprint !== null && !$receipt['inputs_unchanged']) {
            fwrite(STDERR, "Build failed: installed files or runtime changed during the gate.\n");
            $receipt['result'] = 'fail';
            $exitCode = 1;
        }
        $receipt['exit'] = $exitCode;
        $receipt['cache_timings'] = $timings;
        $receipt['seconds'] = round(microtime(true) - $started, 3);
        $writeEvidence($run.'/receipt.json', $receipt);
        if ($exitCode === 0 && $receipt['inputs_unchanged']) {
            try {
                $cache->publish($fingerprint, $run);
            } catch (Throwable) {
                $receipt['cache_warning'] = 'Cache publication unavailable; the next build will run in full';
                fwrite(STDERR, $receipt['cache_warning']."\n");
                $writeEvidence($run.'/receipt.json', $receipt);
            }
        }
    } catch (Throwable) {
        fwrite(STDERR, "Build evidence is incomplete.\n");
        $exitCode = 1;
    }
    flock($lock, LOCK_UN);
    fclose($lock);
}
fwrite(STDOUT, sprintf(
    "\nBuild %s in %.2fs. Evidence: %s\n",
    $exitCode === 0 ? 'passed' : 'failed',
    microtime(true) - $started,
    substr($run, strlen($root) + 1)
));
exit($exitCode);
