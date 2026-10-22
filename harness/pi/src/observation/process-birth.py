"""Read public macOS proc_pidinfo lineage/birth data; never controls a process."""
import ctypes
import json
import sys


class BsdInfo(ctypes.Structure):
    _fields_ = [(name, ctypes.c_uint32) for name in (
        'flags', 'status', 'xstatus', 'pid', 'ppid', 'uid', 'gid', 'ruid', 'rgid', 'svuid', 'svgid', 'reserved')]
    _fields_ += [('comm', ctypes.c_char * 16), ('name', ctypes.c_char * 32)]
    _fields_ += [(name, ctypes.c_uint32) for name in ('nfiles', 'pgid', 'pjobc', 'tdev', 'tpgid')]
    _fields_ += [('nice', ctypes.c_int32), ('seconds', ctypes.c_uint64), ('microseconds', ctypes.c_uint64)]


def lineage(pid, native):
    library = ctypes.CDLL('/usr/lib/libproc.dylib', use_errno=True)
    library.proc_pidinfo.argtypes = [ctypes.c_int, ctypes.c_int, ctypes.c_uint64, ctypes.c_void_p, ctypes.c_int]
    library.proc_pidinfo.restype = ctypes.c_int
    result = []
    for _ in range(32):
        value = BsdInfo()
        if library.proc_pidinfo(pid, 3, 0, ctypes.byref(value), ctypes.sizeof(value)) != ctypes.sizeof(value) or value.pid != pid or value.microseconds >= 1000000:
            raise ValueError('process birth unavailable')
        result.append(dict(pid=pid, parent=value.ppid, bornUs=value.seconds * 1000000 + value.microseconds))
        if pid == native:
            if len(result) < 2:
                raise ValueError('monitor is native process')
            return result
        if value.ppid <= 1:
            break
        pid = value.ppid
    raise ValueError('native lineage unavailable')


if __name__ == '__main__':
    try:
        monitor, native = map(int, sys.argv[1:])
        if monitor < 1 or native < 1:
            raise ValueError()
        print(json.dumps(lineage(monitor, native)))
    except Exception:
        sys.exit(1)
