<?php

declare(strict_types=1);

use Fight\Common\Adapter\Templating\TwigEngine;
use Fight\Common\Application\Service\Container;
use Fight\Common\Application\Templating\TemplateEngine;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

return static function (Container $container): void {
    $container->set(LoaderInterface::class, static function (Container $container): LoaderInterface {
        $loader = new FilesystemLoader();
        foreach ($container['templating.paths'] as $namespace => $directories) {
            foreach ($directories as $directory) {
                // Resolve incomplete template deployments as render failures, not bootstrap failures.
                if (is_dir($directory)) {
                    $loader->addPath($directory, $namespace);
                }
            }
        }

        return $loader;
    });
    $container->set(Environment::class, static function (Container $container): Environment {
        return new Environment($container->get(LoaderInterface::class), $container['templating.twig_options']);
    });
    $container->set(TemplateEngine::class, static function (Container $container): TemplateEngine {
        return new TwigEngine($container->get(Environment::class));
    });
};
