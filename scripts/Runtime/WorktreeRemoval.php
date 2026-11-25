<?php

declare(strict_types=1);

namespace Fight\Runtime;

/**
 * Class WorktreeRemoval
 */
final class WorktreeRemoval
{
    private const DOWN_HOOK = '{{ primary_worktree_path }}/bin/down --worktree {{ worktree_path }}';

    /**
     * Qualifies the live publication before delivered source retirement
     */
    public static function finish(
        string $root,
        Installation $installation,
        string $outcome,
        string $oid,
        string $pr
    ): int {
        return self::remove($root, $installation, $oid, static function (
            string $main,
            string $checkout,
            ?string $ref
        ) use (
            $outcome,
            $oid,
            $pr
        ): void {
            $surviving = realpath($outcome);
            Native::require(
                $surviving !== false && is_file($surviving) && !Native::beneath($surviving, $checkout),
                'Record final outcome outside disposable source before Git removal'
            );
            Native::require($ref !== null, 'Delivered branch removal requires an exact published ref');
            self::publication($main, $oid, $ref, $pr);
        });
    }

    /**
     * Removes Docker before qualifying non-force Worktrunk source removal
     *
     * @phpstan-param (callable(string,string,?string): void)|null $delivery
     */
    public static function remove(
        string $root,
        Installation $installation,
        string $oid,
        ?callable $delivery = null,
        bool $qaRevision = false,
        bool $runtimeFreeFixture = false
    ): int {
        $main = $installation->main;
        $checkout = $installation->checkout;
        $project = $installation->project;
        $entry = $installation->registration;
        Native::require($root === $main, 'Run source removal from canonical safe checkout');
        Native::require(
            !$runtimeFreeFixture || ($delivery === null && !$qaRevision),
            'Runtime-free fixture mode cannot replace delivery or QA runtime teardown'
        );
        $teardown = static function () use ($installation, $runtimeFreeFixture): void {
            if ($runtimeFreeFixture) {
                self::proveRuntimeFree($installation);
            } else {
                Native::require(Teardown::down($installation) === 0, 'Docker teardown incomplete; source retained');
            }
        };
        $teardown();
        if ($entry === null) {
            Native::require(
                !Native::exists($installation->state()),
                'Unlocated generated configuration remains; inspect exact state, never infer retirement'
            );
            Native::require(
                !Native::exists($checkout) && !in_array($checkout, array_column(
                    Native::registrations($main),
                    'path'
                ), true),
                'No locator; original source path/registration must remain absent'
            );
            self::report($project, 'not_observed', ['already_absent' => true]);

            return 0;
        }
        $cwd = getcwd();
        Native::require(
            $cwd !== false && $checkout !== $main && $checkout !== realpath($cwd),
            'Main/current checkout is never disposable; runtime qualification completed'
        );
        $branch = $entry['branch'];
        Native::require(!$runtimeFreeFixture || !str_starts_with(
            $branch,
            'qa/'
        ), 'Runtime-free fixture mode cannot remove a QA-purpose ref');
        Native::require(!$qaRevision || ($delivery === null && preg_match('~^qa/task-\d{5}/'.preg_quote(
            $oid,
            '~'
        ).'$~D', $branch) === 1
            && preg_match('/^[a-f0-9]{40}$/D', $oid) === 1), 'Exact QA-purpose ref/revision required');
        $ref = $branch === '' ? null : 'refs/heads/'.$branch;
        $present = in_array($checkout, array_column(Native::registrations($main), 'path'), true);
        Native::require(
            $present === Native::exists($checkout) && !is_link($checkout),
            'Source path/registration mismatch; inspect partial effects'
        );
        $refOid = static function () use ($main, $ref): string {
            if ($ref === null) {
                return '';
            }
            foreach (
                Native::lines(Native::text([
                'git',
                'for-each-ref',
                '--format=%(refname) %(objectname)',
                $ref
                ], $main)) as $line
            ) {
                [$name, $actual] = explode(' ', $line, 2);
                if ($name === $ref) {
                    return $actual;
                }
            }

            return '';
        };
        $complete = static function (string $result) use ($installation, $refOid, $teardown, $branch, $ref, $oid): int {
            $postconditions = static function () use ($installation, $branch, $refOid, $result, $oid): void {
                $entries = Native::registrations($installation->main);
                Native::require(
                    !Native::exists($installation->checkout),
                    'Worktrunk retained or recreated source; inspect actual outcome, never force'
                );
                foreach ($entries as $entry) {
                    Native::require(
                        $entry['path'] !== $installation->checkout && ($branch === '' || $entry['branch'] !== $branch),
                        'Worktrunk retained a registered owner; inspect actual outcome, never force'
                    );
                }
                Native::require(
                    $refOid() === ($result === 'retained_unmerged' ? $oid : ''),
                    'Worktrunk ref postcondition changed; source/config closeout incomplete'
                );
            };
            $postconditions();
            $teardown();
            $postconditions();
            $state = $installation->state();
            if (Native::exists($state)) {
                Native::require(!Teardown::hasBindConsumer(
                    $installation->main,
                    $state
                ), 'Retired runtime configuration still has a native bind consumer; config retained');
                Native::removeDirectory($state);
            }
            self::report($installation->project, $result, [
                'ref'          => $ref,
                'retained_oid' => $result === 'retained_unmerged' ? $oid : null
            ]);

            return 0;
        };
        if (!$present && $refOid() === '') {
            return $complete('already_absent');
        }
        $sourceState = static function () use (
            $main,
            $checkout,
            $entry,
            $present,
            $ref,
            $branch,
            $oid,
            $delivery,
            $qaRevision
        ): void {
            $entries = Native::registrations($main);
            $current = null;
            foreach ($entries as $candidate) {
                if ($candidate['path'] === $checkout) {
                    $current = $candidate;
                }
            }
            if ($present) {
                Native::require(
                    $current !== null && $current['branch'] === $entry['branch'] && !is_link($checkout),
                    'Source registration changed before removal'
                );
                Native::require(Native::text([
                    'git',
                    'rev-parse',
                    'HEAD'
                ], $checkout) === $oid, 'Source OID changed; unique work retained');
                Native::require(Native::text([
                    'git',
                    'status',
                    '--porcelain',
                    '--untracked-files=all'
                ], $checkout) === '', 'Dirty/unique source retained');
                Native::require(
                    Native::text(['git', 'ls-files', '--others', '--ignored', '--exclude-standard'], $checkout) === '',
                    'Ignored/private output remains; dispose only known temporary output after consumers end'
                );
            } else {
                Native::require(
                    $current === null && !Native::exists($checkout),
                    'Absent source path/registration changed before removal'
                );
                Native::require(!in_array($branch, array_column(
                    $entries,
                    'branch'
                ), true), 'Surviving ref is checked out in a registered worktree; source/ref retained');
            }
            if ($ref !== null) {
                Native::require(Native::text([
                    'git',
                    'rev-parse',
                    $ref
                ], $main) === $oid, 'Exact source ref/OID changed; unique work retained');
            }
            if ($delivery === null && !$qaRevision) {
                Native::require(preg_match('/^[a-f0-9]{40}$/D', $oid) === 1, 'Exact fixture OID required');
                Native::run(['git', 'merge-base', '--is-ancestor', $oid, 'HEAD'], $main);
            }
        };
        $sourceState();
        if ($delivery !== null) {
            $delivery($main, $checkout, $ref);
        }
        $help = Native::text(['wt', 'remove', '--help'], $main);
        foreach (['--yes', '--foreground', '--format'] as $option) {
            Native::require(str_contains(
                $help,
                $option
            ), 'Installed Worktrunk lacks qualified noninteractive removal support');
        }
        $targets = $present ? SourceProtection::check($installation, !$runtimeFreeFixture) : [];
        $sourceState();
        self::hooks($main, $present ? $checkout : $main, $runtimeFreeFixture);
        if ($runtimeFreeFixture) {
            $teardown();
        }
        $sourceState();
        SourceProtection::release($installation, $targets);
        $target = $present ? $checkout : $branch;
        Native::require($target !== '', 'No exact native removal target');
        [
            $exit,
            $stdout
        ] = Native::process([
            'wt',
            'remove',
            '--foreground',
            '--format=json',
            '--yes',
            '--',
            $target
        ], $main, capture: true, input: '', timeout: 120);
        Native::require($exit === 0, 'Worktrunk removal failed; inspect partial effects without force');
        $results = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        Native::require(
            str_starts_with(
                ltrim($stdout),
                '['
            ) && is_array($results) && array_is_list($results) && count($results) === 1 && is_array($results[0]),
            'Unknown Worktrunk removal result; inspect partial effects'
        );
        $actual = $results[0];
        Native::require(
            ($actual['kind'] ?? null) === ($present ? 'worktree' : 'branch_only')
            && ($actual['branch'] ?? null) === $branch
            && array_key_exists('branch_checked_out_at', $actual) && $actual['branch_checked_out_at'] === null
            && ($present ? ($actual['path'] ?? null) === $checkout : array_key_exists('detached_worktree', $actual)
                && $actual['detached_worktree'] === null && is_bool($actual['pruned'] ?? null))
            && in_array($actual['branch_outcome'] ?? null, ['deleted', 'retained_unmerged'], true),
            'Unqualified Worktrunk removal outcome; inspect actual source/ref/registration, never force'
        );

        return $complete($actual['branch_outcome']);
    }

