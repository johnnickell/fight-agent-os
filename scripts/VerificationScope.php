<?php

declare(strict_types=1);

/**
 * Reads Git metadata without fetching objects, refreshing the index on disk, or using replace refs
 *
 * @param string[] $arguments Git arguments as individual strings
 *
 * @phpstan-param list<string> $arguments
 */
function scopeGit(array $arguments): ?string
{
    $process = proc_open(
        ['git', '-c', 'core.fsmonitor=false', '--no-optional-locks', '--no-replace-objects', ...$arguments],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        null,
        array_replace(getenv(), ['GIT_NO_LAZY_FETCH' => '1', 'GIT_ALLOW_PROTOCOL' => ''])
    );
    if (!is_resource($process)) {
        return null;
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return proc_close($process) === 0 && $output !== false ? $output : null;
}

/**
 * Splits Git's NUL-terminated records without interpreting filename bytes
 *
 * @return list<string>|null
 */
function scopeRecords(?string $output): ?array
{
    if ($output === null || ($output !== '' && !str_ends_with($output, "\0"))) {
        return null;
    }

    return $output === '' ? [] : explode("\0", substr($output, 0, -1));
}

/**
 * Maps owned inputs to verification passes and the cross-stack browser smoke lane
 *
 * @return list<string>
 */
function scopeOwners(string $path): array
{
    if (str_starts_with($path, 'planning/') && str_ends_with($path, '.md')) {
        return ['planning'];
    }

    $frontend = ['frontend'];
    $smoke = ['frontend', 'browser_smoke'];
    $app = ['php', ...$smoke];
    $shared = ['php', ...$smoke, 'pi_harness', 'planning'];
    // Put narrower tooling/config paths before their owning source directories.
    $rules = [
        [$frontend, ['client/tests/*', 'client/stories/*', 'client/.storybook/*', 'client/vitest*',
            'client/eslint.config.mjs', 'client/.prettierrc.json', 'packages/react/tests/*',
            'packages/react/stories/*']],
        [$smoke, ['client/*', 'packages/react/*', 'bin/client', 'bin/browser-smoke', 'scripts/browser_smoke*',
            'etc/docker/client-browser/*', '.github/actions/native-client/*', '.github/workflows/client-checks.yml']],
        [['php'], ['tests/*', 'phpcs.xml', 'phpunit.xml', 'phpstan.neon', 'rector.php', 'deptrac.php',
            'bin/php-pass', 'bin/phpunit', 'bin/backend-*', 'bin/architecture', 'scripts/PhpPass.php',
            'scripts/php_pass.php', 'scripts/backend_*', 'scripts/php_syntax.php', 'scripts/test_migrations.php',
            '.github/workflows/php-pass.yml']],
        [$app, ['bootstrap/*', 'config/*', 'database/*', 'public/*', 'resources/*', 'src/*', 'templates/*',
            'composer.json', 'composer.lock', 'cli-config.php', 'migrations.php', '.env.example',
            'bin/openapi', 'scripts/openapi.php', 'scripts/OpenApi/*', 'bin/validations', 'scripts/validations.php',
            'docs/api/*', 'etc/docker/fpm/*', 'etc/docker/https/*', 'etc/docker/php/*', 'etc/docker/ingress/*',
            'bin/composer', 'bin/database', 'bin/console', 'scripts/console.php', 'scripts/guarded_test_database.php',
            'scripts/password_corpus.php', '.github/actions/native-php/*']],
        [['pi_harness'], ['harness/pi/*', '.pi/*', 'bin/harness-check', 'bin/pi-dependencies',
            '.github/workflows/harness-pass.yml']],
        [['planning'], ['bin/planning-check', 'bin/archive-planning', 'scripts/PlanningPortfolio.php',
            'scripts/planning_portfolio.php', 'scripts/archive_planning.py', 'etc/docker/planning/*']],
        [$shared, ['AGENTS.md', '.gitattributes', '.gitignore', '.dockerignore', '.editorconfig',
            'compose.yaml', 'etc/docker/cli/*', 'bin/build', 'bin/runtime', 'scripts/runtime.py',
            'scripts/resource_lifecycle/*', 'bin/verification-scope', 'scripts/verification_scope.php',
            'scripts/VerificationScope.php', 'scripts/build.php', 'scripts/BuildCache.php',
            'scripts/VerificationWorkspace.php', '.github/workflows/build.yml', 'docs/engineering/BUILD.md']]
    ];
    // PHP syntax and style scan scripts/ recursively, even for tools owned by another pass.
    $checkedByPhp = str_starts_with($path, 'scripts/') && str_ends_with($path, '.php');
    foreach ($rules as [$owners, $patterns]) {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return $checkedByPhp && !in_array('php', $owners, true) ? ['php', ...$owners] : $owners;
            }
        }
    }

    return [];
}

/**
 * Reads committed paths and whether each may no longer be a file
 *
 * @return array<array-key, boolean>|null
 */
function scopeCommittedPaths(string $base, string $head): ?array
{
    // Compare deletes and adds independently so both sides of any rename are classified.
    $records = scopeRecords(scopeGit(['diff', '--name-status', '-z', '--no-renames', '--no-relative',
        '--no-ext-diff', '--no-textconv', '--ignore-submodules=none', $base, $head, '--']));
    if ($records === null || count($records) % 2 !== 0) {
        return null;
    }
    $paths = [];
    foreach (array_chunk($records, 2) as $pair) {
        [$status, $path] = $pair;
        if ($path === '' || !in_array($status, ['A', 'D', 'M', 'T'], true)) {
            return null;
        }
        $paths[$path] = $status === 'D' || $status === 'T';
    }

    return $paths;
}

