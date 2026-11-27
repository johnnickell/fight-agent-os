<?php

declare(strict_types=1);

namespace Fight\Runtime;

/**
 * Class SourceProtection
 *
 * @phpstan-type MountTarget array{array{int,int},list<string>}
 */
final class SourceProtection
{
    /**
     * Qualifies live consumers and filesystem protections before retirement
     *
     * @return array<string,MountTarget>
     */
    public static function check(Installation $installation, bool $retireMounts): array
    {
        $main = $installation->main;
        $checkout = $installation->checkout;
        $ids = Native::lines(Native::text(['docker', 'ps', '-aq'], $main));
        foreach ($ids === [] ? [] : Native::query(['docker', 'inspect', ...$ids], $main) as $container) {
            foreach ($container['Mounts'] as $mount) {
                if ($mount['Type'] !== 'bind') {
                    continue;
                }
                $source = Native::canonical($mount['Source']);
                Native::require(!Native::beneath(
                    $source,
                    $checkout
                ), 'Source bind dependency remains; qualify the actual consumer before source removal');
                if (Native::beneath($checkout, $source)) {
                    self::checkParentMount($main, $container, $mount['Destination'], $source, $checkout);
                }
            }
        }
        [
            $status,
            $output,
            $error
        ] = Native::process([
            'lsof',
            '-nP',
            '-Fpuaftn',
            '+D',
            $checkout
        ], $main, capture: true, timeout: 60);
        Native::require(in_array($status, [
            0,
            1
        ], true) && trim($error) === '', 'Source consumer visibility unavailable');
        if (trim($output) !== '') {
            self::checkReaders($main, $output);
        }
        $targets = [];
        foreach (self::tree($checkout) as $path) {
            $info = self::fileInfo($path, $main);
            Native::require(($info['mode'] & 0170000) !== 0140000, 'Source socket remains');
            if (PHP_OS_FAMILY === 'Darwin') {
                $acl = self::acl($main, $path);
                if ($acl !== []) {
                    Native::require($retireMounts, 'Source ACL protection; no automatic stripping: '.$path);
                    $targets[$path] = [self::qualifyMount($installation, $path, $acl), $acl];
                }
            }
        }
        ksort($targets);

        return $targets;
    }

    /**
     * Checks all targets before releasing only the verified delete rules
     *
     * @phpstan-param array<string,MountTarget> $targets
     */
    public static function release(Installation $installation, array $targets): void
    {
        $verify = static function (string $path, array $identity, array $acl) use ($installation): void {
            $current = self::acl($installation->main, $path);
            Native::require(
                $current === $acl && self::qualifyMount($installation, $path, $current) === $identity,
                'Runtime mount target changed before retirement: '.$path
            );
        };
        foreach ($targets as $path => [$identity, $acl]) {
            $verify($path, $identity, $acl);
        }
        foreach ($targets as $path => [$identity, $acl]) {
            $verify($path, $identity, $acl);
            Native::run(['/bin/chmod', '-a#', '0', $path], $installation->main);
            Native::require(self::acl(
                $installation->main,
                $path
            ) === [], 'Runtime mount target ACL retirement incomplete: '.$path);
            echo 'Released owner delete rule on retired runtime mount target: '.$path."\n";
        }
    }

