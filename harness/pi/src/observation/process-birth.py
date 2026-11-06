"""Read public macOS proc_pidinfo lineage/birth data; never controls a process."""
import ctypes
import json
import sys
import time


class RusageInfo(ctypes.Structure):
    # Public RUSAGE_INFO_V0, including the kernel's immutable mach start ticks
    _fields_ = [('uuid', ctypes.c_uint8 * 16)]
    _fields_ += [(name, ctypes.c_uint64) for name in (
        'user', 'system', 'idle', 'interrupts', 'pageins', 'wired', 'resident', 'footprint', 'start', 'exit')]


class Timebase(ctypes.Structure):
    _fields_ = [('numer', ctypes.c_uint32), ('denom', ctypes.c_uint32)]


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
    library.proc_pid_rusage.argtypes = [ctypes.c_int, ctypes.c_int, ctypes.c_void_p]
    library.proc_pid_rusage.restype = ctypes.c_int
    system = ctypes.CDLL('/usr/lib/libSystem.B.dylib')
    system.mach_absolute_time.restype = ctypes.c_uint64
    base = Timebase()
    if system.mach_timebase_info(ctypes.byref(base)) != 0 or not base.numer or not base.denom:
        raise ValueError('kernel clock unavailable')
    before = time.clock_gettime_ns(time.CLOCK_MONOTONIC_RAW)
    absolute = system.mach_absolute_time() * base.numer // base.denom
    after = time.clock_gettime_ns(time.CLOCK_MONOTONIC_RAW)
    if not before <= absolute <= after:
        raise ValueError('kernel/raw clock domain mismatch')
    result = []
    for _ in range(32):
        value = BsdInfo()
        if library.proc_pidinfo(pid, 3, 0, ctypes.byref(value), ctypes.sizeof(value)) != ctypes.sizeof(value) or value.pid != pid or value.microseconds >= 1000000:
            raise ValueError('process birth unavailable')
        usage, current = RusageInfo(), BsdInfo()
        if library.proc_pid_rusage(pid, 0, ctypes.byref(usage)) != 0 or not usage.start:
            raise ValueError('monotonic process birth unavailable')
        # The two public kernel queries must describe the same still-live process
        if library.proc_pidinfo(pid, 3, 0, ctypes.byref(current), ctypes.sizeof(current)) != ctypes.sizeof(current) or any(
                getattr(current, name) != getattr(value, name) for name in ('pid', 'ppid', 'seconds', 'microseconds')):
            raise ValueError('process lifetime changed')
        result.append(dict(pid=pid, parent=value.ppid, bornUs=value.seconds * 1000000 + value.microseconds,
                           bornMonotonicMs=usage.start * base.numer // base.denom // 1000000))
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
