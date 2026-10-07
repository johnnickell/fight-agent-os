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
// Never accept a report left behind by a failed or interrupted execution.
$reportFiles = ['coverage.php', 'clover.xml', 'junit.xml', 'summary.json', 'domain-application.json', 'adapter.json'];
foreach ($reportFiles as $file) {
    if (is_file($reports.'/'.$file) && !unlink($reports.'/'.$file)) {
        throw new RuntimeException('Cannot clear an old backend report.');
    }
}

$steps = [
    'Guarded PostgreSQL reset'         => ['php', 'scripts/guarded_test_database.php', 'reset'],
    'Test migrations'                  => ['php', 'vendor/bin/doctrine-migrations', 'migrate', '--no-interaction'],
    'All backend suites with coverage' => [
        'php', 'vendor/bin/phpunit', '--fail-on-warning', '--fail-on-risky', '--fail-on-skipped',
        '--fail-on-incomplete', '--fail-on-deprecation', '--fail-on-notice',
        '--coverage-php', $reports.'/coverage.php', '--coverage-clover', $reports.'/clover.xml',
        '--log-junit', $reports.'/junit.xml'
    ],
    'Owned coverage policy'            => ['php', 'scripts/backend_coverage.php']
];
foreach ($steps as $name => $command) {
    fwrite(STDOUT, sprintf("\n==> %s\n", $name));
    $environment = $name === 'Test migrations' ? ['DATABASE_URL' => getenv('TEST_DATABASE_URL')] : null;
    $process = new Process($command, $root, $environment);
    $process->setTimeout(null);
    $exit = $process->run(static function (string $type, string $output): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
    });
    if ($exit !== 0) {
        exit($exit);
    }
}
