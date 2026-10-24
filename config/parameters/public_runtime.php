<?php

declare(strict_types=1);

use App\Adapter\Http\Web\PublicRuntimeConfiguration;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    // Validate diagnostics during composition, not only when a browser happens to request HTML.
    $configuration = PublicRuntimeConfiguration::parse(
        getenv('APP_ENV'),
        getenv('APP_DEBUG'),
        getenv('APP_SWAGGER_ALLOWED_ENVIRONMENTS')
    );
    $container->set(PublicRuntimeConfiguration::class, static fn(): PublicRuntimeConfiguration => $configuration);
};
