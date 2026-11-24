#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/stage-observation.php';

const EVENT_LIMIT = 16 * 1024 * 1024;
const ROLE_ERROR = 'unknown stage role; use engineer, reviewer, qa or land';

function monotonic(): float
{
    return hrtime(true) / 1_000_000_000;
}

function stageRecord(array $record): string
{
    return json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
}

function display(mixed $value, int $limit = 320): string
{
    $text = is_scalar($value) ? (string) $value : '';
    $text = preg_replace('/\x1b\[[0-?]*[ -\/]*[@-~]/', '', $text);
    $text = trim(preg_replace('/[\p{C}\s]+/u', ' ', $text) ?? '');
    return preg_match('/\A.{0,' . $limit . '}/us', $text, $match) && $match[0] !== $text ? $match[0] . '…' : $text;
}

function stageArguments(array $argv): array
{
    $required = ['attempt', 'task', 'role', 'provider', 'model', 'thinking'];
    $options = array_fill_keys($required, null) + ['timeout' => 1800, 'heartbeat' => 30, 'harness-package' => null, 'observe-socket' => null];
    for ($i = 1; $i < count($argv); $i++) {
        $argument = $argv[$i];
        if (in_array($argument, ['--help', '-h'], true)) {
            echo "Run one Pi role with visible progress and private evidence.\nUsage: php run-stage.php --attempt DIR --task TASK-NNNNN --role ROLE --provider PROVIDER --model MODEL --thinking LEVEL\n"
                . "  [--timeout SECONDS] [--heartbeat SECONDS] [--harness-package DIR] [--observe-socket PATH]\n";
            exit(0);
        }
        if ($argument === '--' && $i === count($argv) - 1) {
            break;
        }
        [$key, $value] = array_pad(explode('=', $argument, 2), 2, null);
        if (!str_starts_with($key, '--') || !array_key_exists($name = substr($key, 2), $options)) {
            throw new InvalidArgumentException('unknown stage option');
        }
        if ($value === null) {
            $value = $argv[++$i] ?? null;
            if ($value === null || str_starts_with($value, '--')) {
                throw new InvalidArgumentException("missing value for --$name");
            }
        }
        $options[$name] = $value;
    }
    foreach ($required as $name) {
        if (trim($options[$name] ?? '') === '') {
            throw new InvalidArgumentException("--$name is required and must not be empty");
        }
    }
    foreach (['timeout', 'heartbeat'] as $name) {
        $value = $options[$name];
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
            throw new InvalidArgumentException("--$name must be a finite positive number");
        }
        $options[$name] = (float) $value;
    }
    $roles = json_decode(file_get_contents(__DIR__ . '/../stage-roles.json'), true, 512, JSON_THROW_ON_ERROR);
    $key = preg_replace('/\s+/u', '-', strtolower(trim($options['role'])));
    foreach ($roles as $canonical => $aliases) {
        if (in_array($key, $aliases, true)) {
            $options['role'] = $canonical;
            return $options;
        }
    }
    throw new InvalidArgumentException(ROLE_ERROR);
}

final class StageProgress
{
    public ?StageObservation $observation = null;
    public string $lifecycle = 'starting';
    public ?int $exitCode = null;
    public readonly float $started;
    public float $lastOutput;
    public array $tools = [];
    public string $final = '';
    public mixed $stopReason = null;
    public bool $settled = false;
    public bool $aborted = false;
    private string $activity = 'waiting for Pi';

    public function __construct(private readonly string $role, private readonly mixed $log)
    {
        $this->started = $this->lastOutput = monotonic();
    }

    public function emit(string $message): void
    {
        $this->lastOutput = monotonic();
        $elapsed = (int) ($this->lastOutput - $this->started);
        $text = display($message, 480);
        $line = sprintf("[%s %02d:%02d] %s\n", $this->role, intdiv($elapsed, 60), $elapsed % 60, $text);
        fwrite($this->log, $line);
        fflush($this->log);
        $this->observation?->publish($text, $this->lifecycle, $this->exitCode);
        @fwrite(STDOUT, $line);
        @fflush(STDOUT);
    }

