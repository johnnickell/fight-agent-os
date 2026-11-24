<?php

declare(strict_types=1);

interface StageObservation
{
    public function publish(string $text, string $lifecycle, ?int $code): void;
    public function poll(): void;
    public function close(): void;
}

final class PassiveStageObservation implements StageObservation
{
    private mixed $process;
    private mixed $input;
    private string $pending;
    private int $sequence = 0;

    public function __construct(array $identity, float $heartbeat, int $started)
    {
        $this->pending = stageRecord(compact('identity', 'heartbeat', 'started'));
        $this->process = @proc_open([PHP_BINARY, __DIR__ . '/stage-discovery.php'],
            [['pipe', 'r'], STDOUT, STDERR], $pipes);
        if (!is_resource($this->process)) {
            throw new RuntimeException('Optional observation adapter unavailable');
        }
        $this->input = $pipes[0];
        stream_set_blocking($this->input, false);
        $this->poll();
    }

    public function publish(string $text, string $lifecycle, ?int $code): void
    {
        $record = stageRecord(['sequence' => ++$this->sequence, 'text' => $text, 'lifecycle' => $lifecycle, 'exitCode' => $code]);
        if (strlen($this->pending) + strlen($record) <= 65536) {
            $this->pending .= $record;
        }
        $this->poll();
    }

    public function poll(): void
    {
        if ($this->pending === '') {
            return;
        }
        $written = @fwrite($this->input, $this->pending);
        $this->pending = $written === false ? '' : substr($this->pending, $written);
    }

    public function close(): void
    {
        $this->poll();
        fclose($this->input);
        // EOF lets the observation owner remove its witness. Never wait indefinitely.
        $deadline = hrtime(true) + 3_500_000_000;
        while (proc_get_status($this->process)['running']) {
            if (hrtime(true) >= $deadline) {
                proc_terminate($this->process, 9);
                break;
            }
            usleep(10_000);
        }
        proc_close($this->process);
    }
}

final class SocketStageObservation implements StageObservation
{
    private mixed $server;
    private array $file;
    private array $clients = [];
    private array $snapshot;

    public function __construct(private readonly string $path, array $identity, float $heartbeat)
    {
        $parent = realpath(dirname($path));
        if (!str_starts_with($path, '/') || $parent === false || !str_starts_with($parent . '/', getcwd() . '/var/')) {
            throw new RuntimeException('Observation socket must be under this checkout’s var directory');
        }
        $info = stat($parent);
        if ($info['uid'] !== posix_getuid() || ($info['mode'] & 0077) !== 0 || file_exists($path) || is_link($path)) {
            throw new RuntimeException('Observation socket needs an unused path in an owner-only directory');
        }
        $maximum = PHP_OS_FAMILY === 'Darwin' ? 103 : 107;
        if (strlen($path) > $maximum) {
            throw new RuntimeException('Observation socket path is too long');
        }
        $context = stream_context_create(['socket' => ['backlog' => 4]]);
        $server = @stream_socket_server('unix://' . $path, $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($server === false) {
            throw new RuntimeException('Observation socket unavailable');
        }
        $this->server = $server;
        $this->file = lstat($path);
        if (!@chmod($path, 0600)) {
            $this->close();
            throw new RuntimeException('Observation socket permissions unavailable');
        }
        stream_set_blocking($server, false);
        $this->snapshot = ['version' => 'fight-child-observation-v1', 'identity' => $identity, 'sequence' => 0,
            'lifecycle' => 'starting', 'exitCode' => null, 'heartbeatSeconds' => $heartbeat, 'text' => 'Waiting for Pi', 'kind' => 'snapshot'];
    }

    public function publish(string $text, string $lifecycle, ?int $code): void
    {
        $this->snapshot = array_replace($this->snapshot, compact('text', 'lifecycle'),
            ['sequence' => $this->snapshot['sequence'] + 1, 'exitCode' => $code, 'kind' => 'progress']);
        $record = stageRecord($this->snapshot);
        foreach ($this->clients as $id => [$client, $pending]) {
            if (strlen($pending) + strlen($record) > 65536) {
                $this->drop($id);
            } else {
                $this->clients[$id][1] .= $record;
            }
        }
        $this->poll();
    }

    private function drop(int $id): void
    {
        fclose($this->clients[$id][0]);
        unset($this->clients[$id]);
    }

    public function poll(): void
    {
        for ($i = 0; $i < 4 && ($client = @stream_socket_accept($this->server, 0)) !== false; $i++) {
            if (count($this->clients) >= 4 || !stream_set_blocking($client, false)) {
                fclose($client);
                continue;
            }
            $this->clients[(int) $client] = [$client, stageRecord(array_replace($this->snapshot, ['kind' => 'snapshot']))];
        }
        foreach ($this->clients as $id => [$client, $pending]) {
            if (@fread($client, 1) !== '' || feof($client)) {
                $this->drop($id);
                continue;
            }
            if ($pending !== '') {
                $written = @fwrite($client, $pending);
                if ($written === false) {
                    $this->drop($id);
                } else {
                    $this->clients[$id][1] = substr($pending, $written);
                }
            }
        }
    }

    public function close(): void
    {
        if (isset($this->snapshot)) {
            $this->poll();
        }
        foreach (array_keys($this->clients) as $id) {
            $this->drop($id);
        }
        fclose($this->server);
        clearstatcache(true, $this->path);
        $current = @lstat($this->path);
        if ($current && $current['ino'] === $this->file['ino'] && $current['dev'] === $this->file['dev']) {
            @unlink($this->path);
        }
    }
}
