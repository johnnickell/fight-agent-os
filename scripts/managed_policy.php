<?php

declare(strict_types=1);

use App\Adapter\Console\ManagedPolicyConsole;
use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;

// Suppress engine diagnostics as well as caught failures at this operational boundary.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function (): never {
    throw new RuntimeException('Managed policy runtime warning.');
});
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $arguments = array_slice($_SERVER['argv'], 1);
    if ($arguments !== ['preview', '--internal'] && $arguments !== ['apply', '--internal']) {
        fwrite(STDERR, ManagedPolicyConsole::USAGE);
        exit(2);
    }
    $container = require dirname(__DIR__).'/config/services.php';
    $console = new ManagedPolicyConsole(
        InstallationManagedPolicy::create(),
        $container->get(QueryBus::class),
        $container->get(SynchronousCommandBus::class)
    );
    exit($console->run(
        $arguments,
        static function (string $message): void {
            fwrite(STDOUT, $message);
        },
        static function (string $message): void {
            fwrite(STDERR, $message);
        }
    ));
} catch (Throwable) {
    fwrite(STDERR, "Managed policy operation failed; no private diagnostics are emitted.\n");
    exit(1);
}
