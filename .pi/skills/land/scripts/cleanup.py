#!/usr/bin/env python3
"""Qualify and retire one TASK's enumerated local resources (Python 3.9+)."""

import argparse
import ctypes
from datetime import datetime, timedelta, timezone
import fcntl
import hashlib
import http.client
import json
import os
from pathlib import Path
import re
import shutil
import socket
import stat
import subprocess
import sys
import tempfile
import time
from urllib.parse import quote
from urllib.request import Request, urlopen
from urllib.error import HTTPError


COMMANDS = None


class Hold(Exception):
    """Retain an affected resource without changing its protections."""


def require(condition, reason):
    if not condition:
        raise Hold(reason)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def fingerprint(value):
    return digest(json.dumps(value, sort_keys=True, separators=(",", ":")).encode())


def command(args, cwd, codes=(0,), timeout=15, confirmation=None):
    """Capture privately; never replay provider, configuration or environment output."""
    try:
        result = subprocess.run(args, cwd=cwd, input=confirmation,
                                stdin=subprocess.DEVNULL if confirmation is None else None,
                                capture_output=True, timeout=timeout,
                                env=dict(os.environ, GIT_NO_REPLACE_OBJECTS="1", GIT_NO_LAZY_FETCH="1",
                                         COMPOSE_DISABLE_ENV_FILE="1", COMPOSE_ENV_FILES=""))
    except (OSError, subprocess.SubprocessError):
        raise Hold(f"{args[0]} inspection/effect unavailable; inspect tooling and permissions") from None
    if COMMANDS is not None:
        COMMANDS.append({"argv": [str(value) for value in args], "exit": result.returncode,
                         "stdout_sha256": digest(result.stdout), "stderr_sha256": digest(result.stderr)})
    if result.returncode not in codes and COMMANDS is not None and args[:3] == ["docker", "image", "rm"]:
        # Exact Docker retirement errors contain resource references, not configuration/env values.
        COMMANDS[-1]["retirement_error"] = result.stderr.decode("utf-8", errors="replace")[:4096]
    require(result.returncode in codes,
            f"{args[0]} {args[1]} exit {result.returncode}; retain resource; no protection-changing recovery")
    require(len(result.stdout) <= 64 * 1024 * 1024, "inspection exceeds bounded capture")
    return result


def text(args, cwd, **kwargs):
    return command(args, cwd, **kwargs).stdout.decode("utf-8").strip()


def json_command(args, cwd):
    return json.loads(text(args, cwd))


def docker_timestamp(value):
    """Normalize raw RFC3339 nanoseconds conservatively on Python 3.9"""
    require(isinstance(value, str) and re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})", value),
            "raw Docker timestamp unavailable/malformed")
    value = value.replace("Z", "+00:00")
    fractional = re.search(r"\.(\d+)", value)
    rounded = False
    if fractional:
        digits = fractional[1]
        rounded = len(digits) > 6 and any(char != "0" for char in digits[6:])
        value = value[:fractional.start()] + "." + digits[:6].ljust(6, "0") + value[fractional.end():]
    return datetime.fromisoformat(value) + timedelta(microseconds=1 if rounded else 0)


def inside(path, root):
    return path == root or root in path.parents


def absolute(value):
    path = Path(value)
    require(path.is_absolute() and path == path.resolve(), "path must be absolute, canonical and not a symlink")
    return path


def worktrees(safe):
    raw = text(["git", "worktree", "list", "--porcelain"], safe)
    return [dict(line.split(" ", 1) if " " in line else (line, "")
                 for line in block.splitlines()) for block in raw.split("\n\n") if block]


def container_identity(item):
    """Hash immutable identity/configuration; never persist environment values."""
    return fingerprint({key: item.get(key) for key in ("Id", "Created", "Image", "Name", "Config", "HostConfig", "Mounts")})


def network_identity(item):
    """Hash network configuration, excluding mutable endpoints and runtime counters."""
    return fingerprint({key: item.get(key) for key in
                        ("Id", "Name", "Created", "Scope", "Driver", "EnableIPv4", "EnableIPv6", "IPAM",
                         "Internal", "Attachable", "Ingress", "ConfigFrom", "ConfigOnly", "Options", "Labels")})


def volume_identity(item):
    """Bind Docker's available volume identity fields; never use its name alone."""
    return fingerprint({key: item.get(key) for key in
                        ("Name", "Driver", "Mountpoint", "CreatedAt", "Labels", "Options", "Scope")})


def volume_inventory(safe):
    names = text(["docker", "volume", "ls", "--format", "{{.Name}}"], safe).splitlines()
    return json_command(["docker", "volume", "inspect", *names], safe) if names else []


def docker_inventory(safe):
    ids = text(["docker", "ps", "-a", "--no-trunc", "--format", "{{.ID}}"], safe).splitlines()
    return json_command(["docker", "inspect", *ids], safe) if ids else []


def inspect_image(safe, identity):
    """Resolve an exact Docker image ID; denial/aliases are never absence."""
    require(re.fullmatch(r"sha256:[a-f0-9]{64}", identity), "full exact image ID required; references/config aliases held")
    observed = command(["docker", "image", "inspect", identity], safe, codes=(0, 1))
    if observed.returncode:
        require(observed.stdout.strip() == b"[]" and
                observed.stderr.decode().strip() == "Error response from daemon: No such image: " + identity,
                "exact image inspection unavailable/denied/ambiguous; retain unchanged")
        inventory = text(["docker", "image", "ls", "--all", "--no-trunc", "--quiet"], safe).splitlines()
        require(all(re.fullmatch(r"sha256:[a-f0-9]{64}", value) for value in inventory) and identity not in inventory,
                "exact inspection/all-image inventory contradict or are ambiguous; image absence held")
        return None
    values = json.loads(observed.stdout)
    require(len(values) == 1 and values[0]["Id"] == identity,
            "image reference/config/manifest ID differs; exact identity unresolved")
    return values[0]


def image_representation(current):
    """Distinguish daemon image IDs from manifest and multi-platform index IDs."""
    descriptor = current.get("Descriptor")
    if descriptor is None:
        return "legacy-image-id"
    require(descriptor.get("digest") == current["Id"], "image descriptor/config identity ambiguous")
    media_type = descriptor.get("mediaType")
    require(media_type in ("application/vnd.oci.image.manifest.v1+json",
                          "application/vnd.docker.distribution.manifest.v2+json"),
            "manifest-index or unknown image representation needs owning-platform qualification; retained")
    return "manifest"


def require_no_image_consumers(safe, current):
    representation = image_representation(current)
    for container in docker_inventory(safe):
        config_id = container["Image"]
        require(re.fullmatch(r"sha256:[a-f0-9]{64}", config_id), "container image identity unavailable")
        require(config_id != current["Id"], "image has current/stopped container consumer")
        descriptor = container.get("ImageManifestDescriptor")
        if representation == "manifest":
            require(isinstance(descriptor, dict) and
                    descriptor.get("mediaType") in ("application/vnd.oci.image.manifest.v1+json",
                                                    "application/vnd.docker.distribution.manifest.v2+json") and
                    re.fullmatch(r"sha256:[a-f0-9]{64}", descriptor.get("digest", "")),
                    "container config/manifest relationship unavailable; image consumer inspection held")
            require(descriptor["digest"] != current["Id"], "image has current/stopped manifest consumer")
        else:
            require(descriptor is None, "mixed config/manifest consumer representation unresolved")


def local_image_metadata(safe, routes):
    """Read bounded metadata from the positively associated local CLI engine."""
    def selected_endpoint():
        context = json_command(["docker", "context", "inspect"], safe)
        require(len(context) == 1, "local image graph context ambiguous")
        host = os.environ.get("DOCKER_HOST") if not os.environ.get("DOCKER_CONTEXT") else None
        host = host or context[0]["Endpoints"]["docker"]["Host"]
        require(isinstance(host, str) and host.startswith("unix:///") and
                not any(ord(char) < 33 or ord(char) == 127 for char in host),
                "image graph capability requires an explicit local Unix engine")
        return host

    endpoint = selected_endpoint()
    supplied = Path(endpoint.removeprefix("unix://"))

    def binding():
        # Only trusted CLI socket spellings may resolve aliases (e.g. Linux /var/run).
        # Provenance/report/source paths still use the unchanged strict absolute().
        try:
            path = supplied.resolve(strict=True)
            metadata = path.lstat()
            require(stat.S_ISSOCK(metadata.st_mode), "local image graph endpoint is not a socket")
            links = []
            for part in (*reversed(supplied.parents), supplied):
                observed = part.lstat()
                if stat.S_ISLNK(observed.st_mode):
                    links.append((str(part), observed.st_dev, observed.st_ino, observed.st_ctime_ns,
                                  os.readlink(part)))
            return path, (metadata.st_dev, metadata.st_ino, metadata.st_mode,
                          metadata.st_uid, metadata.st_gid, metadata.st_ctime_ns), links
        except (OSError, RuntimeError, ValueError):
            raise Hold("local image graph socket binding unavailable/changed; retain unchanged") from None

    initial = binding()
    path = initial[0]

    def stable():
        require(selected_endpoint() == endpoint and binding() == initial,
                "local CLI endpoint/socket identity changed during observation")

    def get(route):
        stable()
        connection = http.client.HTTPConnection("localhost", timeout=15)
        try:
            connection.sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
            connection.sock.settimeout(15)
            connection.sock.connect(str(path))
            stable()
            connection.request("GET", route)
            response = connection.getresponse()
            raw = response.read(32 * 1024 * 1024 + 1)
            require(response.status == 200 and len(raw) <= 32 * 1024 * 1024,
                    "complete local image graph unavailable/denied/oversized")
            if COMMANDS is not None:
                COMMANDS.append({"observation": "local-engine-get", "endpoint": endpoint,
                                 "route": route, "status": response.status, "sha256": digest(raw)})
            return json.loads(raw)
        except (OSError, http.client.HTTPException, ValueError):
            raise Hold("local image graph observation unavailable/denied/malformed; retain unchanged") from None
        finally:
            connection.close()
    engine = json_command(["docker", "info", "--format", "{{json .}}"], safe)
    require(engine["ID"] and get("/info")["ID"] == engine["ID"], "CLI/API engine association changed")
    values = [get(route) for route in routes]
    require(get("/info")["ID"] == engine["ID"] and
            json_command(["docker", "info", "--format", "{{json .}}"], safe)["ID"] == engine["ID"],
            "CLI/API engine association changed during observation")
    stable()
    return values


def local_image_graphs(safe):
    """Read complete root/manifest metadata from the SAME local CLI engine, without saving layers."""
    rows, = local_image_metadata(safe, ["/images/json?all=1&manifests=1"])
    require(isinstance(rows, list) and len(rows) <= 4096 and
            len({row["Id"] for row in rows}) == len(rows), "complete root inventory ambiguous")
    return rows


def image_root_graph(safe, current, rows=None):
    """Bind each available image and attestation child; unknown/incomplete graphs hold."""
    rows = local_image_graphs(safe) if rows is None else rows
    matches = [row for row in rows if row["Id"] == current["Id"]]
    require(len(matches) == 1, "exact image root graph missing")
    root = matches[0]
    require(root.get("Descriptor") == current.get("Descriptor") and
            root["Descriptor"]["digest"] == current["Id"] and
            root["Descriptor"]["mediaType"] in ("application/vnd.oci.image.index.v1+json",
                "application/vnd.docker.distribution.manifest.list.v2+json",
                "application/vnd.oci.image.manifest.v1+json",
                "application/vnd.docker.distribution.manifest.v2+json"), "root descriptor unresolved")
    children = root.get("Manifests")
    require(isinstance(children, list) and 1 <= len(children) <= 32 and
            len({child["ID"] for child in children}) == len(children), "complete immutable child graph unavailable")
    images, attestations = [], []
    for child in children:
        descriptor = child["Descriptor"]
        require(child["Available"] is True and descriptor["digest"] == child["ID"] and
                re.fullmatch(r"sha256:[a-f0-9]{64}", child["ID"]) and
                descriptor["mediaType"] in ("application/vnd.oci.image.manifest.v1+json",
                                            "application/vnd.docker.distribution.manifest.v2+json"),
                "missing/unknown child descriptor; root retirement held")
        if child["Kind"] == "image":
            platform = child["ImageData"]["Platform"]
            direct_manifest = (descriptor.get("platform") is None and len(children) == 1 and
                child["ID"] == current["Id"] and descriptor == current["Descriptor"] and
                current["Descriptor"]["mediaType"] in ("application/vnd.oci.image.manifest.v1+json",
                                                       "application/vnd.docker.distribution.manifest.v2+json"))
            require((platform == descriptor.get("platform") or direct_manifest) and platform["os"] != "unknown" and
                    platform["architecture"] != "unknown", "image child platform ambiguous")
            selector = "/".join(platform[key] for key in ("os", "architecture", "variant") if platform.get(key))
            observed = json_command(["docker", "image", "inspect", "--platform", selector, current["Id"]], safe)
            require(len(observed) == 1 and observed[0]["Id"] == child["ID"] and
                    observed[0]["Descriptor"] == descriptor and observed[0]["Os"] == platform["os"] and
                    observed[0]["Architecture"] == platform["architecture"], "immutable root/platform mapping changed")
            entry = {"descriptor": descriptor, "config_sha256": fingerprint(observed[0]["Config"]),
                     "rootfs_sha256": fingerprint(observed[0]["RootFS"])}
            if direct_manifest:
                require(observed[0].get("Variant", "") == platform.get("variant", ""), "direct manifest variant contradicts exact platform inspection")
                entry["platform"] = platform  # Corroborated separately; never synthesize a descriptor field.
            images.append(entry)
        else:
            require(child["Kind"] == "attestation" and child["AttestationData"]["For"] in
                    {value["ID"] for value in children if value["Kind"] == "image"} and
                    descriptor.get("annotations", {}).get("vnd.docker.reference.digest") == child["AttestationData"]["For"],
                    "unknown attestation/child relationship")
            attestations.append({"descriptor": descriptor, "for": child["AttestationData"]["For"]})
    require(images, "root has no qualified image platform")
    return {"root": root["Descriptor"], "images": sorted(images, key=lambda value: value["descriptor"]["digest"]),
            "attestations": sorted(attestations, key=lambda value: value["descriptor"]["digest"])}


def open_processes(raw):
    """Parse lsof's typed fields without inheriting a prior process descriptor."""
    processes, process, descriptor = {}, None, None
    for line in raw.decode().splitlines():
        if line.startswith("p"):
            process = {"pid": int(line[1:]), "files": []}
            processes[process["pid"]] = process
            descriptor = None
        elif line.startswith("u") and process is not None:
            process["uid"] = line[1:]
        elif line.startswith("f") and process is not None:
            descriptor = {"fd": line[1:]}
            process["files"].append(descriptor)
        elif line[:1] in ("a", "n", "t") and descriptor is not None:
            descriptor[line[0]] = line[1:]
    return processes


def ipc_xattrs(path):
    """Read complete Darwin IPC metadata through bounded native no-follow APIs"""
    library = ctypes.CDLL("/usr/lib/libSystem.B.dylib", use_errno=True)
    listing, reading = library.listxattr, library.getxattr
    listing.argtypes = [ctypes.c_char_p, ctypes.c_void_p, ctypes.c_size_t, ctypes.c_int]
    listing.restype = ctypes.c_ssize_t
    reading.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_void_p, ctypes.c_size_t, ctypes.c_uint32, ctypes.c_int]
    reading.restype = ctypes.c_ssize_t
    encoded = os.fsencode(path)
    size = listing(encoded, None, 0, 1)  # XATTR_NOFOLLOW
    require(0 <= size <= 1024 * 1024, "native IPC attribute listing unavailable/oversized")
    names = ctypes.create_string_buffer(size)
    require(listing(encoded, names, size, 1) == size, "native IPC attribute names changed/unavailable")
    attributes = {}
    for name in names.raw.split(b"\0"):
        if not name:
            continue
        count = reading(encoded, name, None, 0, 0, 1)
        require(0 <= count <= 1024 * 1024, "native IPC attribute unavailable/oversized")
        value = ctypes.create_string_buffer(count)
        require(reading(encoded, name, value, count, 0, 1) == count, "native IPC attribute changed/unavailable")
        attributes[os.fsdecode(name)] = digest(value.raw)
    return attributes