    /**
     * Reports separately verified retirement effects
     *
     * @phpstan-param array<string,mixed> $extra
     */
    private static function report(string $project, string $result, array $extra): void
    {
        echo Native::json([
            'project'             => $project,
            'eligible_closeout'   => true,
            'source_absent'       => true,
            'registration_absent' => true,
            'runtime_absent'      => true,
            'config_absent'       => true,
            'branch_outcome'      => $result
        ] + $extra)."\n";
    }

    /**
     * Qualifies the complete effective removal hooks in both contexts
     */
    private static function hooks(string $main, string $checkout, bool $runtimeFreeFixture): void
    {
        foreach (array_unique([$main, $checkout]) as $directory) {
            foreach (['pre-remove', 'post-remove'] as $phase) {
                $output = Native::text(['wt', 'hook', 'show', $phase, '--format=json'], $directory);
                $hooks = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
                Native::require(str_starts_with(
                    $output,
                    '['
                ) && is_array($hooks) && array_is_list($hooks), 'Worktrunk hook inventory unavailable');
                foreach ($hooks as $hook) {
                    Native::require(
                        !$runtimeFreeFixture && $phase === 'pre-remove' && ($hook['type'] ?? null) === $phase
                        && ($hook['source'] ?? null) === 'project' && ($hook['name'] ?? null) === 'runtime'
                        && ($hook['template'] ?? null) === self::DOWN_HOOK,
                        sprintf(
                            'Unqualified Worktrunk %s hook in %s context; source retained',
                            $phase,
                            $directory === $main ? 'canonical' : 'target'
                        )
                    );
                }
            }
        }
    }

