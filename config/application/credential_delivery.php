<?php

declare(strict_types=1);

use App\Adapter\CredentialDelivery\CanonicalActivationLink;
use App\Adapter\CredentialDelivery\InMemoryCredentialDeliveryProvider;
use App\Adapter\CredentialDelivery\InvitationMailProvider;
use App\Adapter\CredentialDelivery\InvitationMailTemplate;
use App\Adapter\CredentialDelivery\NullCredentialDeliveryProvider;
use App\Adapter\CredentialDelivery\SodiumCredentialDeliveryCipher;
use App\Adapter\CredentialDelivery\SystemDeliveryClock;
use App\Adapter\Mail\CatcherMailDelivery;
use App\Application\Mail\MailDelivery;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\Common\Application\Service\Container;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

return static function (Container $container): void {
    $environment = getenv('APP_ENV');
    $selection = getenv('APP_CREDENTIAL_DELIVERY_ADAPTER') ?: 'null';

    $cipher = static function (string $purpose): SodiumCredentialDeliveryCipher {
        $key = getenv('APP_CREDENTIAL_DELIVERY_KEY');

        return new SodiumCredentialDeliveryCipher($key === false ? '' : $key, $purpose);
    };
    $container->set(InvitationDeliveryCipher::class, static function () use ($cipher): InvitationDeliveryCipher {
        return $cipher('activation');
    });
    $container->set(PasswordResetDeliveryCipher::class, static function () use ($cipher): PasswordResetDeliveryCipher {
        return $cipher('password_reset');
    });
    $container->set(Clock::class, static fn(): Clock => new SystemDeliveryClock());
    $container->set(CanonicalActivationLink::class, static fn(): CanonicalActivationLink => new CanonicalActivationLink(
        getenv('APP_ACTIVATION_ORIGIN') ?: '',
        getenv('APP_ACTIVATION_PATH') ?: ''
    ));
    $container->set(InvitationMailTemplate::class, static fn(Container $c): InvitationMailTemplate =>
        new InvitationMailTemplate($c->get(CanonicalActivationLink::class)));
    $container->set(SmtpTransport::class, static function (): SmtpTransport {
        // This fixed, private Compose endpoint is not a user-selectable DSN or production fallback.
        $transport = new EsmtpTransport('mailcatcher', 1025, false);
        $transport->setAutoTls(false);
        $stream = $transport->getStream();
        assert($stream instanceof SocketStream);
        $stream->setTimeout(5);

        return $transport;
    });
    $container->set(MailDelivery::class, static fn(Container $c): MailDelivery => new CatcherMailDelivery(
        $c->get(SmtpTransport::class),
        $environment === false ? '' : $environment
    ));
    $container->set(InvitationMailProvider::class, static fn(Container $c): InvitationMailProvider =>
        new InvitationMailProvider($c->get(InvitationMailTemplate::class), $c->get(MailDelivery::class)));
    $container->set(CredentialDeliveryProvider::class, static function (Container $c) use (
        $environment,
        $selection
    ): CredentialDeliveryProvider {
        if (!in_array($environment, ['test', 'development', 'local'], true) || $selection === 'catcher') {
            // Validate required secure inputs before a delivery-enabled runtime can mutate work.
            $c->get(InvitationDeliveryCipher::class);
            $c->get(CanonicalActivationLink::class);
        }
        if (!in_array($environment, ['test', 'development', 'local'], true)) {
            throw new RuntimeException('A real credential delivery provider is required in this environment.');
        }

        return match ($selection) {
            'catcher' => $c->get(InvitationMailProvider::class),
            'null' => new NullCredentialDeliveryProvider(),
            'memory' => new InMemoryCredentialDeliveryProvider(),
            default => throw new RuntimeException('Unsupported local credential delivery adapter.')
        };
    });
};
