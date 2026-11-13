<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$selected = $argv[1] ?? 'all';
$scenarios = ['fragment', 'reload', 'tabs', 'revocation', 'theme'];
if (count($argv ?? []) > 2 || ($selected !== 'all' && !in_array($selected, $scenarios, true))) {
    fwrite(STDERR, "Usage: php scripts/browser_smoke.php [all|fragment|reload|tabs|revocation|theme]\n");
    exit(2);
}
// Share backend coverage's lock: no overlapping guarded schema reset.
$reports = $root.'/.runs/build/reports/backend';
if (!is_dir($reports)) {
    mkdir($reports, 0755, true);
}
$lock = fopen($reports.'/phase.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another database verification phase is running.\n");
    exit(1);
}
$envelopeFile = $root.'/var/browser-smoke/envelope.json';
if (!is_file($envelopeFile)) {
    fwrite(STDERR, "Explicit browser gateway setup is required before acceptance.\n");
    exit(1);
}
$envelopeJson = file_get_contents($envelopeFile);
if ($envelopeJson === false) {
    fwrite(STDERR, "Browser gateway setup is unreadable.\n");
    exit(1);
}
$envelope = json_decode($envelopeJson, true, flags: JSON_THROW_ON_ERROR);
$run = $root.'/var/browser-smoke/run-'.bin2hex(random_bytes(12));
mkdir($run, 0700, true);
$origin = $envelope['origin'];
$fixtureId = bin2hex(random_bytes(16));
$environment = [
    'APP_ENV'                         => 'test',
    'APP_DEBUG'                       => 'false',
    'DATABASE_URL'                    => getenv('TEST_DATABASE_URL'),
    'APP_BROWSER_ORIGIN'              => $origin,
    'APP_ACTIVATION_ORIGIN'           => $origin,
    'APP_ACTIVATION_PATH'             => '/activate',
    'APP_PASSWORD_RESET_ORIGIN'       => $origin,
    'APP_PASSWORD_RESET_PATH'         => '/reset-password/complete',
    'APP_CREDENTIAL_DELIVERY_ADAPTER' => 'catcher',
    'APP_CSRF_MAC_KEY'                => bin2hex(random_bytes(32)),
    'APP_AUTH_RATE_KEY'               => bin2hex(random_bytes(32)),
    'APP_ACTIVATION_RATE_KEY'         => bin2hex(random_bytes(32)),
    'APP_CREDENTIAL_DELIVERY_KEY'     => bin2hex(random_bytes(32)),
    'APP_AUTH_TRANSPORT'              => 'direct',
    'APP_AUTH_TRUSTED_PROXIES'        => '[]',
    'APP_ACCESS_TOKEN_CONFIG'         => json_encode([
        'algorithm'  => 'HS256',
        'issuer'     => $origin,
        'audience'   => 'agent-os-api',
        'active_kid' => 'smoke',
        'keys'       => [['kid' => 'smoke', 'secret_hex' => bin2hex(random_bytes(32))]]
    ], JSON_THROW_ON_ERROR)
];
/**
 * Captures actual tracked/unignored bytes, modes, index and dirty status
 */
$snapshot = static function () use ($root): string {
    $git = static function (array $arguments) use ($root): string {
        $process = new Process(['git', '-c', 'safe.directory='.$root, ...$arguments], $root);
        $process->mustRun();

        return $process->getOutput();
    };
    $files = [];
    $paths = explode("\0", $git(['ls-files', '-z', '--cached', '--others', '--exclude-standard']));
    foreach (array_unique($paths) as $path) {
        $full = $root.'/'.$path;
        if ($path !== '') {
            $files[$path] = is_link($full) ? ['link' => readlink($full)] : (
                is_file($full) ? [hash_file('sha256', $full), fileperms($full)] : ['absent']
            );
        }
    }
    ksort($files);

    return hash('sha256', serialize([$files, $git(['status', '--porcelain=v1']), $git(['ls-files', '--stage'])]));
};
$before = $snapshot();
$results = [];
$server = null;
$exit = 1;
$reset = false;
$mailOwned = false;
$mailClean = false;
$started = microtime(true);
/**
 * Runs fixture commands without forwarding SQL, SMTP, private arguments or application diagnostics
 */
$runCommand = static function (array $command) use ($root, $environment): void {
    $process = new Process($command, $root, $environment);
    $process->setTimeout(120);
    $process->run();
    if (!$process->isSuccessful()) {
        throw new RuntimeException('Browser fixture prerequisite failed.');
    }
};
/**
 * Accesses only the explicitly prepared disposable catcher, retaining bodies in memory
 */