    public function event(stdClass $event): void
    {
        $kind = $event->type ?? null;
        if (!is_string($kind)) {
            return;
        }
        switch ($kind) {
            case 'agent_start':
                $this->lifecycle = 'running';
                $this->settled = false;
                $this->stopReason = null;
                $this->activity = 'agent working';
                break;
            case 'message_start':
                if (($event->message->role ?? null) === 'assistant') {
                    $this->activity = 'waiting for agent response';
                }
                break;
            case 'message_update':
                // Never render thinking, arguments, partial source or tool results.
                $this->activity = 'agent responding';
                break;
            case 'message_end':
                $message = $event->message ?? null;
                if (($message->role ?? null) === 'assistant') {
                    $this->stopReason = $message->stopReason ?? null;
                    $parts = [];
                    foreach ($message->content ?? [] as $block) {
                        if (($block->type ?? null) === 'text') {
                            $parts[] = $block->text ?? '';
                        }
                    }
                    $this->final = implode("\n", $parts);
                    if (trim($this->final) !== '') {
                        $this->emit($this->final);
                    }
                    if (in_array($this->stopReason, ['error', 'aborted'], true)) {
                        $this->emit("Agent response {$this->stopReason}; awaiting Pi retry/settlement");
                    }
                }
                break;
            case 'tool_execution_start':
                $name = display($event->toolName ?? 'tool', 60);
                $path = in_array($name, ['read', 'write', 'edit'], true) ? ($event->args->path ?? $event->args->file_path ?? '') : '';
                $label = trim($name . ' ' . display($path, 160));
                $this->tools[$event->toolCallId ?? ''] = [$label, monotonic()];
                $this->activity = $label;
                $this->emit("Started $label");
                break;
            case 'tool_execution_end':
                $id = $event->toolCallId ?? '';
                [$label, $started] = $this->tools[$id] ?? [display($event->toolName ?? 'tool'), monotonic()];
                unset($this->tools[$id]);
                $this->emit(sprintf('%s: %s (%.1fs)', $label, ($event->isError ?? false) ? 'failed' : 'finished', monotonic() - $started));
                $this->activity = 'agent working';
                break;
            case 'auto_retry_start':
            case 'auto_retry_end':
            case 'compaction_start':
            case 'compaction_end':
                $this->activity = str_replace('_', ' ', $kind);
                $this->emit($this->activity);
                break;
            case 'agent_settled':
                $this->settled = true;
                $this->aborted = ($event->aborted ?? null) !== false;
                $this->lifecycle = 'settled-awaiting-exit';
                $this->activity = 'Pi settled; waiting for process exit';
                $this->emit($this->activity);
                break;
        }
    }

    public function heartbeat(): void
    {
        if ($this->tools !== []) {
            [$label, $started] = reset($this->tools);
            $this->emit(sprintf('Still running %s (%.0fs); %d active tool(s)', $label, monotonic() - $started, count($this->tools)));
        } else {
            $this->emit("Still running — {$this->activity}");
        }
    }
}

final class StageChild
{
    public array $pipes;
    public readonly int $pid;
    private mixed $process;
    private mixed $startup;
    private ?int $exitCode = null;

