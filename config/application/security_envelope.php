<?php

declare(strict_types=1);

use App\Adapter\Http\Middleware\Api\SameOriginApi;
use App\Adapter\Http\Middleware\CanonicalTransport;
use App\Adapter\Http\Middleware\SecurityHeaders;
use App\Adapter\Security\HmacCsrfProofs;
use App\Adapter\Security\HttpSecurityConfiguration;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(HttpSecurityConfiguration::class, static function (): HttpSecurityConfiguration {
        $environment = [];
        foreach (HttpSecurityConfiguration::inputs() as $name) {
            $environment[$name] = getenv($name);
        }

        return new HttpSecurityConfiguration($environment);
    });
    $container->set(SecurityHeaders::class, static fn(Container $container): SecurityHeaders =>
        new SecurityHeaders($container->get(HttpSecurityConfiguration::class)->production));
    $container->set(CanonicalTransport::class, static fn(Container $container): CanonicalTransport =>
        new CanonicalTransport(
            $container->get(HmacCsrfProofs::class),
            $container->get(HttpSecurityConfiguration::class)->production
        ));
    $container->set(SameOriginApi::class, static fn(Container $container): SameOriginApi =>
        new SameOriginApi($container->get(HmacCsrfProofs::class)));
};
