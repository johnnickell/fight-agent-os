<?php

declare(strict_types=1);

$usage = "Usage: php scripts/verification_scope.php --base BASE [--head CANDIDATE]\n";
$arguments = array_slice($argv ?? [], 1);
if ($arguments === ['--help']) {
    echo $usage;
    exit(0);
}
if (
    !in_array(count($arguments), [2, 4], true) || $arguments[0] !== '--base' || $arguments[1] === ''
    || (count($arguments) === 4 && ($arguments[2] !== '--head' || $arguments[3] === ''))
) {
    fwrite(STDERR, $usage);
    exit(2);
}

require __DIR__.'/VerificationScope.php';

echo json_encode(
    scopeReport($arguments[1], $arguments[3] ?? null),
    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
)."\n";
