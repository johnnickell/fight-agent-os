<?php

declare(strict_types=1);

namespace Fight\Runtime;

/**
 * Class DomainBinding
 *
 * Reads and maintains runtime enrollment in the existing local-domain registry
 *
 * @phpstan-type Binding array{id: string, task: string|null, host: string}
 */
final class DomainBinding
{
    private const ZONE = 'local\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}';

    /**
     * Identifies the enrolled namespace for a valid primary or task name
     */
    public static function namespace(string $host): ?string
    {
        if (
            strlen($host) > 253
            || preg_match('/^(?:task-[0-9]{5}-[a-f0-9]{10}\.)?('.self::ZONE.')$/D', $host, $match) !== 1
            || preg_match('/\.(?:test|localhost|local)$/D', $host)
        ) {
            return null;
        }

        return $match[1];
    }

    /**
     * Finds the current registry entry and verifies its native identity
     *
     * @return Binding|null
     */
    public static function current(Installation $installation): ?array
    {
        $project = self::read(self::registry($installation))['projects'][$installation->main] ?? null;
        if ($project === null) {
            return null;
        }
        [$key, , $id] = self::native($installation);
        $binding = $project['bindings'][$key] ?? null;
        Native::require(
            $binding === null || $binding['id'] === $id,
            'Checkout identity changed; explicitly retire stale registration'
        );

        return $binding;
    }

