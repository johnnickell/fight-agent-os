<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Symfony\Component\Process\Process;
use Tooling\Build\BuildCache;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/BuildCache.php';

if (array_slice($argv ?? [], 1) !== [] && array_slice($argv ?? [], 1) !== ['--force']) {
    fwrite(STDERR, "Usage: php scripts/build.php [--force]\n");
    exit(2);
}
$force = ($argv[1] ?? null) === '--force';

$root = dirname(__DIR__);
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
    fwrite(STDERR, "Another canonical gate is running; wait for it to finish.\n");
    exit(1);
}
$started = microtime(true);
$cache = new BuildCache($root);
$fingerprint = null;
// A deliberate fresh attempt retires prior success before interruptible fingerprint work.
if ($force) {
    $cache->invalidate();
}
try {
    fwrite(STDOUT, "Checking build inputs and runtime...\n");
    $fingerprint = $cache->fingerprint();
    $reused = $force ? null : $cache->reusable($fingerprint);
    if ($reused !== null && $fingerprint === $cache->fingerprint()) {
        fwrite(STDOUT, sprintf(
            "Reusing successful build in %.2fs. Original receipt: %s\n",
            microtime(true) - $started,
            $reused
        ));
        fwrite(STDOUT, "Original logs, warnings and reports remain with that receipt. No phases rerun.\n");
        flock($lock, LOCK_UN);
        fclose($lock);
        exit(0);
    }
} catch (Throwable) {
    $fingerprint = null;
    fwrite(STDERR, "Build cache unavailable: input/runtime identity is incomplete; running the full gate.\n");
}
// A failure or interruption from here must never revive an earlier success.
$cache->invalidate();
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
    'phases'            => [],
    'reports'           => ['.runs/build/reports/backend/', '.runs/build/reports/client/'],
    'limits'            => 'Local acceptance, not independent review, QA, merge or deployment'
];
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

        return $exit;
    } finally {
        fclose($log);
    }
};

/**
 * Captures index, status and actual tracked/unignored bytes including dirty starting work
 */
$snapshot = static function () use ($root): array {
    $git = static function (array $arguments) use ($root): string {
        $process = new Process(
            ['git', '-c', 'safe.directory='.$root, ...$arguments],
            $root,
            ['GIT_OPTIONAL_LOCKS' => '0']
        );
        $process->mustRun();

        return $process->getOutput();
    };
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
};

