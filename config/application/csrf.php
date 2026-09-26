<?php

declare(strict_types=1);

use App\Adapter\Http\Middleware\Api\Auth\CsrfBootstrapGuard;
use App\Adapter\Security\HmacCsrfProofs;
use App\Adapter\Security\RandomCsrfNonceGenerator;
use App\Adapter\Security\SystemCsrfClock;
use App\Application\Security\CsrfClock;
use App\Application\Security\CsrfNonceGenerator;
use App\Application\Security\CsrfProofs;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(HmacCsrfProofs::class, static function (): HmacCsrfProofs {
        $key = getenv('APP_CSRF_MAC_KEY');
        $origin = getenv('APP_BROWSER_ORIGIN');

        return new HmacCsrfProofs($key === false ? '' : $key, $origin === false ? '' : $origin);
    });
    $container->set(CsrfProofs::class, static function (Container $container): CsrfProofs {
        return $container->get(HmacCsrfProofs::class);
    });
    $container->set(CsrfClock::class, static function (): CsrfClock {
        return new SystemCsrfClock();
    });
    $container->set(CsrfNonceGenerator::class, static function (): CsrfNonceGenerator {
        return new RandomCsrfNonceGenerator();
    });
    $container->set(CsrfBootstrapGuard::class, static function (Container $container): CsrfBootstrapGuard {
        return new CsrfBootstrapGuard(
            $container->get(HmacCsrfProofs::class),
            $container->get(JSendResponseFactory::class)
        );
    });

    // Fail at application boot, not on the first public request.
    $container->get(HmacCsrfProofs::class);
};
