<?php

declare(strict_types=1);

namespace Fight\Runtime;

use RuntimeException;

/**
 * Class Startup
 */
final class Startup
{
    /**
     * Starts the selected application after configuration and schema checks
     *
     * @phpstan-param array<string,string|true> $options
     */
    public static function up(Installation $installation, array $options): int
    {
        $checkout = $installation->checkout;
        $build = isset($options['--build-only']);
        $isolated = $installation->isolated;
        // Validate the saved approval model before preparing or starting anything.
        $roots = $build ? null : new ApprovedRoots($installation);
        $task = isset($options['--task']) ? (string) $options['--task'] : null;
        $registration = DomainBinding::ensure($installation, $task, isset($options['--unregistered-worktree']));
        $shared = isset($options['--shared-ingress']) || (!$isolated && $registration !== null);
        $installation->admitStorage();
        $fallback = ($isolated ? $installation->project : 'fight-agent-os').'.localhost';
        $host = $registration['host'] ?? $fallback;
        if ($isolated) {
            $installation->prepare($host);
        }
        $args = $installation->compose();
        $env = Installation::environment($isolated);
        if ($build) {
            return Native::process([...$args, '--profile', 'https', 'build'], $checkout, $env)[0];
        }
        if ($isolated || $shared) {
            Ingress::prepare($installation, $host);
            $args = $installation->compose($shared);
            $url = 'https://'.$host;
        } else {
            self::directTls($installation);
            $url = 'https://localhost:18443';
        }
        Native::require(is_file($checkout.'/vendor/autoload.php'), 'Install locked dependencies explicitly before startup');
        foreach (['var', 'var/client/build', 'var/client/shared-react', 'packages/react/dist', 'var/openapi', 'var/validations', '.runs/cache/client'] as $path) {
            Native::mkdir($checkout.'/'.$path);
        }
        Native::run([...$args, '--profile', 'https', 'build'], $checkout, $env);
        Native::run([...$args, 'up', '--detach', '--no-build', '--wait', '--wait-timeout', '120', 'web', 'postgres', 'postgres_test', 'seaweedfs'], $checkout, $env);
        Native::run([...$args, 'exec', '-T', 'web', 'php', 'vendor/bin/doctrine-migrations', 'up-to-date', '--no-interaction'], $checkout, $env);
        $roots->reconfigure(static function (?string $model, callable $observe) use ($args, $checkout, $env): void {
            $started = false;
            try {
                $approved = $model === null ? [] : ['--file', '-'];
                Native::run([...$args, ...$approved, '--profile', 'https', '--profile', 'mail', 'up', '--detach', '--no-build', '--wait', '--wait-timeout', '120'], $checkout, $env, input: $model);
                $started = true;
                $observe();
            } catch (RuntimeException | \JsonException | \ValueError | \TypeError $error) {
                if ($started || $model !== null) {
                    try {
                        Native::run([...$args, '--profile', 'https', 'stop', 'https_php'], $checkout, $env, capture: true, timeout: 30);
                    } catch (RuntimeException $stop) {
                        $reason = $error instanceof RuntimeException ? $error->getMessage() : 'Invalid startup observation';
                        throw new RuntimeException($reason.'; FPM stop failed ('.$stop->getMessage().'); exposure may remain', previous: $error);
                    }
                }
                throw $error;
            }
        });
        Native::run([...$args, '--profile', 'https', 'exec', '-T', 'https', 'nginx', '-t'], $checkout, $env);
        Native::run([...$args, '--profile', 'https', 'exec', '-T', 'https', 'nginx', '-s', 'reload'], $checkout, $env);
        if ($isolated || $shared) {
            Ingress::live($installation->main, $host);
        } else {
            $address = Native::run([...$args, '--profile', 'https', 'port', 'https', '8443'], $checkout, $env, capture: true);
            Native::require(preg_match('/^127\.0\.0\.1:([0-9]+)$/D', $address, $port) === 1, 'HTTPS requires a loopback port mapping');
            Ingress::probe($checkout, $url, ['--connect-to', 'localhost:18443:127.0.0.1:'.$port[1]], false);
        }
        echo $url."\n";

        return 0;
    }

    /**
     * Checks regular local TLS configuration without enrolling trust
     */
    private static function directTls(Installation $installation): void
    {
        $root = $installation->checkout;
        Native::require(is_file($root.'/.env') && !is_link($root.'/.env'), 'Create a regular .env file before HTTPS setup');
        Native::run(['git', 'check-ignore', '--quiet', '.env'], $root);
        $settings = Native::query([...$installation->compose(), '--profile', 'https', 'config', '--format', 'json'], $root, Installation::environment());
        $settings = $settings['services']['https_php']['environment'] ?? [];
        $secret = $installation->state().'/secrets/application.json';
        if (is_file($secret)) {
            $settings = json_decode(Native::read($secret), true, flags: JSON_THROW_ON_ERROR) + $settings;
        }
        Native::require(
            is_string($settings['APP_CSRF_MAC_KEY'] ?? null) && preg_match('/^(?:[0-9a-f]{2}){32,}$/D', $settings['APP_CSRF_MAC_KEY']) === 1,
            'Configure a valid independent APP_CSRF_MAC_KEY before HTTPS setup'
        );
        Native::require(
            ($settings['APP_BROWSER_ORIGIN'] ?? null) === 'https://localhost:18443',
            'Local HTTPS requires APP_BROWSER_ORIGIN=https://localhost:18443. Run ./bin/https setup or correct the effective Compose origin, then ./bin/up.'
        );
        Ingress::verifyKey($root, 'openssl', $root.'/var/https/certs/localhost.pem', $root.'/var/https/certs/localhost-key.pem');
    }
}
