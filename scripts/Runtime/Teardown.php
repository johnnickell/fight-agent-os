<?php

declare(strict_types=1);

namespace Fight\Runtime;

use JsonException;
use RuntimeException;

/**
 * Class Teardown
 */
final class Teardown
{
    private const EMPTY_MODEL = '{"services":{}}';

    /**
     * Removes only the selected native runtime and reports remaining holds
     */
    public static function down(Installation $installation): int
    {
        $main = $installation->main;
        $checkout = $installation->checkout;
        $project = $installation->project;
        $env = Installation::environment();
        if (!$installation->isolated) {
            return Native::process([
                ...$installation->compose(),
                '--profile',
                '*',
                'down',
                '--remove-orphans'
            ], $main, $env)[0];
        }
        $state = $locator = null;
        $model = self::EMPTY_MODEL;
        $failures = [];
        try {
            $state = $installation->state();
            if ($installation->registration !== null) {
                $locator = $state.'/locator.json';
                $cleanup = $state.'/down.json';
                if (!Native::exists($locator)) {
                    $installation->writeLocator();
                }
                if (!Native::exists($cleanup)) {
                    Native::writePrivate($cleanup, self::EMPTY_MODEL);
                }
                Native::require(!is_link($cleanup) && is_file($cleanup), 'Runtime teardown model alias refused');
                $model = self::validatedModel($cleanup, $project);
            }
        } catch (JsonException) {
            $failures[] = 'Invalid runtime teardown model JSON; saved model retained';
        } catch (RuntimeException $error) {
            $failures[] = $error->getMessage();
        }
        // Refused metadata holds output and source retirement, not the native project sweep.
        $removed = ['containers' => 0, 'volumes' => 0, 'networks' => 0, 'image_tags' => 0, 'builders' => 0];
        $effect = static function (array $argv, ?string $input = null) use ($main, $env, &$failures): bool {
            try {
                Native::run(array_values($argv), $main, $env, input: $input);

                return true;
            } catch (RuntimeException $error) {
                $failures[] = $error->getMessage();

                return false;
            }
        };
        $kinds = ['containers' => null, 'volumes' => 'volume', 'networks' => 'network'];
        $before = [];
        foreach ($kinds as $kind => $noun) {
            $before[$kind] = Native::ids($main, $kind, $project);
        }
        $effect([
            'docker',
            'compose',
            '--project-name',
            $project,
            '--project-directory',
            $main,
            '--env-file',
            '/dev/null',
            '--file',
            '-',
            'down',
            '--volumes',
            '--remove-orphans'
        ], $model);
        $builders = Native::lines(Native::text(['docker', 'buildx', 'ls', '--format', '{{.Name}}'], $main));
        if (in_array($project, $builders, true) && $effect(['docker', 'buildx', 'rm', $project])) {
            ++$removed['builders'];
        }
        $cache = 'buildx_buildkit_'.$project.'0_state';
        if (
            Native::text(['docker', 'volume', 'ls', '-q', '--filter', 'name=^'.$cache.'$'], $main) !== ''
            && $effect(['docker', 'volume', 'rm', $cache])
        ) {
            ++$removed['volumes'];
        }
        foreach ($kinds as $kind => $noun) {
            foreach (Native::ids($main, $kind, $project) as $id) {
                if ($kind === 'containers') {
                    if ($effect(['docker', 'stop', '--time', '20', $id])) {
                        $effect(['docker', 'rm', '--volumes', $id]);
                    }
                } else {
                    $effect(['docker', $noun, 'rm', $id]);
                }
            }
            $after = Native::ids($main, $kind, $project);
            $removed[$kind] += count(array_diff($before[$kind], $after));
            if ($after !== []) {
                $failures[] = 'Remaining project '.$kind.': '.implode(', ', $after);
            }
        }
        foreach (self::tags($installation) as $tag) {
            if ($effect(['docker', 'image', 'rm', $tag])) {
                ++$removed['image_tags'];
            }
        }
        $tags = self::tags($installation);
        if ($tags !== []) {
            $failures[] = 'Remaining task image tags: '.implode(', ', $tags);
        }
        $output = $state === null ? null : $state.'/output';
        if ($failures === [] && $output !== null && Native::exists($output)) {
            if (self::hasBindConsumer($main, $output)) {
                $failures[] = 'Task output still has a native bind consumer; output retained';
            } else {
                Native::removeDirectory($output);
            }
        }
        if (
            $installation->registration === null
            && (Native::exists($checkout) || Native::canonical($checkout) !== $checkout)
        ) {
            $failures[] = 'Unregistered/recreated/aliased original source path retained; Docker teardown attempted';
        }
        echo Native::json([
            'project'  => $project,
            'complete' => $failures === [],
            'removed'  => $removed,
            'failures' => $failures,
            'locator'  => $locator
        ])."\n";

        return $failures === [] ? 0 : 1;
    }

    /**
     * Finds remaining native bind consumers of selected private state
     */
    public static function hasBindConsumer(string $main, string $directory): bool
    {
        $ids = Native::lines(Native::text(['docker', 'ps', '-aq'], $main));
        foreach ($ids === [] ? [] : Native::query(['docker', 'inspect', ...$ids], $main) as $consumer) {
            foreach ($consumer['Mounts'] as $mount) {
                if ($mount['Type'] === 'bind' && Native::beneath(Native::canonical($mount['Source']), $directory)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Lists exact task-prefixed image tags without selecting immutable image IDs
     *
     * @return list<string>
     */
    public static function tags(Installation $installation): array
    {
        $tags = Native::lines(Native::text([
            'docker',
            'image',
            'ls',
            '--format',
            '{{.Repository}}:{{.Tag}}'
        ], $installation->main));
        $matches = array_values(array_unique(array_filter($tags, static fn (string $tag): bool => str_starts_with(
            $tag,
            $installation->project.'-'
        ))));
        sort($matches);

        return $matches;
    }

    /**
     * Validates the saved teardown model to owned resources only
     */
    private static function validatedModel(string $file, string $project): string
    {
        // The saved model is only metadata. Never give Compose arbitrary services,
        // includes, foreign volume names or networks on a destructive path.
        $data = json_decode(Native::read($file), flags: JSON_THROW_ON_ERROR);
        Native::require(
            $data instanceof \stdClass && ($data->services ?? null) instanceof \stdClass
            && get_object_vars($data->services) === []
            && array_diff(array_keys(get_object_vars($data)), ['services', 'volumes', 'networks']) === [],
            'Invalid runtime teardown model'
        );
        foreach (['volumes', 'networks'] as $kind) {
            $definitions = $data->{$kind} ?? (object) [];
            Native::require($definitions instanceof \stdClass, 'Invalid runtime teardown resource definitions');
            foreach (get_object_vars($definitions) as $key => $definition) {
                Native::require(
                    $definition instanceof \stdClass && is_string($definition->name ?? null),
                    'Invalid runtime teardown resource name'
                );
                if (($definition->external ?? false) === true) {
                    $definitions->{$key} = (object) ['name' => $definition->name, 'external' => true];
                } else {
                    Native::require(
                        $definition->name === $project.'_'.$key,
                        'Foreign runtime teardown resource refused'
                    );
                    $definitions->{$key} = (object) ['name' => $definition->name];
                }
            }
        }

        return Native::json($data);
    }
}
