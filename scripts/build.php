<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$steps = [
    'Composer manifest' => ['composer', 'validate', '--strict', '--no-interaction'],
    'Planning'          => ['./bin/planning-check'],
    'Architecture'      => [
        'php',
        'vendor/bin/deptrac',
        'analyse',
        '--config-file=deptrac.php',
        '--formatter=table',
        '--no-progress',
        '--report-uncovered',
        '--fail-on-uncovered'
    ],
    'PHPUnit'           => ['php', 'vendor/bin/phpunit']
];

foreach ($steps as $name => $command) {
    fwrite(STDOUT, sprintf("\n==> %s\n", $name));

    $environment = $name === 'Composer manifest' ? ['COMPOSER_ROOT_VERSION' => 'dev-develop'] : null;
    $process = new Process($command, dirname(__DIR__), $environment);
    $process->setTimeout(null);
    $exitCode = $process->run(
        static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        }
    );

    if ($exitCode !== 0) {
        fwrite(STDERR, sprintf("\nBuild failed during %s.\n", $name));
        exit($exitCode);
    }
}

fwrite(STDOUT, "\nBuild passed.\n");
