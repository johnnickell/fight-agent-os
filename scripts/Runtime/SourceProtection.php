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
                Native::require(!Native::beneath($source, $checkout), 'Source bind dependency remains; qualify the actual consumer before source removal');
                if (Native::beneath($checkout, $source)) {
                    self::checkParentMount($main, $container, rtrim($mount['Destination'], '/').'/'.substr($checkout, strlen(rtrim($source, '/')) + 1));
                }
            }
        }
        [$status, $output, $error] = Native::process(['lsof', '-nP', '-Fpuaftn', '+D', $checkout], $main, capture: true, timeout: 60);
        Native::require(in_array($status, [0, 1], true) && trim($error) === '', 'Source consumer visibility unavailable');
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
     * Rechecks all targets before releasing only the verified delete rules
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
            Native::require(self::acl($installation->main, $path) === [], 'Runtime mount target ACL retirement incomplete: '.$path);
            echo 'Released owner delete rule on retired runtime mount target: '.$path."\n";
        }
    }

    /**
     * Checks parent-only bind exposure without stopping foreign services
     *
     * @phpstan-param array<string,mixed> $container
     */
    private static function checkParentMount(string $main, array $container, string $target): void
    {
        Native::require($container['State']['Running'] === true, 'Parent source mount requires live consumer qualification');
        $configuration = array_intersect_key($container['Config'], array_flip(['WorkingDir', 'Entrypoint', 'Cmd']));
        Native::require(!str_contains(Native::json($configuration), $target), 'Container configuration consumes candidate source');
        $script = <<<'SH'
            for process in /proc/[0-9]*; do
                [ "$process" = "/proc/$$" ] && continue
                [ -d "$process" ] || continue
                [ -r "$process/cmdline" ] && [ -r "$process/maps" ] && [ -r "$process/fd" ] || exit 2
                grep -F -q -- "$1" "$process/cmdline" "$process/maps"
                status=$?
                [ "$status" -eq 0 ] && exit 3
                [ "$status" -eq 1 ] || exit 2
                for descriptor in "$process/cwd" "$process/exe" "$process"/fd/*; do
                    [ -L "$descriptor" ] || continue
                    value=$(readlink "$descriptor") || { [ ! -L "$descriptor" ] && continue; exit 2; }
                    case "$value" in "$1"|"$1"/*) exit 3 ;; esac
                done
            done
            exit 0
            SH;
        [$status] = Native::process(['docker', 'exec', $container['Id'], 'sh', '-c', $script, 'source-consumer-check', $target], $main, capture: true, timeout: 60);
        $id = substr($container['Id'], 0, 12);
        Native::require($status !== 3, 'Parent-mounted container consumes candidate source: '.$id);
        Native::require($status === 0, 'Parent-mounted container process visibility unavailable with configured identity: '.$id);
    }

    /**
     * Qualifies only positively identified Docker file-sharing read handles
     */
    private static function checkReaders(string $main, string $output): void
    {
        Native::require(PHP_OS_FAMILY === 'Darwin', 'Source has an active/unknown reader, writer or cwd; retain source');
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
                && preg_match('/^\*?[0-9]+$/D', $record['fd']) === 1, 'Source has active/unknown cwd, writer or reader; retain source');
            if (isset($qualified[$pid])) {
                continue;
            }
            Native::require(
                Native::text(['ps', '-p', $pid, '-o', 'comm='], $main) === '/System/Library/Frameworks/Virtualization.framework/Versions/A/'.'XPCServices/com.apple.Virtualization.VirtualMachine.xpc/Contents/MacOS/com.apple.Virtualization.VirtualMachine',
                'Source reader is not qualified Docker file sharing'
            );
            $account = posix_getpwuid(posix_getuid());
            Native::require(is_array($account), 'Native account visibility unavailable');
            $disk = $account['dir'].'/Library/Containers/com.docker.docker/Data/vms/0/data/Docker.raw';
            Native::require(
                is_file($disk) && in_array('n'.$disk, Native::lines(Native::text(['lsof', '-nP', '-p', $pid, '-Fn'], $main)), true),
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
        Native::require(is_array($info) && $info['uid'] === posix_getuid(), 'Source filesystem protection/foreign owner');
        if (PHP_OS_FAMILY === 'Darwin') {
            Native::require(Native::text(['/usr/bin/stat', '-f', '%f', $path], $main) === '0', 'Source filesystem protection/foreign owner');
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
            in_array($path, [$checkout.'/.runs', $checkout.'/var', $checkout.'/packages/react/dist'], true) && !is_link($path),
            'Source ACL protection outside reserved runtime mount targets: '.$path
        );
        $identity = null;
        foreach (self::tree($path) as $node) {
            $info = self::fileInfo($node, $main);
            Native::require(($info['mode'] & 0170000) === 0040000, 'Runtime mount target still contains data, aliases or protections: '.$path);
            if ($node === $path) {
                $identity = [$info['dev'], $info['ino']];
            } else {
                Native::require(self::acl($main, $node) === [], 'Runtime mount descendant ACL protection: '.$node);
            }
        }
        Native::run(['git', 'check-ignore', '--quiet', '--', $path], $checkout);
        $message = 'Native macOS account lookup unavailable or unverifiable; retain source and rerun the same guarded bin/runtime finish '.'from the canonical checkout with native macOS account access. Do not strip ACLs manually or substitute a guessed owner UUID.';
        try {
            $account = posix_getpwuid(posix_getuid());
            Native::require(is_array($account), $message);
            $record = HostRoots::plist(['/usr/bin/dscl', '-plist', '/Search', '-read', '/Users/'.$account['name'], 'GeneratedUID', 'UniqueID'], $main);
            $ids = $record['dsAttrTypeStandard:GeneratedUID'] ?? null;
            Native::require(($record['dsAttrTypeStandard:UniqueID'] ?? null) === [(string) posix_getuid()]
                && is_array($ids) && count($ids) === 1 && preg_match('/^[a-fA-F0-9]{8}(?:-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12}$/D', $ids[0]) === 1, $message);
        } catch (\RuntimeException | \JsonException | \TypeError $error) {
            throw new \RuntimeException($message, previous: $error);
        }
        Native::require($acl === ['0: '.strtoupper($ids[0]).' deny delete'], 'Source ACL protection is not the sole owner delete rule: '.$path);

        Native::require($identity !== null, 'Runtime mount identity unavailable');

        return $identity;
    }
}
