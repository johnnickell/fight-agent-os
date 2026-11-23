<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Retargets the checked-in gateway without changing its asset allowlist or FastCGI authority
 */
$smokeGateway = static function (string $configuration, string $mode, string $root): string {
    $live = 'root /app/var/client;';
    $checked = 'rewrite ^/build/(.*)$ /production/$1 break;'."\n        root /app/var/client/check;";
    if (substr_count($configuration, $live) === 3 && !str_contains($configuration, $checked)) {
        $configuration = str_replace($live, $checked, $configuration);
    } elseif (substr_count($configuration, $checked) !== 3 || str_contains($configuration, $live)) {
        throw new RuntimeException('Unrecognized asset locations.');
    }
    $old = 'fastcgi_pass https_php:9000;';
    $upstream = $mode === 'native' ? '127.0.0.1' : 'web';
    $new = 'fastcgi_pass '.$upstream.':19087;';
    if (substr_count($configuration, $old) + substr_count($configuration, $new) !== 1) {
        throw new RuntimeException('Unrecognized FastCGI location.');
    }
    $configuration = str_replace($old, $new, $configuration);
    if ($mode === 'shared') {
        return $configuration;
    }
    if ($mode === 'direct') {
        return str_replace('listen 8443 ssl', 'listen 18443 ssl', $configuration);
    }
    if (preg_match('~^/[a-zA-Z0-9/_.-]+$~D', $root) !== 1) {
        throw new RuntimeException('Unsupported native checkout path.');
    }

    return str_replace(
        ['listen 8443 ssl', '/app/public', '/app/var/client/check', '/etc/nginx/certs', '127.0.0.1:8081'],
        ['listen 127.0.0.1:18443 ssl', $root.'/public', $root.'/var/client/check', 'certs', '127.0.0.1:18081'],
        $configuration
    );
};

/**
 * Records the exact gateway and public CA without retaining private fixture data
 */