    public function __construct(array $command, array $env)
    {
        // Wait for the monitor to verify the new group before exec. Pi itself sees EOF on stdin.
        $bootstrap = 'if (posix_setsid() < 0 || fread(STDIN, 1) !== "1") { exit(1); } '
            . 'pcntl_exec("/usr/bin/env", array_slice($argv, 1)); exit(127);';
        $this->process = @proc_open([PHP_BINARY, '-r', $bootstrap, '--', ...$command],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($this->process)) {
            throw new RuntimeException('Pi process unavailable');
        }
        $this->startup = $pipes[0];
        unset($pipes[0]);
        $this->pipes = $pipes;
        $status = proc_get_status($this->process);
        $this->pid = $status['pid'];
        $this->rememberExit($status);
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }
    }

    private function rememberExit(array $status): void
    {
        if (!$status['running']) {
            $this->exitCode = $status['signaled'] ? -$status['termsig'] : $status['exitcode'];
        }
    }

    public function result(): ?int
    {
        if ($this->exitCode === null) {
            $this->rememberExit(proc_get_status($this->process));
        }
        return $this->exitCode;
    }

    public function allowExec(): void
    {
        if ($this->startup !== null && $this->result() === null && posix_getpgid($this->pid) === $this->pid) {
            $written = @fwrite($this->startup, '1');
            $this->closeStartup();
            if ($written !== 1) {
                throw new RuntimeException('Cannot release Pi bootstrap');
            }
        }
    }

    private function closeStartup(): void
    {
        if ($this->startup !== null) {
            fclose($this->startup);
            $this->startup = null;
        }
    }

    public function stop(int $signal, StageProgress $progress, mixed $errors): void
    {
        if ($this->startup !== null && $signal === SIGTERM) {
            // EOF prevents exec even if the bootstrap still belongs to the inherited group.
            $this->closeStartup();
            return;
        }
        if ($this->result() !== null) {
            $this->diagnostic($progress, $errors, 'killpg', 'leader-exited', $signal);
        } elseif (posix_getpgid($this->pid) !== $this->pid) {
            $this->diagnostic($progress, $errors, 'killpg', 'group-changed', $signal);
        } elseif (!posix_kill(-$this->pid, $signal) && ($errno = posix_get_last_error()) !== 3) {
            $this->diagnostic($progress, $errors, 'killpg', 'signal-unavailable', $signal, $errno);
        }
    }

    public function wait(float $seconds): ?int
    {
        $deadline = monotonic() + $seconds;
        while (($result = $this->result()) === null && monotonic() < $deadline) {
            usleep(20_000);
        }
        return $result;
    }

    public function diagnostic(StageProgress $progress, mixed $errors, string $operation, string $reason, ?int $signal = null, ?int $errno = null): void
    {
        $record = ['operation' => $operation, 'reason' => $reason, 'pid' => $this->pid];
        if ($signal !== null) {
            $record['signal'] = $signal;
        }
        if ($errno !== null) {
            $record['errno'] = $errno;
        }
        fwrite($errors, stageRecord($record));
        fflush($errors);
        $progress->emit("Process-group shutdown unverified ($reason); descendants may remain; inspect private stderr.log");
    }

    public function close(): void
    {
        $this->closeStartup();
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        // proc_close would wait without a deadline if the leader remained unreaped.
        if ($this->result() !== null) {
            proc_close($this->process);
        }
    }
}

function createEvidence(string $path): mixed
{
    return @fopen($path, 'xb') ?: throw new RuntimeException('Cannot create private evidence');
}

function writeEvidence(string $path, string $contents): void
{
    $file = createEvidence($path);
    try {
        if (fwrite($file, $contents) !== strlen($contents)) {
            throw new RuntimeException('Cannot write private evidence');
        }
    } finally {
        fclose($file);
    }
}

function consumeEvents(string &$buffer, string $chunk, StageProgress $progress): void
{
    $buffer .= $chunk;
    $offset = 0;
    while (($end = strpos($buffer, "\n", $offset)) !== false) {
        if ($end - $offset > EVENT_LIMIT) {
            throw new RuntimeException('oversized Pi event');
        }
        $event = json_decode(substr($buffer, $offset, $end - $offset), false, 512, JSON_THROW_ON_ERROR);
        if (!$event instanceof stdClass) {
            throw new RuntimeException('Pi event is not an object');
        }
        $progress->event($event);
        $offset = $end + 1;
    }
    $buffer = substr($buffer, $offset);
    if (strlen($buffer) > EVENT_LIMIT) {
        throw new RuntimeException('oversized partial Pi event');
    }
}