    /**
     * Verifies an owned Git-only fixture has no runtime declarations or effects
     */
    private static function proveRuntimeFree(Installation $installation): void
    {
        $main = $installation->main;
        $project = $installation->project;
        Native::require(Native::text([
            'git',
            'remote'
        ], $main) === '', 'Runtime-free fixture must be a local generated repository without remotes');
        foreach (array_unique([$main, $installation->checkout]) as $directory) {
            foreach (
                [
                'bin/down',
                'compose.yaml',
                'compose.yml',
                'docker-compose.yaml',
                'docker-compose.yml'
                ] as $file
            ) {
                Native::require(
                    !Native::exists($directory.'/'.$file),
                    'Runtime-free fixture has an application lifecycle declaration; use its owning teardown'
                );
            }
        }
        foreach ([$installation->state(), $main.'/.runs/compose/'.$project] as $path) {
            Native::require(
                !Native::exists($path),
                'Runtime-free fixture has generated runtime state; use its owning teardown'
            );
        }
        foreach (['containers', 'volumes', 'networks'] as $kind) {
            Native::require(Native::ids(
                $main,
                $kind,
                $project
            ) === [], 'Runtime-free fixture has native project '.$kind.'; use its owning teardown');
        }
        Native::require(
            Teardown::tags($installation) === [],
            'Runtime-free fixture has task image tags; use its owning teardown'
        );
        $builders = Native::lines(Native::text(['docker', 'buildx', 'ls', '--format', '{{.Name}}'], $main));
        $cache = Native::text([
            'docker',
            'volume',
            'ls',
            '-q',
            '--filter',
            'name=^buildx_buildkit_'.$project.'0_state$'
        ], $main);
        Native::require(!in_array(
            $project,
            $builders,
            true
        ) && $cache === '', 'Runtime-free fixture has a task builder/cache; use its owning teardown');
    }

    /**
     * Verifies the exact delivered PR and source ref without publishing anything
     */
    private static function publication(string $root, string $oid, string $ref, string $pr): void
    {
        $origin = Native::text(['git', 'remote', 'get-url', 'origin'], $root);
        Native::require(
            preg_match('~^(?:git@github\.com:|https://github\.com/)([^/]+/[^/]+?)(?:\.git)?$~D', $origin, $match) === 1,
            'Supported exact GitHub origin required for publication proof'
        );
        Native::require(preg_match('~^https://github\.com/'.preg_quote(
            $match[1],
            '~'
        ).'/pull/[1-9][0-9]*$~D', $pr) === 1, 'Exact origin-owned PR required');
        $proof = Native::query([
            'gh',
            'pr',
            'view',
            $pr,
            '--json',
            'url,state,isDraft,headRefName,headRefOid,baseRefName,mergedAt,mergeCommit'
        ], $root);
        Native::require(
            ($proof['url'] ?? null) === $pr && ($proof['headRefOid'] ?? null) === $oid
            && ($proof['headRefName'] ?? null) === substr(
                $ref,
                strlen('refs/heads/')
            ) && ($proof['baseRefName'] ?? null) === 'develop',
            'Live PR repository/base/head identity mismatch'
        );
        if (($proof['state'] ?? null) === 'OPEN') {
            Native::require(
                ($proof['isDraft'] ?? null) === false,
                'Draft evidence publication is not delivery completion'
            );
            Native::require(
                preg_split('/\s+/', Native::text([
                    'git',
                    'ls-remote',
                    '--exit-code',
                    'origin',
                    $ref
                ], $root)) === [
                    $oid,
                    $ref
                ],
                'Published source unavailable or changed'
            );
        } else {
            $merge = $proof['mergeCommit']['oid'] ?? '';
            Native::require(
                ($proof['state'] ?? null) === 'MERGED' && !empty($proof['mergedAt']) && preg_match(
                    '/^[a-f0-9]{40}$/D',
                    $merge
                ) === 1,
                'PR not delivered; preserve local source'
            );
            Native::run(['git', 'merge-base', '--is-ancestor', $merge, 'refs/remotes/origin/develop'], $root);
        }
    }
}