$smokeEnvelope = static function (
    string $state,
    string $topology,
    string $origin,
    string $address,
    string $fpm,
    string $gateway
): void {
    $ca = file_get_contents($state.'/rootCA.pem');
    if ($ca === false || str_contains($ca, 'PRIVATE KEY') || !str_contains($ca, 'BEGIN CERTIFICATE')) {
        throw new RuntimeException('A public CA is required.');
    }
    (new Filesystem())->dumpFile($state.'/envelope.json', json_encode([
        'origin'         => $origin,
        'address'        => $address,
        'fpm'            => $fpm,
        'gateway_sha256' => hash('sha256', $gateway),
        'ca_sha256'      => hash('sha256', $ca),
        'topology'       => $topology
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
};

/**
 * Owns runner-local gateway and catcher processes for exactly one verification command
 *
 * @param list<string> $command
 */
$runNativeSmoke = static function (string $root, array $command) use ($smokeGateway, $smokeEnvelope): int {
    if ($command === []) {
        throw new InvalidArgumentException('A verification command is required.');
    }
    $state = $root.'/var/browser-smoke';
    $filesystem = new Filesystem();
    $filesystem->mkdir($root.'/var');
    // Refuse old/unknown setup rather than cleaning it up or sharing its catcher.
    if (!@mkdir($state, 0700)) {
        throw new RuntimeException('Native browser setup path is occupied.');
    }
    $children = [];
    $result = 1;
    try {
        foreach ([1025, 8025, 18081, 18443, 19087] as $port) {
            $listener = @stream_socket_client('tcp://127.0.0.1:'.$port, timeout: 0.25);
            if (is_resource($listener)) {
                fclose($listener);
                throw new RuntimeException('A native fixture port is occupied.');
            }
        }
        $filesystem->mkdir($state.'/certs', 0700);
        $filesystem->mkdir($root.'/var/client/check');
        $template = file_get_contents($root.'/etc/docker/https/default.conf');
        if ($template === false) {
            throw new RuntimeException('Missing gateway template.');
        }
        $gateway = $smokeGateway($template, 'native', $root);
        // A private prefix and main config: no system nginx config, service or logs.
        $mimeTypes = getenv('FIGHT_BROWSER_SMOKE_MIME_TYPES') ?: '/etc/nginx/mime.types';
        if (preg_match('~^/[a-zA-Z0-9/_.-]+$~D', $mimeTypes) !== 1 || !is_readable($mimeTypes)) {
            throw new RuntimeException('Missing native gateway MIME types.');
        }
        $filesystem->dumpFile($state.'/nginx.conf', implode("\n", [
            'pid nginx.pid;', 'lock_file nginx.lock;', 'error_log /dev/null crit;', 'events {}', 'http {',
            'include '.$mimeTypes.';', 'client_body_temp_path client_temp;', 'proxy_temp_path proxy_temp;',
            'fastcgi_temp_path fastcgi_temp;', 'uwsgi_temp_path uwsgi_temp;', 'scgi_temp_path scgi_temp;', $gateway,
            '}', ''
        ]));
        $openssl = static function (array $arguments) use ($state): void {
            $process = new Process(['openssl', ...$arguments], $state, timeout: 15);
            $process->mustRun();
        };
        $openssl(['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1', '-subj',
            '/CN=Disposable CI Browser CA', '-keyout', 'certs/ca.key', '-out', 'rootCA.pem']);
        $openssl(['req', '-new', '-newkey', 'rsa:2048', '-nodes', '-subj', '/CN=localhost',
            '-addext', 'subjectAltName=DNS:localhost', '-keyout', 'certs/localhost-key.pem', '-out', 'certs/leaf.csr']);
        $openssl(['x509', '-req', '-in', 'certs/leaf.csr', '-CA', 'rootCA.pem', '-CAkey', 'certs/ca.key',
            '-CAcreateserial', '-days', '1', '-copy_extensions', 'copy', '-out', 'certs/localhost.pem']);
        $filesystem->remove([$state.'/certs/ca.key', $state.'/certs/leaf.csr', $state.'/rootCA.srl']);
        $smokeEnvelope($state, 'native-nginx', 'https://localhost:18443', '127.0.0.1', 'php-fpm8.5', $gateway);
        $nginx = ['nginx', '-e', '/dev/null', '-p', $state.'/', '-c', $state.'/nginx.conf'];
        (new Process([...$nginx, '-t'], timeout: 15))->mustRun();
        $children = [
            new Process([...$nginx, '-g', 'daemon off;'], timeout: null),
            new Process(['mailpit', '--listen', '127.0.0.1:8025', '--smtp', '127.0.0.1:1025', '--database',
                $state.'/mailpit.db'], env: ['MP_DISABLE_VERSION_CHECK' => 'true'], timeout: null)
        ];
        foreach ($children as $child) {
            $child->disableOutput();
            $child->start();
        }
        $deadline = microtime(true) + 10;
        do {
            foreach ($children as $child) {
                if (!$child->isRunning()) {
                    throw new RuntimeException('A native browser supplier exited during startup.');
                }
            }
            $probe = new Process(['curl', '--noproxy', '*', '--fail', '--silent', '--max-time', '1',
                '--output', '/dev/null', 'http://127.0.0.1:8025/api/v1/messages'], timeout: 2);
            $probe->run();
            if ($probe->isSuccessful()) {
                break;
            }
            usleep(25000);
        } while (microtime(true) < $deadline);
        if (!$probe->isSuccessful()) {
            throw new RuntimeException('Native catcher is not ready.');
        }
        // Qualify the exact TLS listener without invoking the application or database.
        $tlsProbe = new Process(
            ['curl', '--noproxy', '*', '--silent', '--retry', '3', '--retry-connrefused',
                '--max-time', '2', '--cacert', $state.'/rootCA.pem', '--resolve', 'localhost:18443:127.0.0.1',
                '--output', '/dev/null', '--write-out', '%{http_code}', 'https://localhost:18443/.fixture-readiness'],
            timeout: 10
        );
        $tlsProbe->mustRun();
        if ($tlsProbe->getOutput() !== '404' || !$children[0]->isRunning()) {
            throw new RuntimeException('Native HTTPS gateway is not ready.');
        }
        $verification = new Process($command, $root, ['BROWSER_SMOKE_NATIVE' => '1'], timeout: 2400);
        $result = $verification->run(static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });
        foreach ($children as $child) {
            if (!$child->isRunning()) {
                $result = 1;
            }
        }
    } finally {
        foreach (array_reverse($children) as $child) {
            $child->stop(5);
        }
        $filesystem->remove($state);
        fwrite(STDOUT, "Native browser services stopped; owned envelope and catcher storage removed.\n");
    }

    return $result;
};

try {
    $root = dirname(__DIR__);
    $arguments = $argv ?? [];
    $operation = $arguments[1] ?? '';
    if ($operation === 'native') {
        exit($runNativeSmoke($root, array_slice($arguments, 2)));
    }
    if ($operation === 'plan' && count($arguments) === 2) {
        $input = stream_get_contents(STDIN);
        $config = json_decode($input === false ? '' : $input, true, flags: JSON_THROW_ON_ERROR);
        $services = $config['services'];
        $mount = static function (string $service, string $target) use ($services): string {
            foreach ($services[$service]['volumes'] as $volume) {
                if ($volume['target'] === $target && $volume['type'] === 'bind') {
                    return $volume['source'];
                }
            }

            return '';
        };
        $https = $services['https'];
        $shared = ($https['environment']['FIGHT_LOCAL_INGRESS'] ?? '') === '1';
        $fields = [$config['name'], $mount('web', '/app/var'), $config['networks']['default']['name'],
            $https['image'], $mount('https', '/etc/nginx/certs'), $mount('https', '/etc/nginx/conf.d'),
            $shared ? $https['environment']['VIRTUAL_HOST'] : ''];
        foreach ($fields as $field) {
            if (strpbrk($field, "\r\n,") !== false) {
                throw new RuntimeException('Unsupported Compose field.');
            }
            fwrite(STDOUT, $field."\n");
        }
        exit(0);
    }
    if ($operation === 'front-door' && count($arguments) === 2) {
        $input = stream_get_contents(STDIN);
        $config = json_decode($input === false ? '' : $input, true, flags: JSON_THROW_ON_ERROR);
        $mode = $config['x-fight-front-door'] ?? null;
        if (!in_array($mode, ['external', 'independent', 'herd'], true)) {
            throw new RuntimeException('Unknown shared front door.');
        }
        fwrite(STDOUT, $mode."-front-door/shared-ingress/task-nginx\n");
        exit(0);
    }
    $mode = $arguments[2] ?? '';
    if ($operation === 'render' && in_array($mode, ['direct', 'shared'], true) && count($arguments) === 3) {
        $input = stream_get_contents(STDIN);
        if ($input === false) {
            throw new RuntimeException('Missing gateway input.');
        }
        fwrite(STDOUT, $smokeGateway($input, $mode, $root));
        exit(0);
    }
    if ($operation === 'envelope' && count($arguments) === 5) {
        $input = stream_get_contents(STDIN);
        if ($input === false || $input === '') {
            throw new RuntimeException('Missing gateway input.');
        }
        $smokeEnvelope(
            $root.'/var/browser-smoke',
            $arguments[2],
            $arguments[3],
            $arguments[4],
            '/usr/local/sbin/php-fpm',
            $input
        );
        exit(0);
    }
    throw new InvalidArgumentException('Unknown browser setup operation.');
} catch (Throwable) {
    fwrite(STDERR, "Browser setup or cleanup incomplete; no private configuration emitted.\n");
    exit(1);
}