$exitCode = 1;
$before = null;
try {
    $before = $snapshot();
    $writeEvidence($run.'/before.json', $before);
    if (!extension_loaded('xdebug') || !is_file($root.'/client/node_modules/.fight-install.json')) {
        throw new RuntimeException(
            'Missing coverage/frontend setup. Run ./bin/up and ./bin/client setup explicitly.'
        );
    }

    $steps = [
        'Composer version'       => ['composer', '--version', '--no-interaction'],
        'Composer manifest/lock' => ['composer', 'validate', '--strict', '--no-interaction'],
        'Planning (read-only)'   => ['./bin/planning-check'],
        'PHP syntax/namespaces'  => ['php', 'scripts/php_syntax.php'],
        'PHP style'              => ['php', 'vendor/bin/phpcs', '-s'],
        'PHP types'              => ['php', 'vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=512M'],
        'PHP dependencies'       => [
            'php', 'vendor/bin/deptrac', 'analyse', '--config-file=deptrac.php', '--formatter=table',
            '--no-progress', '--report-uncovered', '--fail-on-uncovered'
        ],
        'Rector (dry-run)'       => ['php', 'vendor/bin/rector', 'process', '--dry-run', '--no-progress-bar'],
        'OpenAPI freshness'      => ['php', 'scripts/openapi.php', 'check'],
        'Validation freshness'   => ['php', 'scripts/validations.php', 'check'],
        'Backend coverage'       => ['php', 'scripts/backend_tests.php']
    ];
    foreach ($steps as $name => $command) {
        $environment = ['COMPOSER_ROOT_VERSION' => 'dev-develop', 'COMPOSER_DISABLE_NETWORK' => '1'];
        if ($name === 'Backend coverage') {
            $environment['APP_ENV'] = 'test';
            $environment['XDEBUG_MODE'] = 'coverage';
        }
        $exitCode = $execute($name, $command, $root, $environment);
        if ($exitCode !== 0) {
            throw new RuntimeException('Build failed during '.$name.'.');
        }
    }

    // Frontend/Pi tools receive no application, database, provider or host credential environment.
    // Explicit runner setup may select tool/browser paths without inheriting its ambient PATH or HOME.
    $frontendEnvironment = $cache->frontendEnvironment($run.'/home');
    $frontendSteps = [
        'Installed frontend lock/runtime' => ['node', 'scripts/dependencies.mjs'],
        'Frontend Node version'           => ['node', '--version'],
        'Frontend npm version'            => ['npm', '--version'],
        'Frontend dependency versions'    => ['npm', 'ls', '--depth=0'],
        'Pi Harness behavior'             => [
            'node',
            '--test',
            '../harness/pi/tests/header.test.mjs',
            '../harness/pi/tests/herdr-workspace.test.mjs',
            '../harness/pi/tests/herdr-activity.test.mjs',
            '../harness/pi/tests/herdr-logs.test.mjs',
            '../harness/pi/tests/discovered-logs.test.mjs',
            '../harness/pi/tests/local-log-sources.test.mjs',
            '../harness/pi/tests/jev.test.mjs',
            '../harness/pi/tests/judgments.test.mjs',
            '../harness/pi/tests/file-screening.test.mjs',
            '../harness/pi/tests/file-admission.test.mjs',
            '../harness/pi/tests/monthly-budget.test.mjs',
            '../harness/pi/tests/screening-profile.test.mjs',
            '../harness/pi/tests/tool-policy.test.mjs',
            '../harness/pi/tests/workspace.test.mjs',
            '../harness/pi/tests/subagents.test.mjs',
            '../harness/pi/tests/registered-children.test.mjs',
            '../harness/pi/tests/custody.test.mjs',
            '../harness/pi/tests/session-file.test.mjs',
            '../harness/pi/tests/runtime-logs.test.mjs',
            '../harness/pi/tests/worktree-logs.test.mjs'
        ]
    ];
    $frontendChecks = [
        'typecheck', 'lint', 'format-check', 'coverage', 'build-check',
        'storybook-build', 'storybook-test', 'storybook-capture'
    ];
    foreach ($frontendChecks as $step) {
        $frontendSteps['Frontend '.$step] = ['npm', 'run', $step];
    }
    foreach ($frontendSteps as $name => $command) {
        $exitCode = $execute($name, $command, $root.'/client', $frontendEnvironment);
        if ($exitCode !== 0) {
            throw new RuntimeException('Build failed during '.$name.'.');
        }
    }
    // Counts, coverage and explicit exclusions remain in the owning tools' reports.
    $reports = [
        'backend'  => '.runs/build/reports/backend/summary.json',
        'frontend' => '.runs/build/reports/client/coverage/coverage-summary.json',
        'catalog'  => '.runs/build/reports/client/catalog-evidence/receipt.json'
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
        $after = $snapshot();
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
                $afterFingerprint = $cache->fingerprint();
            } catch (Throwable) {
                // Known inputs becoming unknown during execution cannot be certified.
            }
        }
        $receipt['inputs_unchanged'] = $fingerprint !== null && $fingerprint === $afterFingerprint;
        if ($fingerprint !== null && !$receipt['inputs_unchanged']) {
            fwrite(STDERR, "Build failed: installed files or runtime changed during the gate.\n");
            $receipt['result'] = 'fail';
            $exitCode = 1;
        }
        $receipt['exit'] = $exitCode;
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
