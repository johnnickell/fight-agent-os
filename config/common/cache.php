<?php

declare(strict_types=1);

use Fight\Common\Adapter\Cache\PsrCache;
use Fight\Common\Application\Service\Container;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

return static function (Container $container): void {
    $container->set(CacheItemPoolInterface::class, static function (): CacheItemPoolInterface {
        return new ArrayAdapter();
    });
    $container->set(CacheInterface::class, static function (Container $container): CacheInterface {
        return new Psr16Cache($container->get(CacheItemPoolInterface::class));
    });
    $container->set(PsrCache::class, static function (Container $container): PsrCache {
        return new PsrCache($container->get(CacheItemPoolInterface::class), $container->get(LoggerInterface::class));
    });
};
