<?php

declare(strict_types=1);

use App\Adapter\Validation\Catalog;
use App\Adapter\Validation\PublicForms;
use App\Adapter\Validation\SchemaProjection;

require dirname(__DIR__).'/vendor/autoload.php';

$mode = $_SERVER['argv'][1] ?? 'check';
if (count($_SERVER['argv']) > 2 || !in_array($mode, ['export', 'check'], true)) {
    fwrite(STDERR, "Usage: ./bin/validations [export|check]\n");
    exit(2);
}
set_error_handler(static function (): never {
    throw new RuntimeException('Validation export warning.');
});
$root = dirname(__DIR__);
$directory = $root.'/.runs/validations';
$file = $directory.'/catalog.json';
$temporary = null;
$stage = 'projection';
$exit = 0;
try {
    $registrations = PublicForms::registrations();
    $sources = [
        'composer.lock', 'src/Adapter/Validation/PublicForms.php', 'src/Adapter/Validation/SchemaProjection.php',
        'src/Adapter/Validation/Catalog.php', 'scripts/validations.php',
        'vendor/johnnickell/fight-common/src/Application/Attribute/Validation.php',
        'vendor/johnnickell/fight-common/src/Application/Validation/RulesParser.php'
    ];
    foreach ($registrations as $registration) {
        $source = (new ReflectionClass($registration['action']))->getFileName();
        if (!is_string($source) || !str_starts_with($source, $root.'/src/')) {
            throw new RuntimeException('Public form source must be an owned Action.');
        }
        $sources[] = substr($source, strlen($root) + 1);
    }
    $sources = array_unique($sources);
    sort($sources, SORT_STRING);
    $snapshot = static function () use ($sources, $root): string {
        $hash = hash_init('sha256');
        foreach ($sources as $source) {
            $bytes = file_get_contents($root.'/'.$source);
            if ($bytes === false) {
                throw new RuntimeException('Cannot read a validation source.');
            }
            hash_update($hash, $source."\0".$bytes."\0");
        }

        return hash_final($hash);
    };
    $before = $snapshot();
    $json = Catalog::generation(SchemaProjection::project($registrations));
    if (strlen($json) > 262144) {
        throw new RuntimeException('Public catalog exceeds the runtime limit.');
    }
    if ($before !== $snapshot()) {
        throw new RuntimeException('Sources changed during projection.');
    }
    $stage = 'private artifact';
    if ($mode === 'export' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create artifact directory.');
    }
    if (realpath($directory) !== $directory || is_link($file)) {
        throw new RuntimeException('Invalid artifact location.');
    }
    if ($mode === 'export') {
        $stage = 'atomic replacement';
        $temporary = $directory.'/.catalog-'.bin2hex(random_bytes(12));
        $stream = fopen($temporary, 'x');
        if ($stream === false) {
            throw new RuntimeException('Cannot create temporary artifact.');
        }
        try {
            if (!chmod($temporary, 0600) || fwrite($stream, $json) !== strlen($json) || !fflush($stream)) {
                throw new RuntimeException('Cannot write artifact.');
            }
        } finally {
            fclose($stream);
        }
        if (file_get_contents($temporary) !== $json || $before !== $snapshot()) {
            throw new RuntimeException('Artifact or source changed during generation.');
        }
        if (!rename($temporary, $file)) {
            throw new RuntimeException('Cannot replace artifact.');
        }
        $temporary = null;
    } elseif (!is_file($file) || file_get_contents($file) !== $json) {
        throw new RuntimeException('Missing or stale artifact.');
    }
    fwrite(STDOUT, sprintf(
        "Validation %s passed: forms=%d sha256=%s source_sha256=%s\n",
        $mode,
        count($registrations),
        hash('sha256', $json),
        $before
    ));
} catch (Throwable) {
    fwrite(STDERR, sprintf("Validation %s failed during %s; retained output is not current proof.\n", $mode, $stage));
    $exit = 1;
} finally {
    try {
        if ($temporary !== null && is_file($temporary)) {
            unlink($temporary);
        }
    } catch (Throwable) {
        fwrite(STDERR, "Validation temporary cleanup failed.\n");
        $exit = 1;
    }
    restore_error_handler();
}
exit($exit);
