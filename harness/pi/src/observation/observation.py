"""Optional local, lossy safe-progress broadcast; never an execution control channel."""

import json
import os
from pathlib import Path
import socket
import stat


class Observation:
    """Keep slow or absent readers independent of stage execution"""

    def __init__(self, path, identity, heartbeat):
        self.path = Path(path)
        if not self.path.is_absolute():
            raise ValueError("observation socket must be absolute")
        # The operator prepares its private runtime directory; never replace another endpoint.
        parent = self.path.parent.stat()
        if not stat.S_ISDIR(parent.st_mode) or parent.st_uid != os.getuid() or parent.st_mode & 0o077:
            raise ValueError("observation socket parent must be owner-only")
        self.server = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        try:
            self.server.bind(str(self.path))
            os.chmod(self.path, 0o600)
            self.inode = self.path.stat().st_ino
            self.server.listen(4)
            self.server.setblocking(False)
        except Exception:
            self.server.close()
            raise
        self.clients = {}
        self.snapshot = dict(version="fight-child-observation-v1", identity=identity,
                             sequence=0, lifecycle="starting", exitCode=None,
                             heartbeatSeconds=heartbeat, text="Waiting for Pi", kind="snapshot")

    def publish(self, text, lifecycle, code=None):
        self.snapshot.update(sequence=self.snapshot["sequence"] + 1, text=text,
                             lifecycle=lifecycle, exitCode=code, kind="progress")
        record = self.encode(self.snapshot)
        for client in list(self.clients):
            pending = self.clients[client] + record
            if len(pending) > 65536:
                self.drop(client)
            else:
                self.clients[client] = pending
        self.poll()

    @staticmethod
    def encode(record):
        # UTF-8 avoids surrogate-escape expansion exceeding the pane's framing cap
        # when multiple bounded identity fields contain supplementary Unicode.
        return (json.dumps(record, ensure_ascii=False) + "\n").encode("utf-8")

    def drop(self, client):
        self.clients.pop(client, None)
        try:
            client.close()
        except OSError:
            pass

    def poll(self):
        # Bound accept/send work per monitor tick; no socket call may wait on a reader.
        for _ in range(4):
            try:
                client, _ = self.server.accept()
            except OSError:
                # Reader/socket resource failures must not control the observed stage.
                break
            try:
                client.setblocking(False)
            except OSError:
                client.close()
                continue
            if len(self.clients) >= 4:
                client.close()
            else:
                self.clients[client] = self.encode(dict(self.snapshot, kind="snapshot"))
        for client in list(self.clients):
            try:
                # No commands are admitted. Detect closed readers without retaining input.
                data = client.recv(1)
                if data == b"" or data:
                    self.drop(client)
                    continue
            except BlockingIOError:
                pass
            except OSError:
                self.drop(client)
                continue
            pending = self.clients[client]
            if not pending:
                continue
            try:
                sent = client.send(pending)
                self.clients[client] = pending[sent:]
            except BlockingIOError:
                pass
            except OSError:
                self.drop(client)

    def close(self):
        self.poll()
        for client in list(self.clients):
            self.drop(client)
        self.server.close()
        # Do not unlink a replacement endpoint belonging to another process.
        try:
            if self.path.lstat().st_ino == self.inode:
                self.path.unlink()
        except FileNotFoundError:
            pass
