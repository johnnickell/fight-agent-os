#!/usr/bin/env python3
"""Qualify and retire one TASK's enumerated local resources (Python 3.9+)."""

import argparse
from datetime import datetime, timezone
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tempfile


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


def command(args, cwd, codes=(0,), timeout=15):
    """Capture privately; never replay provider, configuration or environment output."""
    try:
        result = subprocess.run(args, cwd=cwd, stdin=subprocess.DEVNULL,
                                capture_output=True, timeout=timeout,
                                env=dict(os.environ, GIT_NO_REPLACE_OBJECTS="1", GIT_NO_LAZY_FETCH="1",
                                         COMPOSE_DISABLE_ENV_FILE="1", COMPOSE_ENV_FILES=""))
    except (OSError, subprocess.SubprocessError):
        raise Hold(f"{args[0]} inspection/effect unavailable; inspect tooling and permissions") from None
    if COMMANDS is not None:
        COMMANDS.append({"argv": [str(value) for value in args], "exit": result.returncode,
                         "stdout_sha256": digest(result.stdout), "stderr_sha256": digest(result.stderr)})
    require(result.returncode in codes,
            f"{args[0]} {args[1]} exit {result.returncode}; retain resource; no protection-changing recovery")
    require(len(result.stdout) <= 64 * 1024 * 1024, "inspection exceeds bounded capture")
    return result


def text(args, cwd, **kwargs):
    return command(args, cwd, **kwargs).stdout.decode("utf-8").strip()


def json_command(args, cwd):
    return json.loads(text(args, cwd))


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


def docker_inventory(safe):
    ids = text(["docker", "ps", "-a", "--no-trunc", "--format", "{{.ID}}"], safe).splitlines()
    return json_command(["docker", "inspect", *ids], safe) if ids else []


def file_record(path):
    before = path.lstat()
    require(stat.S_ISREG(before.st_mode) or stat.S_ISDIR(before.st_mode),
            "special file or symlink in preservation inventory; owner must resolve")
    require(before.st_uid == os.getuid(), "artifact ownership unresolved")
    require(not getattr(before, "st_flags", 0), "protected artifact flags; retain unchanged")
    if hasattr(os, "listxattr"):
        attrs = {name: digest(os.getxattr(path, name)) for name in os.listxattr(path)}
    else:
        names = command(["xattr", str(path)], path.parent).stdout.decode().splitlines()
        attrs = {name: digest(command(["xattr", "-p", name, str(path)], path.parent).stdout) for name in names}
    acl = []
    if sys.platform == "darwin":
        listing = command(["ls", "-lde", str(path)], path.parent).stdout.decode().splitlines()
        acl = [line.strip() for line in listing[1:]]
    result = {"kind": "directory" if path.is_dir() else "file", "mode": stat.S_IMODE(before.st_mode),
              "uid": before.st_uid, "gid": before.st_gid, "xattrs": attrs,
              "flags": getattr(before, "st_flags", 0), "acl": acl}
    if path.is_file():
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


def artifact_inventory(root):
    """Inventory everything Git may delete, including empty dirs and ignored state."""
    result = {".": file_record(root)}
    def unavailable(error):
        raise error
    for parent, directories, files in os.walk(root, followlinks=False, onerror=unavailable):
        if Path(parent) == root:
            directories[:] = [name for name in directories if name != ".git"]
            files = [name for name in files if name != ".git"]
        for name in sorted(directories + files):
            path = Path(parent) / name
            result[str(path.relative_to(root))] = file_record(path)
    return result


