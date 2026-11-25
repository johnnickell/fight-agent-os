<?php

declare(strict_types=1);

namespace Fight\Runtime;

/**
 * Class Ingress
 *
 * Consumes the existing supplier without managing its trust, DNS or lifecycle
 */
final class Ingress
{
    private const PROJECT = 'fight-local-ingress';
    private const OWNER = 'io.fight.ingress.owner';
    private const NGINX = 'nginx:1.30.7-alpine@sha256:bef154735288bc38d99fac41ae63730684bcc661503673a04a0f9bc526ba8598';
    private const GENERATOR = 'nginxproxy/docker-gen:0.15.0@sha256:dec247f03aedb22b968e5a1d092bef09d78b85998dc08706f723e4210d553e5d';

    /**
     * Resolves the owned supplier state path without accepting aliases
     */
    private static function state(string $main): string
    {
        $state = $main . '/var/local-ingress';
        Native::require(Native::canonical($state) === $state, 'Aliased ingress state refused');

        return $state;
    }

    /**
     * Calculates the published ports allowed for the existing supplier mode
     *
     * @return list<string>
     */
    private static function publishedPorts(mixed $front, mixed $port): array
    {
        return match (true) {
            $front === 'external' && $port === null => [],
            $front === 'independent' && $port === 443 => ['127.0.0.1:80:80', '127.0.0.1:443:443'],
            $front === 'herd' && is_int($port) && $port >= 1024 && $port <= 65535 => ['127.0.0.1:' . $port . ':443'],
            default => throw new \RuntimeException('Independent ingress uses 443; Herd requires a high HTTPS port; external mode publishes no ports'),
        };
    }

