<?php

declare(strict_types=1);

use Fight\Common\Application\Service\Container;

$container = new Container();

foreach (['parameters', 'common', 'application'] as $directory) {
    $modules = glob(__DIR__.'/'.$directory.'/*.php');
    if ($modules === false) {
        throw new RuntimeException('Service module discovery failed.');
    }
    sort($modules, SORT_STRING);
    foreach ($modules as $module) {
        /** @var callable(Container): void $configure */
        $configure = require $module;
        $configure($container);
    }
}

return $container;
