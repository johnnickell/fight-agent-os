<?php

declare(strict_types=1);

use App\Adapter\Authorization\JsonManagedPolicy;
use App\Domain\Authorization\ManagedPolicyCatalog;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(ManagedPolicyCatalog::class, static fn(): ManagedPolicyCatalog => new JsonManagedPolicy());
    $container->set(ManagedPolicy::class, static fn(Container $container): ManagedPolicy =>
        $container->get(ManagedPolicyCatalog::class)->load());
};
