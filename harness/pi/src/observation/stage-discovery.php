<?php

declare(strict_types=1);

/** Associate the fixed PHP monitor with its current native Pi context without controlling execution */
final class PassiveStageDiscovery
{
    private const REFUSALS = ['monitor-bootstrap-refused', 'monitor-witness-refused', 'monitor-worktree-refused',
        'monitor-birth-refused', 'monitor-lifetime-refused', 'monitor-capacity-eight', 'monitor-admission-refused'];
    private const LIMIT = 65536;
    private readonly int $monitor;
    private readonly float $deadline;
    private string $status = 'no-current-native-route';
    private string $pending = '';
    private bool $closed = false;
    private mixed $channel = null;
    private ?string $witness = null;
    private ?array $witnessFile = null;

    public function __construct(private readonly array $initial)
    {
        $this->monitor = posix_getppid();
        $this->deadline = self::now() + 3;
    }

    private static function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    private function readProgress(): void
    {
        $chunk = @fread(STDIN, 8192);
        if ($chunk === false || feof(STDIN)) {
            $this->closed = true;
        }
        if ($chunk !== false) {
            // Keep complete JSONL frames when dropping progress on backpressure.
            $this->pending .= $chunk;
            if (strlen($this->pending) > self::LIMIT) {
                // Drop complete records only before the admission reply. After
                // admission, closing is safer than cutting a partial socket write.
                if (is_resource($this->channel)) {
                    fclose($this->channel);
                    $this->channel = null;
                    $this->closed = true;
                } else {
                    $end = strpos($this->pending, "\n", strlen($this->pending) - self::LIMIT);
                    $this->pending = $end === false ? '' : substr($this->pending, $end + 1);
                }
            }
        }
    }

    private function remaining(float $maximum = 1): float
    {
        $this->readProgress();
        $remaining = $this->deadline - self::now();
        if ($this->closed || $remaining <= 0) {
            throw new RuntimeException('Observation deadline or closure');
        }
        return min($remaining, $maximum);
    }

