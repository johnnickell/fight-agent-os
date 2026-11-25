<?php

declare(strict_types=1);

// These host tools run before Composer dependencies or application containers exist.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Fight\\Runtime\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $name = substr($class, strlen($prefix));
    if (preg_match('/^[A-Z][A-Za-z0-9]*$/D', $name) === 1 && is_file(__DIR__.'/'.$name.'.php')) {
        require __DIR__.'/'.$name.'.php';
    }
});
