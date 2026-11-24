<?php

declare(strict_types=1);

namespace Fight\Harness\SessionFileBirth;

use FFI;
use RuntimeException;
use Throwable;

const AT_EMPTY_PATH = 0x1000;
const IDENTITY_MASK = 0x900; // STATX_INO | STATX_BTIME

/**
 * Read Linux creation time from one inherited open file without reading its contents
 */
function birth(int $fd): string
{
    if (PHP_OS_FAMILY !== 'Linux' || PHP_INT_SIZE !== 8) {
        throw new RuntimeException('Native creation time unsupported');
    }

    // Linux UAPI fixes statx to 256 bytes. The tail reserves all fields after stx_dev_minor.
    $libc = FFI::cdef(<<<'C'
        struct statx_timestamp {
            int64_t tv_sec;
            uint32_t tv_nsec;
            int32_t reserved;
        };
        struct statx {
            uint32_t stx_mask;
            uint32_t stx_blksize;
            uint64_t stx_attributes;
            uint32_t stx_nlink;
            uint32_t stx_uid;
            uint32_t stx_gid;
            uint16_t stx_mode;
            uint16_t spare;
            uint64_t stx_ino;
            uint64_t stx_size;
            uint64_t stx_blocks;
            uint64_t stx_attributes_mask;
            struct statx_timestamp stx_atime;
            struct statx_timestamp stx_btime;
            struct statx_timestamp stx_ctime;
            struct statx_timestamp stx_mtime;
            uint32_t stx_rdev_major;
            uint32_t stx_rdev_minor;
            uint32_t stx_dev_major;
            uint32_t stx_dev_minor;
            uint8_t tail[112];
        };
        int statx(int dirfd, const char *path, int flags, unsigned int mask, struct statx *value);
        C);
    $value = $libc->new('struct statx');
    if (FFI::sizeof($value) !== 256 || $libc->statx($fd, '', AT_EMPTY_PATH, IDENTITY_MASK, FFI::addr($value)) !== 0) {
        throw new RuntimeException('Native creation time unavailable');
    }

    // php://fd duplicates the inherited descriptor; fstat never reopens the session path.
    $file = @fopen('php://fd/' . $fd, 'rb');
    if ($file === false) {
        throw new RuntimeException('Native file descriptor unavailable');
    }
    try {
        $info = fstat($file);
    } finally {
        fclose($file);
    }

    $major = $value->stx_dev_major;
    $minor = $value->stx_dev_minor;
    // Linux makedev layout, preserving all bits even when PHP represents dev_t as signed.
    $device = (($major & 0xfffff000) << 32) | (($major & 0xfff) << 8)
        | (($minor & 0xffffff00) << 12) | ($minor & 0xff);
    if ($info === false || $value->stx_ino !== $info['ino'] || $device !== $info['dev']) {
        throw new RuntimeException('Native file identity changed');
    }

    $seconds = $value->stx_btime->tv_sec;
    $nanoseconds = $value->stx_btime->tv_nsec;
    if (($value->stx_mask & IDENTITY_MASK) !== IDENTITY_MASK || $seconds <= 0 || $nanoseconds >= 1000000000) {
        throw new RuntimeException('Native creation time unavailable or ambiguous');
    }
    // Concatenation keeps the exact decimal value even past PHP_INT_MAX nanoseconds.
    return $seconds . str_pad((string) $nanoseconds, 9, '0', STR_PAD_LEFT);
}

// Invoke after require returns so PHP has closed the script descriptor before inspecting fd 3.
return static function (): int {
    try {
        echo json_encode(['birthtimeNs' => birth(3)], JSON_THROW_ON_ERROR), "\n";
        return 0;
    } catch (Throwable) {
        return 1;
    }
};