    /**
     * Creates an enrolled worktree name or checks explicit unregistered mode
     *
     * @return Binding|null
     */
    public static function ensure(Installation $installation, ?string $task, bool $unregistered): ?array
    {
        if ($unregistered) {
            Native::require(
                $installation->isolated && $task === null,
                'Unregistered mode requires a linked worktree and cannot select a TASK'
            );
            Native::require(
                self::current($installation) === null,
                'Checkout already has a domain binding; use its registered origin'
            );
            $env = $installation->state().'/task.env';
            if (Native::exists($env)) {
                Native::require(!is_link($env), 'Private environment alias refused');
                $lines = Native::lines(trim(Native::read($env)));
                foreach (['APP_BROWSER_ORIGIN', 'APP_ACTIVATION_ORIGIN', 'APP_PASSWORD_RESET_ORIGIN'] as $key) {
                    $matching = array_filter($lines, static fn (string $line): bool => str_starts_with($line, $key.'='));
                    $current = array_values($matching);
                    Native::require(
                        $current === [$key."='https://".$installation->project.".localhost'"],
                        'Prepared origin differs from the unregistered worktree; preserve its settings'
                    );
                }
            }

            return null;
        }
        $registry = self::registry($installation);
        $lock = dirname($registry).'/domains/bindings.lock';
        Native::mkdir(dirname($lock));
        Native::require(!is_link($lock), 'Aliased registration lock refused');
        $handle = @fopen($lock, 'ab');
        Native::require(is_resource($handle), 'Cannot open registration lock');
        try {
            Native::require(flock($handle, LOCK_EX), 'Cannot lock local-domain registry');
            $data = self::read($registry);
            $project = $data['projects'][$installation->main] ?? null;
            if ($project === null) {
                return null;
            }
            [$key, $marker, $id] = self::native($installation);
            $current = $project['bindings'][$key] ?? null;
            if ($current !== null) {
                Native::require(
                    $current['id'] === $id,
                    'Checkout identity changed; explicitly retire stale registration'
                );
                Native::require(
                    $task === null || $current['task'] === $task,
                    'Checkout already belongs to a different TASK; preview a change explicitly'
                );

                return $current;
            }
            Native::require($installation->isolated, 'Assign the primary checkout explicitly with plan --primary');
            if ($task === null) {
                $branch = Native::text(['git', 'branch', '--show-current'], $installation->checkout);
                $task = preg_match('~^feature/(task-[0-9]{5})(?:-|$)~', $branch, $match) ? strtoupper($match[1]) : '';
            }
            Native::require(
                preg_match('/^TASK-[0-9]{5}$/D', $task) === 1,
                'Select --task TASK-NNNNN for this new Checkout registration'
            );
            $record = $installation->checkout.'/planning/tasks/'.substr($task, 5).'-TASK.md';
            Native::require(
                is_file($record) && preg_match('/^id: '.$task.'$/m', Native::read($record)) === 1,
                'Actual TASK record missing in selected checkout'
            );
            $id ??= strtolower(
                PHP_OS_FAMILY === 'Darwin' ? Native::text(['/usr/bin/uuidgen'], $installation->checkout)
                    : trim(Native::read('/proc/sys/kernel/random/uuid'))
            );
            Native::require(self::uuid($id), 'Native Checkout UUID generation unavailable');
            $next = ['id' => $id, 'task' => $task, 'host' => self::host($project['namespace'], $task, $id)];
            foreach ($data['projects'] as $peer) {
                foreach ($peer['bindings'] as $other) {
                    Native::require(
                        $other['host'] !== $next['host'] && $other['id'] !== $id,
                        'Conflicting hostname or Checkout UUID'
                    );
                }
            }
            $label = 'label=com.docker.compose.project='.$installation->project;
            $ids = Native::lines(Native::text(['docker', 'ps', '-q', '--filter', $label], $installation->main));
            foreach ($ids === [] ? [] : Native::query(['docker', 'inspect', ...$ids], $installation->main) as $item) {
                $service = $item['Config']['Labels']['com.docker.compose.service'] ?? null;
                Native::require(
                    !in_array($service, ['https', 'fight-https-preview'], true),
                    'Stop the selected HTTPS gateway before changing its canonical domain; preserve its data'
                );
            }
            if (!file_exists($marker)) {
                $stream = @fopen($marker, 'xb');
                Native::require(is_resource($stream), 'Cannot exclusively create Checkout identity');
                try {
                    Native::require(fwrite($stream, $id."\n") === 37, 'Cannot write Checkout identity');
                } finally {
                    fclose($stream);
                }
            }
            $data['projects'][$installation->main]['bindings'][$key] = $next;
            foreach ($data['projects'] as &$entry) {
                $entry['bindings'] = (object) $entry['bindings'];
            }
            unset($entry);
            Native::writePrivate($registry, Native::json($data, JSON_PRETTY_PRINT)."\n");

            return $next;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Derives the exact name from the namespace and native Checkout UUID
     */
    private static function host(string $zone, ?string $task, string $id): string
    {
        Native::require(
            $task === null || preg_match('/^TASK-[0-9]{5}$/D', $task) === 1,
            'Expected an actual TASK-NNNNN identity'
        );
        $host = $task === null ? $zone : strtolower($task).'-'.substr(hash('sha256', $id), 0, 10).'.'.$zone;
        Native::require(self::namespace($host) === $zone, 'Invalid registered hostname');

        return $host;
    }

    /**
     * Checks the canonical spelling of a Checkout UUID
     */
    private static function uuid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/D', $id) === 1;
    }

    /**
     * Resolves the owned registry path without following aliases
     */
    private static function registry(Installation $installation): string
    {
        $path = $installation->main.'/var/local-ingress/hosts.json';
        Native::require(Native::canonical(dirname($path).'/domains') === dirname($path).'/domains'
            && Native::canonical($path) === $path && !is_link($path), 'Aliased local-domain registry refused');

        return $path;
    }

    /**
     * Reads and validates the shared registry before returning any binding
     *
     * @return array{version: int, projects: array<string,array{namespace: string, bindings: array<string,Binding>}>}
     */
    private static function read(string $path): array
    {
        $data = file_exists($path) ? json_decode(Native::read($path), true, flags: JSON_THROW_ON_ERROR)
            : ['version' => 1, 'projects' => []];
        Native::require(
            ($data['version'] ?? null) === 1 && is_array($data['projects'] ?? null),
            'Unknown host registry; inspect before mutation'
        );
        $zones = $hosts = $ids = [];
        foreach ($data['projects'] as $root => $project) {
            $zone = $project['namespace'] ?? '';
            Native::require(
                self::namespace($zone) === $zone && is_array($project['bindings'] ?? null)
                    && str_starts_with($root, '/'),
                'Invalid registered project'
            );
            foreach ($zones as $peer) {
                Native::require(
                    $zone !== $peer && !str_ends_with($zone, '.'.$peer) && !str_ends_with($peer, '.'.$zone),
                    'Duplicate or overlapping project namespaces'
                );
            }
            $zones[] = $zone;
            foreach ($project['bindings'] as $key => $binding) {
                $id = $binding['id'] ?? '';
                $host = $binding['host'] ?? '';
                Native::require(self::uuid($id) && $host === self::host($zone, $binding['task'], $id)
                    && !isset($hosts[$host]) && !isset($ids[$id])
                    && preg_match('~^(?:\.|[A-Za-z0-9_. -]+(?:/[A-Za-z0-9_. -]+)*)$~D', $key) === 1
                    && !in_array('..', explode('/', $key), true), 'Invalid or conflicting Checkout registration');
                $hosts[$host] = $ids[$id] = true;
            }
        }

        return $data;
    }

    /**
     * Reads the Checkout identifier from its native Git directory
     *
     * @return array{string,string,string|null}
     */
    private static function native(Installation $installation): array
    {
        $checkout = $installation->checkout;
        $commonPath = Native::text(['git', 'rev-parse', '--path-format=absolute', '--git-common-dir'], $checkout);
        $common = Native::canonical($commonPath);
        $admin = Native::canonical(Native::text(['git', 'rev-parse', '--absolute-git-dir'], $checkout));
        Native::require(Native::beneath($admin, $common), 'Git administrative directory is outside this repository');
        $marker = $admin.'/fight-local-checkout-id';
        Native::require(!is_link($marker), 'Aliased Checkout identity refused');
        $id = file_exists($marker) ? trim(Native::read($marker)) : null;
        Native::require($id === null || self::uuid($id), 'Invalid Checkout UUID');

        return [$admin === $common ? '.' : substr($admin, strlen($common) + 1), $marker, $id];
    }
}
