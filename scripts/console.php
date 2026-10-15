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
    $composition = 'console_invitation.php';
    if (in_array('user:bootstrap-super-admin', array_slice($_SERVER['argv'] ?? [], 1), true)) {
        $composition = 'bootstrap_super_admin.php';
    }
    $configure = require dirname(__DIR__).'/config/application/'.$composition;
    $configure($container);
    exit($container->get(SafeConsoleApplication::class)->run());
} catch (Throwable) {
    fwrite(STDERR, "Console failed or its outcome is uncertain; inspect trusted persisted state.\n");
    exit(1);
}
