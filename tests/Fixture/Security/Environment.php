<?php

declare(strict_types=1);

namespace Tests\Fixture\Security;

final class Environment
{
    public const HMAC_KEY = 'test-hmac-key';
    public const HMAC_SECRET = 'a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1';

    public static function apply(): void
    {
        self::set('APP_HMAC_KEY', self::HMAC_KEY);
        self::set('APP_HMAC_SECRET', self::HMAC_SECRET);
    }

    public static function clear(string $name): void
    {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }

    private static function set(string $name, string $value): void
    {
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
