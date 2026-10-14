<?php

declare(strict_types=1);

use App\Adapter\Console\OrdinaryInvitationConsole;
use App\Application\Invitation\InviteOrdinaryUser;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function (): never {
    throw new RuntimeException('Invitation runtime warning.');
});
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $console = new OrdinaryInvitationConsole(static function (): InviteOrdinaryUser {
        $container = require dirname(__DIR__).'/config/services.php';
        $compose = require dirname(__DIR__).'/config/application/console_invitation.php';

        return $compose($container);
    });
    /** @var list<string> $arguments */
    $arguments = array_slice($_SERVER['argv'], 1);
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
    fwrite(STDERR, "Invitation failed or its outcome is uncertain; inspect trusted persisted state.\n");
    exit(1);
}
