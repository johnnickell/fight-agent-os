<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$directory = $root.'/.runs/build';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('Cannot create the ignored build report directory.');
}
$lock = fopen($directory.'/gate.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another canonical gate is running; wait for it to finish.\n");
    exit(1);
}
$run = $directory.'/'.gmdate('Ymd\THis\Z').'-'.getmypid();
if (!mkdir($run, 0700) || !mkdir($run.'/home', 0700)) {
    throw new RuntimeException('Cannot create the build execution directory.');
}
$started = microtime(true);
$receipt = [
    'schema_version' => 1,
    'result'         => 'incomplete',
    'php'            => PHP_VERSION,
    'xdebug'         => phpversion('xdebug'),
    'packages'       => [],
    'phases'         => [],
    'reports'        => ['.runs/backend-coverage/', '.runs/client/'],
    'limits'         => 'Local acceptance, not independent review, QA, merge or deployment'
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
    $log = fopen($run.'/'.sprintf('%02d', count($receipt['phases']) + 1).'.log', 'w');
    if ($log === false) {
        throw new RuntimeException('Cannot open phase evidence.');
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
    $json = json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    if (file_put_contents($run.'/before.json', $json) === false) {
        throw new RuntimeException('Cannot retain the starting source snapshot.');
    }
    // The service must be rebuilt explicitly after tooling-image changes, never by this command.
    $imageSource = '/usr/local/share/fight-build/Dockerfile';
    $expectedImage = hash_file('sha256', $root.'/etc/docker/cli/Dockerfile');
    if (!is_file($imageSource) || hash_file('sha256', $imageSource) !== $expectedImage) {
        throw new RuntimeException('The web tooling image is missing or stale. Rebuild explicitly with ./bin/up.');
    }
    if (phpversion('xdebug') !== '3.5.0' || !is_file($root.'/client/node_modules/.fight-install.json')) {
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
        'PHP types'              => ['php', 'vendor/bin/phpstan', 'analyse', '--no-progress'],
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
    $frontendEnvironment = array_fill_keys(array_keys(getenv()), false);
    $frontendEnvironment = array_replace($frontendEnvironment, [
        'PATH'                        => '/usr/local/bin:/usr/bin:/bin',
        'HOME'                        => $run.'/home',
        'CI'                          => '1',
        'TZ'                          => 'UTC',
        'STORYBOOK_DISABLE_TELEMETRY' => '1',
        'PLAYWRIGHT_BROWSERS_PATH'    => '/ms-playwright',
        'npm_config_cache'            => $root.'/.runs/client/npm',
        'npm_config_update_notifier'  => 'false',
        'npm_config_offline'          => 'true'
    ]);
    $frontendSteps = [
        'Installed frontend lock/runtime' => ['node', 'scripts/dependencies.mjs'],
        'Frontend Node version'           => ['node', '--version'],
        'Frontend npm version'            => ['npm', '--version'],
        'Frontend dependency versions'    => ['npm', 'ls', '--depth=0'],
        'Pi presentation'                 => ['node', '--test', '../harness/pi/tests/header.test.mjs']
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
        'backend'  => '.runs/backend-coverage/summary.json',
        'frontend' => '.runs/client/coverage/coverage-summary.json',
        'catalog'  => '.runs/client/catalog-evidence/receipt.json'
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
        $json = json_encode($after, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($run.'/after.json', $json) === false) {
            throw new RuntimeException('Cannot retain the final source snapshot.');
        }
        $receipt['source_unchanged'] = $before !== null && $before === $after;
        if (!$receipt['source_unchanged']) {
            fwrite(STDERR, "Build failed: source state changed or is unverified. No automatic restoration.\n");
            $receipt['result'] = 'fail';
            $exitCode = 1;
        }
        $receipt['seconds'] = round(microtime(true) - $started, 3);
        $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($run.'/receipt.json', $json) === false) {
            throw new RuntimeException('Cannot retain the build receipt.');
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
