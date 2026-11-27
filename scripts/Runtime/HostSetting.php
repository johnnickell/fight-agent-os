<?php

declare(strict_types=1);

namespace Fight\Runtime;

use RuntimeException;

/**
 * Class HostSetting
 *
 * Reads only the allowlisted host setting without starting the application
 */
final class HostSetting
{
    /**
     * Resolves a literal PROJECT_BASE with safe diagnostics
     *
     * @phpstan-param list<string> $arguments
     */
    public static function run(array $arguments): int
    {
        if ($arguments !== ['PROJECT_BASE']) {
            fwrite(STDERR, "host setting: unsupported request; use ./bin/env PROJECT_BASE\n");

            return 2;
        }
        try {
            $root = self::invokingRoot();
            $override = getenv('PROJECT_BASE');
            $value = $override === false || $override === '' ? self::fromDotenv($root) : $override;
            Native::require(
                $value !== null && preg_match('/[^\s\x1C-\x1F]/u', $value) !== 0,
                'missing or empty value'
            );
            Native::require(
                preg_match('//u', $value) === 1 && preg_match('/[\x00-\x1F\x7F]/', $value) === 0,
                'malformed value'
            );
            fwrite(STDOUT, $value."\n");
        } catch (RuntimeException $error) {
            fwrite(STDERR, 'PROJECT_BASE: '.$error->getMessage()."\n");

            return 2;
        }

        return 0;
    }

    /**
     * Resolves the invoking checkout independently of the installed command and ambient Git settings
     */
    private static function invokingRoot(): string
    {
        $cwd = getcwd();
        Native::require($cwd !== false, 'invoking checkout unavailable');
        $env = array_filter(
            getenv(),
            static fn (string $key): bool => !str_starts_with($key, 'GIT_'),
            ARRAY_FILTER_USE_KEY
        );
        try {
            [$exit, $stdout] = Native::process(
                ['git', '--no-optional-locks', 'rev-parse', '--show-toplevel'],
                $cwd,
                $env,
                capture: true
            );
        } catch (RuntimeException) {
            throw new RuntimeException('invoking checkout unavailable');
        }
        $root = rtrim($stdout, "\n");
        Native::require(
            $exit === 0 && $root !== '' && preg_match('//u', $root) === 1 && strpbrk($root, "\r\n") === false,
            'invoking checkout unavailable'
        );

        return $root;
    }

    /**
     * Validates assignments and selects only the literal PROJECT_BASE value
     */
    private static function fromDotenv(string $root): ?string
    {
        $content = @file_get_contents($root.'/.env');
        Native::require(is_string($content) && preg_match('//u', $content) === 1, 'dotenv missing or unreadable');
        Native::require(!str_starts_with($content, "\u{FEFF}"), 'malformed dotenv');
        $keys = [];
        $selected = null;
        $lines = explode("\n", $content);
        foreach ($lines as $index => $raw) {
            // Accept CR only as part of CRLF, not as a lone final terminator.
            if ($index < count($lines) - 1 && str_ends_with($raw, "\r")) {
                $raw = substr($raw, 0, -1);
            }
            Native::require(preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $raw) === 0, 'malformed dotenv');
            $line = trim($raw, " \t");
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            Native::require(
                preg_match('/^([A-Za-z_][A-Za-z0-9_]*)[ \t]*=(.*)$/D', $line, $match) === 1,
                'malformed dotenv'
            );
            [, $key, $text] = $match;
            Native::require(!isset($keys[$key]), 'duplicate dotenv key');
            $keys[$key] = true;
            $value = self::parseValue($text);
            Native::require(preg_match('/[\x00-\x1F\x7F]/', $value) === 0, 'malformed dotenv');
            if ($key === 'PROJECT_BASE') {
                $selected = $value;
            }
        }

        return $selected;
    }

    /**
     * Parses one quoted or unquoted value without expansion or escape processing
     */
    private static function parseValue(string $text): string
    {
        $trimmed = trim($text, " \t");
        if (str_starts_with($trimmed, "'") || str_starts_with($trimmed, '"')) {
            $end = strpos($trimmed, $trimmed[0], 1);
            Native::require($end !== false, 'malformed dotenv');
            $suffix = substr($trimmed, $end + 1);
            Native::require(
                trim($suffix, " \t") === '' || preg_match('/^[ \t]+#/', $suffix) === 1,
                'malformed dotenv'
            );

            return substr($trimmed, 1, $end - 1);
        }
        $value = trim(preg_split('/[ \t]+#/', $text, 2)[0] ?? $text, " \t");
        Native::require(strpbrk($value, "'\"") === false, 'malformed dotenv');

        return $value;
    }
}
