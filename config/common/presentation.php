<?php

declare(strict_types=1);

use Fight\Common\Application\Serialization\JsonSerializer;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Serialization\Serializer;

return static function (Container $container): void {
    $container->set(Serializer::class, static function (): Serializer {
        return new JsonSerializer();
    });
};