    /**
     * Loads the explicitly configured supplier model
     *
     * @return array<string,mixed>
     */
    private static function model(string $main): array
    {
        $file = self::state($main) . '/compose.json';
        Native::require(is_file($file) && !is_link($file), 'Run explicit ./bin/ingress setup first');

        return json_decode(Native::read($file), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Reads and validates the configured front door and port
     *
     * @phpstan-param array<string,mixed> $model
     * @return array{mixed,mixed,list<string>}
     */
    private static function listeners(array $model): array
    {
        $front = $model['x-fight-front-door'] ?? 'independent';
        $port = array_key_exists('x-fight-https-port', $model) ? $model['x-fight-https-port'] : 443;

        return [$front, $port, self::publishedPorts($front, $port)];
    }

    /**
     * Qualifies the native supplier configuration, ownership and health
     */
    private static function ready(string $main): void
    {
        $state = self::state($main);
        $model = self::model($main);
        [$front, $port, $published] = self::listeners($model);
        $context = Native::query(['docker', 'context', 'inspect'], $main)[0]['Endpoints']['docker']['Host'] ?? '';
        Native::require(str_starts_with($context, 'unix://'), 'Only an explicit local Unix Docker socket is supported');
        $socket = substr($context, strlen('unix://'));
        Native::require(@filetype($socket) === 'socket', 'Local Docker socket unavailable');
        $limits = ['read_only' => true, 'tmpfs' => ['/tmp:rw,nosuid,noexec,size=32m'], 'cap_drop' => ['ALL'],
            'security_opt' => ['no-new-privileges:true'], 'mem_limit' => '128m', 'cpus' => 0.5,
            'pids_limit' => 64, 'restart' => 'unless-stopped', 'labels' => [self::OWNER => $main]];
        $expected = ['x-fight-owner' => $main, 'x-fight-front-door' => $front, 'x-fight-https-port' => $port,
            'services' => [
                'nginx' => $limits + ['image' => self::NGINX, 'user' => '0:0', 'cap_add' => ['CHOWN', 'SETUID', 'SETGID'],
                    'entrypoint' => ['nginx'], 'command' => ['-g', 'daemon off;'], 'ports' => $published, 'networks' => ['ingress'],
                    'volumes' => [self::mount($state . '/nginx.conf', '/etc/nginx/nginx.conf'), self::mount($state . '/routes', '/etc/nginx/routes'),
                        self::mount($state . '/certs', '/etc/nginx/certs'), self::mount($state . '/proxy-headers.conf', '/etc/nginx/proxy-headers.conf')],
                    'healthcheck' => ['test' => ['CMD', 'nginx', '-t'], 'interval' => '3s', 'timeout' => '3s', 'retries' => 10]],
                'docker-gen' => $limits + ['image' => self::GENERATOR, 'network_mode' => 'none', 'command' => [
                    '-endpoint', 'unix:///tmp/docker.sock', '-watch', '-wait', '500ms:2s', '-notify-sighup', self::PROJECT . '-nginx-1',
                    '/etc/docker-gen/routes.tmpl', '/etc/nginx/routes/routes.conf'], 'depends_on' => ['nginx' => ['condition' => 'service_healthy']],
                    'volumes' => [self::mount($socket, '/tmp/docker.sock'), self::mount($state . '/routes.tmpl', '/etc/docker-gen/routes.tmpl'),
                        self::mount($state . '/routes', '/etc/nginx/routes', false), self::mount($state . '/certs', '/etc/nginx/certs')]],
            ], 'networks' => ['ingress' => ['external' => true, 'name' => self::PROJECT]]];
        Native::require(self::ordered($model) === self::ordered($expected), 'Unknown/changed ingress configuration or Docker context; inspect privately, then explicitly setup');
        foreach (['nginx.conf', 'routes.tmpl', 'proxy-headers.conf'] as $name) {
            $file = $state . '/' . $name;
            Native::require(is_file($file) && !is_link($file) && Native::read($file) === Native::read(dirname(__DIR__, 2) . '/etc/docker/ingress/' . $name),
                'Ingress source/configuration changed; run explicit setup and up before app startup');
        }
        $networks = Native::lines(Native::text(['docker', 'network', 'ls', '-q', '--filter', 'name=^' . self::PROJECT . '$'], $main));
        Native::require(count($networks) === 1, 'Shared ingress absent; explicitly operate ./bin/ingress');
        $network = Native::query(['docker', 'network', 'inspect', $networks[0]], $main)[0];
        Native::require(($network['Labels'][self::OWNER] ?? null) === $main, 'Foreign ingress network; preserve it');
        $ids = Native::ids($main, 'containers', self::PROJECT);
        $items = $ids === [] ? [] : Native::query(['docker', 'inspect', ...$ids], $main);
        $services = [];
        foreach ($items as $item) {
            Native::require(($item['Config']['Labels'][self::OWNER] ?? null) === $main, 'Foreign ingress project; preserve it');
            Native::require($item['State']['Running'] === true && ($item['State']['Health']['Status'] ?? 'healthy') === 'healthy', 'Shared ingress services are not healthy');
            $services[] = $item['Config']['Labels']['com.docker.compose.service'];
        }
        sort($services);
        Native::require($services === ['docker-gen', 'nginx'], 'Shared ingress services not running; operate ./bin/ingress explicitly');
        Native::text(['docker', 'compose', '--project-directory', $state, '--project-name', self::PROJECT, '--env-file', '/dev/null',
            '--file', $state . '/compose.json', 'exec', '-T', 'nginx', 'nginx', '-t'], $main);
    }

    /**
     * Sorts mappings for exact comparison while preserving list order
     *
     * @phpstan-param array<mixed> $map
     * @return array<mixed>
     */
    private static function ordered(array $map): array
    {
        if (!array_is_list($map)) {
            ksort($map, SORT_STRING);
        }

        return array_map(static fn (mixed $value): mixed => is_array($value) ? self::ordered($value) : $value, $map);
    }

    /**
     * Builds an explicit mount against an existing supplier path
     *
     * @return array{type: string, source: string, target: string, read_only: bool, bind: array{create_host_path: false}}
     */
    private static function mount(string $source, string $target, bool $readOnly = true): array
    {
        return ['type' => 'bind', 'source' => $source, 'target' => $target, 'read_only' => $readOnly, 'bind' => ['create_host_path' => false]];
    }

    /**
     * Verifies a regular certificate/key pair without disclosing key material
     */
    public static function verifyKey(string $root, string $openssl, string $cert, string $key): void
    {
        foreach ([$cert, $key] as $path) {
            Native::require(is_file($path) && !is_link($path) && filesize($path) > 0, 'Missing regular TLS leaf/key files; prepare explicitly with ./bin/https setup');
        }
        Native::text([$openssl, 'x509', '-in', $cert, '-checkend', '86400', '-noout'], $root);
        Native::require(Native::text([$openssl, 'x509', '-in', $cert, '-pubkey', '-noout'], $root)
            === Native::text([$openssl, 'pkey', '-in', $key, '-passin', 'pass:', '-pubout'], $root), 'TLS leaf/key mismatch');
    }

    /**
     * Qualifies the exact name under the existing supplier CA
     */
    public static function checkLeaf(string $main, string $host): string
    {
        $zone = DomainBinding::namespace($host);
        Native::require($zone !== null || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.localhost$/D', $host) === 1, 'Invalid local name');
        $state = self::state($main);
        $directory = $state . '/certs/' . ($zone ?? $host);
        Native::require(Native::canonical($directory) === $directory, 'Aliased certificate directory refused');
        $cert = $directory . '/localhost.pem';
        $key = $directory . '/localhost-key.pem';
        $ca = $state . '/certs/rootCA.pem';
        Native::require(is_file($ca) && !is_link($ca) && filesize($ca) > 0, 'Missing regular actual-name CA; explicitly run ./bin/https setup --ingress');
        $openssl = getenv('FIGHT_INGRESS_OPENSSL') ?: 'openssl';
        foreach (['x509' => '-checkhost', 'verify' => '-verify_hostname'] as $command => $capability) {
            [, $out, $err] = Native::process([$openssl, $command, '-help'], $main, capture: true);
            Native::require(str_contains($out . $err, $capability), 'OpenSSL hostname-verification capability missing; explicitly set FIGHT_INGRESS_OPENSSL');
        }
        self::verifyKey($main, $openssl, $cert, $key);
        $san = Native::lines(Native::text([$openssl, 'x509', '-in', $cert, '-noout', '-ext', 'subjectAltName'], $main));
        $observed = explode(', ', trim(implode('', array_slice($san, 1))));
        $expected = $zone === null ? ['DNS:' . $host] : ['DNS:' . $zone, 'DNS:*.' . $zone];
        sort($observed);
        sort($expected);
        Native::require($observed === $expected, 'Leaf SANs must match the exact localhost name or the project base plus one-level wildcard');
        Native::require(str_contains(Native::text([$openssl, 'x509', '-in', $cert, '-checkhost', $host, '-noout'], $main), 'does match certificate'), 'Leaf does not cover the exact canonical hostname');
        Native::text([$openssl, 'verify', '-CAfile', $ca, '-verify_hostname', $host, $cert], $main);

        return $directory;
    }

    /**
     * Writes only the selected application's owned gateway override
     */
    public static function prepare(Installation $installation, string $host): void
    {
        self::ready($installation->main);
        $certs = self::checkLeaf($installation->main, $host);
        $state = $installation->isolated ? $installation->state() : self::state($installation->main) . '/main';
        $gateway = $state . '/gateway';
        Native::require(Native::canonical($gateway) === $gateway, 'Aliased application gateway state refused');
        $template = Native::read($installation->checkout . '/etc/docker/https/default.conf');
        Native::writePrivate($gateway . '/default.conf', strtr($template, ['localhost:18443' => $host, 'SERVER_PORT 18443' => 'SERVER_PORT 443',
            'SERVER_NAME localhost' => 'SERVER_NAME ' . $host, 'server_name localhost' => 'server_name ' . $host]));
        $assets = $installation->outputPath($installation->checkout . '/var/client/check');
        Native::mkdir($assets);
        $origin = ['APP_BROWSER_ORIGIN' => 'https://' . $host, 'APP_ACTIVATION_ORIGIN' => 'https://' . $host,
            'APP_PASSWORD_RESET_ORIGIN' => 'https://' . $host, 'APP_AUTH_TRANSPORT' => 'direct', 'APP_AUTH_TRUSTED_PROXIES' => '[]'];
        $services = ['https' => ['environment' => ['FIGHT_LOCAL_INGRESS' => '1', 'VIRTUAL_HOST' => $host, 'VIRTUAL_PORT' => '8443', 'VIRTUAL_PROTO' => 'https'],
            'networks' => ['default', 'ingress'], 'volumes' => [self::mount($gateway, '/etc/nginx/conf.d'), self::mount($certs, '/etc/nginx/certs'),
                self::mount($installation->outputPath($installation->checkout . '/var/client/build'), '/app/var/client/build'),
                self::mount($assets, '/app/var/client/check')]], 'web' => ['environment' => $origin], 'https_php' => ['environment' => $origin], 'mailcatcher' => []];
        Native::writePrivate($state . '/ingress.yaml', Installation::override($services, true) . "networks:\n  ingress: {external: true, name: fight-local-ingress}\n");
    }

    /**
     * Verifies actual supplier transport and the canonical HTTPS front door
     */
    public static function live(string $main, string $host): void
    {
        self::checkLeaf($main, $host);
        [$front, $port] = self::listeners(self::model($main));
        if ($front === 'herd') {
            self::probe($main, 'https://' . $host, ['--connect-to', $host . ':443:127.0.0.1:' . $port], true);
        }
        self::probe($main, 'https://' . $host, DomainBinding::namespace($host) === null ? ['--resolve', $host . ':443:127.0.0.1'] : [], true);
    }

    /**
     * Probes the root and CSRF endpoints with verification and bounded retries
     *
     * @phpstan-param list<string> $connection
     */
    public static function probe(string $root, string $origin, array $connection, bool $noProxy): void
    {
        foreach (['/', '/api/v1/auth/csrf'] as $path) {
            Native::run(['curl', ...($noProxy ? ['--noproxy', '*'] : []), '--fail', '--silent', '--show-error', '--retry', '5', '--retry-all-errors',
                '--connect-timeout', '3', '--max-time', '10', '--retry-max-time', '60', ...$connection, '--output', '/dev/null', $origin . $path], $root);
        }
    }
}
