"""Read Linux statx creation time from one inherited open file; never reads session contents."""
import ctypes
import json
import os
import sys


class Timestamp(ctypes.Structure):
    _fields_ = [('seconds', ctypes.c_int64), ('nanoseconds', ctypes.c_uint32), ('reserved', ctypes.c_int32)]


class Statx(ctypes.Structure):
    # Linux's fixed 256-byte statx ABI, including space for newer trailing fields
    _fields_ = [('mask', ctypes.c_uint32), ('blksize', ctypes.c_uint32), ('attributes', ctypes.c_uint64),
                ('nlink', ctypes.c_uint32), ('uid', ctypes.c_uint32), ('gid', ctypes.c_uint32),
                ('mode', ctypes.c_uint16), ('spare', ctypes.c_uint16), ('ino', ctypes.c_uint64),
                ('size', ctypes.c_uint64), ('blocks', ctypes.c_uint64), ('attributes_mask', ctypes.c_uint64),
                ('atime', Timestamp), ('btime', Timestamp), ('ctime', Timestamp), ('mtime', Timestamp),
                ('rdev_major', ctypes.c_uint32), ('rdev_minor', ctypes.c_uint32),
                ('dev_major', ctypes.c_uint32), ('dev_minor', ctypes.c_uint32), ('tail', ctypes.c_ubyte * 112)]


def birth(fd):
    if sys.platform != 'linux' or ctypes.sizeof(Statx) != 256:
        raise ValueError('native creation time unsupported')
    library = ctypes.CDLL(None, use_errno=True)
    library.statx.argtypes = [ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_uint, ctypes.POINTER(Statx)]
    library.statx.restype = ctypes.c_int
    value = Statx()
    # AT_EMPTY_PATH on the inherited descriptor; require INO and BTIME in the returned mask
    if library.statx(fd, b'', 0x1000, 0x900, ctypes.byref(value)) != 0 or value.mask & 0x900 != 0x900:
        raise ValueError('native creation time unavailable')
    if value.btime.seconds <= 0 or not 0 <= value.btime.nanoseconds < 1000000000:
        raise ValueError('native creation time ambiguous')
    info = os.fstat(fd)
    if value.ino != info.st_ino or os.makedev(value.dev_major, value.dev_minor) != info.st_dev:
        raise ValueError('native file identity changed')
    return {'birthtimeNs': str(value.btime.seconds * 1000000000 + value.btime.nanoseconds)}


if __name__ == '__main__':
    try:
        print(json.dumps(birth(3)))
    except Exception:
        sys.exit(1)