def file_record(path, allow_symlink=False, metadata=None, allow_ipc=False):
    before = path.lstat()
    link = stat.S_ISLNK(before.st_mode)
    ipc = stat.S_ISSOCK(before.st_mode) or stat.S_ISFIFO(before.st_mode)
    require(stat.S_ISREG(before.st_mode) or stat.S_ISDIR(before.st_mode) or (allow_symlink and link) or (allow_ipc and ipc),
            "special file or symlink in preservation inventory; owner must resolve")
    require(before.st_uid == os.getuid(), "artifact ownership unresolved")
    require(not getattr(before, "st_flags", 0), "protected artifact flags; retain unchanged")
    attrs = {}
    if metadata is None or metadata[0]:
        if ipc and sys.platform == "darwin":
            attrs = ipc_xattrs(path)
        elif hasattr(os, "listxattr"):
            attrs = {name: digest(os.getxattr(path, name, follow_symlinks=not link))
                     for name in os.listxattr(path, follow_symlinks=not link)}
        else:
            flags = ["-s"] if link else []
            names = command(["xattr", *flags, str(path)], path.parent).stdout.decode().splitlines()
            attrs = {name: digest(command(["xattr", *flags, "-p", name, str(path)], path.parent).stdout) for name in names}
    acl = []
    if sys.platform == "darwin" and (metadata is None or metadata[1]):
        listing = command(["ls", "-ldeP", str(path)], path.parent).stdout.decode().splitlines()
        acl = [line.strip() for line in listing[1:]]
    result = {"kind": "socket" if stat.S_ISSOCK(before.st_mode) else "fifo" if stat.S_ISFIFO(before.st_mode) else "symlink" if link else "directory" if stat.S_ISDIR(before.st_mode) else "file",
              "mode": stat.S_IMODE(before.st_mode), "uid": before.st_uid, "gid": before.st_gid,
              "xattrs": attrs, "flags": getattr(before, "st_flags", 0), "acl": acl}
    if ipc:
        result["identity"] = {"device": before.st_dev, "inode": before.st_ino, "ctime_ns": before.st_ctime_ns}
    if link:
        result["target"] = os.readlink(path)
    elif stat.S_ISREG(before.st_mode):
        result["sha256"] = digest(path.read_bytes())
    after = path.lstat()
    require((before.st_ino, before.st_size, before.st_mtime_ns, before.st_ctime_ns) ==
            (after.st_ino, after.st_size, after.st_mtime_ns, after.st_ctime_ns), "artifact changed while inspected")
    return result


def preserved_record(source, destination):
    """Require source metadata; allow only added macOS copy-origin provenance."""
    actual = file_record(destination)
    expected = dict(source)
    expected_attrs = dict(source["xattrs"])
    if sys.platform == "darwin" and "com.apple.provenance" not in expected_attrs:
        # macOS may attach this opaque origin attribute on creation, even through
        # ditto. Never remove it or relax ACLs/modes/flags to obtain equality.
        extra = actual["xattrs"].get("com.apple.provenance")
        if extra is not None:
            expected_attrs["com.apple.provenance"] = extra
    expected["xattrs"] = expected_attrs
    require(actual == expected, "copy digest/permissions/xattrs/ACL mismatch")
    return actual


def exclusion_paths(entries):
    paths = []
    for entry in entries:
        path = Path(entry["path"])
        require(path.parts and not path.is_absolute() and ".." not in path.parts and entry["reason"] and
                entry["disposable"] is True, "exclusion must name a proven disposable relative path and reason")
        paths.append(path)
    return tuple(paths)


def metadata_paths(root):
    """Index native macOS metadata without following links; never assume an error means empty."""
    if sys.platform != "darwin":
        return None
    result = []
    for predicate in ("-xattr", "-acl"):
        observed = command(["find", "-P", ".", "-path", "./.git", "-prune", "-o",
                            predicate, "-print0"], root, timeout=60)
        require(not observed.stderr.strip(), "artifact metadata visibility incomplete")
        result.append({root / name for name in observed.stdout.decode().split("\0") if name})
    return tuple(result)


def artifact_inventory(root, exclusions=(), temporary=()):
    """Inventory all bytes/metadata; only approved disposable symlinks are recorded without following."""
    metadata = metadata_paths(root)
    classified = {entry["path"]: entry for entry in temporary}
    require(len(classified) == len(temporary), "duplicate temporary-artifact classification")
    for name, entry in classified.items():
        require(Path(name) in exclusions and entry["authority"] and entry["reason"] and entry["creator"] and
                entry["record"]["kind"] in ("socket", "fifo", "symlink"), "temporary IPC/link requires exact original disposition/creator proof")
    def record(path, disposable=False):
        flags = tuple(path in paths for paths in metadata) if metadata is not None else None
        entry = classified.get(str(path.relative_to(root)))
        current = file_record(path, allow_symlink=disposable, metadata=flags, allow_ipc=entry is not None)
        if entry is not None:
            observed_metadata = path.lstat()
            generation = {"device": observed_metadata.st_dev, "inode": observed_metadata.st_ino, "ctime_ns": observed_metadata.st_ctime_ns}
            require(current == entry["record"] and generation == entry["generation"],
                    "temporary IPC/link generation/metadata changed; retain artifact")
        return current
    result = {".": record(root)}
    def unavailable(error):
        raise error
    for parent, directories, files in os.walk(root, followlinks=False, onerror=unavailable):
        if Path(parent) == root:
            directories[:] = [name for name in directories if name != ".git"]
            files = [name for name in files if name != ".git"]
        for name in sorted(directories + files):
            path = Path(parent) / name
            relative = path.relative_to(root)
            result[str(relative)] = record(path, any(inside(relative, excluded) for excluded in exclusions))
    require(metadata_paths(root) == metadata, "artifact metadata index changed while inspected")
    return result


class Storage:
    """Admit bounded allocation and reclaim independently recoverable Docker artifacts

    Cache retirement uses the Docker driver's external-image reference checker, not
    inferred task ownership. Private/mutable/source/cache-mount records never qualify.
    """

    def __init__(self, receipt, root, document=None, policy=None):
        self.path = absolute(str(receipt))
        self.safe = absolute(str(root))
        self.path.parent.mkdir(parents=True, exist_ok=True)
        if self.path.exists():
            require(self.path.stat().st_uid == os.getuid() and
                    stat.S_IMODE(self.path.stat().st_mode) == 0o600, "storage receipt must be owned/private")
        self.document = document if document is not None else (json.loads(self.path.read_text()) if self.path.exists() else {})
        policy = policy or {}
        def setting(key, default, minimum, maximum):
            value = policy.get(key, os.environ.get("FIGHT_STORAGE_" + key.upper(), default))
            require(str(value).isdigit() and minimum <= int(value) <= maximum, "invalid bounded storage setting: " + key)
            return int(value)
        self.reserve = setting("reserve_bytes", 20 * 1024 ** 3, 1, 2 ** 60)
        self.allocation = setting("allocation_bytes", 4 * 1024 ** 3, 1, 2 ** 60)
        self.retention = setting("retention_seconds", 604800, 1, 315360000)
        self.limit = setting("max_candidates", 8, 1, 32)
        self.builder = policy.get("builder", os.environ.get("FIGHT_STORAGE_BUILDER", ""))
        self.protected_inputs = policy.get("protected_images", [])
        require(isinstance(self.protected_inputs, list) and len(self.protected_inputs) <= 64 and
                all(isinstance(value, str) and re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.:/@-]{0,511}", value)
                    for value in self.protected_inputs), "invalid protected build/runtime image inputs")
        self.selection = {kind: policy.get(kind) for kind in ("cache_ids", "image_ids")}
        require(all(value is None or isinstance(value, list) and len(value) <= 32 and
                    all(isinstance(identifier, str) and re.fullmatch(r"[a-z0-9]{12,64}" if kind == "cache_ids" else
                        r"sha256:[a-f0-9]{64}", identifier) for identifier in value) and
                    len(value) == len(set(value)) for kind, value in self.selection.items()), "invalid bounded storage selection")
        self.attempt = {"time": datetime.now(timezone.utc).isoformat(), "operation_sha256": digest(Path(__file__).read_bytes()),
                        "source": str(self.safe), "reserve_bytes": self.reserve, "allocation_bytes": self.allocation,
                        "retention_seconds": self.retention, "max_candidates": self.limit, "selection": self.selection, "protected_inputs": self.protected_inputs,
                        "results": [], "complete": False, "admitted": False, "physical_reclaimed_bytes": None}
        self.document.setdefault("storage_attempts", []).append(self.attempt)

    def save(self):
        descriptor, temporary = tempfile.mkstemp(prefix=".storage-", dir=self.path.parent)
        try:
            with os.fdopen(descriptor, "w") as output:
                json.dump(self.document, output, indent=2)
                output.write("\n")
                output.flush()
                os.fsync(output.fileno())
            os.replace(temporary, self.path)
        finally:
            if os.path.exists(temporary):
                os.unlink(temporary)

    def engine(self):
        info = json_command(["docker", "info", "--format", "{{json .}}"], self.safe)
        context = json_command(["docker", "context", "inspect"], self.safe)
        require(len(context) == 1 and info.get("ID"), "engine/context identity unavailable")
        endpoint = os.environ.get("DOCKER_HOST") if not os.environ.get("DOCKER_CONTEXT") else None
        endpoint = endpoint or context[0]["Endpoints"]["docker"]["Host"]
        require(endpoint.startswith("unix:///"), "storage admission requires a local Unix engine")
        # Cross-check the CLI against that exact socket; never measure a remote host as local.
        observed, = local_image_metadata(self.safe, ["/info"])
        require(observed["ID"] == info["ID"], "storage engine association changed")
        return {"id": info["ID"], "endpoint": endpoint, "root": info["DockerRootDir"], "os": info["OperatingSystem"]}

    def host_space(self):
        engine = self.engine()
        require(not hasattr(self, "engine_identity") or engine == self.engine_identity, "storage engine changed")
        self.engine_identity = engine
        if sys.platform == "darwin":
            backing = Path.home() / "Library/Containers/com.docker.docker/Data/vms/0/data/Docker.raw"
            require(engine["os"] == "Docker Desktop" and "docker" in engine["endpoint"].lower(),
                    "unqualified local VM backing store")
            handles = text(["lsof", "-Fn", str(backing)], self.safe).splitlines()
            require("n" + str(backing) in handles, "active Desktop backing-file observation unavailable; allocation refused")
        elif sys.platform.startswith("linux"):
            backing = Path(engine["root"])
        else:
            raise Hold("host storage observation unsupported; allocation refused")
        # Override must identify the actual locally managed engine backing store, not a
        # guessed writable filesystem. Unknown Desktop/custom topologies hold for setup.
        require(backing.exists() and not backing.is_symlink(), "engine backing filesystem unavailable; allocation refused")
        rows = []
        for path in (self.safe, backing):
            device = path.stat().st_dev
            space = os.statvfs(path)
            require(space.f_bavail >= 0 and space.f_frsize > 0, "host free-space measurement unavailable")
            rows.append({"path": str(path), "device": device, "free_bytes": space.f_bavail * space.f_frsize})
        return rows

    def builder_identity(self):
        rows = [json.loads(line) for line in text(["docker", "buildx", "ls", "--format", "{{json .}}"], self.safe).splitlines()]
        candidates = [row for row in rows if row["Name"] == self.builder] if self.builder else [row for row in rows if row["Current"]]
        # Buildx may print the same node twice for its platform groups.
        unique = {fingerprint(row): row for row in candidates}
        require(len(unique) == 1, "owning builder ambiguous/unavailable")
        row = next(iter(unique.values()))
        require(row["Driver"] == "docker" and len(row["Nodes"]) == 1 and
                row["Nodes"][0]["Endpoint"] == json_command(["docker", "context", "inspect"], self.safe)[0]["Name"] and
                not os.environ.get("DOCKER_HOST"), "cache retirement requires the same local Docker-driver worker")
        node = row["Nodes"][0]
        require(node["Status"] == "running" and node["IDs"], "cache worker identity unavailable")
        return {"name": row["Name"], "workers": sorted(node["IDs"]), "endpoint": node["Endpoint"]}

    def usage(self):
        current, = local_image_metadata(self.safe, ["/system/df"])
        require(isinstance(current.get("BuildCache"), list) and len(current["BuildCache"]) <= 16384,
                "raw cache inventory unavailable/oversized")
        require(len({row["ID"] for row in current["BuildCache"]}) == len(current["BuildCache"]), "duplicate raw cache identity")
        self.attempt["docker_logical_usage_sha256"] = fingerprint(current)
        return current["BuildCache"]

    def image_census(self):
        # Keep the immutable root/reference/child census, not usage counters or sizes.
        return sorted([{"id": row["Id"], "created": row["Created"], "tags": sorted(row.get("RepoTags") or []),
                        "digests": sorted(row.get("RepoDigests") or []), "descriptor": row.get("Descriptor"),
                        "children": sorted([{"id": child["ID"], "descriptor": child["Descriptor"],
                                             "available": child["Available"], "kind": child["Kind"]}
                                            for child in row.get("Manifests", [])], key=lambda child: child["id"])}
                       for row in local_image_graphs(self.safe)], key=lambda row: row["id"])

    def cache_filters(self, identifier):
        require(re.fullmatch(r"[a-z0-9]{12,64}", identifier), "invalid exact cache identity")
        # Buildx converts id= to a regexp. Anchor it; boolean fields expose an empty
        # string only when present. Regex ^$ tests presence, unlike shared=true.
        return ["--filter", "id=^" + identifier + "$", "--filter", "shared~=^$",
                "--filter", "immutable~=^$", "--filter", "type=regular"]

    def cache(self, original, result):
        builder = self.builder_identity()
        require(builder == self.builder_snapshot, "builder worker changed")
        identifier = original["ID"]
        latest = next((row for row in self.usage() if row["ID"] == identifier), None)
        if latest is None:
            result.update(status="absent", reason="exact cache absent from successful raw engine inventory")
            return
        require(latest == original and original["InUse"] is False and original["Shared"] is True and
                original["Type"] == "regular", "cache changed/in-use/private/unique; preserve")
        last = docker_timestamp(original["LastUsedAt"])
        require(last.tzinfo is not None and (datetime.now(timezone.utc) - last).total_seconds() >= self.retention,
                "cache is inside retention window")
        filters = self.cache_filters(identifier)
        observed = [json.loads(line) for line in text(["docker", "buildx", "du", "--builder", builder["name"],
                    *filters, "--format", "json"], self.safe).splitlines()]
        require(len(observed) == 1 and observed[0]["ID"] == identifier and observed[0]["Shared"] is True and
                observed[0]["Mutable"] is False and observed[0]["Reclaimable"] is True and observed[0]["Type"] == "regular",
                "raw engine/builder recoverability observations disagree")
        protected = self.image_census()
        require(protected and protected == self.protected_images, "retained image backing changed/unavailable")
        result.update(recovery_basis="same-engine external-image reference checker plus retained image-root census; no private cache",
                      observation=original, builder=builder, protected_images_sha256=fingerprint(protected))
        self.save()
        require(self.engine() == self.engine_identity and self.builder_identity() == builder,
                "engine/builder changed before cache effect")
        require(self.image_census() == protected, "image backing changed before cache effect")
        result.update(status="pending", reason="exact bounded filtered cache retirement intent")
        self.save()
        # --all admits shared records to the candidate check; exact-ID + shared +
        # immutable + regular remain conjunctive engine filters. The engine rechecks
        # active refs and last-use under its cache lock. No unfiltered prune or force.
        command(["docker", "buildx", "prune", "--builder", builder["name"], "--all", *filters,
                 "--filter", "until=" + str(self.retention) + "s"], self.safe, timeout=60, confirmation=b"y\n")
        require(self.engine() == self.engine_identity and self.image_census() == protected,
                "protected image census changed after cache effect")
        remaining = next((row for row in self.usage() if row["ID"] == identifier), None)
        result.update(status="removed" if remaining is None else "held",
                      reason="exact cache absent; backing image roots retained" if remaining is None else
                             "engine retained cache (new use/retention/reference); no retry")

    def downloadable(self, current):
        """Verify every immutable manifest/blob through the public Docker Hub API

        No local credentials, registry mutation, mutable tag assertion or manifest-only
        availability claim. Bounded streamed content hashes are retained, not tokens.
        """
        references = sorted(set((current.get("RepoDigests") or []) + (current.get("RepoTags") or [])))
        require(references and len(references) <= 8 and all("@" in value for value in references),
                "mutable/unreferenced image may contain unique work; preserve")
        repositories = set()
        for value in references:
            repository, identity = value.rsplit("@", 1)
            require(identity == current["Id"], "image reference does not bind exact root")
            repository = repository.removeprefix("docker.io/")
            require("." not in repository.split("/")[0] and re.fullmatch(r"[a-z0-9_/-]+", repository),
                    "registry recoverability capability unavailable; preserve")
            repositories.add(repository if "/" in repository else "library/" + repository)
        require(len(repositories) == 1, "multiple remote repositories require separate qualification")
        repository = repositories.pop()
        token_url = "https://auth.docker.io/token?service=registry.docker.io&scope=repository:" + repository + ":pull"
        with urlopen(token_url, timeout=15) as response:
            token = json.loads(response.read(65537))["token"]
        require(isinstance(token, str) and len(token) <= 65536, "public registry token unavailable")
        budget, records, seen = 256 * 1024 * 1024, [], set()
        deadline = time.monotonic() + 60
        def fetch(identity, media, size=None):
            nonlocal budget
            require(re.fullmatch(r"sha256:[a-f0-9]{64}", identity), "unsupported remote content digest")
            if identity in seen:
                return
            require(len(seen) < 128 and time.monotonic() < deadline, "remote graph exceeds bounded qualification")
            seen.add(identity)
            manifest = "manifest" in media or "index" in media or "list" in media
            route = "manifests/" if manifest else "blobs/"
            request = Request("https://registry-1.docker.io/v2/" + repository + "/" + route + identity,
                              headers={"Authorization": "Bearer " + token, "Accept": media})
            hashed, length, chunks = hashlib.sha256(), 0, []
            with urlopen(request, timeout=15) as response:
                while True:
                    require(time.monotonic() < deadline, "remote content qualification deadline reached; preserve image")
                    chunk = response.read(min(1024 * 1024, budget + 1))
                    if not chunk:
                        break
                    budget -= len(chunk)
                    require(budget >= 0, "remote graph exceeds byte qualification budget")
                    hashed.update(chunk)
                    length += len(chunk)
                    if manifest:
                        require(length <= 1024 * 1024, "remote manifest oversized")
                        chunks.append(chunk)
            require("sha256:" + hashed.hexdigest() == identity and (size is None or length == size),
                    "remote immutable content hash/size mismatch")
            records.append({"digest": identity, "bytes": length})
            if manifest:
                value = json.loads(b"".join(chunks))
                children = value.get("manifests", []) if "manifests" in value else [value["config"], *value["layers"]]
                require(isinstance(children, list) and len(children) <= 64, "remote graph unsupported")
                for child in children:
                    fetch(child["digest"], child["mediaType"], child["size"])
        descriptor = current.get("Descriptor")
        require(descriptor and descriptor["digest"] == current["Id"], "legacy image remote identity unresolved")
        fetch(current["Id"], descriptor["mediaType"], descriptor["size"])
        return {"repository": repository, "root": current["Id"], "verified_content": records}

    def image_consumers(self, current):
        roots = local_image_graphs(self.safe)
        matches = [row for row in roots if row["Id"] == current["Id"]]
        require(len(matches) == 1 and matches[0].get("Manifests"), "image platform graph unavailable")
        manifests = {child["ID"] for child in matches[0]["Manifests"]}
        require(all(re.fullmatch(r"sha256:[a-f0-9]{64}", value) for value in manifests), "invalid image child identity")
        for consumer in docker_inventory(self.safe):
            require(consumer["Image"] != current["Id"], "image has current/stopped consumer")
            descriptor = consumer.get("ImageManifestDescriptor")
            require(descriptor and re.fullmatch(r"sha256:[a-f0-9]{64}", descriptor.get("digest", "")),
                    "image consumer platform visibility unavailable")
            require(descriptor["digest"] not in manifests | {current["Id"]}, "image has current/stopped platform consumer")

    def image(self, original, result):
        current = inspect_image(self.safe, original["id"])
        require(current is not None, "image changed/absent before qualification")
        created = docker_timestamp(current["Created"])
        require((datetime.now(timezone.utc) - created).total_seconds() >= self.retention, "image inside retention window")
        self.image_consumers(current)
        recovery = self.downloadable(current)
        result.update(recovery=recovery, inspection_sha256=fingerprint(current))
        self.save()
        before = self.image_census()
        require(original in before and self.engine() == self.engine_identity, "image/engine changed before effect")
        self.image_consumers(current)
        require(fingerprint(inspect_image(self.safe, original["id"])) == fingerprint(current), "image identity/references changed")
        result.update(status="pending", reason="verified downloadable exact image retirement intent")
        self.save()
        command(["docker", "image", "rm", current["Id"]], self.safe, timeout=60)
        require(inspect_image(self.safe, current["Id"]) is None and self.engine() == self.engine_identity,
                "image removal postcondition failed")
        require(self.image_census() == [row for row in before if row["id"] != current["Id"]],
                "other image roots/references changed; aggregate incomplete")
        result.update(status="removed", reason="exact image absent; full immutable remote content verified; no layer ownership claim")

    def reclaim(self):
        caches = []
        self.builder_snapshot = None
        try:
            self.builder_snapshot = self.builder_identity()
            self.attempt["builder"] = self.builder_snapshot
            caches = self.usage()
        except (Hold, OSError, ValueError, KeyError, TypeError) as error:
            # Builder visibility is not an image-retirement dependency. Retain the
            # precise cache hold while independently qualifying image effects.
            self.attempt["results"].append({"kind": "cache", "identity": "builder-inventory", "status": "held",
                "reason": str(error) if isinstance(error, Hold) else type(error).__name__, "commands": []})
        self.protected_images = self.image_census()
        require(self.engine() == self.engine_identity, "engine changed during storage inventory")
        # Cache first: its recoverability depends on preserving ALL image roots.
        now = datetime.now(timezone.utc)
        candidates = []
        for row in caches:
            if row.get("Type") != "regular" or row.get("Shared") is not True or row.get("InUse") is not False:
                continue
            try:
                if self.selection["cache_ids"] is not None or (now - docker_timestamp(row["LastUsedAt"])).total_seconds() >= self.retention:
                    candidates.append(("cache", row))
            except (Hold, KeyError, TypeError, ValueError) as error:
                self.attempt["results"].append({"kind": "cache", "identity": row["ID"], "status": "held", "commands": [],
                    "reason": str(error) if isinstance(error, Hold) else type(error).__name__})
        candidates += [("image", row) for row in self.protected_images if row["digests"] and not
                       any("@" not in reference for reference in row["tags"]) and
                       isinstance(row["created"], int) and now.timestamp() - row["created"] >= self.retention]
        protected_ids = {value.rsplit("@", 1)[-1] for value in self.protected_inputs if "@" in value or value.startswith("sha256:")}
        protected_ids.update(row["id"] for row in self.protected_images if set(self.protected_inputs) &
                             set(row.get("tags", []) + row.get("digests", [])))
        candidates = [(kind, row) for kind, row in candidates if kind != "image" or row["id"] not in protected_ids]
        self.attempt["protected_input_ids"] = sorted(protected_ids)
        candidates = [(kind, row) for kind, row in candidates if self.selection[kind + "_ids"] is None or
                      (row["ID"] if kind == "cache" else row["id"]) in self.selection[kind + "_ids"]]
        self.attempt["candidate_count"] = len(candidates)
        self.attempt["budget_exhausted"] = len(candidates) > self.limit
        self.attempt["protected_cache_count"] = len(caches) - sum(kind == "cache" for kind, _ in candidates)
        self.attempt["excluded_storage_policy"] = "private, mutable, source and cache-mount records; unique/unproven or mutable-tagged images; all volumes/host data remain untouched"
        for kind, item in candidates[:self.limit]:
            identifier = item["ID"] if kind == "cache" else item["id"]
            result = {"kind": kind, "identity": identifier, "status": "held", "commands": [],
                      "ownership": "historical task ownership not required for positively recoverable artifacts"}
            self.attempt["results"].append(result)
            global COMMANDS
            COMMANDS = result["commands"]
            try:
                getattr(self, kind)(item, result)
            except (Hold, OSError, ValueError, KeyError, TypeError) as error:
                result.update(status="held", reason=str(error) if isinstance(error, Hold) else type(error).__name__)
            finally:
                COMMANDS = None
                self.save()
        for kind, observed in (("cache", {row["ID"] for row in caches}),
                               ("image", {row["id"] for row in self.protected_images})):
            for identifier in self.selection[kind + "_ids"] or []:
                if identifier not in observed:
                    result = {"kind": kind, "identity": identifier, "status": "absent", "commands": [],
                              "reason": "exact identity absent from successful same-engine inventory; no effect replayed"}
                    if kind == "image":
                        COMMANDS = result["commands"]
                        try:
                            require(any(entry.get("identity") == identifier and
                                        entry.get("recovery", {}).get("root") == identifier and entry.get("inspection_sha256")
                                        for attempt in self.document.get("storage_attempts", [])[:-1]
                                        for entry in attempt["results"]),
                                    "absent/unresolvable image lacks prior exact-root recovery proof; config/index ambiguity held")
                            require(inspect_image(self.safe, identifier) is None,
                                    "image census contradicts exact inspection; absence held")
                        except (Hold, OSError, ValueError, KeyError, TypeError) as error:
                            result.update(status="held", reason=str(error) if isinstance(error, Hold) else type(error).__name__)
                        finally:
                            COMMANDS = None
                    self.attempt["results"].append(result)
        self.attempt["complete"] = not self.attempt["budget_exhausted"] and all(
            row["status"] in ("removed", "absent") for row in self.attempt["results"])

    def run(self, admit=True):
        self.save()
        try:
            self.engine_identity = self.engine()
            self.attempt["engine"] = self.engine_identity
            measured = True
            for phase in ("host_before",):
                try:
                    self.attempt[phase] = self.host_space()
                except (Hold, OSError, ValueError, KeyError, TypeError) as error:
                    measured = False
                    self.attempt[phase] = {"hold": str(error) if isinstance(error, Hold) else type(error).__name__}
            self.reclaim()
            try:
                self.attempt["host_after"] = self.host_space()
            except (Hold, OSError, ValueError, KeyError, TypeError) as error:
                measured = False
                self.attempt["host_after"] = {"hold": str(error) if isinstance(error, Hold) else type(error).__name__}
            self.attempt["admitted"] = admit and measured and all(row["free_bytes"] >= self.reserve + self.allocation
                                                                 for row in self.attempt["host_after"])
            if admit and not self.attempt["admitted"]:
                self.attempt["hold"] = "reserve plus bounded allocation unavailable after one reclaim pass; stop allocation; owner frees known disposable storage or provisions capacity"
        except (Hold, OSError, ValueError, KeyError, TypeError) as error:
            self.attempt.update(admitted=False, complete=False, hold=str(error) if isinstance(error, Hold) else type(error).__name__)
        self.save()
        return self.attempt


