<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tooling\Build\PhpPass;
use Tooling\Build\VerificationWorkspace;

if (array_slice($argv ?? [], 1) !== []) {
    fwrite(STDERR, "Usage: php scripts/php_pass.php\n");
    exit(2);
}

$root = dirname(__DIR__);
if (!is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Missing locked Composer dependencies. See docs/engineering/BUILD.md.\n");
    exit(1);
}
require $root.'/vendor/autoload.php';
require __DIR__.'/PhpPass.php';
require __DIR__.'/VerificationWorkspace.php';

$exit = 1;
try {
    // The full gate reads and hashes the same backend reports; never replace them during that gate.
    $lock = VerificationWorkspace::lock($root);
    try {
        $before = VerificationWorkspace::snapshot($root);
        try {
            $exit = PhpPass::run($root, static function (string $name, array $command, string $cwd, array $env): int {
                fwrite(STDOUT, sprintf("\n==> %s\n", $name));
                $process = new Process($command, $cwd, $env);
                $process->setTimeout(null);

                return $process->run(static function (string $type, string $output): void {
                    fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
                });
            });
        } finally {
            if ($before !== VerificationWorkspace::snapshot($root)) {
                throw new RuntimeException(
                    'PHP verification failed: source changed during the pass. No automatic restoration.'
                );
            }
        }
    } finally {
        fclose($lock);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    $exit = 1;
}
exit($exit);