    /**
     * Checks parent-only bind exposure without stopping foreign services
     *
     * @phpstan-param array<string,mixed> $container
     */
    public static function checkParentMount(
        string $main,
        array $container,
        string $destination,
        string $source,
        string $directory
    ): void {
        $target = rtrim($destination, '/').'/'.substr($directory, strlen(rtrim($source, '/')) + 1);
        Native::require(
            $container['State']['Running'] === true,
            'Parent mount requires live consumer qualification'
        );
        // Only Docker-declared read-only tmpfs mounts can qualify for the fallback.
        $cover = '';
        foreach (($container['HostConfig']['Tmpfs'] ?? []) as $path => $options) {
            if (
                is_string($path) && is_string($options) && preg_match('~^(?:/[A-Za-z0-9_.-]+)+$~D', $path) === 1
                && in_array('ro', explode(',', $options), true) && !in_array('rw', explode(',', $options), true)
                && str_starts_with($target, $path.'/') && strlen($path) > strlen($cover)
            ) {
                $cover = $path;
            }
        }
        $configuration = [];
        foreach (['WorkingDir', 'Entrypoint', 'Cmd'] as $key) {
            foreach ((array) ($container['Config'][$key] ?? null) as $value) {
                Native::require(is_string($value), 'Container configuration cannot be qualified');
                Native::require(!str_contains($value, "\0"), 'Container configuration cannot be qualified');
                $configuration[] = $value;
            }
        }
        $mountCheck = <<<'SH'
            export LC_ALL=C
            cover=$2
            verify_mask() {
                [ -n "$cover" ] && [ "$(realpath -e "$cover")" = "$cover" ] || return 1
                device=$(stat -c '%Hd:%Ld' -- "$cover") || return 1
                namespace=$(readlink "/proc/$$/ns/mnt") || return 1
                # Read the probe's mount namespace, not the Docker configuration alone.
                # Reject stacked mounts, aliases of the tmpfs and any deeper mount in its subtree.
                awk -v cover="$cover" -v device="$device" -v namespace="$namespace" '
                    $5 == cover {
                        at_cover++
                        for (i = 7; i <= NF && $i != "-"; i++) {}
                        if ($3 == device && $4 == "/" && $6 ~ /(^|,)ro(,|$)/ &&
                            NF == i + 3 && $(i+1) == "tmpfs" && $(i+2) == "tmpfs") id = $1
                    }
                    $3 == device { on_device++ }
                    index($5, cover "/") == 1 { deeper++ }
                    END {
                        if (!id || at_cover != 1 || on_device != 1 || deeper) exit 1
                        print namespace ":" id ":" device
                    }
                ' "/proc/$$/mountinfo"
            }
            SH;
        $script = <<<'SH'
            mask=
            if ! target=$(realpath -- "$1" 2>/dev/null); then
                case "$1/" in *'/../'*|*'/./'*|*'//'*) exit 2 ;; esac
                mask=$(verify_mask) || exit 2
                # A process may still have a kernel reference below the mask from an older view.
                target=$cover
            fi
            within_target() {
                case "$1" in "$target"|"$target"/*) exit 3 ;; esac
            }
            check_path() {
                case "$1" in
                    /*) path=$1 ;;
                    */*|.|..) path=$2/$1 ;;
                    *) [ -e "$2/$1" ] || [ -L "$2/$1" ] || return; path=$2/$1 ;;
                esac
                # Some realpath implementations preserve a leading double slash.
                while [ "${path#//}" != "$path" ]; do path=${path#/}; done
                # Walking out via .. still depends on the directory the path enters.
                prefix=$path/
                while [ "${prefix#*/../}" != "$prefix" ]; do
                    prefix=${prefix%/../*}
                    check_resolved "${prefix:-/}"
                    prefix=$prefix/
                done
                check_resolved "$path"
            }
            check_resolved() {
                # For a not-yet-created descendant, check its nearest existing parent.
                entry=$1
                while [ ! -e "$entry" ]; do
                    [ ! -L "$entry" ] || exit 2
                    entry=${entry%/*}
                    [ -n "$entry" ] || entry=/
                done
                resolved=$(realpath "$entry") || exit 2
                if [ -d "$resolved" ]; then [ -x "$resolved" ] || exit 2; fi
                within_target "$resolved"
            }
            arguments_as_lines() {
                # Preserve each NUL-delimited field, encoding multiline fields separately.
                xargs -0 sh -c '
                    newline="
            "
                    for argument do
                        case "$argument" in
                            *"$newline"*)
                                bytes=$(printf "%s" "$argument" | od -An -v -to1) || exit 2
                                printf "O" || exit 2
                                for byte in $bytes; do printf "\\\\0%s" "$byte" || exit 2; done
                                printf "\n" || exit 2
                                ;;
                            *) printf "L%s\n" "$argument" || exit 2 ;;
                        esac
                    done
                ' arguments-as-lines
            }
            backtick='`'
            check_text() {
                # Qualify a literal argument before interpreting any shell text within it.
                while IFS= read -r record; do
                    case "$record" in
                        L*) line=${record#L} ;;
                        O*) line=$(printf '%b_' "${record#O}") || exit 2; line=${line%_} ;;
                        *) exit 2 ;;
                    esac
                    case "$line" in
                        /*|./*|../*) check_path "$line" "$1" ;;
                        */*)
                            first=$1/${line%%/*}
                            if [ -e "$first" ] || [ -L "$first" ]; then check_path "$line" "$1"; fi
                            ;;
                    esac
                    tokens=$(printf '%s\n' "$line" | grep -oE "\"[^\"]*\"|'[^']*'|[^[:space:]\"'$backtick=:;|&<>()]+")
                    case $? in 0) ;; 1) continue ;; *) exit 2 ;; esac
                    while IFS= read -r token; do
                        case "$token" in
                            \"*\") token=${token#\"}; token=${token%\"} ;;
                            \'*\') token=${token#\'}; token=${token%\'} ;;
                        esac
                        case "$token" in -?*=*) token=${token#*=} ;; esac
                        [ -z "$token" ] || check_path "$token" "$1"
                    done <<EOF
            $tokens
            EOF
                done
            }
            if [ -z "$mask" ]; then
                working=$(pwd -P) || exit 2
                arguments=$(arguments_as_lines) || exit 2
                check_text "$working" <<EOF
            $arguments
            EOF
            fi
            for process in /proc/[0-9]*; do
                [ "$process" = "/proc/$$" ] && continue
                [ -d "$process" ] || continue
                [ -r "$process/cmdline" ] && [ -r "$process/maps" ] && [ -r "$process/fd" ] || exit 2
                [ "$process/root" -ef "/proc/$$/root" ] && [ "$process/ns/mnt" -ef "/proc/$$/ns/mnt" ] || exit 2
                working=$(readlink "$process/cwd") || exit 2
                case "$working" in /*' (deleted)') exit 2 ;; /*) ;; *) exit 2 ;; esac
                if [ -z "$mask" ]; then
                    arguments=$(arguments_as_lines < "$process/cmdline") || exit 2
                    check_text "$working" <<EOF
            $arguments
            EOF
                fi
                # The kernel already reports resolved paths for mappings and handles.
                mappings=$(cat "$process/maps") || exit 2
                while read -r range perms offset device inode mapped; do
                    within_target "${mapped% (deleted)}"
                done <<EOF
            $mappings
            EOF
                for descriptor in "$process/cwd" "$process/exe" "$process"/fd/*; do
                    [ -L "$descriptor" ] || continue
                    value=$(readlink "$descriptor") || { [ ! -L "$descriptor" ] && continue; exit 2; }
                    within_target "${value% (deleted)}"
                done
            done
            if [ -n "$mask" ]; then
                [ "$(verify_mask)" = "$mask" ] || exit 2
                printf '%s\n' "$mask"
            fi
            exit 0
            SH;
        [$status, $mask] = Native::process([
            'docker',
            'exec',
            '-i',
            $container['Id'],
            'sh',
            '-c',
            $mountCheck."\n".$script,
            'parent-bind-consumer-check',
            $target,
            $cover
        ], $main, capture: true, input: implode("\0", $configuration)."\0", timeout: 60);
        $id = substr($container['Id'], 0, 12);
        Native::require($status !== 3, 'Parent-mounted container consumes candidate directory: '.$id);
        if ($status === 0 && $mask !== '') {
            // Docker's tmpfs may be root-only. Keep the consumer scan under the configured identity;
            // use root only to prove the matched cover has no entries through which lookup can continue.
            $emptyCover = <<<'SH'
                mask=$(verify_mask) || exit 2
                entries=$(find "$cover" -mindepth 1 -maxdepth 1 -print -quit) || exit 2
                [ -z "$entries" ] && [ "$(verify_mask)" = "$mask" ] || exit 2
                printf '%s\n' "$mask"
                SH;
            [$status, $verified, $error] = Native::process([
                'docker',
                'exec',
                '--user',
                '0:0',
                $container['Id'],
                'sh',
                '-c',
                $mountCheck."\n".$emptyCover,
                'parent-bind-empty-cover-check',
                $target,
                $cover
            ], $main, capture: true, timeout: 60);
            Native::require(
                $status === 0 && $error === '' && $verified === $mask,
                'Parent-mounted container mask emptiness unavailable: '.$id
            );
        }
        Native::require(
            $status === 0,
            'Parent-mounted container process visibility unavailable with configured identity: '.$id
        );
    }

    /**
     * Qualifies only positively identified Docker file-sharing read handles
     */
    private static function checkReaders(string $main, string $output): void
    {
        Native::require(
            PHP_OS_FAMILY === 'Darwin',
            'Source has an active/unknown reader, writer or cwd; retain source'
        );
        $records = $users = [];
        $pid = null;
        $index = null;
        foreach (Native::lines(trim($output)) as $line) {
            $value = substr($line, 1);
            switch ($line[0] ?? '') {
                case 'p':
                    $pid = $value;
                    $index = null;
                    break;
                case 'u':
                    $users[$pid] = $value;
                    break;
                case 'f':
                    $index = count($records);
                    $records[] = ['pid' => $pid, 'fd' => $value];
                    break;
                case 'a':
                case 't':
                    if ($index !== null) {
                        $records[$index][$line[0]] = $value;
                    }
                    break;
            }
        }
        Native::require($records !== [], 'Source consumer visibility unavailable');
        $qualified = [];
        foreach ($records as $record) {
            $pid = $record['pid'];
            Native::require(is_string($pid) && ctype_digit($pid) && ($users[$pid] ?? null) === (string) posix_getuid()
                && ($record['a'] ?? null) === 'r' && in_array($record['t'] ?? null, ['REG', 'DIR'], true)
                && preg_match(
                    '/^\*?[0-9]+$/D',
                    $record['fd']
                ) === 1, 'Source has active/unknown cwd, writer or reader; retain source');
            if (isset($qualified[$pid])) {
                continue;
            }
            Native::require(
                Native::text([
                    'ps',
                    '-p',
                    $pid,
                    '-o',
                    'comm='
                ], $main) === implode('/', [
                    '/System/Library/Frameworks/Virtualization.framework/Versions/A',
                    'XPCServices/com.apple.Virtualization.VirtualMachine.xpc/Contents/MacOS',
                    'com.apple.Virtualization.VirtualMachine'
                ]),
                'Source reader is not qualified Docker file sharing'
            );
            $account = posix_getpwuid(posix_getuid());
            Native::require(is_array($account), 'Native account visibility unavailable');
            $disk = $account['dir'].'/Library/Containers/com.docker.docker/Data/vms/0/data/Docker.raw';
            Native::require(
                is_file($disk) && in_array('n'.$disk, Native::lines(Native::text([
                    'lsof',
                    '-nP',
                    '-p',
                    $pid,
                    '-Fn'
                ], $main)), true),
                'Virtualization reader lacks actual Docker runtime backing evidence'
            );
            $qualified[$pid] = true;
        }
    }

    /**
     * Checks native filesystem ownership and flags without following links
     *
     * @return array<mixed>
     */
    private static function fileInfo(string $path, string $main): array
    {
        clearstatcache(true, $path);
        $info = @lstat($path);
        Native::require(
            is_array($info) && $info['uid'] === posix_getuid(),
            'Source filesystem protection/foreign owner'
        );
        if (PHP_OS_FAMILY === 'Darwin') {
            Native::require(Native::text([
                '/usr/bin/stat',
                '-f',
                '%f',
                $path
            ], $main) === '0', 'Source filesystem protection/foreign owner');
        }

        return $info;
    }

    /**
     * Lists descendants without following symlinks
     *
     * @return \Generator<int,string>
     */
    private static function tree(string $path): \Generator
    {
        yield $path;
        if (!is_link($path) && is_dir($path)) {
            foreach (new \FilesystemIterator($path) as $child) {
                Native::require($child instanceof \SplFileInfo, 'Cannot inspect selected source');
                yield from self::tree($child->getPathname());
            }
        }
    }

    /**
     * Reads the actual macOS ACL entries
     *
     * @return list<string>
     */
    private static function acl(string $main, string $path): array
    {
        return array_map('trim', array_slice(Native::lines(Native::text(['/bin/ls', '-lden', $path], $main)), 1));
    }

    /**
     * Qualifies the sole owner delete rule on an empty reserved mount target
     *
     * @phpstan-param list<string> $acl
     * @return array{int,int}
     */
    private static function qualifyMount(Installation $installation, string $path, array $acl): array
    {
        $main = $installation->main;
        $checkout = $installation->checkout;
        Native::require(
            in_array($path, [
                $checkout.'/.runs',
                $checkout.'/var',
                $checkout.'/packages/react/dist'
            ], true) && !is_link($path),
            'Source ACL protection outside reserved runtime mount targets: '.$path
        );
        $identity = null;
        foreach (self::tree($path) as $node) {
            $info = self::fileInfo($node, $main);
            Native::require(
                ($info['mode'] & 0170000) === 0040000,
                'Runtime mount target still contains data, aliases or protections: '.$path
            );
            if ($node === $path) {
                $identity = [$info['dev'], $info['ino']];
            } else {
                Native::require(self::acl($main, $node) === [], 'Runtime mount descendant ACL protection: '.$node);
            }
        }
        Native::run(['git', 'check-ignore', '--quiet', '--', $path], $checkout);
        $message = implode(' ', [
            'Native macOS account lookup unavailable or unverifiable; retain source and rerun the same',
            'guarded bin/runtime finish from the canonical checkout with native macOS account access.',
            'Do not strip ACLs manually or substitute a guessed owner UUID.'
        ]);
        try {
            $account = posix_getpwuid(posix_getuid());
            Native::require(is_array($account), $message);
            $record = HostRoots::plist([
                '/usr/bin/dscl',
                '-plist',
                '/Search',
                '-read',
                '/Users/'.$account['name'],
                'GeneratedUID',
                'UniqueID'
            ], $main);
            $ids = $record['dsAttrTypeStandard:GeneratedUID'] ?? null;
            Native::require(($record['dsAttrTypeStandard:UniqueID'] ?? null) === [(string) posix_getuid()]
                && is_array($ids) && count($ids) === 1 && preg_match(
                    '/^[a-fA-F0-9]{8}(?:-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12}$/D',
                    $ids[0]
                ) === 1, $message);
        } catch (\RuntimeException | \JsonException | \TypeError $error) {
            throw new \RuntimeException($message, previous: $error);
        }
        Native::require(
            $acl === ['0: '.strtoupper($ids[0]).' deny delete'],
            'Source ACL protection is not the sole owner delete rule: '.$path
        );

        Native::require($identity !== null, 'Runtime mount identity unavailable');

        return $identity;
    }
}
