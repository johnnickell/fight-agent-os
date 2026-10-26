"""Best-effort passive native association; never owns stage execution or cancellation."""

import json
import os
from pathlib import Path
import secrets
import socket
import stat
import subprocess
import sys
import threading
import time


def process_identity(pid, timeout=1):
    return subprocess.check_output(["ps", "-p", str(pid), "-o", "lstart=", "-o", "command="],
                                   stderr=subprocess.DEVNULL, timeout=timeout).decode().strip()


def private(path, directory=False):
    info = path.lstat()
    valid = stat.S_ISDIR(info.st_mode) if directory else stat.S_ISREG(info.st_mode)
    if not valid or info.st_uid != os.getuid() or info.st_mode & 0o077 or path.resolve() != path or (not directory and info.st_size > 8192):
        raise ValueError("invalid observation runtime candidate")
    return info


class AutomaticObservation:
    """Discover exact native ancestry in the background and export only safe progress"""

    def __init__(self, identity, heartbeat, started=None):
        self.identity, self.heartbeat = identity, heartbeat
        # Qualify a shared raw clock; older Python monotonic() can have a process-local origin.
        self.started = int(time.clock_gettime(time.CLOCK_MONOTONIC_RAW) * 1000) if started is None else started
        self.channel = None
        self.pending = b""
        self.sequence = 0
        self.stopped = threading.Event()
        self.lock = threading.Lock()
        self.witness = None
        self.status = "no-current-native-route"
        threading.Thread(target=self.discover, daemon=True, name="passive-observation").start()

    def discover(self):
        # All optional subprocess/network discovery is off the stage/primary-input path.
        # No command, shell policy, environment or observed process is modified.
        channel = None
        deadline = time.monotonic() + 3

        def remaining(maximum=1):
            elapsed = deadline - time.monotonic()
            if self.stopped.is_set() or elapsed <= 0:
                raise TimeoutError("observation deadline/cancellation")
            return min(maximum, elapsed)

        try:
            rows = subprocess.check_output(["ps", "-axo", "pid=,ppid="], timeout=remaining()).decode().splitlines()
            parents = dict(tuple(map(int, row.split())) for row in rows if row.strip())
            ancestors, pid = [], os.getppid()
            for _ in range(32):
                if pid <= 1:
                    break
                ancestors.append(pid)
                pid = parents.get(pid, 1)
            worktree = Path.cwd().resolve()
            raw = subprocess.check_output(["git", "worktree", "list", "--porcelain", "-z"], timeout=remaining())
            roots = [Path(record[9:].decode()) for record in raw.split(b"\0") if record.startswith(b"worktree ")]
            if len(roots) > 32:
                return
            # A route can be starting concurrently with the unchanged shell/monitor launch.
            while not self.stopped.is_set() and time.monotonic() < deadline:
                routes = []
                for root in roots:
                    remaining()
                    directory = root / "var/terminal/native"
                    try:
                        private(directory, True)
                        with os.scandir(directory) as scan:
                            entries = [Path(entry.path) for _, entry in zip(range(9), scan)]
                        if len(entries) > 8:
                            return
                        for path in entries:
                            remaining()
                            private(path)
                            route = json.loads(path.read_text())
                            native = route["native"]
                            if native["pid"] not in ancestors or self.started < native["started"] or path.name != f"{native['pid']}-{route['generation']}.json":
                                continue
                            if process_identity(native["pid"], remaining()) != native["process"] or route["primary"] != str(root.resolve()):
                                continue
                            expected = str(Path(__file__).resolve().with_name("custodian.mjs"))
                            if process_identity(route["pid"], remaining()) != route["process"] or not route["process"].endswith(f" {expected} {route['generation']}"):
                                self.status = "native-package-or-lifetime-mismatch"
                                continue
                            routes.append((ancestors.index(native["pid"]), route))
                    except (OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError):
                        continue
                if routes:
                    routes.sort(key=lambda item: item[0])
                    if len(routes) > 1 and routes[0][0] == routes[1][0]:
                        self.status = "ambiguous-native-route"
                        return  # Ambiguous current native context: never guess.
                    route = routes[0][1]
                    break
                self.stopped.wait(0.1)
            else:
                return
            self.status = "monitor-admission-unavailable"
            token = secrets.token_hex(32)
            runtime = worktree / "var/terminal/monitors"
            runtime.mkdir(parents=True, mode=0o700, exist_ok=True)
            private(runtime, True)
            witness = runtime / f"{os.getpid()}-{token}.json"
            value = dict(pid=os.getpid(), process=process_identity(os.getpid(), remaining()), token=token,
                         nativePid=route["native"]["pid"], nativeProcess=route["native"]["process"],
                         worktree=str(worktree), identity=self.identity, heartbeat=self.heartbeat,
                         created=int(time.time() * 1000), started=self.started)
            with witness.open("x") as file:
                os.chmod(witness, 0o600)
                json.dump(value, file)
            self.witness = (witness, witness.stat().st_ino)
            endpoint = Path(route["primary"]) / "var/terminal/s" / (route["generation"].replace("-", "")[:24] + ".sock")
            info = endpoint.lstat()
            if not stat.S_ISSOCK(info.st_mode) or info.st_uid != os.getuid() or info.st_mode & 0o077 or info.st_dev != route["endpoint"]["dev"] or info.st_ino != route["endpoint"]["ino"]:
                return
            channel = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
            channel.settimeout(remaining(0.5))
            local = os.path.relpath(endpoint)
            channel.connect(min((str(endpoint), local), key=lambda path: len(os.fsencode(path))))
            channel.sendall((json.dumps(dict(type="monitor", pid=os.getpid(), token=token, worktree=str(worktree))) + "\n").encode())
            # Absolute admission deadline, not an activity-reset timeout.
            expires, reply = min(time.monotonic() + 1, deadline), b""
            while b"\n" not in reply and len(reply) < 128:
                channel.settimeout(max(0.001, expires - time.monotonic()))
                chunk = channel.recv(128 - len(reply))
                if not chunk or time.monotonic() >= expires:
                    return
                reply += chunk
            if json.loads(reply) != dict(observed=True, pid=os.getpid()):
                return
            channel.setblocking(False)
            with self.lock:
                if self.stopped.is_set():
                    return
                self.channel, channel = channel, None
                self.status = "connected"
        except Exception:
            pass  # Failure is strictly observation unavailability, never a stage result.
        finally:
            if channel is not None:
                channel.close()
            if self.channel is None:
                self.remove_witness()
                if not self.stopped.is_set():
                    try:
                        print(f"Child observation unavailable ({self.status}); stage execution unchanged", file=sys.stderr, flush=True)
                    except OSError:
                        pass

    def publish(self, text, lifecycle, code=None):
        try:
            self.sequence += 1
            record = (json.dumps(dict(sequence=self.sequence, text=text, lifecycle=lifecycle, exitCode=code), ensure_ascii=False) + "\n").encode()
            if len(self.pending) + len(record) <= 65536:
                self.pending += record
            self.poll()
        except Exception:
            pass

    def poll(self):
        if not self.lock.acquire(blocking=False):
            return
        try:
            if self.channel is not None and self.pending:
                sent = self.channel.send(self.pending)
                self.pending = self.pending[sent:]
        except BlockingIOError:
            pass
        except OSError:
            self.channel.close()
            self.channel = None
            self.pending = b""
        finally:
            self.lock.release()

    def remove_witness(self):
        try:
            if self.witness and self.witness[0].lstat().st_ino == self.witness[1]:
                self.witness[0].unlink()
        except OSError:
            pass

    def close(self):
        self.stopped.set()
        self.poll()  # Nonblocking; dropped terminal progress remains unknown, never invented.
        if self.lock.acquire(blocking=False):
            try:
                if self.channel is not None:
                    self.channel.close()
                    self.channel = None
            finally:
                self.lock.release()
        self.remove_witness()