function runStage(array $args, int $observationStarted): int
{
    umask(0077);
    if (!preg_match('/\ATASK-[0-9]{5}\z/', $args['task'])) {
        throw new RuntimeException('invalid TASK identity');
    }
    $attempt = realpath($args['attempt']);
    if ($attempt === false || !is_file($attempt . '/request.md') || !@mkdir($attempt . '/dispatch.lock', 0700)) {
        throw new RuntimeException('attempt must be fresh and contain request.md');
    }
    foreach (['events.jsonl', 'progress.log', 'stderr.log', 'final.txt', 'exit-code', 'pi-exit-code', 'process.json', 'sessions'] as $name) {
        if (file_exists($attempt . '/' . $name) || is_link($attempt . '/' . $name)) {
            throw new RuntimeException('attempt already contains execution evidence');
        }
    }
    $package = dirname(__DIR__, 2);
    if ($args['harness-package'] !== null && realpath($args['harness-package']) !== $package) {
        throw new RuntimeException('selected package must own this monitor');
    }
    $checkout = realpath(getcwd());
    $identity = [];
    foreach (['task' => 80, 'role' => 60, 'provider' => 120, 'model' => 120, 'thinking' => 40, 'worktree' => 1024, 'attempt' => 1024] as $name => $limit) {
        $identity[$name] = display($args[$name] ?? $checkout, $limit);
    }
    $identity['attempt'] = display($attempt, 1024);
    ['task' => $task, 'role' => $role, 'provider' => $provider, 'model' => $model,
        'thinking' => $thinking, 'timeout' => $timeout, 'heartbeat' => $heartbeat] = $args;
    $command = ['pi', '-e', $package, '--mode', 'json', '--session-dir', $attempt . '/sessions',
        '--name', "$task $role", '--provider', $provider, '--model', $model, '--thinking', $thinking, '--', '@' . $attempt . '/request.md'];
    $env = array_replace(getenv(), ['PI_PROVIDER' => $provider, 'PI_MODEL' => $model, 'PI_REASONING_LEVEL' => $thinking,
        'FIGHT_WORKFLOW_PHASE' => $role, 'FIGHT_WORKFLOW_TASK' => $task, 'FIGHT_WORKFLOW_CHECKOUT' => $checkout]);
    $cancelled = 0;
    pcntl_async_signals(true);
    pcntl_signal(SIGPIPE, SIG_IGN);
    $cancel = static function (int $signal) use (&$cancelled): void { $cancelled = 128 + $signal; };
    pcntl_signal(SIGINT, $cancel);
    pcntl_signal(SIGTERM, $cancel);

    $raw = createEvidence($attempt . '/events.jsonl');
    $errors = createEvidence($attempt . '/stderr.log');
    $log = createEvidence($attempt . '/progress.log');
    $progress = new StageProgress($role, $log);
    if ($args['observe-socket'] !== null) {
        $progress->observation = new SocketStageObservation($args['observe-socket'], $identity, $heartbeat);
    }
    $progress->emit("Starting $task — $provider/$model, thinking=$thinking");
    $child = null;
    $forced = 0;
    $code = 1;
    try {
        $child = new StageChild($command, $env);
        writeEvidence($attempt . '/process.json', stageRecord(['monitor_pid' => getmypid(), 'pi_pid' => $child->pid]));
        if ($progress->observation === null) {
            try {
                $progress->observation = new PassiveStageObservation($identity, $heartbeat, $observationStarted);
            } catch (Throwable) {
                @fwrite(STDERR, "Child observation unavailable (adapter-setup-unavailable); stage execution unchanged\n");
            }
        }
        $pipes = $child->pipes;
        $buffer = '';
        $terminationAt = null;
        while ($pipes !== [] || $child->result() === null) {
            $now = monotonic();
            $progress->observation?->poll();
            if (!$forced && ($cancelled || $now >= $progress->started + $timeout)) {
                $forced = $cancelled ?: 124;
                $progress->emit($cancelled ? 'Cancellation requested' : 'Stage timed out');
                $terminationAt = $now;
                $child->stop(SIGTERM, $progress, $errors);
            }
            if (!$forced && !$cancelled) {
                $child->allowExec();
            }
            if ($terminationAt !== null && $now - $terminationAt >= 5) {
                break; // An escaped descendant may still hold a pipe.
            }
            if ($pipes !== []) {
                $ready = $pipes;
                $write = $except = null;
                if (@stream_select($ready, $write, $except, 0, 200_000) > 0) {
                    foreach ($ready as $pipe) {
                        $key = array_search($pipe, $pipes, true);
                        $chunk = fread($pipe, 65536);
                        if ($chunk === false) {
                            throw new RuntimeException('Cannot read Pi pipe');
                        }
                        if ($chunk !== '') {
                            fwrite($key === 1 ? $raw : $errors, $chunk);
                            if ($key === 1) {
                                consumeEvents($buffer, $chunk, $progress);
                            }
                        } elseif (feof($pipe)) {
                            unset($pipes[$key]);
                        }
                    }
                }
            } else {
                usleep(20_000);
            }
            if (monotonic() - $progress->lastOutput >= $heartbeat) {
                $progress->heartbeat();
            }
        }
        $forced = $forced ?: $cancelled;
        if ($forced) {
            $child->stop(SIGKILL, $progress, $errors);
        }
        $piCode = $child->wait(5);
        if ($piCode === null) {
            throw new RuntimeException('Pi did not exit');
        }
        $code = $forced ?: ($piCode >= 0 ? $piCode : 128 - $piCode);
        if ($code === 0 && ($buffer !== '' || $progress->tools !== [] || !$progress->settled || $progress->aborted || $progress->stopReason !== 'stop')) {
            $code = 1;
            $progress->emit('Incomplete or failed Pi response; inspect private events and session');
        }
    } catch (Throwable $error) {
        $code = $forced ?: ($cancelled ?: 1);
        $type = get_class($error);
        $progress->emit("Stage monitor failed ($type); inspect private logs");
        fwrite($errors, "$type\n");
    } finally {
        try {
            if ($child !== null) {
                if ($code) {
                    $child->stop(SIGTERM, $progress, $errors);
                    if ($child->wait(5) === null) {
                        $child->stop(SIGKILL, $progress, $errors);
                        if ($child->wait(5) === null) {
                            $child->diagnostic($progress, $errors, 'wait', 'leader-unreaped');
                        }
                    }
                }
                $piCode = $child->result();
                $child->close();
                writeEvidence($attempt . '/pi-exit-code', ($piCode ?? 'unknown') . "\n");
            }
            writeEvidence($attempt . '/final.txt', $progress->final);
            writeEvidence($attempt . '/exit-code', "$code\n");
            $progress->lifecycle = $code === 0 ? 'completed' : 'failed';
            $progress->exitCode = $code;
            $progress->emit("Process complete (exit $code); evidence still requires coordinator review");
        } finally {
            $progress->observation?->close();
            fclose($raw);
            fclose($errors);
            fclose($log);
        }
    }
    return $code;
}

$started = hrtime(true);
$observationStarted = is_int($started) ? intdiv($started, 1_000_000) : 0;
try {
    $args = stageArguments($argv);
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
} catch (Throwable) {
    fwrite(STDERR, "Cannot start stage: packaged role vocabulary unavailable\n");
    exit(1);
}
if (PHP_VERSION_ID < 80200 || PHP_INT_SIZE < 8 || !function_exists('pcntl_async_signals') || !function_exists('posix_setsid')) {
    fwrite(STDERR, "Cannot start stage: 64-bit PHP 8.2+ CLI with pcntl and posix is required\n");
    exit(1);
}
try {
    exit(runStage($args, $observationStarted));
} catch (Throwable $error) {
    fwrite(STDERR, 'Cannot start stage (' . get_class($error) . "); verify PHP CLI, fresh attempt and request.md\n");
    exit(1);
}
