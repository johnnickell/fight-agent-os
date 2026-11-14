<?php

declare(strict_types=1);

/* Tooling only: keep mounted secrets inside PHP instead of expanding an absent shell environment. */
$testMigrationArguments = $_SERVER['argv'] ?? [];
$argv = [$testMigrationArguments[0] ?? __FILE__, 'check'];
require __DIR__.'/guarded_test_database.php';
putenv('DATABASE_URL='.getenv('TEST_DATABASE_URL'));
$argv = $testMigrationArguments;
unset($testMigrationArguments);
require dirname(__DIR__).'/vendor/bin/doctrine-migrations';