/**
 * Reads visible staged, unstaged, and non-ignored untracked paths and whether each may no longer be a file
 *
 * @return array<array-key, boolean>|null
 */
function scopeWorkingPaths(): ?array
{
    $index = scopeRecords(scopeGit(['ls-files', '-v', '-z', '--full-name', ':/']));
    if ($index === null) {
        return null;
    }
    foreach ($index as $entry) {
        // Sparse and assume-unchanged entries can conceal local changes from status.
        if ($entry === '' || $entry[0] === 'S' || str_contains('abcdefghijklmnopqrstuvwxyz', $entry[0])) {
            return null;
        }
    }

    $status = scopeRecords(scopeGit(['status', '--porcelain=v1', '-z', '--no-renames',
        '--untracked-files=all', '--ignore-submodules=none']));
    if ($status === null) {
        return null;
    }
    $paths = [];
    foreach ($status as $entry) {
        $code = substr($entry, 0, 2);
        if (
            strlen($entry) < 4 || $entry[2] !== ' ' || strpbrk($code, 'URC') !== false
            || in_array($code, ['AA', 'DD'], true)
        ) {
            return null;
        }
        $path = substr($entry, 3);
        $paths[$path] = ($paths[$path] ?? false) || strpbrk($code, 'DT') !== false;
    }

    return $paths;
}

/**
 * Resolves a caller-supplied ref to a commit without treating it as a Git option
 */
function scopeCommit(string $ref): ?string
{
    if (str_starts_with($ref, 'refs/')) {
        $exactRef = scopeGit(['show-ref', '--verify', '--hash', '--', $ref]);
        if ($exactRef === null) {
            return null;
        }
        $ref = trim($exactRef);
    } elseif ($ref !== 'HEAD' && preg_match('/\A(?:[0-9a-f]{40}|[0-9a-f]{64})\z/', $ref) !== 1) {
        $symbolic = scopeGit(['-c', 'core.warnAmbiguousRefs=true', 'rev-parse', '--verify',
            '--symbolic-full-name', '--end-of-options', $ref]);
        if ($symbolic === null || !str_starts_with($symbolic, 'refs/')) {
            return null;
        }

        return scopeCommit(trim($symbolic));
    }
    $result = scopeGit(['rev-parse', '--verify', '--end-of-options', $ref.'^{commit}']);

    return $result === null ? null : trim($result);
}

/**
 * Selects passes from a unique merge base, or selects all when evidence is incomplete
 *
 * @return array<string, mixed>
 */
function scopeReport(string $baseRef, ?string $headRef): array
{
    $baseTip = scopeCommit($baseRef);
    $head = scopeCommit($headRef ?? 'HEAD');
    $comparisonBase = null;
    $fallback = null;
    $paths = [];
    if ($baseTip === null || $head === null) {
        $fallback = 'The requested base or candidate commit is unavailable.';
    } elseif (scopeGit(['rev-parse', '--is-shallow-repository']) !== "false\n") {
        $fallback = 'Git history is shallow or could not be verified.';
    } else {
        $mergeBases = explode("\n", trim(scopeGit(['merge-base', '--all', $baseTip, $head]) ?? ''));
        if ($mergeBases === [''] || count($mergeBases) !== 1) {
            $fallback = 'Git could not establish one comparison base.';
        } else {
            $comparisonBase = $mergeBases[0];
            $committed = scopeCommittedPaths($comparisonBase, $head);
            $working = $headRef === null ? scopeWorkingPaths() : [];
            if ($committed === null || $working === null) {
                $fallback = 'Git could not establish the complete changed-path list.';
            } else {
                $paths = $committed;
                foreach ($working as $path => $mayDisappear) {
                    $paths[$path] = ($paths[$path] ?? false) || $mayDisappear;
                }
                ksort($paths, SORT_STRING);
            }
        }
    }

    $matched = [];
    foreach ($paths as $pathKey => $mayDisappear) {
        $path = (string) $pathKey;
        $owners = scopeOwners($path);
        if ($owners === []) {
            $fallback = 'No verification owner is known for '.$path.'.';
            break;
        }
        // Planning validates links to Markdown files even outside the planning directory.
        if ($mayDisappear && str_ends_with($path, '.md') && !in_array('planning', $owners, true)) {
            $owners[] = 'planning';
        }
        foreach ($owners as $owner) {
            $matched[$owner][] = $path;
        }
    }

    $decisions = [];
    foreach (['php', 'frontend', 'pi_harness', 'planning', 'browser_smoke'] as $pass) {
        $inputs = $matched[$pass] ?? [];
        $decisions[$pass] = [
            'selected' => $fallback !== null || $inputs !== [],
            'reason'   => $fallback ?? ($inputs === [] ? 'No owned input changed.' : sprintf(
                '%d matching path(s); first: %s',
                count($inputs),
                $inputs[0]
            ))
        ];
    }
    $decisions['frontend']['browser_smoke'] = $decisions['browser_smoke'];
    unset($decisions['browser_smoke']);

    return [
        'base_ref'        => $baseRef,
        'base_tip'        => $baseTip,
        'comparison_base' => $comparisonBase,
        'candidate'       => $head,
        'scope'           => $headRef === null ? 'working_tree' : 'commit',
        'fallback'        => $fallback,
        'passes'          => $decisions
    ];
}