# Qualified legacy operation, not a version gate or permission to execute old code.
LEGACY_NO_EFFECT_OPERATION = "05b3758fb220514dd8e656de9fdb101546770394bb1c0a4b7fdbf1187a87ae2c"


def qualify_legacy_append(plan, attempt, source_bytes, image_outputs):
    """Corroborate the one supported legacy early-hold path, without executing it."""
    require(digest(source_bytes) == LEGACY_NO_EFFECT_OPERATION and
            set(attempt) == {"time", "apply", "operation_sha256", "results", "complete"} and
            attempt["operation_sha256"] == LEGACY_NO_EFFECT_OPERATION and attempt["apply"] is True and
            attempt["complete"] is False, "unqualified legacy source/attempt; recovery held")
    require(datetime.fromisoformat(attempt["time"]).tzinfo is not None, "legacy attempt time unavailable")
    require(not plan["caches"] and set(image_outputs) == {v["identity"] for v in plan["images"]},
            "legacy cache/image coverage unsupported")
    expected = []
    for kind, items in (("container", plan["containers"]), ("volume", plan["volumes"]),
                        ("network", plan["networks"]), ("image", plan["images"]),
                        ("worktree", [plan["worktree"]]), ("ref", [plan["branch"]])):
        for item in items:
            result = {"kind": kind, "identity": item["identity"], "status": "held", "commands": [],
                      "recovery": "resource owner resolves the named prerequisite, then resume this receipt once"}
            if item["disposition"] == "retain":
                require(kind == "image" and item["reason"] and item["creation"]["kind"] == "reused",
                        "legacy retention path unsupported")
                raw = image_outputs[item["identity"]]
                images = json.loads(raw)
                require(len(images) == 1 and images[0]["Id"] == item["identity"] and
                        fingerprint(images[0]) == item["inspection_sha256"], "legacy image identity changed")
                result.update(status="retained", reason=item["reason"], creation=item["creation"],
                              storage={"size_bytes": images[0]["Size"], "physical_reclaimed_bytes": None},
                              commands=[{"argv": ["docker", "image", "inspect", item["identity"]], "exit": 0,
                                         "stdout_sha256": digest(raw), "stderr_sha256": digest(b"")}])
            else:
                require(item["disposition"] == "hold" or
                        (item["disposition"] == "remove" and (item["ownership"] != "proven" or item["consumers"] != [])),
                        "legacy attempt could reach an effect; recovery held")
                result["reason"] = item["reason"] if item["disposition"] == "hold" else "ownership or consumers unresolved/active"
            expected.append(result)
    expected.append({"kind": "final-verification", "identity": plan["task"], "status": "failed",
                     "reason": "protected report/private evidence changed"})
    require(attempt["results"] == expected, "legacy result/read-only-command coverage changed/unknown")


def verification_snapshot(reference, plan):
    """Read an immutable return and its exact snapshot, including the bounded successor."""
    def retained(entry):
        path = absolute(entry["path"])
        require(set(entry) == {"path", "sha256"} and
                inside(path, Path(plan["safe_checkout"]) / ".runs/tasks" / plan["task"] / "runs") and
                not inside(path, absolute(plan["worktree"]["identity"])) and path.is_file() and not path.is_symlink() and
                path.stat().st_uid == os.getuid() and stat.S_IMODE(path.stat().st_mode) == 0o600,
                "verification history must remain private and external")
        raw = path.read_bytes()
        require(digest(raw) == entry["sha256"], "immutable verification history changed")
        return raw

    event = json.loads(retained(reference))
    require(event["version"] in (1, 2) and event["kind"] == "return-to-verification" and
            event["plan_sha256"] == fingerprint(plan) and
            all(event["subject"][key] == plan[key] for key in ("task", "origin", "ref", "safe_checkout", "git_common_dir")),
            "verification history subject/link changed")
    original = json.loads(retained(event["original_receipt"]))
    require(original["cleanup"]["plan"] == plan and original["lifecycle"]["subject"] == event["subject"],
            "verification snapshot subject/plan changed")
    if event["version"] == 2:
        require(set(event) == {"version", "kind", "at", "subject", "source", "plan_sha256", "original_receipt",
                              "predecessor", "legacy_source", "image_observations", "protected_history", "workers",
                              "original_monitor", "exit_records", "publication_observations", "authority", "identity_observation"},
                "unknown verification recovery state")
        predecessor = json.loads(retained(event["predecessor"]))
        require(predecessor["version"] == 1 and "published_setup_failure" in predecessor["publication_observations"] and
                original["cleanup"]["verification_return"] == event["predecessor"], "unsupported recovery predecessor")
        _, previous = verification_snapshot(event["predecessor"], plan)
        old, current = previous["cleanup"], original["cleanup"]
        mutable = {"operations", "stages", "resources", "processes", "monitor_attempts"}
        require({k: v for k, v in original["lifecycle"].items() if k not in mutable} ==
                {k: v for k, v in previous["lifecycle"].items() if k not in mutable} and
                all(original["lifecycle"][key][:len(previous["lifecycle"][key])] == previous["lifecycle"][key]
                    for key in ("operations", "stages", "processes", "monitor_attempts")), "original recovery private state/history changed")
        require({k: v for k, v in current.items() if k not in ("attempts", "verification_return")} ==
                {k: v for k, v in old.items() if k != "attempts"} and
                len(current["attempts"]) == len(old["attempts"]) + 1 and current["attempts"][:-1] == old["attempts"],
                "recovery must preserve all original cleanup fields and append exactly one attempt")
        require(event["publication_observations"] == predecessor["publication_observations"] and
                event["original_monitor"] == predecessor["original_monitor"] and event["exit_records"] == predecessor["exit_records"],
                "original return/worker/hosting evidence changed")
        require(datetime.fromisoformat(current["attempts"][-1]["time"]) > datetime.fromisoformat(predecessor["at"]),
                "legacy append predates suspension")
        require(set(event["legacy_source"]) == {"revision", "record"} and
                re.fullmatch(r"[a-f0-9]{40}", event["legacy_source"]["revision"]), "legacy Git source unavailable")
        qualify_legacy_append(plan, current["attempts"][-1], retained(event["legacy_source"]["record"]),
                              {key: retained(value) for key, value in event["image_observations"].items()})
        observation = event["identity_observation"]
        require(set(observation) == {"baseline_intent", "current"} and
                original["lifecycle"]["operations"][-1]["intent"] == observation["baseline_intent"], "original census linkage changed")
        intent = json.loads(retained(observation["baseline_intent"]))
        inventory = json.loads(retained(observation["current"]))
        require(intent["kind"] == "compose-command" and intent["subject"] == event["subject"] and
                intent["runtime"] == original["lifecycle"]["runtime"] and
                datetime.fromisoformat(intent["at"]) < datetime.fromisoformat(current["attempts"][-1]["time"]),
                "pre-incident census subject/time changed")
        for kind, key, identity in (("containers", "Id", container_identity), ("networks", "Id", network_identity),
                                    ("volumes", "Name", volume_identity)):
            known = {v["identity"]: v for v in original["lifecycle"]["resources"][kind]}
            require({v[key] for v in inventory[kind]} & set(known) == set(intent["before"][kind]) & set(known) and
                    all(identity(value) == known[value[key]]["inspection_sha256"] for value in inventory[kind] if value[key] in known),
                    "recovery census identity/presence changed")
        replacements = event["protected_history"]
        changed = {entry["path"]: entry for entry in replacements}
        require(len(changed) == len(replacements) and changed and set(changed) <= {
            str(Path(plan["safe_checkout"]) / ".runs" / directory / plan["task"] / name)
            for directory, name in (("reviews", "review.md"), ("qa", "qa.md"))}, "only canonical report succession supported")
        for report in replacements:
            original_report = next(v for v in plan["protected_files"] if v["path"] == report["path"])
            require(set(report) == {"path", "original", "current_record"} and
                    file_record(absolute(report["original"]["path"])) == original_report["record"] and
                    digest(retained(report["original"])) == original_report["record"]["sha256"] and
                    report["current_record"] != original_report["record"], "original protected report not preserved")
    return event, original


