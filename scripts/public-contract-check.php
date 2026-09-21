<?php

declare(strict_types=1);

$installed = require 'vendor/composer/installed.php';
foreach (['johnnickell/fight-common', 'johnnickell/fight-access-control'] as $package) {
    if (!isset($installed['versions'][$package])) {
        throw new RuntimeException("Production dependency {$package} is not installed.");
    }
}
foreach (['src/Domain', 'src/Application'] as $forbidden) {
    if (is_dir($forbidden)) {
        throw new RuntimeException("Shared source must not be copied into {$forbidden}.");
    }
}
fwrite(STDOUT, "Public Composer dependency contract passed.\n");
