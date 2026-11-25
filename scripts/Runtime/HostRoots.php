<?php

declare(strict_types=1);

namespace Fight\Runtime;

/**
 * Class HostRoots
 *
 * Qualifies the physical host directory behind an approval
 */
final class HostRoots
{
    public const PATH = '~^/[A-Za-z0-9_./ -]{1,1023}$~D';

    /**
     * Converts a native property list to a validated JSON object
     *
     * @phpstan-param list<string> $args
     * @return array<mixed>
     */
    public static function plist(array $args, string $cwd): array
    {
        $raw = Native::text($args, $cwd);
        $json = Native::run(
            ['/usr/bin/plutil', '-convert', 'json', '-o', '-', '--', '-'],
            $cwd,
            capture: true,
            input: $raw
        );
        $result = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        Native::require(is_array($result), 'Cannot qualify the native property list');

        return $result;
    }

    /**
     * Qualifies canonical local storage and returns its directory identity
     *
     * @phpstan-param list<string> $protected
     * @return array{int,int,int}
     */
    public static function qualify(string $path, string $access, array $protected): array
    {
        clearstatcache(true);
        $components = explode('/', substr($path, 1));
        Native::require(
            preg_match(self::PATH, $path) === 1 && count($components) > 1
                && array_intersect($components, ['', '.', '..']) === [],
            'Use an explicit absolute bounded root without traversal or ambiguous components'
        );
        Native::require(realpath($path) === $path, 'Symlink aliases and escaping paths are not accepted');
        foreach (self::lineage($path) as $parent) {
            Native::require(!is_link($parent), 'Symlink aliases and escaping paths are not accepted');
        }
        $account = posix_getpwuid(posix_getuid());
        Native::require(
            is_array($account) && str_starts_with($account['dir'], '/'),
            'Native account visibility unavailable'
        );
        $homes = [$account['dir'], ...self::homes($path)];
        foreach (array_unique($homes) as $home) {
            if (str_starts_with($home, '/') && !in_array($home, ['/', '/nonexistent', '/var/empty'], true)) {
                $home = Native::canonical($home);
                Native::require(
                    !Native::beneath($home, $path) && !self::same($path, $home),
                    'Complete home directories and their parents are not accepted'
                );
            }
        }
        Native::require(
            !(count($components) === 2 && in_array(strtolower($components[0]), ['home', 'users'], true)),
            'Complete home directories and their parents are not accepted'
        );
        foreach ($protected as $item) {
            Native::require(
                !Native::beneath($path, $item) && !Native::beneath($item, $path),
                'Root overlaps private installation state'
            );
            foreach (self::lineage($item) as $parent) {
                Native::require(!self::same($path, $parent), 'Root overlaps private installation state');
            }
            foreach (self::lineage($path) as $parent) {
                Native::require(!self::same($parent, $item), 'Root overlaps private installation state');
            }
        }
        Native::require(
            is_dir($path) && is_readable($path) && is_executable($path),
            'Root is not a readable, searchable directory'
        );
        Native::require($access !== 'rw' || is_writable($path), 'Root does not permit the requested write access');
        $handle = @fopen($path, 'rb');
        Native::require(is_resource($handle), 'Root or host filesystem is missing, inaccessible or cannot be qualified');
        try {
            $before = fstat($handle);
            Native::require(is_array($before) && ($before['mode'] & 0170000) === 0040000, 'Root is not a directory');
            if (PHP_OS_FAMILY === 'Darwin') {
                self::darwin($path);
                $stamp = Native::text(['/usr/bin/stat', '-f', '%FB', $path], $path);
                $birth = preg_match('/^[0-9]+\.[0-9]{9}$/D', $stamp) ? (int) ((float) $stamp * 1e9) : null;
            } elseif (PHP_OS_FAMILY === 'Linux') {
                self::linux($path);
                $stamp = Native::run(
                    ['/usr/bin/stat', '--format=%w', '--', $path],
                    $path,
                    [...getenv(), 'LC_ALL' => 'C', 'TZ' => 'UTC0'],
                    capture: true
                );
                $birth = preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\.\d{9} \+0000$/D', $stamp) === 1
                    ? (int) substr((string) preg_replace('/\D/', '', $stamp), 0, -4) : null;
            } else {
                throw new \RuntimeException('Host platform is not supported for approved roots');
            }
            clearstatcache(true);
            $after = @lstat($path);
            Native::require(
                $after !== false && $after['dev'] === $before['dev'] && $after['ino'] === $before['ino']
                    && realpath($path) === $path,
                'Root identity changed during qualification'
            );
            Native::require($birth !== null && $birth > 0, 'Cannot qualify a stable directory creation identity');

            return [$before['dev'], $before['ino'], $birth];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Lists the path and all its parents in ascending order
     *
     * @return list<string>
     */
    public static function lineage(string $path): array
    {
        $paths = [$path];
        while (($parent = dirname($path)) !== $path) {
            $paths[] = $path = $parent;
        }

        return $paths;
    }

    /**
     * Checks whether two existing paths identify the same filesystem object
     */
    private static function same(string $first, string $second): bool
    {
        $a = @stat($first);
        $b = @stat($second);

        return $a !== false && $b !== false && $a['dev'] === $b['dev'] && $a['ino'] === $b['ino'];
    }

    /**
     * Lists account homes using the native account service
     *
     * @return list<string>
     */
    private static function homes(string $cwd): array
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $rows = Native::lines(Native::text(
                ['/usr/bin/dscl', '/Search', '-list', '/Users', 'NFSHomeDirectory'],
                $cwd
            ));

            return array_map(static fn (string $row): string => preg_split('/\s+/', $row, 2)[1] ?? '', $rows);
        }
        $rows = Native::lines(Native::text(['getent', 'passwd'], $cwd));

        return array_map(static fn (string $row): string => explode(':', $row)[5] ?? '', $rows);
    }

