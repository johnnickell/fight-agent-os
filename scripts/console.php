<?php

declare(strict_types=1);

use App\Adapter\Console\SafeConsoleApplication;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function (): never {
    throw new RuntimeException('Console runtime warning.');
});
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $container = require dirname(__DIR__).'/config/services.php';
    exit($container->get(SafeConsoleApplication::class)->run());
} catch (Throwable) {
    fwrite(STDERR, "Console failed or its outcome is uncertain; inspect trusted persisted state.\n");
    exit(1);
}
