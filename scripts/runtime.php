<?php

declare(strict_types=1);

use Fight\Runtime\DomainBinding;
use Fight\Runtime\Installation;
use Fight\Runtime\Native;
use Fight\Runtime\Secrets;
use Fight\Runtime\Teardown;

require_once __DIR__.'/Runtime/autoload.php';

/**
 * Prepares settings and locked tooling without starting the app
 */
function runtimeSetup(Installation $installation, bool $unregistered): void
{
    if ($unregistered) {
        DomainBinding::ensure($installation, null, true);
    }
    $installation->admitStorage();
    $env = Installation::environment($installation->isolated);
    if ($installation->isolated) {
        $registration = $unregistered ? null : DomainBinding::ensure($installation, null, false);
        $installation->prepare($registration['host'] ?? $installation->project.'.localhost', initialize: true);
    } else {
        $private = $installation->checkout.'/.env';
        Native::require(is_file($private) && !is_link($private), 'Create the ignored .env before runtime setup');
        $raw = Native::run(
            [...$installation->compose(), 'config', '--environment'],
            $installation->checkout,
            $env,
            capture: true
        );
        $values = [];
        foreach (Native::lines($raw) as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $values[$key] = $value;
            }
        }
        Secrets::provision($installation->state().'/secrets', $values);
    }
    Native::run([$installation->checkout.'/bin/pi-dependencies', 'setup'], $installation->checkout, $env);
    echo "Installation configuration and Pi dependencies prepared; no application service or migration started.\n";
}

/**
 * Runs Docker with the selected project environment and mounts
 *
 * @phpstan-param list<string> $arguments
 */
function runtimeDocker(Installation $installation, array $arguments): int
{
    Native::require($arguments !== [], 'Missing Docker command');
    $checkout = $installation->checkout;
    $env = Installation::environment($installation->isolated);
    if ($arguments[0] === 'compose') {
        $state = $installation->isolated ? $installation->state() : '';
        if ($installation->isolated && (!is_file($state.'/override.yaml') || !is_dir($state.'/output'))) {
            $binding = DomainBinding::current($installation);
            $installation->prepare($binding['host'] ?? $installation->project.'.localhost');
        }

        return Native::process([...$installation->compose(), ...array_slice($arguments, 1)], $checkout, $env)[0];
    }
    if ($installation->isolated && $arguments[0] === 'run') {
        for ($i = 2, $count = count($arguments); $i < $count; ++$i) {
            if ($arguments[$i - 1] === '--mount') {
                $fields = explode(',', $arguments[$i]);
                foreach ($fields as &$field) {
                    if (str_starts_with($field, 'source=') || str_starts_with($field, 'src=')) {
                        $field = 'source='.$installation->outputPath(explode('=', $field, 2)[1]);
                    }
                }
                unset($field);
                $arguments[$i] = implode(',', $fields);
            }
        }
        array_splice($arguments, 1, 0, ['--label', 'com.docker.compose.project='.$installation->project]);
    }

    return Native::process(['docker', ...$arguments], $checkout, $env)[0];
}

/**
 * Parses arguments and dispatches the requested runtime operation
 *
 * @phpstan-param list<string> $arguments
 */