def verify_plan_history(cleanup):
    """Verify the immutable plan chain without creating an execution attempt."""
    plan_hash = fingerprint(cleanup["plan"])
    require(cleanup.get("plan_sha256") == plan_hash, "original immutable plan hash missing/changed")
    history = cleanup.get("plan_history", [])
    require(isinstance(history, list) and len(history) <= 128 and
            all(fingerprint(entry["plan"]) == entry["plan_sha256"] for entry in history),
            "historical immutable plan changed")
    require(all(entry["next_plan_sha256"] == (history[index + 1]["plan_sha256"] if index + 1 < len(history) else plan_hash)
                for index, entry in enumerate(history)), "plan revision linkage changed")
    for entry in history:
        if "verification_return" not in entry:
            continue
        plan = entry["plan"]

        _, original = verification_snapshot(entry["verification_return"], plan)
        require(cleanup["attempts"][:len(original["cleanup"]["attempts"])] == original["cleanup"]["attempts"],
                "original verification plan/attempts changed")
    if cleanup.get("verification_return"):
        _, original = verification_snapshot(cleanup["verification_return"], cleanup["plan"])
        require({k: v for k, v in cleanup.items() if k != "verification_return"} ==
                {k: v for k, v in original["cleanup"].items() if k != "verification_return"},
                "suspended plan/history/attempts changed")
    return plan_hash


