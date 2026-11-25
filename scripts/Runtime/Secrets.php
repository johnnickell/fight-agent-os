<?php

declare(strict_types=1);

namespace Fight\Runtime;

/**
 * Class Secrets
 */
final class Secrets
{
    private const APPLICATION_KEYS = ['APP_ACCESS_TOKEN_CONFIG', 'APP_CSRF_MAC_KEY', 'APP_AUTH_RATE_KEY',
        'APP_ACTIVATION_RATE_KEY', 'APP_CREDENTIAL_DELIVERY_KEY'];

    private const REQUIRED = [...self::APPLICATION_KEYS, 'POSTGRES_PASSWORD', 'POSTGRES_TEST_PASSWORD'];

    /**
     * Generates independent disposable worktree secrets and environment settings
     */
    public static function worktree(string $state, string $host): void
    {
        $origin = 'https://' . $host;
        $values = [
            'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_AUTH_TRANSPORT' => 'direct',
            'APP_AUTH_TRUSTED_PROXIES' => '[]', 'APP_BROWSER_ORIGIN' => $origin,
            'APP_ACTIVATION_ORIGIN' => $origin, 'APP_ACTIVATION_PATH' => '/activate',
            'APP_PASSWORD_RESET_ORIGIN' => $origin, 'APP_PASSWORD_RESET_PATH' => '/reset-password/complete',
            'APP_CREDENTIAL_DELIVERY_ADAPTER' => 'null', 'POSTGRES_DB' => 'agent_os', 'POSTGRES_USER' => 'agent_os',
            'POSTGRES_TEST_DB' => 'agent_os_test', 'POSTGRES_TEST_USER' => 'agent_os_test',
            'FIGHT_AGENT_OS_USER' => posix_getuid() . ':' . posix_getgid(),
        ];
        foreach (self::REQUIRED as $key) {
            $values[$key] = bin2hex(random_bytes(str_starts_with($key, 'POSTGRES_') ? 24 : 32));
        }
        $values['APP_ACCESS_TOKEN_CONFIG'] = Native::json([
            'algorithm' => 'HS256', 'issuer' => $origin, 'audience' => 'agent-os-api', 'active_kid' => 'local',
            'keys' => [['kid' => 'local', 'secret_hex' => bin2hex(random_bytes(32))]],
        ]);
        self::provision($state . '/secrets', $values);
        $values = array_diff_key($values, array_flip(self::REQUIRED));
        $values['FIGHT_RUNTIME_SECRETS_DIR'] = $state . '/secrets';
        $content = '';
        foreach ($values as $key => $value) {
            $content .= $key . "='" . str_replace("'", "\\'", $value) . "'\n";
        }
        Native::writePrivate($state . '/task.env', $content);
    }

    /**
     * Provisions separate service secrets without rotating existing credentials
     *
     * @phpstan-param array<string,string> $values
     */
    public static function provision(string $directory, array $values): void
    {
        Native::require(Native::canonical($directory) === $directory, 'Secret directory alias refused');
        Native::mkdir($directory);
        $existing = 0;
        foreach (['application.json', 'tooling.json', 'postgres', 'postgres_test', 'seaweedfs.json'] as $name) {
            $path = $directory . '/' . $name;
            Native::require(!is_link($path), 'Secret file alias refused');
            if (file_exists($path)) {
                Native::require(is_file($path), 'Expected a regular installation secret file');
                ++$existing;
            }
        }
        if ($existing > 0) {
            Native::require($existing === 5, 'Partial secret setup; inspect privately before continuing');
            echo "Existing installation secrets retained; no rotation performed.\n";

            return;
        }
        foreach (self::REQUIRED as $key) {
            Native::require(isset($values[$key]) && $values[$key] !== '', 'Configure independent application keys and database passwords before explicit runtime setup');
        }
        $app = array_intersect_key($values, array_flip(self::APPLICATION_KEYS));
        $urls = [];
        foreach (['postgres' => 'agent_os', 'postgres_test' => 'agent_os_test'] as $host => $default) {
            $prefix = strtoupper($host);
            $user = $values[$prefix . '_USER'] ?? $default;
            $name = $values[$prefix . '_DB'] ?? $user;
            $password = $values[$prefix . '_PASSWORD'];
            $urls[$host] = 'postgresql://' . rawurlencode($user) . ':' . rawurlencode($password) . '@' . $host . ':5432/' . rawurlencode($name);
            Native::writePrivate($directory . '/' . $host, $password);
        }
        $app['DATABASE_URL'] = $urls['postgres'];
        Native::writePrivate($directory . '/application.json', Native::json($app) . "\n");
        Native::writePrivate($directory . '/tooling.json', Native::json($app + ['TEST_DATABASE_URL' => $urls['postgres_test']]) . "\n");
        $identity = ['name' => 'installation-storage', 'credentials' => [[
            'accessKey' => bin2hex(random_bytes(16)), 'secretKey' => bin2hex(random_bytes(32)),
        ]], 'actions' => ['Admin', 'Read', 'Write', 'List', 'Tagging']];
        Native::writePrivate($directory . '/seaweedfs.json', Native::json(['identities' => [$identity]]) . "\n");
        echo "Created service-specific ignored secrets; values were not printed.\n";
    }
}