function runtimeMain(array $arguments): int
{
    $cwd = getcwd();
    Native::require($cwd !== false, 'Cannot resolve runtime working directory');
    $root = realpath($cwd);
    $selector = null;
    $argument = $arguments[0] ?? '';
    if ($argument === '--worktree' || str_starts_with($argument, '--worktree=')) {
        array_shift($arguments);
        $selector = $argument === '--worktree' ? array_shift($arguments) : substr($argument, strlen('--worktree='));
        Native::require(
            $selector !== null && $selector !== '' && !str_starts_with($selector, '--'),
            'Missing worktree selector'
        );
    }
    $action = array_shift($arguments);
    if ($action === '-h' || $action === '--help') {
        echo 'Usage: bin/runtime [--worktree SELECTOR] '.
            "{up|down|inspect|setup|docker|roots|paths|storage-admit|project-name|image-name|finish}\n";

        return 0;
    }
    $options = [];
    $positionals = [];
    $flags = match ($action) {
        'setup' => ['--unregistered-worktree'],
        'up' => ['--unregistered-worktree', '--build-only', '--shared-ingress'],
        default => [],
    };
    $values = match ($action) {
        'setup', 'down', 'inspect' => ['--worktree'],
        'up' => ['--worktree', '--task'],
        'finish' => ['--worktree', '--outcome', '--published-oid', '--pr'],
        'roots' => ['--apply', '--access'],
        'storage-admit' => ['--protect-image'],
        default => [],
    };
    if ($action === 'docker') {
        $positionals = $arguments;
    } else {
        while (($argument = array_shift($arguments)) !== null) {
            if ($argument === '--') {
                array_push($positionals, ...$arguments);
                break;
            }
            if ($argument === '--help' || $argument === '-h') {
                printf("Usage: bin/runtime %s %s\n", $action, implode(' ', [...$values, ...$flags]));

                return 0;
            }
            [$key, $value] = array_pad(explode('=', $argument, 2), 2, null);
            if (in_array($key, $values, true)) {
                $value ??= array_shift($arguments);
                Native::require(
                    $value !== null && $value !== '' && !str_starts_with($value, '--'),
                    'Missing value for '.$key
                );
                $options[$key] = $value;
            } elseif ($value === null && in_array($key, $flags, true)) {
                $options[$key] = true;
            } else {
                Native::require(!str_starts_with($argument, '-'), 'Unknown runtime option: '.$key);
                $positionals[] = $argument;
            }
        }
    }
    $selector = $options['--worktree'] ?? $selector;
    $actions = ['up', 'down', 'inspect', 'setup', 'docker', 'roots', 'paths', 'storage-admit',
        'project-name', 'image-name', 'finish'];
    Native::require(in_array($action, $actions, true), 'Select a runtime command; see bin/runtime --help');
    Native::require(match ($action) {
        'docker', 'paths', 'roots' => $positionals !== [],
        'image-name' => count($positionals) === 1,
        default => $positionals === [],
    }, 'Unexpected or missing runtime arguments; see bin/runtime --help');
    Native::require($root !== false, 'Invalid checkout path');
    $installation = Installation::resolve(
        $root,
        $selector,
        implicit: !in_array($action, ['up', 'down', 'finish'], true),
        removed: in_array($action, ['down', 'finish'], true)
    );
    switch ($action) {
        case 'setup':
            runtimeSetup($installation, isset($options['--unregistered-worktree']));
            break;
        case 'up':
            return Fight\Runtime\Startup::up($installation, $options);
        case 'down':
            return Teardown::down($installation);
        case 'finish':
            foreach (['--worktree', '--outcome', '--published-oid', '--pr'] as $required) {
                Native::require(isset($options[$required]), 'Missing required finish option: '.$required);
            }

            return Fight\Runtime\WorktreeRemoval::finish(
                $root,
                $installation,
                (string) $options['--outcome'],
                (string) $options['--published-oid'],
                (string) $options['--pr']
            );
        case 'docker':
            return runtimeDocker($installation, $positionals);
        case 'inspect':
            $resources = [];
            foreach (['containers', 'volumes', 'networks'] as $kind) {
                $resources[$kind] = Native::ids($installation->main, $kind, $installation->project);
            }
            echo Native::json([
                'checkout'  => $installation->checkout,
                'project'   => $installation->project,
                'resources' => $resources
            ])."\n";
            break;
        case 'storage-admit':
            $installation->admitStorage();
            break;
        case 'project-name':
            echo $installation->project."\n";
            break;
        case 'image-name':
            $tag = $installation->isolated ? $installation->project.'-'.$positionals[0]
                : 'fight-agent-os-client-browser:node24.22.1-playwright1.61.0';
            echo $tag."\n";
            break;
        case 'paths':
            foreach ($positionals as $value) {
                Native::require($value !== '', 'Output path must not be empty');
                $target = $value[0] === '/' ? $value : $installation->checkout.'/'.$value;
                $target = Native::canonical($target);
                Native::require(
                    Native::beneath($target, $installation->checkout) && $target !== $installation->checkout,
                    'Output path must remain beneath selected checkout'
                );
                $mapped = $installation->outputPath($target);
                Native::require(Native::canonical($mapped) === $mapped, 'Output path alias refused');
                Native::mkdir($mapped);
            }
            break;
        case 'roots':
            Fight\Runtime\ApprovedRoots::operate($installation, $positionals, $options);
            break;
    }

    return 0;
}

umask(0077);
try {
    exit(runtimeMain(array_slice($_SERVER['argv'] ?? [], 1)));
} catch (RuntimeException $error) {
    fwrite(STDERR, 'Runtime operation incomplete: '.$error->getMessage()."\n");
    exit(1);
} catch (JsonException | TypeError | ValueError $error) {
    fwrite(STDERR, "Runtime operation incomplete: invalid native configuration or result; inspect privately.\n");
    exit(1);
}
