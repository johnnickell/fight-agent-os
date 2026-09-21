<?php

declare(strict_types=1);

use App\Adapter\Http\IndexAction;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(IndexAction::class, static function (): IndexAction {
        return new IndexAction();
    });
};
