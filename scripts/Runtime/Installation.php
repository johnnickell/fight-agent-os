<?php

declare(strict_types=1);

namespace Fight\Runtime;

/**
 * Class Installation
 */
final readonly class Installation
{
    /**
     * Constructs Installation
     *
     * @phpstan-param array{path: string, branch: string}|null $registration
     */
    public function __construct(
        public string $main,
        public string $checkout,
        public string $project,
        public ?array $registration,
        public bool $isolated
    ) {
    }

    /**
     * Derives the branch-independent project name from exact checkout paths
     */
    public static function project(string $main, string $checkout): string
    {
        return 'fight-wt-'.substr(hash('sha256', $main."\0".$checkout), 0, 12);
    }

    /**
     * Resolves a registered checkout or its exact teardown locator
     */
    public static function resolve(
        string $root,
        ?string $selector = null,
        bool $implicit = false,
        bool $removed = false
    ): self {
        $entries = Native::registrations($root);
        $main = $entries[0]['path'];
        if ($selector === null && $implicit && $main !== $root) {
            $selector = $root;
        }
        if ($selector === null) {
            return new self($main, $main, 'fight-agent-os', null, false);
        }
        $matches = array_values(array_filter($entries, static fn (array $entry): bool => self::matches(
            $entry,
            $selector
        )));
        Native::require(count($matches) <= 1, 'Ambiguous worktree; use its exact registered path or full branch');
        if ($matches !== []) {
            $checkout = $matches[0]['path'];
            Native::require($checkout !== $main, 'Main checkout is not disposable; omit --worktree to select main');

            return new self($main, $checkout, self::project($main, $checkout), $matches[0], true);
        }
        Native::require($removed, 'No matching registered worktree: '.$selector);
        $locators = [];
        $paths = glob($main.'/var/worktrees/fight-wt-*/locator.json', GLOB_ERR);
        Native::require($paths !== false, 'Cannot inventory worktree teardown locators');
        foreach ($paths as $path) {
            Native::require(!is_link($path) && Native::canonical($path) === $path, 'Runtime locator alias refused');
            $entry = json_decode(Native::read($path), true, flags: JSON_THROW_ON_ERROR);
            if (self::matches($entry, $selector)) {
                $project = self::project($main, $entry['path']);
                Native::require(
                    $entry['path'] !== $main && $entry['project'] === $project && basename(dirname($path)) === $project,
                    'Invalid teardown locator'
                );
                $locators[] = new self($main, $entry['path'], $project, $entry, true);
            }
        }
        Native::require(count($locators) <= 1, 'Ambiguous removed worktree; use its exact original path');
        if ($locators !== []) {
            return $locators[0];
        }
        Native::require(str_starts_with(
            $selector,
            '/'
        ), 'No registered worktree/locator; use the exact original absolute path');

        return new self($main, $selector, self::project($main, $selector), null, true);
    }

    /**
     * Builds the environment without ambient Compose or task settings
     *
     * @return array<string,string>
     */
    public static function environment(bool $isolated = false): array
    {
        $env = getenv();
        $env['FIGHT_AGENT_OS_DEFAULT_USER'] = posix_getuid().':'.posix_getgid();
        foreach (array_keys($env) as $key) {
            if (
                str_starts_with($key, 'COMPOSE_')
                || ($isolated && ($key === 'FIGHT_RUNTIME_SECRETS_DIR'
                    || preg_match('/^(APP_|POSTGRES_|FIGHT_TASK_)/', $key)))
            ) {
                unset($env[$key]);
            }
        }

        return $env;
    }

    /**
     * Renders a Compose service override with explicit port resets
     *
     * @phpstan-param array<string,array<string,mixed>> $services
     */
    public static function override(array $services, bool $replaceVolumes = false): string
    {
        $lines = ['services:'];
        foreach ($services as $name => $settings) {
            array_push($lines, '  '.$name.':', '    ports: !reset []');
            foreach ($settings as $key => $value) {
                $lines[] = '    '.$key.': '.($replaceVolumes && $key === 'volumes' ? '!override ' : '').Native::json(
                    $value,
                    JSON_UNESCAPED_UNICODE
                );
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Builds an explicit Compose bind mount declaration
     *
     * @return array{type: string, source: string, target: string, read_only?: true}
     */
    public static function bind(string $source, string $destination, bool $readOnly = false): array
    {
        $mount = ['type' => 'bind', 'source' => $source, 'target' => $destination];
        if ($readOnly) {
            $mount['read_only'] = true;
        }

        return $mount;
    }

    /**
     * Resolves the installation state path without accepting aliases
     */
    public function state(): string
    {
        $path = $this->main.($this->isolated ? '/var/worktrees/'.$this->project : '/var/runtime');
        Native::require(Native::canonical($path) === $path && !is_link($path), 'Runtime state alias refused');

        return $path;
    }

    /**
     * Checks host space and daemon availability before expensive operations
     */
    public function admitStorage(): void
    {
        $limits = [];
        foreach (['FIGHT_STORAGE_RESERVE_BYTES' => 20, 'FIGHT_STORAGE_ALLOCATION_BYTES' => 4] as $key => $default) {
            $raw = getenv($key);
            $limit = $raw === false ? $default * 1024 ** 3 : filter_var($raw, FILTER_VALIDATE_INT);
            Native::require($limit !== false && $limit >= 0, 'Invalid nonnegative storage allowance: '.$key);
            $limits[] = $limit;
        }
        $free = @disk_free_space($this->checkout);
        Native::require(
            $free !== false && $free >= array_sum($limits),
            'Insufficient host space; use explicit owned cleanup, never global prune'
        );
        Native::text(['docker', 'info', '--format', '{{.ID}}'], $this->checkout);
    }

    /**
     * Builds the explicit native Compose command prefix
     *
     * @return list<string>
     */
    public function compose(bool $shared = false): array
    {
        $args = [
            'docker',
            'compose',
            '--project-directory',
            $this->checkout,
            '--project-name',
            $this->project,
            '--file',
            $this->checkout.'/compose.yaml'
        ];
        if ($this->isolated) {
            $state = $this->state();
            foreach (['task.env', 'override.yaml', 'ingress.yaml'] as $name) {
                Native::require(!is_link($state.'/'.$name), 'Aliased worktree Compose configuration refused');
            }
            array_push($args, '--env-file', $state.'/task.env', '--file', $state.'/override.yaml');
            if (is_file($state.'/ingress.yaml')) {
                array_push($args, '--file', $state.'/ingress.yaml');
            }
        } else {
            $env = $this->checkout.'/.env';
            array_push($args, '--env-file', file_exists($env) ? $env : '/dev/null');
            if ($shared) {
                array_push($args, '--file', $this->main.'/var/local-ingress/main/ingress.yaml');
            }
        }

        return $args;
    }

    /**
     * Maps reserved worktree output to its native per-project mounts
     */
    public function outputPath(string $path): string
    {
        if (!$this->isolated) {
            return $path;
        }
        foreach (
            [
            '/var'   => '/var/worktrees/'.$this->project.'/output',
            '/.runs' => '/.runs/compose/'.$this->project
            ] as $location => $target
        ) {
            $original = $this->checkout.$location;
            if (Native::beneath($path, $original)) {
                return $this->main.$target.substr($path, strlen($original));
            }
        }

        return $path;
    }

    /**
     * Initializes isolated settings, mount targets and the teardown model
     */
    public function prepare(string $host, bool $initialize = false): void
    {
        $state = $this->state();
        Native::mkdir($state);
        $private = $state.'/task.env';
        Native::require(!is_link($private), 'Private environment alias refused');
        if (!file_exists($private)) {
            Native::require($initialize, 'Run bin/runtime setup explicitly before preparing this installation');
            Secrets::worktree($state, $host);
        }
        foreach (['.runs', 'var', 'packages/react/dist'] as $relative) {
            $mount = $this->checkout.'/'.$relative;
            Native::require(
                Native::canonical($mount) === $mount && !is_link($mount),
                'Worktree mount target alias refused'
            );
            Native::mkdir($mount);
        }
        $output = $state.'/output';
        $evidence = $this->main.'/.runs/compose/'.$this->project;
        foreach (
            [
            $output,
            $output.'/client/build',
            $output.'/client/shared-react',
            $output.'/openapi',
            $output.'/validations',
            $evidence
            ] as $path
        ) {
            Native::require(Native::canonical($path) === $path, 'Runtime output alias refused');
            Native::mkdir($path);
        }
        $common = Native::text(['git', 'rev-parse', '--path-format=absolute', '--git-common-dir'], $this->checkout);
        $mounts = [
            self::bind($output, '/app/var'),
            self::bind($evidence, '/app/.runs'),
            self::bind($common, $common, true) + ['bind' => ['create_host_path' => false]]
        ];
        $services = [
            'web'         => [
                'image'   => $this->project.'-web',
                'volumes' => [
                    ...$mounts,
                    self::bind($output.'/client/shared-react', '/app/packages/react/dist')
                ]
            ],
            'composer'    => ['image' => $this->project.'-web', 'volumes' => $mounts],
            'https_php'   => [
                'image'   => $this->project.'-https_php',
                'volumes' => array_map(
                    static fn (string $path): array => self::bind($output.'/'.$path, '/app/var/'.$path, true),
                    ['client/build', 'openapi', 'validations']
                )
            ],
            'https'       => ['volumes' => [self::bind($output.'/client/build', '/app/var/client/build', true)]],
            'mailcatcher' => []
        ];
        foreach ($services as &$settings) {
            $settings['labels'] = ['io.fight.checkout' => $this->checkout, 'io.fight.runtime' => 'worktree'];
        }
        unset($settings);
        Native::writePrivate($state.'/override.yaml', self::override($services));
        $this->writeLocator();
        $model = Native::query([
            ...$this->compose(),
            '--profile',
            '*',
            'config',
            '--format',
            'json'
        ], $this->checkout, self::environment(true));
        Native::writePrivate($state.'/down.json', Native::json([
            'services' => (object) [],
            'volumes'  => (object) ($model['volumes'] ?? []),
            'networks' => (object) ($model['networks'] ?? [])
        ])."\n");
    }

    /**
     * Records the minimal locator needed after source has disappeared
     */
    public function writeLocator(): void
    {
        Native::require($this->registration !== null, 'Runtime locator requires an exact registered checkout');
        Native::writePrivate($this->state().'/locator.json', Native::json([
            'path'    => $this->checkout,
            'branch'  => $this->registration['branch'],
            'project' => $this->project
        ], JSON_PRETTY_PRINT)."\n");
    }

    /**
     * Matches a checkout, branch, basename or exact task selector
     *
     * @phpstan-param array{path: string, branch: string} $entry
     */
    private static function matches(array $entry, string $selector): bool
    {
        return in_array($selector, [$entry['path'], $entry['branch'], basename($entry['path'])], true)
            || (preg_match('/^task-\d{5}$/D', $selector) === 1
                && ($entry['branch'] === 'feature/'.$selector || str_starts_with(
                    $entry['branch'],
                    'feature/'.$selector.'-'
                )));
    }
}
