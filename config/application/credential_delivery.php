<?php

declare(strict_types=1);

use App\Adapter\CredentialDelivery\InMemoryCredentialDeliveryProvider;
use App\Adapter\CredentialDelivery\NullCredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $environment = getenv('APP_ENV');
    $selection = getenv('APP_CREDENTIAL_DELIVERY_ADAPTER') ?: 'null';

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
