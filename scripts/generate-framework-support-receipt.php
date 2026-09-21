<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require $projectRoot.'/scripts/framework-support-receipt.php';

$receipt = frameworkSupportReceipt($projectRoot);
$path = $projectRoot.'/evidence/framework-support/receipt-v1.json';

if ($argc === 1) {
    if (!is_file($path) || !hash_equals(frameworkSupportCanonicalJson($receipt), (string) file_get_contents($path))) {
        fwrite(STDERR, "Committed framework-support receipt has drifted; refresh it with --write.\n");
        exit(1);
    }
    exit(0);
}

if ($argc !== 2 || $argv[1] !== '--write') {
    fwrite(STDERR, "Usage: php scripts/generate-framework-support-receipt.php [--write]\n");
    exit(2);
}

if (!is_dir(dirname($path))) {
    mkdir(dirname($path), 0777, true);
}

file_put_contents($path, frameworkSupportCanonicalJson($receipt));
