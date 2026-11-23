<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

if (getenv('APP_ENV') !== 'test' || !extension_loaded('xdebug') || getenv('XDEBUG_MODE') !== 'coverage') {
    throw new RuntimeException('Backend coverage requires explicit test mode and installed Xdebug in coverage mode.');
}

$root = dirname(__DIR__);
$reports = $root.'/.runs/build/reports/backend';
if (!is_dir($reports) && !mkdir($reports, 0777, true) && !is_dir($reports)) {
    throw new RuntimeException('Cannot create backend report directory.');
}
$lock = fopen($reports.'/phase.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another backend coverage phase is running; wait before resetting its database.\n");
    exit(1);
}
// Never accept a report left behind by a failed or interrupted execution.
$suites = ['Unit', 'Integration', 'Functional', 'Postgres'];
// Retire the known combined outputs from checkouts that previously ran the old phase.
$reportFiles = ['coverage.php', 'clover.xml', 'junit.xml', 'unit-coverage.php', 'unit-coverage.json'];
foreach ($suites as $suite) {
    $reportFiles[] = 'junit-'.strtolower($suite).'.xml';
}
foreach ($reportFiles as $file) {
    $path = $reports.'/'.$file;
    if ((file_exists($path) || is_link($path)) && !unlink($path)) {
        throw new RuntimeException('Cannot clear an old backend report.');
    }
}

$steps = [
    'Guarded PostgreSQL identity/version' => ['php', 'scripts/guarded_test_database.php', 'check'],
    'Guarded PostgreSQL reset'            => ['php', 'scripts/guarded_test_database.php', 'reset'],
    'Test migrations'                     => ['php', 'vendor/bin/doctrine-migrations', 'migrate', '--no-interaction']
];
foreach ($suites as $suite) {
    $options = $suite === 'Unit' ? ['--coverage-php', $reports.'/unit-coverage.php'] : ['--no-coverage'];
    $steps[$suite.' PHPUnit'] = [
        'php', 'vendor/bin/phpunit', '--testsuite', $suite, '--fail-on-empty-test-suite',
        '--fail-on-warning', '--fail-on-risky', '--fail-on-skipped', '--fail-on-incomplete', '--fail-on-deprecation',
        '--fail-on-notice', '--log-junit', $reports.'/junit-'.strtolower($suite).'.xml', ...$options
    ];
}
$steps['Unit-only coverage policy'] = [
    'php', '-d', 'memory_limit=384M', '-d', 'opcache.enable_cli=1', '-d', 'opcache.optimization_level=0',
    'scripts/backend_coverage.php'
];
$exit = 1;
$resetCompleted = false;
try {
    foreach ($steps as $name => $command) {
        fwrite(STDOUT, sprintf("\n==> %s\n", $name));
        $environment = match ($name) {
            'Test migrations' => ['DATABASE_URL' => getenv('TEST_DATABASE_URL'), 'XDEBUG_MODE' => 'off'],
            'Unit PHPUnit', 'Unit-only coverage policy' => ['XDEBUG_MODE' => 'coverage'],
            default => ['XDEBUG_MODE' => 'off']
        };
        $process = new Process($command, $root, $environment);
        $process->setTimeout(null);
        $exit = $process->run(static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });
        if ($exit !== 0) {
            fwrite(STDERR, sprintf("Backend phase failed during %s.\n", $name));
            break;
        }
        if ($name === 'Guarded PostgreSQL reset') {
            $resetCompleted = true;
        }
    }
} finally {
    // Recheck both configured and connected identity before removing only this test schema.
    if ($resetCompleted) {
        fwrite(STDOUT, "\n==> Guarded PostgreSQL cleanup\n");
        $cleanup = new Process(['php', 'scripts/guarded_test_database.php', 'reset'], $root);
        $cleanup->setTimeout(null);
        $cleanupExit = $cleanup->run(static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });
        if ($cleanupExit !== 0) {
            fwrite(STDERR, "Backend test cleanup failed; verification is incomplete.\n");
            $exit = $exit === 0 ? $cleanupExit : $exit;
        }
    }
    flock($lock, LOCK_UN);
    fclose($lock);
}
exit($exit);
