<?php

declare(strict_types=1);

use App\Adapter\Console\SafeConsoleApplication;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

require dirname(__DIR__).'/vendor/autoload.php';
$container = require dirname(__DIR__).'/config/services.php';
// Native CI's separately prepared disposable catcher is loopback-only. This
// fixture changes a transport registration, never the production provider graph.
if (getenv('BROWSER_SMOKE_NATIVE') === '1') {
    $container->set(SmtpTransport::class, static function (): SmtpTransport {
        $transport = new EsmtpTransport('127.0.0.1', 1025, false);
        $transport->setAutoTls(false);

        return $transport;
    });
}
$application = $container->get(SafeConsoleApplication::class);
$application->setAutoExit(false);
exit($application->run(new ArrayInput([
    'command'             => 'user:bootstrap-super-admin',
    '--internal'          => true,
    '--confirm-bootstrap' => true,
    '--operator'          => UserId::generate()->toString(),
    '--email'             => 'browser-smoke@example.test'
]), new NullOutput()));
