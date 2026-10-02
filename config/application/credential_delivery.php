<?php

declare(strict_types=1);

use App\Adapter\CredentialDelivery\InMemoryCredentialDeliveryProvider;
use App\Adapter\CredentialDelivery\NullCredentialDeliveryProvider;
use App\Adapter\CredentialDelivery\SodiumCredentialDeliveryCipher;
use App\Adapter\CredentialDelivery\SystemDeliveryClock;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\Common\Application\Service\Container;

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
    $container->set(Clock::class, static function (): Clock {
        return new SystemDeliveryClock();
    });
    $container->set(CredentialDeliveryProvider::class, static function () use (
        $environment,
        $selection
    ): CredentialDeliveryProvider {
        if (!in_array($environment, ['test', 'development', 'local'], true)) {
            throw new RuntimeException('A real credential delivery provider is required in this environment.');
        }

        return match ($selection) {
            'null' => new NullCredentialDeliveryProvider(),
            'memory' => new InMemoryCredentialDeliveryProvider(),
            default => throw new RuntimeException('Unsupported local credential delivery adapter.')
        };
    });
};
