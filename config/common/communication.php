<?php

declare(strict_types=1);

use Fight\Common\Adapter\Mail\Null\NullMailTransport;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Adapter\Sms\Null\NullSmsTransport;
use Fight\Common\Adapter\Socket\MercureHubPublisher;
use Fight\Common\Adapter\Socket\PrivateMercureHubPublisher;
use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Service\Container;
use Fight\Common\Application\Sms\SmsService;
use Fight\Common\Application\Sms\Transport\SmsTransport;
use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Socket\Publisher;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;

return static function (Container $container): void {
    $container->set(MailerInterface::class, static function (): MailerInterface {
        return new Mailer(Transport::fromDsn(getenv('MAILER_DSN') ?: 'null://null'));
    });
    $container->set(MailTransport::class, static function (Container $container): MailTransport {
        return getenv('MAILER_DSN')
            ? new SymfonyMailTransport($container->get(MailerInterface::class))
            : new NullMailTransport();
    });
    $container->set(MailService::class, static function (Container $container): MailService {
        return new MailService($container->get(MailTransport::class), new SymfonyMailFactory());
    });
    $container->set(SmsTransport::class, static function (): SmsTransport {
        return new NullSmsTransport();
    });
    $container->set(SmsService::class, static function (Container $container): SmsService {
        return new SmsService($container->get(SmsTransport::class));
    });
    $container->set(HubInterface::class, static function (): HubInterface {
        $url = getenv('MERCURE_URL');
        if ($url) {
            $token = getenv('MERCURE_JWT');
            if (!is_string($token) || $token === '') {
                throw new RuntimeException('MERCURE_JWT must be configured when MERCURE_URL is set.');
            }

            return new Hub($url, new StaticTokenProvider($token));
        }

        return new MockHub(
            'http://localhost/.well-known/mercure',
            new StaticTokenProvider('local-mercure-token'),
            static function (): string {
                return 'local';
            }
        );
    });
    $container->set(Publisher::class, static function (Container $container): Publisher {
        return new MercureHubPublisher($container->get(HubInterface::class));
    });
    $container->set(PrivatePublisher::class, static function (Container $container): PrivatePublisher {
        return new PrivateMercureHubPublisher($container->get(HubInterface::class));
    });
};
