<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$paths = [
    'bootstrap', 'config', 'database/migrations', 'public', 'scripts', 'src', 'tests',
    'cli-config.php', 'deptrac.php', 'migrations.php', 'rector.php'
];
$files = [];
foreach ($paths as $path) {
    $target = $root.'/'.$path;
    if (is_file($target)) {
        $files[] = $target;
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if ($entry->isFile() && $entry->getExtension() === 'php') {
            $files[] = $entry->getPathname();
        }
    }
}

sort($files);
$failures = 0;
foreach ($files as $file) {
    $process = new Process([PHP_BINARY, '-l', $file], $root);
    $process->run();
    if (!$process->isSuccessful()) {
        ++$failures;
        fwrite(STDERR, $file.":\n".$process->getOutput().$process->getErrorOutput());
        continue;
    }

    $relative = substr($file, strlen($root) + 1);
    $prefix = str_starts_with($relative, 'src/') ? 'App' : (str_starts_with($relative, 'tests/') ? 'Tests' : null);
    if ($prefix === null || $relative === 'tests/bootstrap.php') {
        continue;
    }

    $path = substr($relative, strpos($relative, '/') + 1);
    $directory = dirname($path);
    $expectedNamespace = $prefix.($directory === '.' ? '' : '\\'.str_replace('/', '\\', $directory));
    $expectedType = pathinfo($path, PATHINFO_FILENAME);
    $namespace = null;
    $tokens = token_get_all((string) file_get_contents($file));
    foreach ($tokens as $index => $token) {
        if (!is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE && $namespace === null) {
            $namespace = '';
        } elseif ($namespace === '' && in_array($token[0], [T_NAME_QUALIFIED, T_STRING], true)) {
            $namespace = $token[1];
        }

        if (!in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            continue;
        }

        for ($next = $index + 1; isset($tokens[$next]); ++$next) {
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                continue;
            }

            if (is_array($tokens[$next]) && $tokens[$next][0] === T_STRING && $tokens[$next][1] !== $expectedType) {
                ++$failures;
                fwrite(STDERR, sprintf("%s: type %s does not match its file name\n", $relative, $tokens[$next][1]));
            }
            break;
        }
    }

    if ($namespace !== $expectedNamespace) {
        ++$failures;
        fwrite(STDERR, sprintf(
            "%s: expected namespace %s, found %s\n",
            $relative,
            $expectedNamespace,
            $namespace ?? '(none)'
        ));
    }
}

printf("PHP syntax/owned namespaces: %d files, %d failures\n", count($files), $failures);
exit($failures === 0 ? 0 : 1);