    private function inspect(array $command): string
    {
        $end = self::now() + $this->remaining();
        $child = @proc_open($command, [['file', '/dev/null', 'r'], ['pipe', 'w'], ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($child)) {
            throw new RuntimeException('Process inspection unavailable');
        }
        $output = '';
        $exit = null;
        stream_set_blocking($pipes[1], false);
        try {
            while (!$this->closed && self::now() < $end && strlen($output) <= 1024 * 1024) {
                $this->readProgress();
                $output .= fread($pipes[1], 65536);
                $status = proc_get_status($child);
                if (!$status['running']) {
                    $exit ??= $status['exitcode'];
                    if (feof($pipes[1])) {
                        if ($exit === 0) {
                            return $output;
                        }
                        break;
                    }
                }
                usleep(5_000);
            }
            throw new RuntimeException('Process inspection incomplete');
        } finally {
            if ($exit === null) {
                proc_terminate($child, SIGKILL);
            }
            fclose($pipes[1]);
            proc_close($child);
        }
    }

    private function processIdentity(int $pid): string
    {
        return trim($this->inspect(['ps', '-p', (string) $pid, '-o', 'lstart=', '-o', 'command=']));
    }

    private static function privatePath(string $path, int $type): array
    {
        clearstatcache(true, $path);
        $file = @lstat($path);
        if (!$file || ($file['mode'] & 0170077) !== $type || $file['uid'] !== posix_getuid() || realpath($path) !== $path ||
            ($type === 0100000 && $file['size'] > 8192)) {
            throw new RuntimeException('Invalid observation runtime candidate');
        }
        return $file;
    }

    private function closestRoute(string $checkout): ?array
    {
        $parents = [];
        foreach (explode("\n", $this->inspect(['ps', '-axo', 'pid=,ppid='])) as $row) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $row, $matches)) {
                $parents[(int) $matches[1]] = (int) $matches[2];
            }
        }
        $ancestry = [];
        for ($pid = $parents[$this->monitor] ?? 1; $pid > 1 && count($ancestry) < 32; $pid = $parents[$pid] ?? 1) {
            if (isset($ancestry[$pid])) {
                return null;
            }
            $ancestry[$pid] = count($ancestry);
        }
        $roots = [];
        foreach (explode("\0", $this->inspect(['git', 'worktree', 'list', '--porcelain', '-z'])) as $record) {
            if (str_starts_with($record, 'worktree ')) {
                $roots[] = substr($record, 9);
            }
        }
        if (count($roots) > 32 || !in_array($checkout, $roots, true)) {
            return null;
        }
        while (!$this->closed && self::now() < $this->deadline) {
            $routes = [];
            foreach ($roots as $root) {
                $this->remaining();
                $directory = $root . '/var/terminal/native';
                try {
                    self::privatePath($directory, 0040000);
                    $count = 0;
                    foreach (new FilesystemIterator($directory) as $entry) {
                        $this->remaining();
                        if (++$count > 8) {
                            return null;
                        }
                        $path = $entry->getPathname();
                        self::privatePath($path, 0100000);
                        $route = json_decode((string) @file_get_contents($path, false, null, 0, 8193), true, 512, JSON_THROW_ON_ERROR);
                        $native = $route['native'] ?? [];
                        $pid = $native['pid'] ?? null;
                        $generation = $route['generation'] ?? null;
                        if (!is_int($pid) || !isset($ancestry[$pid]) || !is_int($native['started'] ?? null) ||
                            $this->initial['started'] < $native['started'] || !is_string($generation) || $entry->getFilename() !== "$pid-$generation.json" ||
                            $this->processIdentity($pid) !== ($native['process'] ?? null) || ($route['primary'] ?? null) !== realpath($root)) {
                            continue;
                        }
                        $expected = __DIR__ . '/custodian.mjs';
                        if (!is_int($route['pid'] ?? null) || !is_string($route['process'] ?? null) ||
                            $this->processIdentity($route['pid']) !== $route['process'] || !str_ends_with($route['process'], " $expected $generation")) {
                            $this->status = 'native-package-or-lifetime-mismatch';
                            continue;
                        }
                        $routes[] = [$ancestry[$pid], $route];
                    }
                } catch (Throwable) {
                    // Invalid or unavailable private path is not a candidate.
                    continue;
                }
            }
            if ($routes !== []) {
                usort($routes, static fn ($a, $b) => $a[0] <=> $b[0]);
                if (($routes[1][0] ?? null) === $routes[0][0]) {
                    $this->status = 'ambiguous-native-route';
                    return null;
                }
                return $routes[0][1];
            }
            usleep(100_000);
        }
        return null;
    }

    private static function encode(array $record): string
    {
        return json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function connect(string $checkout, array $route): bool
    {
        $pid = $this->monitor;
        $this->status = 'monitor-witness-unavailable';
        $token = bin2hex(random_bytes(32));
        $runtime = $checkout . '/var/terminal/monitors';
        @mkdir($runtime, 0700, true);
        self::privatePath($runtime, 0040000);
        $path = "$runtime/$pid-$token.json";
        $witness = self::encode(['pid' => $pid, 'process' => $this->processIdentity($pid), 'token' => $token,
            'nativePid' => $route['native']['pid'], 'nativeProcess' => $route['native']['process'], 'worktree' => $checkout,
            'identity' => $this->initial['identity'], 'heartbeat' => $this->initial['heartbeat'],
            'created' => (int) (microtime(true) * 1000), 'started' => $this->initial['started']]);
        $file = @fopen($path, 'xb');
        if (!$file) {
            return false;
        }
        $this->witness = $path;
        $this->witnessFile = fstat($file);
        try {
            if (fwrite($file, $witness) !== strlen($witness)) {
                return false;
            }
        } finally {
            fclose($file);
        }
        $this->status = 'monitor-endpoint-unavailable';
        $endpoint = $route['primary'] . '/var/terminal/s/' . substr(str_replace('-', '', $route['generation']), 0, 24) . '.sock';
        $info = self::privatePath($endpoint, 0140000);
        if ($info['dev'] !== ($route['endpoint']['dev'] ?? null) || $info['ino'] !== ($route['endpoint']['ino'] ?? null)) {
            return false;
        }
        $from = explode('/', $checkout);
        $to = explode('/', $endpoint);
        while ($from !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }
        $relative = str_repeat('../', count($from)) . implode('/', $to);
        $shorter = strlen($relative) < strlen($endpoint) ? $relative : $endpoint;
        $this->status = 'monitor-connect-unavailable';
        if (strlen($shorter) > (PHP_OS_FAMILY === 'Darwin' ? 103 : 107)) {
            return false;
        }
        $channel = @stream_socket_client('unix://' . $shorter, $errno, $error, $this->remaining(.5));
        if ($channel === false) {
            return false;
        }
        try {
            stream_set_blocking($channel, false);
            $this->status = 'monitor-bootstrap-send-unavailable';
            $bootstrap = self::encode(['type' => 'monitor', 'pid' => $pid, 'token' => $token, 'worktree' => $checkout]);
            if (@fwrite($channel, $bootstrap) !== strlen($bootstrap)) {
                return false;
            }
            $response = '';
            $end = self::now() + $this->remaining();
            $this->status = 'monitor-admission-reply-timeout';
            while (self::now() < $end && !$this->closed && strlen($response) < 128) {
                $this->readProgress();
                $chunk = @fread($channel, 128 - strlen($response));
                if ($chunk === false || ($chunk === '' && feof($channel))) {
                    $this->status = 'monitor-admission-transport-closed';
                    return false;
                }
                $response .= $chunk;
                if (str_contains($response, "\n")) {
                    $this->status = 'monitor-admission-reply-invalid';
                    $reply = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                    if (($reply['observed'] ?? null) === true && ($reply['pid'] ?? null) === $pid && count($reply) === 2) {
                        $this->channel = $channel;
                        return true;
                    }
                    if (($reply['observed'] ?? null) === false && in_array($reply['reason'] ?? null, self::REFUSALS, true)) {
                        $this->status = $reply['reason'];
                    }
                    return false;
                }
                usleep(5_000);
            }
            return false;
        } finally {
            if ($this->channel !== $channel) {
                fclose($channel);
            }
        }
    }

    public function run(): void
    {
        try {
            $checkout = getcwd();
            $route = $this->closestRoute($checkout);
            if ($route === null || !$this->connect($checkout, $route)) {
                return;
            }
            $this->status = 'connected';
            while (!$this->closed) {
                $this->readProgress();
                if (is_resource($this->channel) && $this->pending !== '') {
                    $sent = @fwrite($this->channel, $this->pending);
                    if ($sent === false) {
                        break;
                    }
                    $this->pending = substr($this->pending, $sent);
                }
                if (!$this->closed) {
                    $read = [STDIN];
                    $write = $this->pending !== '' && is_resource($this->channel) ? [$this->channel] : null;
                    $except = null;
                    @stream_select($read, $write, $except, 0, 200_000);
                }
            }
        } catch (Throwable) {
            // Optional discovery cannot decide the lifecycle or outcome of a stage.
        } finally {
            if (is_resource($this->channel)) {
                fclose($this->channel);
            }
            if ($this->witness !== null) {
                clearstatcache(true, $this->witness);
                $current = @lstat($this->witness);
                if ($current && $current['dev'] === $this->witnessFile['dev'] && $current['ino'] === $this->witnessFile['ino']) {
                    @unlink($this->witness);
                }
            }
            if (!$this->closed && $this->status !== 'connected') {
                @fwrite(STDERR, "Child observation unavailable ({$this->status}); stage execution unchanged\n");
            }
        }
    }
}

// One-way monitor input. No execution, cancellation or authority commands.
pcntl_signal(SIGINT, SIG_IGN);
pcntl_signal(SIGTERM, SIG_IGN);
pcntl_signal(SIGPIPE, SIG_IGN);
umask(0077);
try {
    $initial = json_decode((string) fgets(STDIN, 8193), true, 512, JSON_THROW_ON_ERROR);
    stream_set_blocking(STDIN, false);
    (new PassiveStageDiscovery($initial))->run();
} catch (Throwable) {
    @fwrite(STDERR, "Child observation unavailable (adapter-setup-unavailable); stage execution unchanged\n");
}
