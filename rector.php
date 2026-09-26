<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Ternary\SimplifyTautologyTernaryRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/config',
        __DIR__.'/bootstrap',
        __DIR__.'/database/migrations',
        __DIR__.'/scripts',
        __DIR__.'/public',
        __DIR__.'/cli-config.php',
        __DIR__.'/deptrac.php',
        __DIR__.'/migrations.php',
        __DIR__.'/rector.php'
    ])
    ->withRules([
        SimplifyTautologyTernaryRector::class
    ])
    ->withCache(__DIR__.'/var/cache/rector');
