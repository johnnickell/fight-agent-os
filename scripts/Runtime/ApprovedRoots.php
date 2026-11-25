<?php

declare(strict_types=1);

namespace Fight\Runtime;

use RuntimeException;

/**
 * Class ApprovedRoots
 *
 * @phpstan-type Root array{path:string,access:string,identity:array{int,int,int}}
 */
final readonly class ApprovedRoots
{
    private const SERVICE = 'https_php';
    private const PREFIX = '/run/fight-approved-roots';
    private const MANIFEST = '/run/fight-root-configuration';
    private const KEY = 'x-fight-approved-roots';
    private const LIMIT = 16;
    private const NAME = '/^[a-z][a-z0-9-]{0,31}$/D';

    private string $config;
    /** @var array<string,Root> */
    private array $roots;
    /** @var list<string> */
    private array $protected;

    /**
     * Constructs ApprovedRoots
     */
    public function __construct(private Installation $installation)
    {
        $this->config = $installation->state() . '/approved-roots/roots.json';
        $this->roots = $this->load();
        $main = $installation->main;
        $protected = [$main . '/var', $main . '/.env', $main . '/.git', $main . '/.runs/compose'];
        if ($installation->isolated && file_exists($installation->checkout . '/.env')) {
            $protected[] = $installation->checkout . '/.env';
        }
        $this->protected = $protected;
    }

    /**
     * Computes a stable digest for existing settings and previews
     *
     * Preserves the canonical separators used in stored target digests.
     */
    private static function fingerprint(mixed $value): string
    {
        $encode = static function (mixed $item) use (&$encode): string {
            if (is_object($item)) {
                $entries = get_object_vars($item);
                ksort($entries, SORT_STRING);
                $fields = [];
                foreach ($entries as $key => $field) {
                    $fields[] = Native::json((string) $key) . ': ' . $encode($field);
                }

                return '{' . implode(', ', $fields) . '}';
            }
            if (is_array($item)) {
                return array_is_list($item) ? '[' . implode(', ', array_map($encode, $item)) . ']' : $encode((object) $item);
            }

            return Native::json($item);
        };

        return hash('sha256', $encode($value));
    }

    /**
     * Derives the exact mount destination from an approved root
     *
     * @phpstan-param Root $root
     */
    private static function target(string $name, array $root): string
    {
        return self::PREFIX . '/' . $name . '-' . self::fingerprint($root);
    }

    /**
     * Renders the exact private settings and Compose bind declarations
     *
     * @phpstan-param array<string,Root> $roots
     */
    private function model(array $roots, ?string $directory = null): string
    {
        $entries = [];
        $bind = static fn (string $source, string $destination, bool $readOnly): array => [
            'type' => 'bind', 'source' => $source, 'target' => $destination, 'read_only' => $readOnly,
            'bind' => ['create_host_path' => false, 'propagation' => 'rprivate'],
        ];
        $mounts = [$bind($directory ?? dirname($this->config), self::MANIFEST, true)];
        foreach ($roots as $name => $root) {
            $target = self::target($name, $root);
            $entries[$name] = $root + ['target' => $target];
            $mounts[] = $bind($root['path'], $target, $root['access'] === 'ro');
        }
        $model = [self::KEY => ['version' => 1, 'roots' => (object) $entries], 'services' => [self::SERVICE => ['volumes' => $mounts]]];

        return preg_replace_callback('/^ +/m', static fn (array $match): string => str_repeat(' ', intdiv(strlen($match[0]), 2)),
            Native::json($model, JSON_PRETTY_PRINT)) . "\n";
    }

    /**
     * Reads only a bounded private file with matching bind declarations
     *
     * @return array<string,Root>
     */
    private function load(): array
    {
        clearstatcache(true);
        Native::require(Native::canonical($this->config) === $this->config && !is_link($this->config), 'Approved-root configuration alias refused');
        if (!Native::exists($this->config)) {
            return [];
        }
        $before = @lstat($this->config);
        Native::require($before !== false && ($before['mode'] & 0177777) === 0100600
            && $before['uid'] === posix_getuid() && $before['size'] <= 65536, 'Approved-root settings must be a private regular file of bounded size');
        $handle = @fopen($this->config, 'rb');
        Native::require(is_resource($handle), 'Cannot safely read the approved-root configuration');
        try {
            $after = fstat($handle);
            clearstatcache(true, $this->config);
            $current = @lstat($this->config);
            Native::require(is_array($after) && is_array($current) && !is_link($this->config)
                && $after['dev'] === $before['dev'] && $after['ino'] === $before['ino']
                && $current['dev'] === $before['dev'] && $current['ino'] === $before['ino']
                && ($after['mode'] & 0177777) === 0100600 && $after['uid'] === posix_getuid(), 'Approved-root file changed during inspection');
            $raw = stream_get_contents($handle, 65537);
        } finally {
            fclose($handle);
        }
        Native::require(is_string($raw) && strlen($raw) <= 65536, 'Approved-root settings exceed the size limit');
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $entries = $data[self::KEY]['roots'] ?? null;
        Native::require(is_array($entries) && count($entries) <= self::LIMIT, 'Invalid approved-root configuration');
        $roots = [];
        foreach ($entries as $name => $root) {
            Native::require(is_string($name) && preg_match(self::NAME, $name) === 1 && is_array($root) && count($root) === 4
                && in_array($root['access'] ?? null, ['ro', 'rw'], true) && preg_match(HostRoots::PATH, $root['path'] ?? '') === 1
                && is_array($root['identity'] ?? null) && array_is_list($root['identity']) && count($root['identity']) === 3
                && array_filter($root['identity'], 'is_int') === $root['identity'] && is_string($root['target'] ?? null), 'Invalid approved-root entry');
            $roots[$name] = [
                'path'     => $root['path'],
                'access'   => $root['access'],
                'identity' => [(int) $root['identity'][0], (int) $root['identity'][1], (int) $root['identity'][2]]
            ];
        }
        Native::require($raw === $this->model($roots), 'Approved-root configuration does not match its exact mount declarations');

        return $roots;
    }

    /**
     * Revalidates directory identity and rejects overlapping roots
     *
     * @phpstan-param array<string,Root> $roots
     */
    private function qualify(array $roots): void
    {
        Native::require(count($roots) <= self::LIMIT, 'At most ' . self::LIMIT . ' roots may be approved');
        $selected = $ancestors = [];
        foreach ($roots as $root) {
            Native::require(HostRoots::qualify($root['path'], $root['access'], $this->protected) === $root['identity'],
                'Approved root identity changed; preview an explicit change or remove it');
            $identity = $root['identity'][0] . ':' . $root['identity'][1];
            Native::require(!isset($selected[$identity]) && !isset($ancestors[$identity]), 'Duplicate or overlapping approved roots are not accepted');
            foreach (array_slice(HostRoots::lineage($root['path']), 1) as $parent) {
                $info = @stat($parent);
                Native::require($info !== false, 'Approved root ancestry changed during qualification');
                $key = $info['dev'] . ':' . $info['ino'];
                Native::require(!isset($selected[$key]), 'Duplicate or overlapping approved roots are not accepted');
                $ancestors[$key] = true;
            }
            $selected[$identity] = true;
        }
    }

    /**
     * Requires the selected local Unix Docker context for host path checks
     */
    private function localDocker(): void
    {
        Native::require(!getenv('DOCKER_HOST') && !getenv('DOCKER_CONTEXT'), 'Use the selected local Unix Docker context without environment overrides');
        Native::require(str_starts_with(Native::text(['docker', 'context', 'inspect', '--format', '{{.Endpoints.docker.Host}}'], $this->installation->checkout), 'unix:///'),
            'A local Unix Docker context is required for approved roots');
    }

    /**
     * Runs an operation under the native lock on the settings directory
     */
    private function locked(callable $operation): void
    {
        $directory = dirname($this->config);
        Native::require(Native::canonical($directory) === $directory, 'Approved-root configuration directory alias refused');
        Native::mkdir($directory);
        $stream = @fopen($directory, 'rb');
        Native::require(is_resource($stream), 'Cannot open approved-root configuration directory');
        try {
            $info = fstat($stream);
            Native::require($info !== false && ($info['mode'] & 0170000) === 0040000 && flock($stream, LOCK_EX), 'Cannot lock approved-root configuration');
            clearstatcache(true);
            $current = @lstat($directory);
            Native::require($current !== false && $current['dev'] === $info['dev'] && $current['ino'] === $info['ino']
                && Native::canonical($directory) === $directory, 'Approved-root configuration directory changed');
            $operation();
        } finally {
            fclose($stream);
        }
    }

    /**
     * Resolves the application observation directory without following aliases
     */
    private function observation(): string
    {
        $directory = dirname($this->config) . '/observed';
        Native::require(Native::canonical($directory) === $directory, 'Approved-root observation directory alias refused');

        return $directory;
    }

    /**
     * Revokes the previous mount observation before any change
     */
    private function revoke(string $directory): void
    {
        $manifest = $directory . '/roots.json';
        if (Native::exists($manifest)) {
            Native::require(unlink($manifest), 'Cannot revoke previous approved-root observation');
        }
    }

    /**
     * Applies only the revalidated mount model and observes the resulting container
     *
     * @phpstan-param callable(?string, callable():void):void $start
     */
    public function reconfigure(callable $start): void
    {
        $this->locked(function () use ($start): void {
            Native::require($this->load() === $this->roots, 'Approved-root configuration changed; run startup again after reviewing status');
            $directory = $this->observation();
            if ($this->roots !== []) {
                $this->localDocker();
                Native::mkdir($directory);
            }
            $this->revoke($directory);
            $model = $this->roots === [] ? null : $this->model($this->roots, $directory);
            $this->qualify($this->roots);
            $start($model, function () use ($directory, $model): void {
                if ($model === null && !is_dir($directory)) {
                    return;
                }
                [$container, $mounts] = $this->runningMounts();
                [, $missing, $stale] = $this->compare($directory, $mounts);
                Native::require($container !== null && $missing === [] && $stale === [],
                    'Docker did not report the exact approved-root mounts; stop the selected FPM service and review status');
                if ($model !== null) {
                    Native::writePrivate($directory . '/roots.json', $model);
                }
            });
        });
    }

    /**
     * Reads the mounts of the single expected FPM service container
     *
     * @return array{string|null,list<array<string,mixed>>}
     */
    private function runningMounts(): array
    {
        $this->localDocker();
        $checkout = $this->installation->checkout;
        $ids = Native::lines(Native::text(['docker', 'ps', '-q', '--filter', 'label=com.docker.compose.project=' . $this->installation->project,
            '--filter', 'label=com.docker.compose.service=' . self::SERVICE, '--filter', 'label=com.docker.compose.oneoff=False'], $checkout));
        Native::require(count($ids) <= 1, 'Cannot identify a unique running application service');
        if ($ids === []) {
            return [null, []];
        }
        $mounts = Native::query(['docker', 'inspect', '--format', '{{json .Mounts}}', $ids[0]], $checkout);
        Native::require(array_is_list($mounts), 'Cannot verify the Docker mount report');
        foreach ($mounts as $mount) {
            Native::require(is_array($mount) && is_string($mount['Destination'] ?? null), 'Cannot verify the Docker mount report');
        }

        return [$ids[0], $mounts];
    }

    /**
     * Compares approved mount descriptors with the real destination inventory
     *
     * @phpstan-param list<array<string,mixed>> $mounts
     * @return array{array<string,true>,array<string,array{string,bool}>,array<string,list<array<string,mixed>>>}
     */
    private function compare(string $directory, array $mounts): array
    {
        $expected = [];
        foreach ($this->roots as $name => $root) {
            $expected[self::target($name, $root)] = [$root['path'], $root['access'] === 'rw'];
        }
        if ($expected !== []) {
            $expected[self::MANIFEST] = [$directory, false];
        }
        $observed = $matched = [];
        foreach ($mounts as $mount) {
            $target = $mount['Destination'];
            if ($target === self::MANIFEST || str_starts_with($target, self::PREFIX . '/')) {
                $observed[$target][] = $mount;
            }
        }
        foreach ($expected as $target => [$source, $writable]) {
            $candidates = $observed[$target] ?? [];
            if (count($candidates) === 1 && ($candidates[0]['Type'] ?? null) === 'bind'
                && ($candidates[0]['Source'] ?? null) === $source && ($candidates[0]['RW'] ?? null) === $writable) {
                $matched[$target] = true;
            }
        }

        return [$matched, array_diff_key($expected, $matched), array_diff_key($observed, $matched)];
    }

    /**
     * Reads application access facts without reporting native diagnostic data
     *
     * @return array{array<string,array<string,mixed>>,string|null}
     */
    private function observationFacts(string $container): array
    {
        $code = 'require "/app/vendor/autoload.php";echo json_encode((new App\\Adapter\\Installation\\ApprovedRootAccess())->observe());';
        try {
            $result = Native::query(['docker', 'exec', $container, 'php', '-r', $code], $this->installation->checkout);
            Native::require(is_array($result['roots'] ?? null) && (!isset($result['reason']) || is_string($result['reason'])), 'Invalid application observation');
            $facts = [];
            foreach ($result['roots'] as $fact) {
                Native::require(is_string($fact['locator'] ?? null) && !isset($facts[$fact['locator']]) && is_bool($fact['mounted'] ?? null)
                    && is_bool($fact['readable'] ?? null) && is_bool($fact['writable'] ?? null) && (!isset($fact['reason']) || is_string($fact['reason'])),
                    'Invalid application root observation');
                $facts[$fact['locator']] = $fact;
            }

            return [$facts, $result['reason'] ?? null];
        } catch (RuntimeException | \JsonException $error) {
            return [[], 'application_observation_unavailable'];
        }
    }

    /**
     * Reports configured roots and independently observed access states
     */
    private function status(): void
    {
        $results = [];
        try {
            [$container, $mounts] = $this->runningMounts();
            [$matched, , $stale] = $this->compare($this->observation(), $mounts);
        } catch (RuntimeException | \JsonException $error) {
            foreach ($this->roots as $name => $root) {
                $results[] = self::row($name, $root) + ['mounted' => null, 'readable' => null, 'writable' => null, 'reason' => 'docker_mount_report_unavailable'];
            }
            echo Native::json(['roots' => $results, 'stale_mounts' => null, 'reason' => 'docker_mount_report_unavailable'], JSON_PRETTY_PRINT) . "\n";

            return;
        }
        [$facts, $reason] = $container !== null && isset($matched[self::MANIFEST]) ? $this->observationFacts($container) : [[], null];
        foreach ($this->roots as $name => $root) {
            $fact = $facts['root:' . $name] ?? [];
            $mounted = isset($matched[self::target($name, $root)]);
            $seen = $mounted && ($fact['mounted'] ?? false);
            try {
                $current = HostRoots::qualify($root['path'], $root['access'], $this->protected) === $root['identity'];
            } catch (RuntimeException | \JsonException $error) {
                $current = false;
            }
            $results[] = self::row($name, $root) + ['mounted' => $mounted,
                'readable' => $current && $seen && $fact['readable'], 'writable' => $current && $seen && $root['access'] === 'rw' && $fact['writable'],
                'reason' => !$current ? 'host_root_unqualified' : (!$mounted ? 'exact_mount_not_observed'
                    : (!$seen ? ($reason ?? $fact['reason'] ?? 'application_observation_unavailable') : ($fact['reason'] ?? null)))];
        }
        echo Native::json(['roots' => $results, 'stale_mounts' => count($stale)], JSON_PRETTY_PRINT) . "\n";
    }

    /**
     * Builds the public settings status for the selected locator
     *
     * @phpstan-param Root $root
     * @return array<string,mixed>
     */
    private static function row(string $name, array $root): array
    {
        return ['locator' => 'root:' . $name, 'canonical_path' => $root['path'], 'configured' => true, 'write_granted' => $root['access'] === 'rw'];
    }

    /**
     * Processes explicit root status, preview and apply requests
     *
     * @phpstan-param list<string> $args
     * @phpstan-param array<string,string|true> $options
     */
    public static function operate(Installation $installation, array $args, array $options): void
    {
        $self = new self($installation);
        $operation = array_shift($args);
        $access = $options['--access'] ?? 'ro';
        Native::require(in_array($access, ['ro', 'rw'], true), 'Root access must be ro or rw');
        Native::require(match ($operation) {
            'status' => $args === [] && $options === [],
            'remove' => count($args) === 1 && !isset($options['--access']),
            'add', 'change' => count($args) === 2,
            default => false,
        }, 'Use roots status, add NAME PATH, change NAME PATH, or remove NAME');
        if ($operation === 'status') {
            $self->status();

            return;
        }
        $name = $args[0];
        Native::require(preg_match(self::NAME, $name) === 1, 'Use a short lowercase root name (letters, digits and hyphens)');
        $before = $self->roots[$name] ?? null;
        Native::require(($before === null) === ($operation === 'add'), 'Root name already exists or is not configured');
        $after = $operation === 'remove' ? null : ['path' => $args[1], 'access' => $access, 'identity' => HostRoots::qualify($args[1], $access, $self->protected)];
        $proposed = $self->roots;
        if ($after === null) {
            unset($proposed[$name]);
        } else {
            $proposed[$name] = $after;
            $self->qualify($proposed);
        }
        $digest = self::fingerprint([$self->config, (object) $self->roots, $name, $operation, $after]);
        echo Native::json(['operation' => $operation, 'locator' => 'root:' . $name, 'configuration' => $self->config,
            'before' => $before['path'] ?? null, 'before_access' => $before['access'] ?? null, 'after' => $after['path'] ?? null, 'access' => $after['access'] ?? null,
            'reconfigure' => $installation->isolated ? './bin/up --worktree ' . $installation->checkout : './bin/up (preserve your existing ingress mode)',
            'expected' => 'Only settings change; explicit startup revalidates roots and checks Docker mounts',
            'required' => 'Approve only paths whose parent directories untrusted users cannot modify',
            'recovery' => 'Preview and apply the inverse change; stop FPM to end existing exposure; no source is removed', 'apply_digest' => $digest], JSON_PRETTY_PRINT) . "\n";
        if (!isset($options['--apply'])) {
            return;
        }
        Native::require($options['--apply'] === $digest, 'Preview no longer matches; review a new preview before applying');
        $self->locked(function () use ($self, $after, $proposed): void {
            Native::require($self->load() === $self->roots, 'Configuration changed since preview; review a new preview before applying');
            if ($after !== null) {
                $self->qualify($proposed);
            }
            $self->revoke($self->observation());
            Native::writePrivate($self->config, $self->model($proposed));
        });
        echo "Configuration saved. Runtime exposure is unchanged until explicit reconfiguration.\n";
    }
}