$mailbox = static function (bool $delete = false): int {
    $host = getenv('BROWSER_SMOKE_NATIVE') === '1' ? '127.0.0.1' : 'mailcatcher';
    $context = stream_context_create(['http' => ['method' => $delete ? 'DELETE' : 'GET', 'timeout' => 5]]);
    $body = file_get_contents('http://'.$host.':8025/api/v1/messages', false, $context);
    if ($body === false) {
        throw new RuntimeException('Disposable catcher unavailable.');
    }

    return $delete ? 0 : (int) json_decode($body, true, flags: JSON_THROW_ON_ERROR)['total'];
};
try {
    if ($mailbox() !== 0) {
        throw new RuntimeException('Disposable catcher is not empty; preserve unknown mail.');
    }
    $mailOwned = true;
    $connection = @stream_socket_client('tcp://127.0.0.1:19087', $errorCode, $errorMessage, 1);
    if ($connection !== false) {
        fclose($connection);
        throw new RuntimeException('Preserve unknown FastCGI listener; fixture port is occupied.');
    }
    $runCommand(['php', 'scripts/guarded_test_database.php', 'check']);
    foreach ($selected === 'all' ? $scenarios : [$selected] as $scenario) {
        $runCommand(['php', 'scripts/guarded_test_database.php', 'reset']);
        $reset = true;
        $runCommand(['php', 'vendor/bin/doctrine-migrations', 'migrate', '--no-interaction']);
        $runCommand(['php', 'scripts/browser_smoke_seed.php']);
        // Real FPM executes public/index.php behind the separately prepared owning
        // Nginx gateway. Its private port has no development supplier or fallback.
        // Correlate the actual HTTP supplier before any browser database operation.
        // This prepend adds only a random readiness header, never HTTPS metadata.
        file_put_contents($run.'/correlation.php', "<?php header('X-Browser-Smoke-Fixture: ".$fixtureId."');");
        file_put_contents($run.'/fpm.conf', implode("\n", [
            '[global]', 'error_log = /dev/null', 'daemonize = no',
            '[smoke]', 'user = root', 'group = root', 'listen = 0.0.0.0:19087',
            'pm = static', 'pm.max_children = 2', 'clear_env = no', 'catch_workers_output = no',
            'php_admin_flag[log_errors] = off', 'php_admin_flag[display_errors] = off',
            'php_admin_value[auto_prepend_file] = '.$run.'/correlation.php', ''
        ]));
        $server = new Process([
            $envelope['fpm'], '--nodaemonize', '--allow-to-run-as-root', '--fpm-config', $run.'/fpm.conf'
        ], $root, $environment);
        $server->disableOutput();
        $server->start();
        $runnerEnvironment = array_replace(array_fill_keys(array_keys(getenv()), false), [
            'PATH'                       => getenv('FIGHT_BUILD_TOOL_PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'HOME'                       => $run,
            'BROWSER_SMOKE_FIXTURE'      => $fixtureId,
            'BROWSER_SMOKE_ORIGIN'       => $origin,
            'BROWSER_SMOKE_ADDRESS'      => $envelope['address'],
            'BROWSER_SMOKE_CA'           => $root.'/var/browser-smoke/rootCA.pem',
            'TZ'                         => 'UTC',
            'npm_config_offline'         => 'true',
            'npm_config_update_notifier' => 'false',
            'BROWSER_SMOKE_NATIVE'       => getenv('BROWSER_SMOKE_NATIVE') ?: '0',
            'PLAYWRIGHT_BROWSERS_PATH'   => getenv('PLAYWRIGHT_BROWSERS_PATH') ?: '/ms-playwright'
        ]);
        $runner = new Process(
            ['node', 'scripts/browser-smoke.mjs', $scenario, $run],
            $root.'/client',
            $runnerEnvironment
        );
        $runner->setTimeout(120);
        $runner->run(static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });
        $serverAlive = $server->isRunning();
        $server->stop(2);
        $server = null;
        if (!$serverAlive) {
            throw new RuntimeException('The isolated FastCGI supplier exited.');
        }
        $pattern = '/Browser smoke PASS: ([a-z]+); ([0-9]+) assertions; ([0-9.]+)s; Chromium ([0-9.]+)/';
        if (!$runner->isSuccessful() || preg_match($pattern, $runner->getOutput(), $match) !== 1) {
            throw new RuntimeException('Critical browser smoke failed.');
        }
        $results[] = [
            'scenario'   => $match[1],
            'assertions' => (int) $match[2],
            'seconds'    => (float) $match[3],
            'chromium'   => $match[4]
        ];
    }
    $exit = 0;
} catch (Throwable) {
    fwrite(STDERR, "Critical browser smoke incomplete; no private fixture diagnostics are emitted.\n");
} finally {
    $server?->stop(2);
    if ($reset) {
        try {
            $runCommand(['php', 'scripts/guarded_test_database.php', 'reset']);
        } catch (Throwable) {
            fwrite(STDERR, "Browser test schema cleanup incomplete.\n");
            $exit = 1;
        }
    }
    if ($mailOwned) {
        try {
            $mailbox(true);
            $mailClean = $mailbox() === 0;
        } catch (Throwable) {
            fwrite(STDERR, "Browser catcher cleanup incomplete.\n");
        }
        if (!$mailClean) {
            $exit = 1;
        }
    }
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) {
            foreach (new FilesystemIterator($path) as $item) {
                assert($item instanceof SplFileInfo);
                $remove($item->getPathname());
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    };
    $remove($run);
    $unchanged = $before === $snapshot();
    if (!$unchanged) {
        fwrite(STDERR, "Browser phase changed source/index/status; no automatic restoration.\n");
        $exit = 1;
    }
    $report = $root.'/.runs/build/reports/browser-smoke';
    if (!is_dir($report)) {
        mkdir($report, 0755, true);
    }
    file_put_contents($report.'/summary.json', json_encode([
        'exit'             => $exit,
        'scenarios'        => $results,
        'source_unchanged' => $unchanged,
        'seconds'          => round(microtime(true) - $started, 3),
        'scratch_removed'  => !file_exists($run),
        'mailbox_empty'    => $mailClean,
        'topology'         => $envelope['topology'],
        'origin'           => $origin,
        'gateway_sha256'   => $envelope['gateway_sha256'],
        'ca_sha256'        => $envelope['ca_sha256'],
        'limits'           => 'Chromium/Linux; host mapping, not workstation DNS or production enrollment'
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    flock($lock, LOCK_UN);
    fclose($lock);
}
fwrite(STDOUT, sprintf("Critical browser smoke: exit %d, %.2fs\n", $exit, microtime(true) - $started));
exit($exit);