    /**
     * Qualifies fixed APFS media and excludes child mounts
     */
    private static function darwin(string $path): void
    {
        $mounts = [];
        foreach (Native::lines(Native::text(['/sbin/mount'], $path)) as $line) {
            Native::require(
                preg_match('~^(.+?) on (/.*) \([^()]+\)$~D', $line, $parts) === 1,
                'Cannot qualify the host mount table'
            );
            $mounts[$parts[2]] = $parts[1];
        }
        $visible = [$path];
        if (self::same($path, '/System/Volumes/Data'.$path)) {
            $visible[] = '/System/Volumes/Data'.$path;
        }
        $point = '';
        $source = '';
        foreach ($mounts as $mount => $device) {
            foreach ($visible as $candidate) {
                Native::require(
                    $mount === $candidate || !Native::beneath($mount, $candidate),
                    'Root contains another filesystem mount'
                );
                if (Native::beneath($candidate, $mount) && strlen($mount) > strlen($point)) {
                    $point = $mount;
                    $source = $device;
                }
            }
        }
        Native::require(str_starts_with($source, '/dev/disk'), 'Root is not on supported fixed local storage');
        $info = self::plist(['/usr/sbin/diskutil', 'info', '-plist', $source], $path);
        Native::require(
            ($info['Internal'] ?? null) === true && ($info['RemovableMedia'] ?? null) === false
            && in_array($info['BusProtocol'] ?? null, ['Apple Fabric', 'PCI', 'PCI-Express', 'SATA', 'NVMe'], true)
            && ($info['FilesystemType'] ?? null) === 'apfs' && ($info['MountPoint'] ?? null) === $point,
            'Root is not on supported fixed local APFS storage'
        );
        // BSD ls reports the directory's native path; resolve physical cwd as well
        // so case-insensitive aliases do not gain a second spelling.
        Native::require(Native::text(['/bin/pwd', '-P'], $path) === $path, 'Use the canonical spelling of the directory');
    }

    /**
     * Qualifies a local ext4 or XFS filesystem on supported physical media
     */
    private static function linux(string $path): void
    {
        $matches = [];
        $devices = [];
        foreach (Native::lines(trim(Native::read('/proc/self/mountinfo'))) as $line) {
            [$before, $after] = array_pad(explode(' - ', $line, 2), 2, '');
            $fields = explode(' ', $before);
            Native::require(count($fields) >= 6 && count(explode(' ', $after)) >= 3, 'Cannot qualify the host mount table');
            $point = (string) preg_replace_callback(
                '/\\\\([0-7]{3})/',
                static fn (array $part): string => chr((int) octdec($part[1]) & 255),
                $fields[4]
            );
            Native::require($point === $path || !Native::beneath($point, $path), 'Root contains another filesystem mount');
            $device = $fields[2];
            $devices[$device] = ($devices[$device] ?? 0) + 1;
            if (Native::beneath($path, $point)) {
                $matches[] = [$point, $fields[3], $device, explode(' ', $after)[0]];
            }
        }
        usort($matches, static fn (array $a, array $b): int => strlen($b[0]) <=> strlen($a[0]));
        Native::require(
            $matches !== [] && (!isset($matches[1]) || $matches[0][0] !== $matches[1][0]),
            'Host filesystem mount is ambiguous'
        );
        [$point, $root, $device, $kind] = $matches[0];
        Native::require(
            $point !== $path && $devices[$device] === 1,
            'Whole filesystem mounts and filesystem aliases are not accepted'
        );
        $block = realpath('/sys/dev/block/'.$device);
        Native::require(is_string($block), 'Root is not on supported fixed local Linux storage');
        $disk = file_exists($block.'/partition') ? dirname($block) : $block;
        Native::require(
            in_array($kind, ['ext4', 'xfs'], true) && $root === '/'
                && preg_match('~/pci[0-9a-f]{4}:[0-9a-f]{2}/~', $disk) === 1
                && preg_match('~/(?:nvme|ata[0-9]+)/~', $disk) === 1 && !preg_match('~/usb[^/]*/~', $disk)
                && is_file($disk.'/removable') && trim(Native::read($disk.'/removable')) === '0',
            'Root is not on supported fixed local Linux storage'
        );
    }
}
