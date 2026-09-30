<?php

declare(strict_types=1);

use Tooling\OpenApi\Document;

require dirname(__DIR__).'/vendor/autoload.php';

$argv = $_SERVER['argv'] ?? [];
$mode = $argv[1] ?? 'check';
if (count($argv) > 2 || !in_array($mode, ['export', 'check'], true)) {
    fwrite(STDERR, "Usage: ./bin/openapi [export|check]\n");
    exit(2);
}

set_error_handler(static function (): never {
    throw new RuntimeException('OpenAPI filesystem or generation warning.');
});
$exitCode = 0;
$temporary = null;
$stage = 'generation and validation';
try {
    $json = Document::generate();
    $directory = dirname(__DIR__).'/.runs/openapi';
    $artifact = $directory.'/openapi.json';
    $stage = 'private artifact directory';
    if ($mode === 'export' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create the private artifact directory.');
    }

    if (realpath($directory) !== $directory || is_link($artifact)) {
        throw new RuntimeException('The private artifact directory must exist without symlinks.');
    }

    if ($mode === 'export') {
        $stage = 'atomic artifact replacement';
        $candidate = $directory.'/.openapi-'.bin2hex(random_bytes(12));
        $stream = fopen($candidate, 'x');
        if ($stream === false) {
            throw new RuntimeException('Cannot create a private temporary artifact.');
        }

        $temporary = $candidate;
        try {
            if (!chmod($temporary, 0600) || fwrite($stream, $json) !== strlen($json) || !fflush($stream)) {
                throw new RuntimeException('Cannot write the complete validated artifact.');
            }
        } finally {
            fclose($stream);
        }

        if (file_get_contents($temporary) !== $json) {
            throw new RuntimeException('Temporary bytes differ from the complete validated artifact.');
        }

        if (!rename($temporary, $artifact)) {
            throw new RuntimeException('Cannot atomically replace the artifact.');
        }

        $temporary = null;
    } else {
        $stage = 'artifact freshness (run ./bin/openapi export after source changes)';
        if (!is_file($artifact) || file_get_contents($artifact) !== $json) {
            throw new RuntimeException('The artifact is missing or stale.');
        }
    }

    fwrite(STDOUT, sprintf("OpenAPI %s passed: .runs/openapi/openapi.json sha256=%s\n", $mode, hash('sha256', $json)));
} catch (Throwable) {
    // Do not echo exception text, source contents, environment values or machine paths.
    fwrite(STDERR, sprintf(
        "OpenAPI failed during %s; no successful export receipt. Retained output is not current proof.\n",
        $stage
    ));
    $exitCode = 1;
} finally {
    try {
        if (is_string($temporary) && is_file($temporary)) {
            unlink($temporary);
        }
    } catch (Throwable) {
        fwrite(STDERR, "OpenAPI temporary cleanup failed; inspect the private artifact directory.\n");
        $exitCode = 1;
    }
    restore_error_handler();
}

exit($exitCode);
