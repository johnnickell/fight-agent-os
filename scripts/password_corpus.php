<?php

declare(strict_types=1);

// Transformation v1: offline, pinned ASCII source -> sorted unique SHA-256 hex lines.
// Derived data is CC BY-SA 4.0; see resources/passwords/NOTICE.md.
$arguments = $_SERVER['argv'];
if (count($arguments) !== 3) {
    fwrite(STDERR, "Usage: php scripts/password_corpus.php INPUT OUTPUT\n");
    exit(1);
}

$source = @file_get_contents($arguments[1]);
if (
    $source === false
    || hash('sha256', $source) !== 'ea4c906ebb0b26790c549a047962573f72ccc26f42212b83d70165d9c03fb72b'
) {
    fwrite(STDERR, "Pinned corpus input integrity failed.\n");
    exit(1);
}

$lines = explode("\n", $source);
if (array_pop($lines) !== '' || count($lines) !== 12645) {
    fwrite(STDERR, "Pinned corpus input format failed.\n");
    exit(1);
}

$hashes = [];
foreach ($lines as $line) {
    if ($line === '' || preg_match('/[^\x20-\x7e]/', $line) !== 0) {
        fwrite(STDERR, "Pinned corpus input encoding failed.\n");
        exit(1);
    }
    $hashes[] = hash('sha256', strtr($line, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'));
}
$hashes = array_values(array_unique($hashes));
sort($hashes, SORT_STRING);
$output = implode("\n", $hashes)."\n";
if (@file_put_contents($arguments[2], $output) !== strlen($output)) {
    fwrite(STDERR, "Corpus output write failed.\n");
    exit(1);
}
printf("Corpus transformation v1 passed: entries=%d sha256=%s\n", count($hashes), hash('sha256', $output));