class Cleanup:
    def __init__(self, receipt, apply=False):
        self.path = absolute(receipt)
        self.document = json.loads(self.path.read_text())
        require(not self.document["cleanup"].get("verification_return"),
                "plan suspended for verification; Land must explicitly generate a fresh qualified plan")
        self.plan = self.document["cleanup"]["plan"]
        self.state = self.document["cleanup"].setdefault("attempts", [])
        self.apply = apply
        self.safe = absolute(self.plan["safe_checkout"])
        self.subject = self.plan["task"]
        require(re.fullmatch(r"TASK-\d{5}", self.subject), "invalid subject")
        self.store = self.safe / ".runs" / "tasks" / self.subject / "runs"
        require(inside(self.path, self.store), "receipt must be in canonical subject runs outside candidates")
        require(self.path.stat().st_uid == os.getuid() and stat.S_IMODE(self.path.stat().st_mode) == 0o600,
                "receipt must already be private and owned (0600)")
        require(self.document["cleanup"]["version"] == 1, "unsupported cleanup receipt")
        require(all(isinstance(self.plan.get(kind), list) for kind in
                    ("containers", "networks", "volumes", "images", "caches")),
                "explicit storage/image/cache inventory missing; recover creation evidence before cleanup")
        require(all(len(self.plan[kind]) <= 32 for kind in
                    ("containers", "networks", "volumes", "images", "caches")) and
                len(self.plan["protected_files"]) <= 128, "exact-subject resource bounds exceeded")
        for kind in ("containers", "networks", "volumes", "images", "caches"):
            identities = [item["identity"] for item in self.plan[kind]]
            require(len(identities) == len(set(identities)), "duplicate exact-subject resource identity")
        self.plan_hash = fingerprint(self.plan)
        previous = self.document["cleanup"].get("plan_sha256")
        require(previous is None or previous == self.plan_hash, "plan changed; reconcile explicitly in a new receipt linked to prior results")
        history = self.document["cleanup"].get("plan_history", [])
        if any("verification_return" in entry for entry in history):
            verify_plan_history(self.document["cleanup"])
        require(len(history) <= 128 and all(fingerprint(entry["plan"]) == entry["plan_sha256"] for entry in history), "historical immutable plan changed")
        if history:
            require(all(entry["next_plan_sha256"] == (history[index + 1]["plan_sha256"] if index + 1 < len(history) else self.plan_hash)
                        for index, entry in enumerate(history)), "plan revision linkage changed")
        self.document["cleanup"]["plan_sha256"] = self.plan_hash
        self.results = []
        self.attempt = {"time": datetime.now(timezone.utc).isoformat(), "apply": apply,
                        "operation_sha256": digest(Path(__file__).read_bytes()), "plan_sha256": self.plan_hash,
                        "results": self.results, "complete": False}
        self.state.append(self.attempt)

    def save(self):
        descriptor, temp = tempfile.mkstemp(prefix=".cleanup-", dir=self.path.parent)
        try:
            with os.fdopen(descriptor, "w") as output:
                json.dump(self.document, output, indent=2)
                output.write("\n")
                output.flush()
                os.fsync(output.fileno())
            os.replace(temp, self.path)
        finally:
            if os.path.exists(temp):
                os.unlink(temp)

    def effect(self, kind, item, operation):
        global COMMANDS
        result = {"kind": kind, "identity": item["identity"], "status": "held", "commands": [],
                  "recovery": "resource owner resolves the named prerequisite, then resume this receipt once"}
        self.results.append(result)
        COMMANDS = result["commands"]
        try:
            require(item["disposition"] in ("remove", "retain", "hold"), "unknown disposition")
            if item["disposition"] == "retain":
                require(item["reason"], "retention reason missing")
                if kind in ("container", "network", "volume", "image"):
                    self.observe_retained(kind, item, result)
                elif kind == "cache":
                    self.cache(item, result)
                result.update(status="retained", reason=item["reason"])
            else:
                require(item["disposition"] == "remove", item["reason"])
                require(item["ownership"] == "proven" and
                        (item["consumers"] == [] or (item.get("consumer_policy") == "fresh" and item["consumers"] is None)),
                        "ownership or known consumers unresolved/active")
                # Generated lifecycle candidates defer the no-consumer conclusion, not
                # the check: each operation below must qualify live visibility before effects.
                self.subject_checks()
                operation(item, result)
        except Hold as error:
            result.update(status="held", reason=str(error))
        except (OSError, ValueError, KeyError, TypeError) as error:
            result.update(status="held", reason=f"{type(error).__name__}: inspection/preservation unavailable; retain unchanged")
        lifecycle = self.document.get("lifecycle")
        if lifecycle is not None and kind in ("container", "network", "volume", "image", "cache"):
            for resource in lifecycle["resources"][kind + "s"]:
                if resource["identity"] == item["identity"]:
                    resource["history"].append({"at": self.attempt["time"], "state": result["status"],
                                                "plan_sha256": self.plan_hash, "attempt": len(self.state),
                                                "reason": result.get("reason")})
        self.save()
        COMMANDS = None

    def subject_checks(self):
        require(Path.cwd().resolve() == self.safe, "execute only from canonical safe checkout")
        trees = worktrees(self.safe)
        require(trees and Path(trees[0]["worktree"]) == self.safe, "safe checkout is not canonical main")
        require(text(["git", "remote", "get-url", "origin"], self.safe) == self.plan["origin"], "origin changed")
        require(text(["git", "rev-parse", "--path-format=absolute", "--git-common-dir"], self.safe) == self.plan["git_common_dir"], "Git common directory changed")
        require(re.fullmatch(r"refs/heads/feature/task-" + self.subject[5:] + r"-[a-z0-9-]+", self.plan["ref"]), "ref not bound to subject")
        require(re.fullmatch(r"[a-f0-9]{40}", self.plan["oid"]), "invalid candidate OID")
        candidate = absolute(self.plan["worktree"]["identity"])
        for report in self.plan["protected_files"]:
            path = absolute(report["path"])
            require(file_record(path) == report["record"], "protected report/private evidence changed")
        for original in self.plan.get("protected_source_files", []):
            path = absolute(original["path"])
            require(inside(path, candidate) and original["preserved"] in self.plan["protected_files"],
                    "protected source/external binding changed")
            require(not candidate.exists() or file_record(path) == original["record"], "protected source changed before removal")
        disposition = self.plan["worktree"]["disposition"]
        require(disposition in ("remove", "retain", "hold"), "unknown worktree disposition")
        if disposition == "remove":
            require(not any(inside(absolute(report["path"]), candidate) for report in self.plan["protected_files"]),
                    "canonical report inside deletion candidate")
        if self.plan.get("readiness"):
            reference = self.plan["readiness"]
            raw = self.retained_evidence(reference)
            readiness = json.loads(raw)
            require(readiness["subject"]["task"] == self.subject and readiness["subject"]["checkout"] == str(candidate),
                    "cleanup readiness subject changed")
            if "preservation" in readiness:
                self.verify_preservation(readiness["preservation"])
            if readiness["ready"] is not True:
                require(self.plan["worktree"]["disposition"] != "remove" and self.plan["branch"]["disposition"] != "remove",
                        "incomplete preservation cannot retire source/ref")
        for worker in self.plan["exited_workers"]:
            # PID reuse is not an exit observation. A live PID always holds, even after reuse.
            observed = command(["ps", "-p", str(worker["pid"]), "-o", "lstart="], self.safe, codes=(0, 1))
            require(observed.returncode == 1 and not observed.stdout.strip(), "original worker PID still live or reused; coordinator must observe exit")
        if self.plan.get("purpose") == "disposable-runtime":
            require(self.plan["worktree"]["disposition"] == "retain" and self.plan["branch"]["disposition"] == "retain" and
                    self.plan["runtime"]["ownership"] == "proven" and self.plan["runtime"]["disposable_authority"],
                    "runtime-only authority cannot retire source/ref or unqualified data")
            command(["git", "cat-file", "-e", self.plan["oid"] + "^{commit}"], self.safe)
            self.attempt["runtime_authority_observed_at"] = datetime.now(timezone.utc).isoformat()
            return
        publication = self.plan["publication"]
        fields = "url,state,mergedAt,mergeCommit,baseRefName,headRefName,headRefOid,headRepository,headRepositoryOwner"
        raw = command(["gh", "pr", "view", publication["url"], "--repo", publication["repository"], "--json", fields], self.safe)
        require(len(raw.stdout) <= 16384, "hosting observation too large")
        pr = json.loads(raw.stdout)
        branch = self.plan["ref"].removeprefix("refs/heads/")
        require(pr["url"] == publication["url"] == f"https://github.com/{publication['repository']}/pull/{publication['number']}", "PR identity mismatch")
        owner, repository = publication["repository"].split("/")
        require(self.plan["origin"] in (f"git@github.com:{owner}/{repository}.git", f"https://github.com/{owner}/{repository}.git"), "hosting/origin mismatch")
        require(pr["headRepository"]["name"] == repository and pr["headRepositoryOwner"]["login"] == owner, "fork/head repository mismatch")
        require(pr["baseRefName"] == publication["base"] and pr["headRefName"] == branch and pr["headRefOid"] == self.plan["oid"], "PR base/head/candidate changed")
        require(publication["base_ref"] in (f"refs/heads/{publication['base']}", f"refs/remotes/origin/{publication['base']}"), "integration ref not bound to intended base")
        require(publication["delivery_verified"] is True, "delivery/QA/provenance gates unresolved")
        if pr["state"] == "MERGED":
            merge = pr["mergeCommit"]["oid"]
            require(re.fullmatch(r"[a-f0-9]{40}", merge) and pr["mergedAt"], "incomplete merge evidence")
            require(merge == publication["merge_oid"] and pr["mergedAt"] == publication["merged_at"], "merge binding changed")
            command(["git", "merge-base", "--is-ancestor", merge, publication["base_ref"]], self.safe)
        else:
            require(pr["state"] == "OPEN" and pr["mergedAt"] is None and pr["mergeCommit"] is None, "PR closed/unverified")
            remote = text(["git", "ls-remote", "--heads", "origin", self.plan["ref"]], self.safe)
            require(remote == f"{self.plan['oid']}\t{self.plan['ref']}", "published ref missing or changed")
        self.attempt["publication_observed_at"] = datetime.now(timezone.utc).isoformat()

    def runtime(self, item=None):
        binding = (item or {}).get("runtime", self.plan["runtime"])
        require(binding["ownership"] == "proven", "runtime binding ownership unresolved")
        cwd = absolute(binding["working_directory"])
        args = ["docker", "compose", "--project-name", binding["project"], "--project-directory", str(cwd)]
        for profile in binding.get("profiles", []):
            require(re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.-]{0,63}", profile), "invalid explicit runtime profile")
            args += ["--profile", profile]
        for entry in binding["env_files"]:
            path = absolute(entry["path"])
            require(digest(path.read_bytes()) == entry["sha256"], "runtime env-file changed/missing")
            args += ["--env-file", str(path)]
        require(binding["config_files"], "missing qualified Compose configuration")
        for entry in binding["config_files"]:
            path = absolute(entry["path"])
            require(digest(path.read_bytes()) == entry["sha256"], "runtime configuration changed/missing")
            args += ["--file", str(path)]
        model = json_command([*args, "config", "--format", "json"], cwd)
        require(fingerprint(model) == binding["resolved_sha256"] and model["name"] == binding["project"], "resolved runtime binding changed; no generic setup fallback")
        return binding, args, model

    def retained_evidence(self, entry):
        path = absolute(entry["path"])
        require(not inside(path, absolute(self.plan["worktree"]["identity"])) and
                path.stat().st_uid == os.getuid() and stat.S_IMODE(path.stat().st_mode) == 0o600,
                "standalone evidence must be private, owned and outside removal candidates")
        raw = path.read_bytes()
        require(len(raw) <= 16 * 1024 * 1024 and digest(raw) == entry["sha256"],
                "standalone evidence missing/changed; preserve original and recover proof")
        return raw

    def standalone(self, item=None):
        """Qualify an explicit creation capability, never a Compose-validation bypass."""
        binding = (item or {}).get("runtime", self.plan["runtime"])
        require(binding["kind"] == "standalone" and binding["ownership"] == "proven",
                "standalone capability ownership unresolved")
        record = json.loads(self.retained_evidence(binding["creation_record"]))
        require(record["version"] == 1 and record["task"] == self.subject and
                record["origin"] == self.plan["origin"] and record["authority"] and
                record["data"] == "disposable" and fingerprint(record) == binding["resolved_sha256"],
                "standalone creation subject/authority/configuration contradiction")
        source = record["source"]
        require(re.fullmatch(r"[a-f0-9]{40}", source["revision"]) and source["workload"],
                "standalone source revision/workload unresolved")
        basis = record["basis"]
        require(basis["kind"] in ("allocation", "legacy-adoption") and 1 <= len(basis["evidence"]) <= 16,
                "standalone original allocation evidence unavailable")
        evidence = b"\n".join(self.retained_evidence(entry) for entry in basis["evidence"])
        require(source["revision"].encode() in evidence, "standalone source not corroborated by retained evidence")
        if basis["kind"] == "legacy-adoption":
            prior = json.loads(self.retained_evidence(basis["prior_receipt"]))
            old = prior["cleanup"]["plan"]
            require(old["task"] == self.subject and old["origin"] == self.plan["origin"] and
                    old["oid"] == self.plan["oid"] and fingerprint(old) == basis["prior_plan_sha256"],
                    "legacy adoption original subject/plan changed")
            for kind in ("containers", "networks"):
                require(set(record[kind]) <= {entry["identity"] for entry in old[kind]},
                        "legacy adoption adds resources outside original subject")
        for kind in ("containers", "networks"):
            require(len(record[kind]) <= 32, "standalone allocation bounds exceeded")
            for identifier, resource in record[kind].items():
                require(re.fullmatch(r"[a-f0-9]{64}", identifier) and identifier.encode() in evidence and
                        (resource["inspection_sha256"].encode() in evidence or
                         (kind == "networks" and resource["created"].encode() in evidence)),
                        "exact standalone allocation/configuration proof absent; resource-specific evidence hold")
        return record

    def standalone_container(self, item, current):
        record = self.standalone(item)
        proof = record["containers"][item["identity"]]
        require(proof["name"] == current["Name"] == item["name"] and
                proof["image"] == current["Image"] and
                proof["inspection_sha256"] == item["inspection_sha256"] == container_identity(current) and
                proof["mounts_sha256"] == fingerprint(current["Mounts"]),
                "standalone container creation/configuration/mount identity changed")
        require(not any(key.startswith("com.docker.compose.") for key in (current["Config"].get("Labels") or {})),
                "Compose-labelled resource requires the Compose capability")
        stopped_oneoff = proof["workload"]["kind"] == "oneoff" and not current["State"]["Running"]
        allowed_networks = set(record["networks"]) | {value["name"] for value in record["networks"].values()}
        if stopped_oneoff:
            allowed_networks.add("none")
        require(not current["HostConfig"].get("PortBindings") and
                current["NetworkSettings"]["Networks"] and
                current["HostConfig"]["NetworkMode"] in allowed_networks and
                not current["HostConfig"].get("Privileged") and not current["HostConfig"].get("AutoRemove"),
                "standalone published/host/privileged/auto-removing runtime outside qualified capability")
        for name, attached in current["NetworkSettings"]["Networks"].items():
            if stopped_oneoff and name == "none" and current["HostConfig"]["NetworkMode"] == "none":
                continue  # No network allocation or endpoint; never admit host networking.
            identifier = attached.get("NetworkID")
            # Docker drops NetworkID for stopped endpoints; resolve the recorded exact name instead.
            matches = [key for key, value in record["networks"].items() if value["name"] == name]
            require(len(matches) == 1 and (not identifier or identifier == matches[0]),
                    "standalone network identity unknown/shared")
            network = json_command(["docker", "network", "inspect", matches[0]], self.safe)[0]
            require(network_identity(network) == record["networks"][matches[0]]["inspection_sha256"],
                    "standalone network configuration changed")
            if stopped_oneoff and record["networks"][matches[0]].get("creation") == "reused":
                continue  # Retire only the stopped creator, never its shared network or peers.
            eligible = {entry["identity"]: entry for entry in self.plan["containers"]
                        if entry["disposition"] == "remove" and entry["ownership"] == "proven" and
                        entry["data"] == "disposable" and (entry["consumers"] == [] or
                        (entry.get("consumer_policy") == "fresh" and entry["consumers"] is None))}
            peers = {entry["Id"] for entry in docker_inventory(self.safe)
                     if name in entry["NetworkSettings"]["Networks"]}
            require(peers <= set(eligible) & set(record["containers"]) and
                    set(network["Containers"]) <= set(eligible) & set(record["containers"]),
                    "standalone network has stopped/shared/unknown consumer; preserve workload")
        workload = proof["workload"]
        if current["State"]["Running"]:
            processes = command(["docker", "top", item["identity"], "-eo", "pid,ppid,args"], self.safe).stdout
            require(digest(processes) == workload["processes_sha256"] == item["processes_sha256"],
                    "standalone processes changed/active consumer")
            # Connection inspection is live, not a hash of a historical no-consumer declaration.
            probe = "awk 'FNR > 1 && $4 == \"01\" {n++} END {print n+0}' /proc/net/tcp /proc/net/tcp6; awk 'NR > 1 && $6 == \"03\" {n++} END {print n+0}' /proc/net/unix"
            sockets = command(["docker", "exec", item["identity"], "sh", "-c", probe], self.safe)
            require(not sockets.stderr.strip() and sockets.stdout.split() == [b"0", b"0"],
                    "standalone active connections or unavailable socket inspection")
            if workload["kind"] == "idle":
                require(current["Config"]["Cmd"] and current["Config"]["Cmd"][0] == "sleep" and
                        len(processes.decode().splitlines()) == 2,
                        "standalone idle workload has unknown processes")
            elif workload["kind"] == "postgres":
                sessions = command(["docker", "exec", item["identity"], "sh", "-c",
                    'psql -U "${POSTGRES_USER:-postgres}" -d "${POSTGRES_DB:-${POSTGRES_USER:-postgres}}" -Atc "SELECT count(*) FROM pg_stat_activity WHERE backend_type=\'client backend\' AND pid<>pg_backend_pid()"'], self.safe)
                require(sessions.stdout.strip() == b"0" and not sessions.stderr.strip(), "standalone PostgreSQL sessions active/unknown")
            elif workload["kind"] == "mysql":
                sessions = command(["docker", "exec", item["identity"], "sh", "-c",
                    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -Nse "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID<>CONNECTION_ID() AND USER<>\'event_scheduler\'"'], self.safe)
                require(sessions.stdout.strip() == b"0" and not sessions.stderr.strip(), "standalone MySQL sessions active/unknown")
            else:
                raise Hold("standalone workload consumer-inspection capability unavailable")
        else:
            require(workload["kind"] in ("idle", "postgres", "mysql", "oneoff") and not current["State"].get("Restarting"),
                    "stopped standalone workload identity unresolved")
        # Reuse the SAME named/anonymous provenance implementation. Declarations must come from
        # original allocation evidence, never inferred from names or invented Compose labels.
        declarations = proof["volume_declarations"]
        require(fingerprint(declarations).encode() in b"\n".join(
            self.retained_evidence(entry) for entry in record["basis"]["evidence"])
            or not declarations, "standalone volume declaration allocation proof absent")
        return {"services": {item["service"]: {"volumes": declarations}}, "volumes": {}}

    def host_consumers(self, candidate):
        observation = command(["lsof", "-nP", "-Fpcuaftn"], self.safe)
        require(not observation.stderr.strip(), "host consumer visibility incomplete")
        for process in open_processes(observation.stdout).values():
            matching = [entry for entry in process["files"] if entry.get("n", "").startswith("/") and
                        inside(Path(entry["n"].removesuffix(" (deleted)")), candidate)]
            if not matching:
                continue
            require(all(entry.get("a") == "r" and entry.get("t") in ("DIR", "REG", "LINK") and
                        re.fullmatch(r"\*?\d+", entry["fd"]) for entry in matching),
                    "active cwd/writer/unknown descriptor; retain resource")
            links = self.excluded_link_records(matching, candidate)
            self.readonly_file_sharing(process, matching, candidate)
            require(self.excluded_link_records(matching, candidate) == links,
                    "excluded link/parent metadata changed during reader qualification")
            if links:
                self.attempt.setdefault("readonly_file_sharing", {})[str(process["pid"])]["excluded_links"] = links

    def excluded_link_records(self, matching, candidate):
        """Bind explicitly disposable LINKs and ordinary parents without following targets."""
        links = [entry for entry in matching if entry.get("t") == "LINK"]
        if not links:
            return {}
        worktree = self.plan["worktree"]
        require(absolute(worktree["identity"]) == candidate, "LINK candidate differs from approved worktree")
        exclusions = exclusion_paths(worktree["exclusions"])
        require(all(path.parts and str(path) != "." for path in exclusions), "invalid LINK exclusion")
        records = {}
        for entry in links:
            path = Path(entry["n"])
            relative = path.relative_to(candidate)
            require(str(path) == entry["n"] and ".." not in relative.parts and
                    any(inside(relative, excluded) for excluded in exclusions),
                    "LINK is not an explicitly excluded disposable path")
            require(not any(inside(Path(protected["path"]), path) for protected in self.plan["protected_files"]),
                    "LINK overlaps protected evidence")
            # Inspect parents from the canonical candidate outward, never through a link.
            parents = list(path.parents)
            parents = list(reversed(parents[:parents.index(candidate) + 1]))
            for current in [*parents, path]:
                if str(current) in records:
                    continue
                before = current.lstat()
                record = file_record(current, allow_symlink=current == path)
                require(record["kind"] == ("symlink" if current == path else "directory") and
                        not any(" deny " in (" " + acl + " ") for acl in record["acl"]) and
                        (current == path or record["mode"] & 0o300 == 0o300),
                        "excluded LINK/parent kind or protection unresolved")
                after = current.lstat()
                identity = lambda value: (value.st_dev, value.st_ino, value.st_mode, value.st_uid,
                    value.st_gid, value.st_size, value.st_mtime_ns, value.st_ctime_ns)
                require(identity(before) == identity(after), "excluded LINK/parent changed while inspected")
                records[str(current)] = {"identity": identity(after), "record": record}
        return records

    def readonly_file_sharing(self, process, matching, candidate):
        """Qualify signed local VM or backend readers, never arbitrary read-only consumers."""
        require(sys.platform == "darwin" and process.get("uid") == str(os.getuid()), "unknown read-only consumer")
        vm = "/System/Library/Frameworks/Virtualization.framework/Versions/A/XPCServices/com.apple.Virtualization.VirtualMachine.xpc/Contents/MacOS/com.apple.Virtualization.VirtualMachine"
        backend = "/Applications/Docker.app/Contents/MacOS/com.docker.backend"
        identity_args = ["ps", "-p", str(process["pid"]), "-o", "uid=,lstart=,comm="]
        identity = text(identity_args, self.safe)
        parts = identity.split(maxsplit=6)
        require(len(parts) == 7 and parts[0] == process["uid"] and parts[6] in (vm, backend),
                "read-only consumer is not qualified Docker VM/backend executable")
        exe = parts[6]
        requirement = 'anchor apple' if exe == vm else 'anchor apple generic and certificate leaf[subject.OU] = "9BNSXJN65R"'
        command(["codesign", "--verify", "--strict", "-R", "=" + requirement, exe], self.safe)
        executable_hash = digest(Path(exe).read_bytes())
        files = {entry.get("n"): entry for entry in process["files"]}
        disk = str(Path.home() / "Library/Containers/com.docker.docker/Data/vms/0/data/Docker.raw")
        kernel = "/Applications/Docker.app/Contents/Resources/linuxkit/kernel"
        endpoint = str(Path.home() / ".docker/run/docker.sock")
        backend_socket = str(Path.home() / "Library/Containers/com.docker.docker/Data/backend.sock")
        if exe == vm:
            require(files.get(disk, {}).get("a") == "u" and files.get(kernel, {}).get("a") == "r", "VM/Docker backing identity unavailable")
            associated = {name: files[name] for name in (disk, kernel)}
        else:
            require(all(files.get(name, {}).get("t") == "unix" and files[name].get("a") == "u"
                        for name in (endpoint, backend_socket)),
                    "backend/local Docker socket association unavailable")
            associated = {name: files[name] for name in (endpoint, backend_socket)}
        context = json_command(["docker", "context", "inspect"], self.safe)
        engine = json_command(["docker", "info", "--format", "{{json .}}"], self.safe)
        require(len(context) == 1 and context[0]["Endpoints"]["docker"]["Host"] == "unix://" + endpoint and
                not os.environ.get("DOCKER_HOST") and engine["OperatingSystem"] == "Docker Desktop" and engine["ID"],
                "VM/backend local Docker context binding unavailable")
        fresh = command(["lsof", "-nP", "-p", str(process["pid"]), "-Fpcuaftn"], self.safe)
        require(not fresh.stderr.strip(), "file-sharing descriptor visibility incomplete")
        latest = open_processes(fresh.stdout).get(process["pid"], {})
        latest_matching = [entry for entry in latest.get("files", []) if entry.get("n", "").startswith("/") and
                           inside(Path(entry["n"].removesuffix(" (deleted)")), candidate)]
        require(latest.get("uid") == process["uid"] and latest_matching == matching and
                all(entry in latest["files"] for entry in associated.values()) and
                text(identity_args, self.safe) == identity and digest(Path(exe).read_bytes()) == executable_hash,
                "file-sharing process birth/executable/descriptor identity changed")
        self.attempt.setdefault("readonly_file_sharing", {})[str(process["pid"])] = {
            "process_identity": identity, "executable_sha256": executable_hash,
            "association": associated, "engine_id": engine["ID"], "context_endpoint": "unix://" + endpoint,
            "candidate_readonly_descriptors": matching}

    def consumers(self, candidate):
        self.host_consumers(candidate)
        for item in docker_inventory(self.safe):
            for mount in item["Mounts"]:
                if mount["Type"] != "bind":
                    continue
                source = Path(mount["Source"]).resolve()
                require(not inside(source, candidate), "explicit Docker candidate bind dependency; retain checkout")
                if not inside(candidate, source):
                    continue
                if not item["State"]["Running"]:
                    continue  # Full stopped-container observation: no active process/cwd.
                exposed = str(Path(mount["Destination"]) / candidate.relative_to(source))
                # Inspect every live process cwd/descriptor through the existing container
                # identity; no teardown, shell mutation or alternate-owner retry.
                probe = 'for p in /proc/[0-9]*; do for f in "$p/cwd" "$p"/fd/*; do [ -L "$f" ] || continue; v=$(readlink "$f") || { [ ! -e "$p" ] || echo INSPECTION_UNAVAILABLE; continue; }; printf "%s\\n" "$v"; done; done'
                observed = command(["docker", "exec", item["Id"], "sh", "-c", probe], self.safe)
                paths = observed.stdout.decode().splitlines()
                require(not observed.stderr.strip() and "INSPECTION_UNAVAILABLE" not in paths, "parent mount consumer visibility incomplete")
                require(not any(path.startswith("/") and inside(Path(path), Path(exposed)) for path in paths), "actual candidate use through shared parent mount")
                self.attempt.setdefault("unrelated_parent_mounts", []).append({"container": item["Id"],
                    "source": str(source), "candidate_target": exposed,
                    "configuration_sha256": container_identity(item), "process_paths_sha256": digest(observed.stdout)})

    def service_visibility(self, item, current):
        """Observe complete worker descriptors and connections without changing execution identity"""
        require(not current["State"].get("Restarting") and not current["State"].get("Paused"),
                "service restarting/paused; descriptor visibility held")
        if not current["State"]["Running"]:
            # A stopped container has no workers, but immutable identity and all mount/consumer
            # protections still apply. Never convert an inspection error into stopped state.
            return
        host = current["HostConfig"]
        network = host.get("NetworkMode", "")
        require(host.get("PidMode", "") in ("", "private") and isinstance(network, str) and
                network != "host" and not network.startswith("container:"),
                "shared/host worker namespace outside service visibility capability")
        def engine_workers():
            raw = command(["docker", "top", item["identity"], "-eo", "pid,ppid"], self.safe).stdout
            lines = [line.split() for line in raw.decode().splitlines()]
            require(lines and lines[0] == ["PID", "PPID"] and 1 <= len(lines) - 1 <= 256 and
                    all(len(line) == 2 and all(value.isdecimal() for value in line) for line in lines[1:]) and
                    len({line[0] for line in lines[1:]}) == len(lines) - 1,
                    "complete engine worker census unavailable")
            return sorted(tuple(line) for line in lines[1:])
        census = engine_workers()
        # Python is a capability, not an installation or version prerequisite. Do not retry as
        # another user or add ptrace/DAC capabilities when procfs denies any worker descriptor.
        probe = r'''
import hashlib, json, os, pathlib, re
root = pathlib.Path('/proc')
me = os.getpid()
def workers():
    return sorted(int(p.name) for p in root.iterdir() if p.name.isdigit() and int(p.name) != me)
def observe(pid):
    p = root / str(pid)
    birth = (p / 'stat').read_text().rsplit(')', 1)[1].split()[19]
    links = {name: os.readlink(p / name) for name in ('cwd', 'exe', 'root')}
    fd = p / 'fd'
    names = sorted(os.listdir(fd))
    if len(names) > 4096:
        raise RuntimeError('descriptor budget exceeded')
    descriptors = []
    for name in names:
        target = os.readlink(fd / name)
        info = (p / 'fdinfo' / name).read_text()
        descriptors.append([name, target, info])
    if sorted(os.listdir(fd)) != names:
        raise RuntimeError('descriptor set changed')
    return [pid, birth, links, descriptors]
before = workers()
if not 1 <= len(before) <= 256:
    raise RuntimeError('worker census unavailable/oversized')
first = [observe(pid) for pid in before]
# All network namespaces visible from these workers must be the service namespace.
network = os.readlink(root / str(me) / 'ns/net')
for pid in before:
    if os.readlink(root / str(pid) / 'ns/net') != network:
        raise RuntimeError('worker network namespace unresolved')
# A connected peer needs positive ownership, not inference from endpoint names. This
# finite capability accepts no connected sockets; internal/unknown peers HOLD alike.
tables = {}
for name in ('tcp', 'tcp6', 'udp', 'udp6'):
    tables[name] = (root / 'net' / name).read_text()
    rows = tables[name].splitlines()
    if not rows or 'rem_address' not in rows[0] and 'remote_address' not in rows[0]:
        raise RuntimeError('socket table unavailable')
    for line in rows[1:]:
        fields = line.split()
        if len(fields) < 10 or fields[3] != '0A' and fields[2].split(':')[1] != '0000':
            raise RuntimeError('connected network peer unresolved')
tables['unix'] = (root / 'net/unix').read_text()
rows = tables['unix'].splitlines()
if not rows or 'Inode' not in rows[0]:
    raise RuntimeError('Unix socket table unavailable')
owned_sockets = {re.fullmatch(r'socket:\[(\d+)\]', d[1]).group(1)
                 for worker in first for d in worker[3] if re.fullmatch(r'socket:\[(\d+)\]', d[1])}
table = set()
for line in rows[1:]:
    fields = line.split()
    if len(fields) < 7:
        raise RuntimeError('Unix socket table incomplete')
    table.add(fields[6])
    if fields[5] != '01':
        raise RuntimeError('connected Unix peer ownership unresolved')
# TCP sockets do not appear in the Unix table. Require every socket descriptor to
# occur in a successfully read namespace socket table, including listening sockets.
for name in ('tcp', 'tcp6', 'udp', 'udp6', 'raw', 'raw6'):
    if name not in tables:
        tables[name] = (root / 'net' / name).read_text()
    rows = tables[name].splitlines()
    if not rows or len(rows) > 8192 or name.startswith('raw') and len(rows) > 1:
        raise RuntimeError('socket table incomplete/unsupported/oversized')
    for line in rows[1:]:
        fields = line.split()
        if len(fields) < 10:
            raise RuntimeError('socket table malformed')
        table.add(fields[9])
if not owned_sockets <= table:
    raise RuntimeError('unsupported socket descriptor')
second = [observe(pid) for pid in before]
if workers() != before or first != second or any(
        (root / 'net' / name).read_text() != value for name, value in tables.items()):
    raise RuntimeError('worker/descriptor/peer observations changed')
print(json.dumps({'complete': True, 'workers': len(before), 'sha256': hashlib.sha256(
    json.dumps([first, tables], sort_keys=True).encode()).hexdigest(), 'connected_peers': 0}))
'''
        try:
            observed = command(["docker", "exec", item["identity"], "python3", "-B", "-c", probe], self.safe)
        except Hold as error:
            raise Hold("service worker descriptor/peer inspection unavailable; " + str(error)) from None
        require(not observed.stderr.strip(), "service descriptor/peer visibility incomplete")
        visibility = json.loads(observed.stdout)
        require(visibility.get("complete") is True and visibility.get("workers") == len(census) and
                visibility.get("connected_peers") == 0 and
                re.fullmatch(r"[a-f0-9]{64}", visibility.get("sha256", "")),
                "service descriptor/peer visibility unavailable or unsupported")
        require(engine_workers() == census, "engine worker census changed during descriptor inspection")
        processes = command(["docker", "top", item["identity"], "-eo", "pid,ppid,args"], self.safe).stdout
        require(digest(processes) == item["processes_sha256"], "service process evidence changed before effect")
        visibility["engine_workers_sha256"] = fingerprint(census)
        observations = self.attempt.setdefault("service_visibility", [])
        previous = [value for value in observations if value["container"] == item["identity"]]
        require(not previous or previous[-1] == {"container": item["identity"], **visibility},
                "service worker/descriptor/peer observations changed before effect")
        observations.append({"container": item["identity"], **visibility})

    def container(self, item, result):
        # Reconcile absence before reading runtime inputs that checkout retirement
        # may have deleted. A present resource still needs every runtime check.
        inventory = docker_inventory(self.safe)
        current = next((value for value in inventory if value["Id"] == item["identity"]), None)
        if current is None:
            require(not any(value["Name"] == item["name"] for value in inventory), "container recreated under recorded name; new identity held")
            result.update(status="absent", reason="exact ID and recorded name absent on successful full inventory")
            return
        self.creation_evidence(item)
        binding = item.get("runtime", self.plan["runtime"])
        if binding.get("kind", "compose") == "standalone":
            model = self.standalone_container(item, current)
        else:
            binding, args, model = self.runtime(item)
            require(container_identity(current) == item["inspection_sha256"], "container identity/configuration/mounts changed")
            labels = current["Config"]["Labels"]
            service = item["service"]
            require(service in model["services"], "container service absent from qualified configuration")
            require(labels.get("com.docker.compose.project") == binding["project"] and
                    labels.get("com.docker.compose.service") == service and
                    labels.get("com.docker.compose.project.working_dir") == binding["working_directory"] and
                    labels.get("com.docker.compose.project.config_files") == ",".join(entry["path"] for entry in binding["config_files"]), "Compose/resource binding mismatch")
            expected = text([*args, "config", "--hash", service], binding["working_directory"]).split()
            require(len(expected) == 2, "current Compose service hash unavailable")
            if labels.get("com.docker.compose.oneoff") == "True":
                self.compose_oneoff(item, current, binding)
            else:
                require(labels.get("com.docker.compose.config-hash") == expected[1], "current Compose service hash contradicts container")
        if current["State"]["Running"]:
            processes = command(["docker", "top", item["identity"], "-eo", "pid,ppid,args"], self.safe).stdout
            require(digest(processes) == item["processes_sha256"], "container processes changed/unknown consumers")
        self.service_visibility(item, current)
        require(item["data"] == "disposable", "durable or unresolved data disposition")
        self.capture_mounts(item, current, model)
        candidate = absolute(self.plan["worktree"]["identity"])
        for mount in current["Mounts"]:
            if mount["Type"] == "bind" and mount.get("RW"):
                source = absolute(mount["Source"])
                require(inside(source, candidate) and item["bind_data"].get(str(source)) == "preserve",
                        "writable bind data disposition unresolved; retain container")
        # Host checkout/session dependencies hold the container as well as the checkout.
        self.host_consumers(candidate)
        # Re-read exact immutable identity immediately before normal stop/removal.
        latest = json_command(["docker", "inspect", item["identity"]], self.safe)[0]
        require(container_identity(latest) == item["inspection_sha256"], "container changed before effect")
        if binding.get("kind", "compose") == "standalone":
            self.standalone_container(item, latest)
        self.service_visibility(item, latest)
        if not self.apply:
            result.update(status="qualified", reason="preview only")
            return
        result.update(status="pending", reason="qualified normal stop/remove; outcome must be reobserved")
        self.save()
        if latest["State"]["Running"]:
            command(["docker", "stop", "--time", "10", item["identity"]], self.safe, timeout=20)
        # Stop is an effect too; an interruption resumes from current stopped state.
        self.subject_checks()
        latest = json_command(["docker", "inspect", item["identity"]], self.safe)[0]
        if binding.get("kind", "compose") == "standalone":
            self.standalone_container(item, latest)
        else:
            self.runtime(item)
        require(container_identity(latest) == item["inspection_sha256"] and not latest["State"]["Running"], "container changed/restarted after stop")
        self.service_visibility(item, latest)
        self.host_consumers(candidate)
        command(["docker", "rm", item["identity"]], self.safe)
        require(not any(value["Id"] == item["identity"] or value["Name"] == item["name"] for value in docker_inventory(self.safe)), "container removal postcondition failed/recreated")
        result.update(status="removed", reason="exact container ID/name absence verified; mount provenance retained for separate volume qualification")

    def compose_oneoff(self, item, current, binding):
        """Bind CLI-overridden one-offs to original intent, returned ID and full creator configuration."""
        require(isinstance(item.get("oneoff_proof"), dict), "one-off original command/configuration capability missing; preserve creator")
        proof = json.loads(self.retained_evidence(item["oneoff_proof"]))
        intent = json.loads(self.retained_evidence(proof["original_intent"]))
        returned = json.loads(self.retained_evidence(proof["original_return"])) if proof.get("original_return") else None
        dispatch = json.loads(self.retained_evidence(proof["original_dispatch"])) if proof.get("original_dispatch") else None
        observation = json.loads(self.retained_evidence(proof["original_observation"]))
        originals = [v for v in observation["containers"] if v["Id"] == item["identity"]]
        require(proof["version"] == 1 and proof["task"] == self.subject and proof["origin"] == self.plan["origin"] and
                proof["container"] == item["identity"] and proof["operation"] == intent["id"] == observation["operation"] and
                proof["runtime_sha256"] == binding["resolved_sha256"], "one-off original subject/operation/runtime mismatch")
        require(intent["subject"]["task"] == self.subject and intent["subject"]["origin"] == self.plan["origin"] and intent["kind"] == "run" and
                item["service"] in intent["argv"] and item["identity"] not in intent["before"]["containers"],
                "one-off exact original allocating command unavailable")
        if returned is not None:
            require(returned["operation"] == intent["id"] and returned["exit"] == 0 and
                    item["identity"] in re.findall(r"\b[a-f0-9]{64}\b", returned["stdout"]),
                    "one-off original returned ID missing/contradictory")
        else:
            require(proof["mode"] == "dispatch-reconciliation" and dispatch is not None and
                    dispatch["operation"] == intent["id"] and dispatch["intent"] == proof["original_intent"] and
                    dispatch["subject"]["task"] == self.subject and dispatch["subject"]["origin"] == self.plan["origin"] and
                    fingerprint(dispatch["runtime"]) == fingerprint(binding) and item["service"] in dispatch["argv"] and
                    datetime.fromisoformat(re.sub(r"(\.\d{6})\d+(?=\+)", r"\1", current["Created"].replace("Z", "+00:00"))) >= datetime.fromisoformat(dispatch["at"]),
                    "one-off pending command lacks original durable dispatch/configuration proof; hold, never repeat allocation")
        require(len(originals) == 1 and container_identity(originals[0]) == item["inspection_sha256"] == container_identity(current) and
                originals[0]["Config"]["Labels"].get("io.fight.lifecycle.operation") == intent["id"] and
                originals[0]["Config"]["Labels"].get("com.docker.compose.config-hash") == current["Config"]["Labels"].get("com.docker.compose.config-hash"),
                "one-off original creator/configuration hash changed")
        policy = intent["policy"]["services"][item["service"]]
        require(policy["data"] == item["data"] == "disposable" and policy["authority"] == item["authority"],
                "one-off original data/discard authority mismatch")

    def creation_evidence(self, item):
        creation = item["creation"]
        if creation["kind"] == "legacy-created":
            record = self.standalone()
            require(record["basis"]["kind"] == "legacy-adoption" and
                    (item["identity"] in record["containers"] or item["identity"] in record["networks"]),
                    "legacy resource missing original allocation proof; hold without retrospective intent")
            return creation
        require(creation["kind"] == "created", "merely used/shared resource is not task-created")
        intent = absolute(creation["intent"])
        require(not inside(intent, absolute(self.plan["worktree"]["identity"])) and
                intent.stat().st_uid == os.getuid() and stat.S_IMODE(intent.stat().st_mode) == 0o600,
                "creation intent must be owned/private and outside removal candidates")
        require(digest(intent.read_bytes()) == creation["intent_sha256"], "creation intent missing/changed; recover original evidence")
        return creation

    def volume_size(self, name, result):
        # Docker reports approximate display units, not exact reclaimed physical bytes.
        usage = json_command(["docker", "system", "df", "--verbose", "--format", "{{json .Volumes}}"], self.safe)
        matches = [item for item in usage if item["Name"] == name]
        require(len(matches) == 1, "volume size observation unavailable; retain and recover inspection")
        result["storage"] = {"docker_size": matches[0]["Size"], "links": matches[0]["Links"],
                             "observed_at": datetime.now(timezone.utc).isoformat(),
                             "physical_reclaimed_bytes": None}

    def image_declarations(self, item, current):
        """Resolve original declarations; only an explicit immutable proof can recover config-ID not-found."""
        lookup = command(["docker", "image", "inspect", current["Image"]], self.safe, codes=(0, 1))
        if lookup.returncode == 0:
            images = json.loads(lookup.stdout)
            require(len(images) == 1 and images[0]["Id"] == current["Image"], "creator config lookup is an unresolved alias")
            return images[0]["Config"].get("Volumes") or {}
        require(lookup.stdout.strip() == b"[]" and lookup.stderr.decode().strip() ==
                "Error response from daemon: No such image: " + current["Image"],
                "creator image declaration inspection unavailable/denied")
        require(isinstance(item.get("image_declaration"), dict),
                "config-ID declaration unavailable; original immutable root/platform proof required")
        proof = json.loads(self.retained_evidence(item["image_declaration"]))
        require(proof["version"] == 1 and proof["task"] == self.subject and proof["origin"] == self.plan["origin"] and
                proof["container"] == current["Id"] and proof["config_id"] == current["Image"],
                "original declaration subject/config mismatch")
        originals = json.loads(self.retained_evidence(proof["original_containers"]))
        originals = originals if isinstance(originals, list) else [originals]
        creators = [value for value in originals if value["Id"] == current["Id"]]
        require(len(creators) == 1 and container_identity(creators[0]) == container_identity(current) == item["inspection_sha256"],
                "original declaration creator/configuration/mount mismatch")
        descriptor = current["ImageManifestDescriptor"]
        require(descriptor == creators[0]["ImageManifestDescriptor"] and descriptor["platform"] == proof["platform"],
                "original declaration descriptor/platform mismatch")
        root = inspect_image(self.safe, proof["root"])
        require(root is not None, "original immutable declaration root missing")
        old_plan = json.loads(self.retained_evidence(proof["original_root_plan"]))["cleanup"]["plan"]
        require(old_plan["task"] == self.subject and old_plan["origin"] == self.plan["origin"] and
                any(value["identity"] == root["Id"] and value["inspection_sha256"] == fingerprint(root)
                    for value in old_plan["images"]), "original root inspection binding missing/changed")
        platform = proof["platform"]
        selector = "/".join(platform[key] for key in ("os", "architecture", "variant") if platform.get(key))
        observed = json_command(["docker", "image", "inspect", "--platform", selector, root["Id"]], self.safe)
        original_images = json.loads(self.retained_evidence(proof["original_platform_images"]))
        original_images = original_images if isinstance(original_images, list) else [original_images]
        matches = [value for value in original_images if value["Id"] == descriptor["digest"]]
        require(len(observed) == len(matches) == 1 and observed[0]["Descriptor"] == descriptor and
                observed[0]["Id"] == descriptor["digest"] and observed[0]["Os"] == platform["os"] and
                observed[0]["Architecture"] == platform["architecture"] and
                matches[0]["Descriptor"] == descriptor and
                all(observed[0][key] == matches[0][key] for key in ("Config", "RootFS", "Descriptor")),
                "original immutable platform/config/RootFS/declarations differ")
        graph = image_root_graph(self.safe, root)
        require(any(value["descriptor"] == descriptor and value["config_sha256"] == fingerprint(matches[0]["Config"])
                    and value["rootfs_sha256"] == fingerprint(matches[0]["RootFS"]) for value in graph["images"]),
                "creator descriptor not in immutable root graph")
        self.attempt.setdefault("image_declarations", {})[current["Id"]] = {
            "proof": item["image_declaration"], "root": root["Id"], "config_id": current["Image"],
            "descriptor": descriptor, "graph_sha256": fingerprint(graph),
            "declarations_sha256": fingerprint(matches[0]["Config"].get("Volumes") or {})}
        return matches[0]["Config"].get("Volumes") or {}

    def capture_mounts(self, item, current, model):
        """Persist exact creating-container observations before any stop/removal effect."""
        binding = item.get("runtime", self.plan["runtime"])
        mounts = [{key: mount.get(key) for key in ("Type", "Name", "Source", "Destination", "RW")}
                  for mount in current["Mounts"]]
        volumes = {entry["identity"]: entry for entry in self.plan["volumes"]}
        declared = self.image_declarations(item, current)
        for mount in mounts:
            if mount["Type"] != "volume":
                continue
            require(mount["Name"] in volumes, "mounted volume missing from exact inventory; retain creating container")
            volume = volumes[mount["Name"]]
            require(volume["disposition"] in ("remove", "retain", "hold") and volume["reason"],
                    "volume data/retention disposition unresolved")
            if volume["disposition"] != "remove":
                continue  # Removing a disposable container never implies discarding retained data.
            creation = self.creation_evidence(volume)
            require(volume["data"] == "disposable" and volume["authority"], "volume data/authority unresolved")
            if creation["container"] != current["Id"]:
                continue  # This task container merely uses storage; only its creator can supply creation proof.
            require(creation["destination"] == mount["Destination"],
                    "volume creation destination not bound to this workload")
            observed = json_command(["docker", "volume", "inspect", mount["Name"]], self.safe)[0]
            require(volume_identity(observed) == volume["inspection_sha256"], "volume identity changed before provenance capture")
            configured = [value for value in model["services"][item["service"]].get("volumes", [])
                          if value["target"] == mount["Destination"]]
            if binding.get("kind", "compose") == "standalone":
                require(configured and configured[0]["type"] == "volume" and
                        configured[0].get("allocated_name") == mount["Name"] and
                        configured[0].get("created_absent") is True,
                        "standalone named/anonymous volume original allocation proof unavailable")
            elif configured and configured[0].get("source"):
                config = model["volumes"][configured[0]["source"]]
                require(not config.get("external", False) and config["name"] == mount["Name"],
                        "external/shared configured volume; no discard through task association")
            else:
                require(mount["Destination"] in declared or
                        (configured and configured[0]["type"] == "volume"),
                        "anonymous mount declaration unresolved")
        observation = {"container": current["Id"], "container_identity": container_identity(current),
                       "image": current["Image"], "mounts": mounts,
                       "runtime_sha256": binding["resolved_sha256"]}
        provenance = self.document["cleanup"].setdefault("mount_provenance", {})
        require(current["Id"] not in provenance or provenance[current["Id"]] == observation,
                "creating workload provenance changed; retain original evidence")
        provenance[current["Id"]] = observation
        self.save()

    def observe_retained(self, kind, item, result):
        """Retention is an observed policy disposition, not a silent missing-resource exemption."""
        if kind == "container":
            current = json_command(["docker", "inspect", item["identity"]], self.safe)[0]
            require(container_identity(current) == item["inspection_sha256"], "retained container missing/recreated/changed")
        elif kind == "network":
            current = json_command(["docker", "network", "inspect", item["identity"]], self.safe)[0]
            require(current["Id"] == item["identity"] and current["Name"] == item["name"] and
                    current["Created"] == item["created"], "retained network missing/recreated/changed")
        elif kind == "volume":
            current = json_command(["docker", "volume", "inspect", item["identity"]], self.safe)[0]
            require(volume_identity(current) == item["inspection_sha256"], "retained volume missing/recreated")
            self.volume_size(item["identity"], result)
        else:
            current = inspect_image(self.safe, item["identity"])
            require(current is not None, "retained image absent; ownership/identity needs reconciliation")
            result["storage"] = {"size_bytes": current["Size"], "physical_reclaimed_bytes": None}
        result["creation"] = item["creation"]

    def volume(self, item, result):
        self.creation_evidence(item)
        require(item["data"] == "disposable" and item["authority"], "durable/unknown storage or discard authority unresolved")
        provenance = self.document["cleanup"].get("mount_provenance", {}).get(item["creation"]["container"])
        require(provenance, "creating-container mount provenance unavailable; recover original evidence")
        creator = next((entry for entry in self.plan["containers"] if entry["identity"] == provenance["container"]), None)
        require(creator and creator["inspection_sha256"] == provenance["container_identity"] and
                provenance["runtime_sha256"] == creator.get("runtime", self.plan["runtime"])["resolved_sha256"] and
                any(mount["Type"] == "volume" and mount["Name"] == item["identity"] and
                    mount["Destination"] == item["creation"]["destination"] for mount in provenance["mounts"]),
                "storage provenance does not match the original creating workload")
        inventory = volume_inventory(self.safe)
        current = next((value for value in inventory if value["Name"] == item["identity"]), None)
        if current is None:
            result.update(status="absent", reason="exact volume name absent on successful inventory; original creation evidence retained")
            return
        require(volume_identity(current) == item["inspection_sha256"], "volume recreated/identity changed; retain new resource")
        self.volume_size(item["identity"], result)
        require(not any(mount["Type"] == "volume" and mount["Name"] == item["identity"]
                        for container in docker_inventory(self.safe) for mount in container["Mounts"]),
                "volume has current consumer, including stopped/shared containers; retain unchanged")
        # Repeat exact identity and all-consumer observations immediately before the effect.
        latest = json_command(["docker", "volume", "inspect", item["identity"]], self.safe)[0]
        require(volume_identity(latest) == item["inspection_sha256"], "volume changed before removal")
        if not self.apply:
            result.update(status="qualified", reason="preview only")
            return
        result.update(status="pending", reason="qualified task-created disposable volume; reobserve absence")
        self.save()
        self.subject_checks()
        require(not any(mount["Type"] == "volume" and mount["Name"] == item["identity"]
                        for container in docker_inventory(self.safe) for mount in container["Mounts"]),
                "new/stopped volume consumer before effect; retain unchanged")
        latest = json_command(["docker", "volume", "inspect", item["identity"]], self.safe)[0]
        require(volume_identity(latest) == item["inspection_sha256"], "volume changed immediately before effect")
        command(["docker", "volume", "rm", item["identity"]], self.safe)
        require(not any(value["Name"] == item["identity"] for value in volume_inventory(self.safe)),
                "volume removal postcondition failed/recreated")
        result.update(status="removed", reason="exact disposable volume absence verified; physical disk reclaim not inferred")

    def owned_root_proof(self, item):
        """Require original source/allocation/reference authority, not a unique tag or no consumers."""
        retirement = item["retirement"]
        require(retirement["kind"] == "owned-root-references", "unknown image retirement capability")
        proof = json.loads(self.retained_evidence(retirement["proof"]))
        require(proof["version"] == 1 and proof["task"] == self.subject and proof["origin"] == self.plan["origin"] and
                proof["root"] == item["identity"] and proof["data"] == item["data"] == "disposable" and
                proof["authority"] == item["authority"] and proof["authority"], "image root/source/discard authority mismatch")
        original = json.loads(self.retained_evidence(proof["original_image"]))
        require(original["Id"] == item["identity"] and fingerprint(original) == item["inspection_sha256"] and
                proof["graph"]["root"] == original["Descriptor"], "original root inspection/graph mismatch")
        references = sorted((original["RepoTags"] or []) + (original["RepoDigests"] or []))
        require(references and references == proof["references"] and len(references) <= 32 and
                len(references) == len(set(references)) and
                all(re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.:/@-]{0,511}", value) for value in references),
                "exact original owned reference set missing/ambiguous")
        basis = proof["basis"]
        require(re.fullmatch(r"[a-f0-9]{40}", basis["source_revision"]) and 1 <= len(basis["evidence"]) <= 16,
                "original image source/allocation evidence missing")
        evidence = b"\n".join(self.retained_evidence(entry) for entry in basis["evidence"])
        require(basis["source_revision"].encode() in evidence and item["identity"].encode() in evidence,
                "original source/exported root not corroborated")
        for reference in references:
            if "@" in reference:
                repository, identifier = reference.rsplit("@", 1)
                require(identifier == item["identity"] and any(value.startswith(repository + ":") for value in references),
                        "digest reference has no original owned naming relationship")
            else:
                require(reference.encode() in evidence, "original reference creation/naming proof missing")
        self.creation_evidence(item)
        return proof, original

    def root_reference_state(self, identity, rows):
        """Do not conflate config aliases, rebound references or denied inspection with absence."""
        current = inspect_image(self.safe, identity)
        matches = [row for row in rows if row["Id"] == identity]
        require(len(matches) == (1 if current else 0), "root exact inspection/complete graph census contradict")
        if current:
            summary = matches[0]
            tags, digests = set(current["RepoTags"] or []), set(current["RepoDigests"] or [])
            require(tags == set(summary["RepoTags"] or []) and
                    set(summary["RepoDigests"] or []) <= digests,
                    "root reference census changed")
            require(len(tags) + len(digests) <= 32 and
                    all(re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.:/@-]{0,511}", value) for value in tags | digests),
                    "root reference census ambiguous/oversized")
            for reference in digests:
                repository, separator, identifier = reference.rpartition("@")
                require(separator and identifier == identity and any(tag.startswith(repository + ":") for tag in tags),
                        "repository digest lacks exact root/owned naming relationship")
            # The summary may omit RepoDigests. Never synthesize them from tags: exact
            # CLI AND same-engine API lookups must positively resolve every digest to
            # this entire root inspection. Raw OCI-load tags remain exact census names.
            selectors = [identity, *sorted(digests)]
            observations = local_image_metadata(self.safe, ["/images/json?all=1&manifests=1",
                *["/images/" + quote(value, safe="") + "/json" for value in selectors]])
            require(isinstance(observations[0], list) and len(observations[0]) == len(rows) and
                    {row["Id"]: row for row in observations[0]} == {row["Id"]: row for row in rows} and
                    all(value == current for value in observations[1:]),
                    "root reference/inspection census changed or contradicts exact API lookup")
            require(not any(row["Id"] != identity and (tags | digests) &
                    set((row["RepoTags"] or []) + (row["RepoDigests"] or [])) for row in rows),
                    "foreign/rebound reference in complete root census")
            for reference in sorted(digests):
                require(json_command(["docker", "image", "inspect", reference], self.safe) == [current],
                        "repository digest exact CLI/root relationship changed")
            require(inspect_image(self.safe, identity) == current, "root changed during reference corroboration")
        return current

    def protected_image_roots(self, rows, identity):
        """Record unrelated root references and child descriptors; shared layers stay engine-owned."""
        return {row["Id"]: {"descriptor": row.get("Descriptor"),
                    "references": sorted(set((row["RepoTags"] or []) + (row["RepoDigests"] or []))),
                    "children": [{key: child.get(key) for key in ("ID", "Descriptor", "Available", "Kind")}
                                 for child in row.get("Manifests", [])]}
                for row in rows if row["Id"] != identity}

    def require_no_root_consumers(self, proof, rows):
        manifests = {child["descriptor"]["digest"] for child in proof["graph"]["images"]}
        summary = next((row for row in rows if row["Id"] == proof["root"]), None)
        require(summary is not None and all(not child.get("ImageData", {}).get("Containers") for child in summary["Manifests"]),
                "root platform has current/stopped engine consumers")
        for container in docker_inventory(self.safe):
            require(re.fullmatch(r"sha256:[a-f0-9]{64}", container["Image"]), "container config identity unknown")
            descriptor = container.get("ImageManifestDescriptor")
            require(isinstance(descriptor, dict) and descriptor.get("mediaType") in
                    ("application/vnd.oci.image.manifest.v1+json", "application/vnd.docker.distribution.manifest.v2+json") and
                    re.fullmatch(r"sha256:[a-f0-9]{64}", descriptor.get("digest", "")),
                    "root/config/platform consumer visibility incomplete")
            require(container["Image"] != proof["root"] and descriptor["digest"] not in manifests,
                    "image root/platform has current/stopped consumer, including alias consumers")

    def verify_root_references(self, proof):
        rows = local_image_graphs(self.safe)
        require(not any(row["Id"] == proof["root"] or set(proof["references"]) &
                        set((row["RepoTags"] or []) + (row["RepoDigests"] or [])) for row in rows),
                "owned reference/root present or rebound after effect")
        for reference in proof["references"]:
            lookup = command(["docker", "image", "inspect", reference], self.safe, codes=(0, 1))
            require(lookup.returncode == 1 and lookup.stdout.strip() == b"[]" and
                    lookup.stderr.decode().strip() == "Error response from daemon: No such image: " + reference,
                    "exact original reference absence unavailable/denied/rebound")

    def image_root(self, item, result):
        proof, original = self.owned_root_proof(item)
        proof_hash = fingerprint(proof)
        previous = [entry for attempt in self.state for entry in attempt["results"]
                    if entry["kind"] == "image" and entry["identity"] == item["identity"] and
                    entry.get("root_observation", {}).get("proof_sha256") == proof_hash and entry.get("reference_intent")]
        rows = local_image_graphs(self.safe)
        current = self.root_reference_state(item["identity"], rows)
        protected = self.protected_image_roots(rows, item["identity"])
        if previous:
            # Only another proven completed root effect may explain a missing protected root on resume.
            completed = {entry["identity"] for attempt in self.state for entry in attempt["results"]
                         if entry["kind"] == "image" and entry.get("root_observation") and entry["status"] in ("removed", "absent")}
            expected = {key: value for key, value in previous[-1]["protected_image_roots"].items() if key not in completed}
            require(protected == expected, "protected root/reference/child graph changed during interrupted retirement")
        if current is None:
            require(previous, "absent root lacks same-receipt original observation/effect intent")
            self.verify_root_references(proof)
            result.update(status="absent", reason="original owned root/references absent; no removal replay",
                          root_observation=previous[-1]["root_observation"])
            return
        graph = image_root_graph(self.safe, current, rows)
        require(graph == proof["graph"], "immutable image-root/platform/config graph changed")
        references = sorted((current["RepoTags"] or []) + (current["RepoDigests"] or []))
        require(set(references) <= set(proof["references"]), "new/foreign image reference; preserve root")
        require(references == proof["references"] or previous, "changed reference set has no original persisted retirement intent")
        require({key: value for key, value in current.items() if key not in ("RepoTags", "RepoDigests")} ==
                {key: value for key, value in original.items() if key not in ("RepoTags", "RepoDigests")},
                "image root immutable inspection changed")
        self.require_no_root_consumers(proof, rows)
        result.update(root_observation={"identity": current["Id"], "proof_sha256": proof_hash,
                       "inspection_sha256": item["inspection_sha256"], "graph": graph, "references": proof["references"]},
                      protected_image_roots=protected, storage={"size_bytes": current["Size"], "physical_reclaimed_bytes": None})
        if not self.apply:
            result.update(status="qualified", reason="preview of exact owned root/reference retirement only")
            return
        # Normal removal can also retire its repository digest/root on the last tag.
        # Persist the entire owned before-set before the supported effect.
        for _ in range(len(proof["references"]) + 1):
            self.subject_checks()
            rows = local_image_graphs(self.safe)
            require(self.protected_image_roots(rows, item["identity"]) == protected, "foreign root/reference/child changed before effect")
            current = self.root_reference_state(item["identity"], rows)
            if current is None:
                break
            require(image_root_graph(self.safe, current, rows) == graph and
                    {key: value for key, value in current.items() if key not in ("RepoTags", "RepoDigests")} ==
                    {key: value for key, value in original.items() if key not in ("RepoTags", "RepoDigests")},
                    "root graph/immutable inspection changed immediately before effect")
            references = sorted((current["RepoTags"] or []) + (current["RepoDigests"] or []))
            require(set(references) <= set(proof["references"]), "foreign/rebound reference before effect")
            self.require_no_root_consumers(proof, rows)
            tags = [value for value in references if "@" not in value]
            # Exact-ID normal removal handles a single owned naming relationship, including
            # OCI-load names not normalized like CLI tags. Docker may refuse; never force it.
            target = tags[0] if len(tags) > 1 else item["identity"]
            result.update(status="pending", reason="normal exact owned reference/root removal; postconditions pending",
                          reference_intent={"target": target, "before_references": references})
            self.save()
            command(["docker", "image", "rm", target], self.safe, timeout=60)
            after_rows = local_image_graphs(self.safe)
            require(self.protected_image_roots(after_rows, item["identity"]) == protected,
                    "protected root/reference/child postcondition failed")
            after = self.root_reference_state(item["identity"], after_rows)
            after_refs = sorted((after["RepoTags"] or []) + (after["RepoDigests"] or [])) if after else []
            require(after is None or set(after_refs) < set(references), "image reference retirement made no bounded progress")
            result.setdefault("reference_effects", []).append({"target": target, "before": references, "after": after_refs})
            self.save()
        require(inspect_image(self.safe, item["identity"]) is None, "owned image root remains; retirement incomplete")
        self.verify_root_references(proof)
        result.update(status="removed", reason="owned root/references absent; foreign roots/shared children preserved; physical reclaim unknown")

    def image(self, item, result):
        if item.get("retirement") is not None:
            self.image_root(item, result)
            return
        self.creation_evidence(item)
        require(item["data"] == "disposable" and item["authority"], "image discard authority unresolved")
        current = inspect_image(self.safe, item["identity"])
        if current is None:
            # An arbitrary config digest may be unresolvable even while its manifest
            # exists. Resume absence only for this plan's previously resolved ID.
            require(any(entry["identity"] == item["identity"] and
                        entry.get("image_observation", {}).get("identity") == item["identity"] and
                        entry["image_observation"].get("representation") in ("manifest", "legacy-image-id") and
                        entry["image_observation"].get("inspection_sha256") == item["inspection_sha256"]
                        for attempt in self.state[:-1] for entry in attempt["results"] if entry["kind"] == "image"),
                    "absent/unresolvable image lacks prior exact image-ID proof; config/index ambiguity held")
            result.update(status="absent", reason="prior exact image ID absent by explicit inspect and all-image inventory")
            return
        require(fingerprint(current) == item["inspection_sha256"],
                "image identity/tags changed; shared or recreated subject held")
        representation = image_representation(current)
        require(not current["RepoTags"] and not current["RepoDigests"],
                "image has tag/digest references; exclusive reference creation/retirement unqualified; retained")
        result["image_observation"] = {"identity": current["Id"], "inspection_sha256": fingerprint(current),
                                       "representation": representation, "descriptor": current.get("Descriptor")}
        require_no_image_consumers(self.safe, current)
        result["storage"] = {"size_bytes": current["Size"], "physical_reclaimed_bytes": None}
        if not self.apply:
            result.update(status="qualified", reason="preview only")
            return
        result.update(status="pending", reason="exact created image, normal removal only; shared layers protected by Docker")
        self.save()
        self.subject_checks()
        require_no_image_consumers(self.safe, current)
        latest = inspect_image(self.safe, item["identity"])
        require(latest is not None and fingerprint(latest) == item["inspection_sha256"],
                "image changed immediately before effect")
        command(["docker", "image", "rm", item["identity"]], self.safe)
        require(inspect_image(self.safe, item["identity"]) is None, "image removal postcondition failed")
        result.update(status="removed", reason="exact image absent by explicit inspect and all-image inventory; no shared-layer reclaim claim")

    def cache(self, item, result):
        # BuildKit has no qualified exact deletion path here. Only positively reused
        # cache may be intentionally retained; eligible created cache still holds.
        require(item["disposition"] == "retain" and item["creation"]["kind"] == "reused",
                "exact created-cache retirement unavailable; retain pending owning builder recovery, never prune")
        builder = item["builder"]
        require(re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.-]{0,63}", builder), "invalid exact builder")
        builders = [json.loads(line) for line in text(["docker", "buildx", "ls", "--format", "{{json .}}"], self.safe).splitlines()]
        matches = [entry for entry in builders if entry["Name"] == builder]
        require(matches and all(sorted(identifier for node in entry["Nodes"] for identifier in node["IDs"]) == item["worker_ids"]
                                for entry in matches), "builder worker identity changed/unavailable")
        require(re.fullmatch(r"[a-z0-9]{12,64}", item["identity"]), "invalid exact cache ID")
        entries = [json.loads(line) for line in text(["docker", "buildx", "du", "--builder", builder,
                   "--filter", "id=" + item["identity"], "--format", "json"], self.safe).splitlines()]
        require(len(entries) == 1, "retained cache absent/ambiguous; inspect owning builder")
        current = entries[0]
        require(current["ID"] == item["identity"] and current["CreatedAt"] == item["created_at"] and
                current["Parents"] == item["parents"] and current["Type"] == item["type"] and not current["Mutable"],
                "retained cache identity changed or currently mutable")
        result["storage"] = {"docker_size": current["Size"], "shared": current["Shared"],
                             "reclaimable": current["Reclaimable"], "parents": current["Parents"],
                             "physical_reclaimed_bytes": None}
        result["creation"] = item["creation"]

    def network(self, item, result):
        ids = text(["docker", "network", "ls", "--no-trunc", "--format", "{{.ID}} {{.Name}}"], self.safe).splitlines()
        matches = [line.split() for line in ids]
        if not any(value[0] == item["identity"] for value in matches):
            require(not any(value[1] == item["name"] for value in matches), "network recreated under recorded name; held")
            result.update(status="absent", reason="exact network ID/name absent on successful inventory")
            return
        self.creation_evidence(item)
        standalone = item.get("runtime", self.plan["runtime"]).get("kind", "compose") == "standalone"
        network = json_command(["docker", "network", "inspect", item["identity"]], self.safe)[0]
        if standalone:
            proof = self.standalone(item)["networks"][item["identity"]]
            require(proof["name"] == network["Name"] == item["name"] and
                    proof["created"] == network["Created"] == item["created"] and
                    proof["inspection_sha256"] == network_identity(network), "standalone network allocation/configuration changed")
            require(not any(key.startswith("com.docker.compose.") for key in (network.get("Labels") or {})),
                    "Compose-labelled network requires Compose capability")
        else:
            binding, _, model = self.runtime(item)
            configuration = model["networks"][item["key"]]
            require(not configuration.get("external", False) and configuration["name"] == item["name"] == network["Name"], "external/shared/network identity mismatch")
            require(network["Labels"].get("com.docker.compose.project") == binding["project"] and
                    network["Labels"].get("com.docker.compose.network") == item["key"] and
                    network["Created"] == item["created"], "network ownership changed")
        require(not network["Containers"], "network has current endpoints (foreign/shared/remaining); retain unchanged")
        require(not any(name == item["name"] or value.get("NetworkID") == item["identity"]
                        for container in docker_inventory(self.safe)
                        for name, value in container["NetworkSettings"]["Networks"].items()),
                "network has current or stopped configured consumer; retain unchanged")
        if not self.apply:
            result.update(status="qualified", reason="preview only")
            return
        result.update(status="pending", reason="qualified empty owned network; reobserve outcome")
        self.save()
        self.subject_checks()
        latest = json_command(["docker", "network", "inspect", item["identity"]], self.safe)[0]
        if standalone:
            require(network_identity(latest) == self.standalone(item)["networks"][item["identity"]]["inspection_sha256"],
                    "standalone network changed immediately before effect")
        require(latest["Id"] == item["identity"] and latest["Created"] == item["created"] and not latest["Containers"],
                "network identity/endpoints changed before effect")
        require(not any(name == item["name"] or value.get("NetworkID") == item["identity"]
                        for container in docker_inventory(self.safe)
                        for name, value in container["NetworkSettings"]["Networks"].items()),
                "new/stopped configured network consumer before effect")
        command(["docker", "network", "rm", item["identity"]], self.safe)
        after = text(["docker", "network", "ls", "--no-trunc", "--format", "{{.ID}} {{.Name}}"], self.safe).splitlines()
        require(not any(item["identity"] in line or line.split()[-1] == item["name"] for line in after), "network postcondition failed/recreated")
        result.update(status="removed", reason="exact network ID/name absence verified")

    def local_tip(self):
        ref = self.plan["ref"]
        current = text(["git", "for-each-ref", "--format=%(objectname)", ref], self.safe)
        require(current == self.plan["oid"], "local ref missing/changed or unpublished commits")
        require(text(["git", "rev-list", "--count", f"{self.plan['oid']}..{ref}"], self.safe) == "0", "local-only commits")

    def verify_preservation(self, preservation):
        """Verify complete retained-inventory coverage, not just integrity of listed copies."""
        source, destination = Path(preservation["source"]), Path(preservation["destination"])
        files = preservation["files"]
        records = {str(Path(entry["old"]).relative_to(source)): entry["record"] for entry in files}
        require(len(records) == len(files) and
                fingerprint(records) == preservation.get("retained_inventory_sha256"),
                "external preservation completeness unverified; retain resource and recover inventory evidence")
        require(all(Path(entry["new"]) == destination / "files" / Path(entry["old"]).relative_to(source) and
                    file_record(Path(entry["new"])) == entry.get("destination_record", entry["record"])
                    for entry in files), "external preservation copy changed or unavailable")

    def preserve(self, candidate, item, include_source=False):
        excluded = exclusion_paths(item["exclusions"])
        before = artifact_inventory(candidate, excluded, item.get("temporary_artifacts", []))
        def excluded_path(name):
            return any(inside(Path(name), path) for path in excluded)
        # Git's untracked listing can omit nested Git-shaped evidence. Select from the
        # complete no-follow inventory instead, subtracting only exact tracked files and
        # approved exclusions. Preserve directory metadata, including tracked-file parents.
        tracked = set(command(["git", "ls-files", "--cached", "-z"], candidate).stdout.decode().split("\0"))
        retained = {name: record for name, record in before.items() if name != "." and not excluded_path(name) and
                    (include_source or record["kind"] == "directory" or name not in tracked)}
        destination = absolute(item["preserve_to"])
        require(inside(destination, self.store) and not inside(destination, candidate), "preservation destination must be canonical task-owned runs")
        destination.mkdir(mode=0o700, parents=True, exist_ok=True)
        require(destination.stat().st_uid == os.getuid() and stat.S_IMODE(destination.stat().st_mode) == 0o700, "preservation destination not private/owned")
        manifest = []
        for name in sorted(retained, key=lambda value: (len(Path(value).parts), value)):
            source, target = candidate / name, destination / "files" / name
            absolute(str(target))  # Refuse symlinked destination parents before any private copy.
            record = before[name]
            if target.exists():
                try:
                    copied = preserved_record(record, target)
                except Hold:
                    raise Hold("existing preservation copy mismatch; retain source and prior copy") from None
            else:
                target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
                if record["kind"] == "directory":
                    target.mkdir(mode=record["mode"], exist_ok=True)
                    if sys.platform == "darwin":
                        # copyfile.h: METADATA = ACL | STAT | XATTR; NOFOLLOW on both paths.
                        # No DATA or RECURSIVE: copying a directory must not admit its children.
                        try:
                            copyfile = ctypes.CDLL("/usr/lib/libSystem.B.dylib", use_errno=True).copyfile
                            copyfile.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_void_p, ctypes.c_uint32]
                            copyfile.restype = ctypes.c_int
                            require(copyfile(os.fsencode(source), os.fsencode(target), None,
                                             7 | (1 << 18) | (1 << 19)) == 0,
                                    "directory metadata copy denied/unavailable; retain source and partial copy")
                        except (OSError, AttributeError):
                            raise Hold("native directory metadata capability unavailable; retain source") from None
                    else:
                        shutil.copystat(source, target, follow_symlinks=False)
                else:
                    # No mode/ACL changes on a source or an existing destination.
                    if sys.platform == "darwin":
                        command(["ditto", "--rsrc", "--extattr", "--acl", str(source), str(target)], self.safe)
                    else:
                        shutil.copy2(source, target, follow_symlinks=False)
                copied = preserved_record(record, target)
            manifest.append({"old": str(source), "new": str(target), "record": record, "destination_record": copied})
        require(artifact_inventory(candidate, excluded, item.get("temporary_artifacts", [])) == before, "source inventory changed during preservation")
        self.attempt["preservation"] = {"source": str(candidate), "destination": str(destination),
                                        "exclusions": item["exclusions"], "files": manifest,
                                        "source_inventory_sha256": fingerprint(before),
                                        "retained_inventory_sha256": fingerprint(retained)}
        self.verify_preservation(self.attempt["preservation"])
        self.save()
        return before, manifest

    def checkout(self, item, result):
        candidate = absolute(item["identity"])
        require(not self.plan.get("pending_operations"), "unresolved original allocation holds source retirement; reconcile and explicitly refresh the plan")
        require(candidate != self.safe and not inside(self.safe, candidate), "main/safe checkout held")
        require(not inside(self.path, candidate), "receipt inside candidate")
        trees = worktrees(self.safe)
        registered = next((tree for tree in trees if tree["worktree"] == str(candidate)), None)
        if registered is None:
            require(not candidate.exists(), "unregistered path exists/recreated; held")
            prior = [attempt["preservation"] for attempt in self.state if "preservation" in attempt]
            require(prior, "absent checkout has no verifiable external preservation receipt")
            self.verify_preservation(prior[-1])
            result.update(status="absent", reason="worktree registration/filesystem absent; prior external copy verified")
            return
        require(candidate.is_dir() and candidate.stat().st_ino == item["inode"] and
                registered.get("branch") == self.plan["ref"] and registered["HEAD"] == self.plan["oid"] and
                "locked" not in registered, "worktree identity/OID/lock changed")
        self.local_tip()
        require(not text(["git", "status", "--porcelain", "--untracked-files=all"], candidate), "tracked/untracked changes; preserve unpublished work")
        self.consumers(candidate)
        excluded = exclusion_paths(item["exclusions"])
        inventory = artifact_inventory(candidate, excluded, item.get("temporary_artifacts", []))
        require(not any(" deny " in (" " + acl + " ") for record in inventory.values() for acl in record["acl"]),
                "filesystem deny ACL/protection present; permission hold; no removal or protection changes")
        require(all(record["mode"] & 0o300 == 0o300 for record in inventory.values() if record["kind"] == "directory"),
                "directory write/search permission unavailable; retain protections")
        if not self.apply:
            result.update(status="qualified", reason="preview only; external preservation still required on apply")
            return
        before, _ = self.preserve(candidate, item)
        self.subject_checks()
        self.local_tip()
        self.consumers(candidate)
        require(artifact_inventory(candidate, excluded, item.get("temporary_artifacts", [])) == before, "source changed immediately before removal")
        self.verify_preservation(self.attempt["preservation"])
        result.update(status="pending", reason="preservation verified; qualified normal Git removal; reobserve outcome")
        self.save()
        command(["git", "worktree", "remove", str(candidate)], self.safe, timeout=60)
        require(not candidate.exists() and not any(tree["worktree"] == str(candidate) for tree in worktrees(self.safe)), "checkout removal postcondition failed")
        self.verify_preservation(self.attempt["preservation"])
        result.update(status="removed", reason="filesystem/registration absent and external digests/modes verified")

    def branch_configuration(self):
        branch = self.plan["ref"].removeprefix("refs/heads/")
        pattern = "^branch\\." + re.escape(branch) + "\\."
        existing = command(["git", "config", "--local", "--get-regexp", pattern], self.safe, codes=(0, 1))
        if existing.returncode == 0:
            command(["git", "config", "--local", "--remove-section", "branch." + branch], self.safe)
        require(command(["git", "config", "--local", "--get-regexp", pattern], self.safe, codes=(0, 1)).returncode == 1,
                "owned branch configuration postcondition failed")

    def branch(self, item, result):
        require(not self.plan.get("pending_operations"), "unresolved original allocation holds ref retirement")
        require(item["identity"] == self.plan["ref"], "branch not bound to subject")
        current = text(["git", "for-each-ref", "--format=%(objectname)", item["identity"]], self.safe)
        if not current:
            require(not any(tree.get("branch") == item["identity"] for tree in worktrees(self.safe)), "absent ref has worktree dependency")
            if self.apply:
                self.branch_configuration()
            result.update(status="absent", reason="exact ref absent; owned configuration reconciled on apply")
            return
        self.local_tip()
        require(not any(tree.get("branch") == item["identity"] for tree in worktrees(self.safe)), "branch still checked out")
        candidate = absolute(self.plan["worktree"]["identity"])
        require(not candidate.exists(), "checkout dependency still exists")
        self.consumers(candidate)
        if not self.apply:
            result.update(status="qualified", reason="preview only")
            return
        result.update(status="pending", reason="exact published/merged OID compare-and-delete; reobserve outcome")
        self.save()
        command(["git", "update-ref", "-d", item["identity"], self.plan["oid"]], self.safe)
        require(not text(["git", "for-each-ref", "--format=%(objectname)", item["identity"]], self.safe), "local ref postcondition failed")
        self.branch_configuration()
        result.update(status="removed", reason="OID-guarded local ref/config absence verified; remote untouched")

    def verify_docker_results(self):
        """Reobserve successful effects/retentions at final intake; receipts are not postconditions."""
        if not any(self.plan[kind] for kind in ("containers", "networks", "volumes", "images", "caches")):
            return
        containers = docker_inventory(self.safe)
        volumes = volume_inventory(self.safe)
        networks = text(["docker", "network", "ls", "--no-trunc", "--format", "{{.ID}} {{.Name}}"], self.safe).splitlines()
        for result in self.results:
            if result["status"] not in ("removed", "absent", "retained"):
                continue
            kind, identity = result["kind"], result["identity"]
            if kind not in ("container", "network", "volume", "image"):
                continue
            item = next(value for value in self.plan[kind + "s"] if value["identity"] == identity)
            if result["status"] == "retained":
                self.observe_retained(kind, item, result)
                continue
            if kind == "container":
                require(not any(value["Id"] == identity or value["Name"] == item["name"] for value in containers),
                        "container recreated after effect; final verification held")
            elif kind == "volume":
                require(not any(value["Name"] == identity for value in volumes), "volume recreated after effect; final verification held")
            elif kind == "network":
                require(not any(line.split()[0] == identity or line.split()[1] == item["name"] for line in networks),
                        "network recreated after effect; final verification held")
            else:
                require(inspect_image(self.safe, identity) is None, "image present after effect; final verification held")
                if item.get("retirement") is not None:
                    proof, _ = self.owned_root_proof(item)
                    self.verify_root_references(proof)

    def run(self):
        self.save()
        for item in self.plan["containers"]:
            self.effect("container", item, self.container)
        for item in self.plan["volumes"]:
            self.effect("volume", item, self.volume)
        for item in self.plan["networks"]:
            self.effect("network", item, self.network)
        for item in self.plan["images"]:
            self.effect("image", item, self.image)
        for item in self.plan["caches"]:
            self.effect("cache", item, self.cache)
        if self.plan.get("recoverable_storage") is not None and self.apply:
            storage = Storage(self.path, self.safe, document=self.document, policy=self.plan["recoverable_storage"])
            observation = storage.run(admit=False)
            self.results.append({"kind": "recoverable-storage", "identity": self.subject,
                                 "status": ("removed" if any(row["status"] == "removed" for row in observation["results"]) else "absent") if observation["complete"] else "held",
                                 "reason": observation.get("hold", "inspect independent storage per-effect results"),
                                 "storage_attempt": len(self.document["storage_attempts"]) - 1})
        for pending in self.plan.get("pending_operations", []):
            self.results.append({"kind": "allocation", "identity": pending["identity"], "status": "held", "reason": pending["reason"]})
        self.effect("worktree", self.plan["worktree"], self.checkout)
        self.effect("ref", self.plan["branch"], self.branch)
        try:
            self.subject_checks()
            self.verify_docker_results()
            self.attempt["retained_protected_files_verified"] = True
        except (Hold, OSError, ValueError, KeyError) as error:
            self.results.append({"kind": "final-verification", "identity": self.subject,
                                 "status": "failed", "reason": str(error) if isinstance(error, Hold) else type(error).__name__})
        self.attempt["complete"] = self.apply and all(item["status"] in ("removed", "absent", "retained") for item in self.results)
        self.save()
        summary = [{key: value for key, value in item.items() if key != "commands"} for item in self.results]
        print(json.dumps({"task": self.subject, "complete": self.attempt["complete"], "results": summary}, indent=2))
        return 0 if self.attempt["complete"] else 1


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--receipt", help="Private exact-subject handoff, or storage admission receipt (default var/storage.json)")
    parser.add_argument("--apply", action="store_true", help="Execute qualified effects; default is observation only")
    parser.add_argument("--runtime-retire", action="store_true", help="Retire only an explicitly disposable runtime receipt; retain source/ref")
    parser.add_argument("--protect-image", action="append", default=[], help="Current caller input reference/root; excluded from image reclamation")
    storage_mode = parser.add_mutually_exclusive_group()
    storage_mode.add_argument("--storage-admit", action="store_true", help="One automatic bounded recoverable-artifact pass; refuse insufficient/unavailable reserve")
    storage_mode.add_argument("--storage-reclaim", action="store_true", help="Same bounded storage operation without allocation admission")
    args = parser.parse_args()
    os.umask(0o077)
    if not args.receipt:
        if not (args.storage_admit or args.storage_reclaim):
            parser.error("--receipt is required for exact-subject cleanup")
        args.receipt = str(Path.cwd() / "var/storage.json")
    try:
        absolute(args.receipt).parent.mkdir(parents=True, exist_ok=True)
        if args.runtime_retire:
            require(not (args.storage_admit or args.storage_reclaim), "runtime retirement is not storage admission")
            plan = json.loads(absolute(args.receipt).read_text())["cleanup"]["plan"]
            require(plan.get("purpose") == "disposable-runtime" and plan["worktree"]["disposition"] == "retain" and
                    plan["branch"]["disposition"] == "retain", "runtime retirement needs explicit original disposable authority; source/ref retained")
            os.chdir(absolute(plan["safe_checkout"]))
        # Separate persistent lock inode survives atomic receipt replacement; never delete it.
        lock = absolute(args.receipt + ".lock")
        with lock.open("a") as handle:
            fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
            if args.storage_admit or args.storage_reclaim:
                require(not (Path.cwd() / "var/resource-lifecycle.json").exists(),
                        "bound TASK storage admission uses bin/runtime and its SAME receipt/configuration")
                storage = Storage(args.receipt, Path.cwd(), policy={"protected_images": args.protect_image})
                require("lifecycle" not in storage.document, "bound lifecycle storage cannot bypass original locator/plan guards")
                observation = storage.run(admit=args.storage_admit)
                print(json.dumps({key: value for key, value in observation.items() if key != "results"}, indent=2))
                return 0 if (observation["admitted"] if args.storage_admit else observation["complete"]) else 1
            return Cleanup(args.receipt, args.apply).run()
    except (Hold, OSError, ValueError, KeyError, TypeError) as error:
        reason = str(error) if isinstance(error, Hold) else type(error).__name__
        print(json.dumps({"complete": False, "hold": reason, "recovery": "inspect exact receipt/prerequisite; no force or protection changes"}))
        return 1


if __name__ == "__main__":
    sys.exit(main())
