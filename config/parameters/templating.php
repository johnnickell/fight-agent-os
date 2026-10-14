<?php

declare(strict_types=1);

use Fight\Common\Application\Service\Container;
use Twig\Loader\FilesystemLoader;

return static function (Container $container): void {
    $directory = $container['app.templates_dir'];
    $container['templating.paths'] = [
        FilesystemLoader::MAIN_NAMESPACE => [$directory],
        'client'                         => [$directory.'/client']
    ];
    $container['templating.twig_options'] = [
        'debug'            => false,
        'charset'          => 'UTF-8',
        'cache'            => false,
        'auto_reload'      => true,
        'strict_variables' => true,
        'autoescape'       => 'name',
        'optimizations'    => -1
    ];
};