class Cleanup:
    def __init__(self, receipt, apply=False):
        self.path = absolute(receipt)
        self.document = json.loads(self.path.read_text())
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
        require(len(self.plan["containers"]) <= 32 and len(self.plan["networks"]) <= 32 and
                len(self.plan["protected_files"]) <= 128, "exact-subject resource bounds exceeded")
        self.plan_hash = fingerprint(self.plan)
        previous = self.document["cleanup"].get("plan_sha256")
        require(previous is None or previous == self.plan_hash, "plan changed; reconcile explicitly in a new receipt linked to prior results")
        self.document["cleanup"]["plan_sha256"] = self.plan_hash
        self.results = []
        self.attempt = {"time": datetime.now(timezone.utc).isoformat(), "apply": apply,
                        "operation_sha256": digest(Path(__file__).read_bytes()),
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
                result.update(status="retained", reason=item["reason"])
            else:
                require(item["disposition"] == "remove", item["reason"])
                require(item["ownership"] == "proven" and item["consumers"] == [],
                        "ownership or consumers unresolved/active")
                self.subject_checks()
                operation(item, result)
        except Hold as error:
            result.update(status="held", reason=str(error))
        except (OSError, ValueError, KeyError, TypeError) as error:
            result.update(status="held", reason=f"{type(error).__name__}: inspection/preservation unavailable; retain unchanged")
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
        for report in self.plan["protected_files"]:
            path = absolute(report["path"])
            require(not inside(path, absolute(self.plan["worktree"]["identity"])), "canonical report inside deletion candidate")
            require(file_record(path) == report["record"], "protected report/private evidence changed")
        for worker in self.plan["exited_workers"]:
            # PID reuse is not an exit observation. A live PID always holds, even after reuse.
            observed = command(["ps", "-p", str(worker["pid"]), "-o", "lstart="], self.safe, codes=(0, 1))
            require(observed.returncode == 1 and not observed.stdout.strip(), "original worker PID still live or reused; coordinator must observe exit")
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

    def runtime(self):
        binding = self.plan["runtime"]
        require(binding["ownership"] == "proven", "runtime binding ownership unresolved")
        cwd = absolute(binding["working_directory"])
        args = ["docker", "compose", "--project-name", binding["project"], "--project-directory", str(cwd)]
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

    def host_consumers(self, candidate):
        observation = command(["lsof", "-nP", "-Fpcuaftn"], self.safe)
        require(not observation.stderr.strip(), "host consumer visibility incomplete")
        processes, process, descriptor = {}, None, None
        for line in observation.stdout.decode().splitlines():
            if line.startswith("p"):
                process = {"pid": int(line[1:]), "files": []}
                processes[process["pid"]] = process
            elif line.startswith("u") and process is not None:
                process["uid"] = line[1:]
            elif line.startswith("f") and process is not None:
                descriptor = {"fd": line[1:]}
                process["files"].append(descriptor)
            elif line[:1] in ("a", "n", "t") and descriptor is not None:
                descriptor[line[0]] = line[1:]
        for process in processes.values():
            matching = [entry for entry in process["files"] if entry.get("n", "").startswith("/") and
                        inside(Path(entry["n"].removesuffix(" (deleted)")), candidate)]
            if not matching:
                continue
            require(all(entry.get("a") == "r" and re.fullmatch(r"\*?\d+", entry["fd"]) for entry in matching),
                    "active cwd/writer/unknown descriptor; retain resource")
            # Qualify one supported local Docker Desktop file-sharing topology from live
            # executable, process birth/owner, open VM disk/kernel, context and engine.
            require(sys.platform == "darwin" and process.get("uid") == str(os.getuid()), "unknown read-only consumer")
            exe = "/System/Library/Frameworks/Virtualization.framework/Versions/A/XPCServices/com.apple.Virtualization.VirtualMachine.xpc/Contents/MacOS/com.apple.Virtualization.VirtualMachine"
            identity = text(["ps", "-p", str(process["pid"]), "-o", "uid=,lstart=,comm="], self.safe)
            require(identity.endswith(exe), "read-only consumer is not qualified Docker VM executable")
            command(["codesign", "--verify", "--strict", exe], self.safe)
            files = {entry.get("n"): entry for entry in process["files"]}
            disk = str(Path.home() / "Library/Containers/com.docker.docker/Data/vms/0/data/Docker.raw")
            kernel = "/Applications/Docker.app/Contents/Resources/linuxkit/kernel"
            require(files.get(disk, {}).get("a") == "u" and files.get(kernel, {}).get("a") == "r", "VM/Docker backing identity unavailable")
            context = json_command(["docker", "context", "inspect"], self.safe)
            engine = json_command(["docker", "info", "--format", "{{json .}}"], self.safe)
            require(len(context) == 1 and context[0]["Endpoints"]["docker"]["Host"] ==
                    "unix://" + str(Path.home() / ".docker/run/docker.sock") and
                    engine["OperatingSystem"] == "Docker Desktop" and engine["ID"], "VM/local Docker context binding unavailable")
            self.attempt.setdefault("readonly_file_sharing", {})[str(process["pid"])] = {
                "process_identity": identity, "executable_sha256": digest(Path(exe).read_bytes()),
                "disk": disk, "kernel": kernel, "engine_id": engine["ID"],
                "context_endpoint": context[0]["Endpoints"]["docker"]["Host"],
                "candidate_readonly_descriptors": len(matching)}

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

    def container(self, item, result):
        # Reconcile absence before reading runtime inputs that checkout retirement
        # may have deleted. A present resource still needs every runtime check.
        inventory = docker_inventory(self.safe)
        current = next((value for value in inventory if value["Id"] == item["identity"]), None)
        if current is None:
            require(not any(value["Name"] == item["name"] for value in inventory), "container recreated under recorded name; new identity held")
            result.update(status="absent", reason="exact ID and recorded name absent on successful full inventory")
            return
        binding, args, model = self.runtime()
        require(container_identity(current) == item["inspection_sha256"], "container identity/configuration/mounts changed")
        labels = current["Config"]["Labels"]
        service = item["service"]
        require(service in model["services"], "container service absent from qualified configuration")
        require(labels.get("com.docker.compose.project") == binding["project"] and
                labels.get("com.docker.compose.service") == service and
                labels.get("com.docker.compose.project.working_dir") == binding["working_directory"] and
                labels.get("com.docker.compose.project.config_files") == ",".join(entry["path"] for entry in binding["config_files"]), "Compose/resource binding mismatch")
        expected = text([*args, "config", "--hash", service], binding["working_directory"]).split()
        require(len(expected) == 2 and labels.get("com.docker.compose.config-hash") == expected[1], "current Compose service hash contradicts container")
        if current["State"]["Running"]:
            processes = command(["docker", "top", item["identity"], "-eo", "pid,ppid,args"], self.safe).stdout
            require(digest(processes) == item["processes_sha256"], "container processes changed/unknown consumers")
        require(item["data"] == "disposable" and all(mount["Type"] != "volume" for mount in current["Mounts"]), "durable or unresolved data disposition")
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
        if not self.apply:
            result.update(status="qualified", reason="preview only")
            return
        result.update(status="pending", reason="qualified normal stop/remove; outcome must be reobserved")
        self.save()
        if latest["State"]["Running"]:
            command(["docker", "stop", "--time", "10", item["identity"]], self.safe, timeout=20)
        # Stop is an effect too; an interruption resumes from current stopped state.
        self.subject_checks()
        self.runtime()
        latest = json_command(["docker", "inspect", item["identity"]], self.safe)[0]
        require(container_identity(latest) == item["inspection_sha256"] and not latest["State"]["Running"], "container changed/restarted after stop")
        self.host_consumers(candidate)
        command(["docker", "rm", item["identity"]], self.safe)
        require(not any(value["Id"] == item["identity"] or value["Name"] == item["name"] for value in docker_inventory(self.safe)), "container removal postcondition failed/recreated")
        result.update(status="removed", reason="exact container ID/name absence verified; no volumes removed")

    def network(self, item, result):
        ids = text(["docker", "network", "ls", "--no-trunc", "--format", "{{.ID}} {{.Name}}"], self.safe).splitlines()
        matches = [line.split() for line in ids]
        if not any(value[0] == item["identity"] for value in matches):
            require(not any(value[1] == item["name"] for value in matches), "network recreated under recorded name; held")
            result.update(status="absent", reason="exact network ID/name absent on successful inventory")
            return
        binding, _, model = self.runtime()
        network = json_command(["docker", "network", "inspect", item["identity"]], self.safe)[0]
        configuration = model["networks"][item["key"]]
        require(not configuration.get("external", False) and configuration["name"] == item["name"] == network["Name"], "external/shared/network identity mismatch")
        require(network["Labels"].get("com.docker.compose.project") == binding["project"] and
                network["Labels"].get("com.docker.compose.network") == item["key"] and
                network["Created"] == item["created"], "network ownership changed")
        require(not network["Containers"], "network has current endpoints (foreign/shared/remaining); retain unchanged")
        if not self.apply:
            result.update(status="qualified", reason="preview only")
            return
        result.update(status="pending", reason="qualified empty owned network; reobserve outcome")
        self.save()
        command(["docker", "network", "rm", item["identity"]], self.safe)
        after = text(["docker", "network", "ls", "--no-trunc", "--format", "{{.ID}} {{.Name}}"], self.safe).splitlines()
        require(not any(item["identity"] in line or line.split()[-1] == item["name"] for line in after), "network postcondition failed/recreated")
        result.update(status="removed", reason="exact network ID/name absence verified")

    def local_tip(self):
        ref = self.plan["ref"]
        current = text(["git", "for-each-ref", "--format=%(objectname)", ref], self.safe)
        require(current == self.plan["oid"], "local ref missing/changed or unpublished commits")
        require(text(["git", "rev-list", "--count", f"{self.plan['oid']}..{ref}"], self.safe) == "0", "local-only commits")

    def preserve(self, candidate, item):
        before = artifact_inventory(candidate)
        excluded = item["exclusions"]
        for entry in excluded:
            path = Path(entry["path"])
            require(not path.is_absolute() and ".." not in path.parts and entry["reason"] and
                    entry["disposable"] is True, "exclusion must name a proven disposable relative path and reason")
        def excluded_path(name):
            return any(inside(Path(name), Path(entry["path"])) for entry in excluded)
        # Every ignored/untracked file is copied unless explicitly disposable. Tracked bytes
        # also enter the stability inventory, not the retained copy.
        untracked = command(["git", "ls-files", "--others", "-z"], candidate).stdout.decode().split("\0")
        names = {name for name in untracked if name and not excluded_path(name)}
        names |= {name for name, record in before.items() if name != "." and record["kind"] == "directory" and not excluded_path(name) and
                  (any(inside(Path(child), Path(name)) for child in names) or not any(inside(Path(child), Path(name)) and child != name for child in before))}
        destination = absolute(item["preserve_to"])
        require(inside(destination, self.store) and not inside(destination, candidate), "preservation destination must be canonical task-owned runs")
        destination.mkdir(mode=0o700, parents=True, exist_ok=True)
        require(destination.stat().st_uid == os.getuid() and stat.S_IMODE(destination.stat().st_mode) == 0o700, "preservation destination not private/owned")
        manifest = []
        for name in sorted(names, key=lambda value: (len(Path(value).parts), value)):
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
                    shutil.copystat(source, target, follow_symlinks=False)
                else:
                    # No mode/ACL changes on a source or an existing destination.
                    if sys.platform == "darwin":
                        command(["ditto", "--rsrc", "--extattr", "--acl", str(source), str(target)], self.safe)
                    else:
                        shutil.copy2(source, target, follow_symlinks=False)
                copied = preserved_record(record, target)
            manifest.append({"old": str(source), "new": str(target), "record": record, "destination_record": copied})
        require(artifact_inventory(candidate) == before, "source inventory changed during preservation")
        self.attempt["preservation"] = {"source": str(candidate), "destination": str(destination),
                                        "exclusions": excluded, "files": manifest,
                                        "source_inventory_sha256": fingerprint(before)}
        self.save()
        return before, manifest

    def checkout(self, item, result):
        candidate = absolute(item["identity"])
        require(candidate != self.safe and not inside(self.safe, candidate), "main/safe checkout held")
        require(not inside(self.path, candidate), "receipt inside candidate")
        trees = worktrees(self.safe)
        registered = next((tree for tree in trees if tree["worktree"] == str(candidate)), None)
        if registered is None:
            require(not candidate.exists(), "unregistered path exists/recreated; held")
            prior = [attempt["preservation"] for attempt in self.state if "preservation" in attempt]
            require(prior and all(file_record(Path(entry["new"])) == entry.get("destination_record", entry["record"])
                                  for entry in prior[-1]["files"]), "absent checkout has no verifiable external preservation receipt")
            result.update(status="absent", reason="worktree registration/filesystem absent; prior external copy verified")
            return
        require(candidate.is_dir() and candidate.stat().st_ino == item["inode"] and
                registered.get("branch") == self.plan["ref"] and registered["HEAD"] == self.plan["oid"] and
                "locked" not in registered, "worktree identity/OID/lock changed")
        self.local_tip()
        require(not text(["git", "status", "--porcelain", "--untracked-files=all"], candidate), "tracked/untracked changes; preserve unpublished work")
        self.consumers(candidate)
        inventory = artifact_inventory(candidate)
        require(not any(" deny " in (" " + acl + " ") for record in inventory.values() for acl in record["acl"]),
                "filesystem deny ACL/protection present; permission hold; no removal or protection changes")
        require(all(record["mode"] & 0o300 == 0o300 for record in inventory.values() if record["kind"] == "directory"),
                "directory write/search permission unavailable; retain protections")
        if not self.apply:
            result.update(status="qualified", reason="preview only; external preservation still required on apply")
            return
        before, manifest = self.preserve(candidate, item)
        self.subject_checks()
        self.local_tip()
        self.consumers(candidate)
        require(artifact_inventory(candidate) == before, "source changed immediately before removal")
        require(all(file_record(Path(entry["new"])) == entry["destination_record"] for entry in manifest), "external copy changed before removal")
        result.update(status="pending", reason="preservation verified; qualified normal Git removal; reobserve outcome")
        self.save()
        command(["git", "worktree", "remove", str(candidate)], self.safe, timeout=60)
        require(not candidate.exists() and not any(tree["worktree"] == str(candidate) for tree in worktrees(self.safe)), "checkout removal postcondition failed")
        require(all(file_record(Path(entry["new"])) == entry["destination_record"] for entry in manifest), "external preservation postcondition failed")
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

    def run(self):
        self.save()
        for item in self.plan["containers"]:
            self.effect("container", item, self.container)
        for item in self.plan["networks"]:
            self.effect("network", item, self.network)
        self.effect("worktree", self.plan["worktree"], self.checkout)
        self.effect("ref", self.plan["branch"], self.branch)
        try:
            self.subject_checks()
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
    parser.add_argument("--receipt", required=True, help="Existing private exact-subject JSON handoff/receipt")
    parser.add_argument("--apply", action="store_true", help="Execute qualified effects; default is observation only")
    args = parser.parse_args()
    os.umask(0o077)
    try:
        # Separate persistent lock inode survives atomic receipt replacement; never delete it.
        lock = absolute(args.receipt + ".lock")
        with lock.open("a") as handle:
            fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
            return Cleanup(args.receipt, args.apply).run()
    except (Hold, OSError, ValueError, KeyError, TypeError) as error:
        reason = str(error) if isinstance(error, Hold) else type(error).__name__
        print(json.dumps({"complete": False, "hold": reason, "recovery": "inspect exact receipt/prerequisite; no force or protection changes"}))
        return 1


if __name__ == "__main__":
    sys.exit(main())
