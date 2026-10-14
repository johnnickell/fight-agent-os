<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

if (getenv('APP_ENV') !== 'test' || phpversion('xdebug') !== '3.5.0' || getenv('XDEBUG_MODE') !== 'coverage') {
    throw new RuntimeException('Backend coverage requires explicit test mode and Xdebug 3.5.0 in coverage mode.');
}

$root = dirname(__DIR__);
$reports = $root.'/.runs/backend-coverage';
if (!is_dir($reports) && !mkdir($reports, 0777, true) && !is_dir($reports)) {
    throw new RuntimeException('Cannot create backend report directory.');
}
$lock = fopen($reports.'/phase.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another backend coverage phase is running; wait before resetting its database.\n");
    exit(1);
}
// Never accept a report left behind by a failed or interrupted execution.
$reportFiles = ['coverage.php', 'clover.xml', 'junit.xml', 'summary.json', 'domain-application.json', 'adapter.json'];
foreach ($reportFiles as $file) {
    if (is_file($reports.'/'.$file) && !unlink($reports.'/'.$file)) {
        throw new RuntimeException('Cannot clear an old backend report.');
    }
}

$steps = [
    'Guarded PostgreSQL identity/version' => ['php', 'scripts/guarded_test_database.php', 'check'],
    'Guarded PostgreSQL reset'            => ['php', 'scripts/guarded_test_database.php', 'reset'],
    'Test migrations'                     => ['php', 'vendor/bin/doctrine-migrations', 'migrate', '--no-interaction'],
    'All backend suites with coverage'    => [
        'php', 'vendor/bin/phpunit', '--fail-on-warning', '--fail-on-risky', '--fail-on-skipped',
        '--fail-on-incomplete', '--fail-on-deprecation', '--fail-on-notice',
        '--coverage-php', $reports.'/coverage.php', '--coverage-clover', $reports.'/clover.xml',
        '--log-junit', $reports.'/junit.xml'
    ],
    'Owned coverage policy'               => ['php', 'scripts/backend_coverage.php']
];
$exit = 1;
$resetCompleted = false;
try {
    foreach ($steps as $name => $command) {
        fwrite(STDOUT, sprintf("\n==> %s\n", $name));
        $environment = $name === 'Test migrations' ? ['DATABASE_URL' => getenv('TEST_DATABASE_URL')] : null;
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
