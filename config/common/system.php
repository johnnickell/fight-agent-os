<?php

declare(strict_types=1);

use Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner;
use Fight\Common\Adapter\Routing\Slim\SlimUrlGenerator;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Scheduler\Scheduler;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Value\DateTime\Timezone;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Psr7\Uri;

return static function (Container $container): void {
    $container->set(ProcessRunner::class, static function (Container $container): ProcessRunner {
        return new SymfonyProcessRunner($container->get(LoggerInterface::class), delay: 1);
    });
    $container->set(Scheduler::class, static function (Container $container): Scheduler {
        $directory = sprintf('%s/var/scheduler', $container['app.project_dir']);
        $container->get(Filesystem::class)->mkdir($directory);

        return Scheduler::withProcessRunner(
            new Timezone('UTC'),
            $directory,
            $container->get(ProcessRunner::class),
            $container->get(LoggerInterface::class),
            $container->get(MailService::class)
        );
    });
    $container->set(UrlGenerator::class, static function (Container $container): UrlGenerator {
        return new SlimUrlGenerator($container->get(App::class)->getRouteCollector(), new Uri('http', 'localhost'));
    });
};
