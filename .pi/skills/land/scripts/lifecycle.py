#!/usr/bin/env python3
"""Record owning local Docker allocations in the existing exact-TASK handoff."""

import argparse
from contextlib import contextmanager
from datetime import datetime, timezone
import fcntl
import json
import os
from pathlib import Path
import re
import signal
import stat
import subprocess
import sys
import tempfile
import time
import uuid

sys.dont_write_bytecode = True
from cleanup import (Hold, absolute, container_identity, digest, file_record,
                     fingerprint, image_root_graph, inside, network_identity, require, verify_plan_history, volume_identity,
                     LEGACY_NO_EFFECT_OPERATION, qualify_legacy_append, verification_snapshot)

LABEL = "io.fight.lifecycle.operation"
KINDS = ("containers", "networks", "volumes", "images", "caches")
SAFE_CWD = None  # One CLI invocation; never an asynchronous observer in the candidate


def now():
    return datetime.now(timezone.utc).isoformat()


def atomic(path, value, exclusive=False):
    """Flush a private record and its directory before allowing an external effect."""
    require(path.parent.is_dir(), "record parent must already exist")
    descriptor, temporary = tempfile.mkstemp(prefix=".lifecycle-", dir=path.parent)
    try:
        with os.fdopen(descriptor, "wb") as output:
            output.write(value if isinstance(value, bytes) else (json.dumps(value, indent=2) + "\n").encode())
            output.flush()
            os.fsync(output.fileno())
        if exclusive:
            os.link(temporary, path)
        else:
            os.replace(temporary, path)
        directory = os.open(path.parent, os.O_RDONLY)
        try:
            os.fsync(directory)
        finally:
            os.close(directory)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def private(path):
    require(path.is_file() and not path.is_symlink() and path.stat().st_uid == os.getuid()
            and stat.S_IMODE(path.stat().st_mode) == 0o600, "evidence must be owned, regular and private (0600)")
    return path.read_bytes()


def reference(path):
    return {"path": str(path), "sha256": digest(private(path))}


def observe(argv, cwd, codes=(0,), timeout=30):
    result = subprocess.run([str(arg) for arg in argv], cwd=SAFE_CWD if SAFE_CWD is not None and argv[0] != "git" else cwd, capture_output=True,
                            stdin=subprocess.DEVNULL, timeout=timeout,
                            env=dict(os.environ, COMPOSE_DISABLE_ENV_FILE="1", COMPOSE_ENV_FILES=""))
    require(result.returncode in codes, f"{' '.join(str(value) for value in argv[:3])} observation exit {result.returncode}; no dependent allocation")
    require(len(result.stdout) <= 32 * 1024 * 1024, "observation exceeds finite capture")
    return result


def text(argv, cwd):
    return observe(argv, cwd).stdout.decode().strip()


def query(argv, cwd):
    return json.loads(observe(argv, cwd).stdout)


def source(root):
    names = observe(["git", "ls-files", "-z", "--cached", "--others", "--exclude-standard"], root).stdout.split(b"\0")
    records = {}
    for name in set(names) - {b""}:
        path = root / os.fsdecode(name)
        if path.is_symlink():
            records[os.fsdecode(name)] = {"link": os.readlink(path)}
        elif path.is_file():
            records[os.fsdecode(name)] = {"sha256": digest(path.read_bytes()), "mode": stat.S_IMODE(path.stat().st_mode)}
    return {"revision": text(["git", "rev-parse", "HEAD"], root), "files_sha256": fingerprint(records)}


def sanitized(raw, binding):
    output = raw.decode(errors="replace")
    values = [value for key, value in os.environ.items() if re.search(r"KEY|TOKEN|SECRET|PASSWORD|AUTH", key, re.I)]
    for entry in binding.get("env_files", []):
        for line in Path(entry["path"]).read_text().splitlines():
            if "=" in line and not line.lstrip().startswith("#"):
                values.append(line.split("=", 1)[1].strip().strip("'\""))
    for value in sorted(set(values), key=len, reverse=True):
        if len(value) >= 8:
            output = output.replace(value, "<redacted>")
    return output


def process_identity(pid, cwd):
    observed = observe(["ps", "-p", str(pid), "-o", "pid=,ppid=,uid=,lstart=,comm="], cwd, codes=(0, 1))
    return {"pid": pid, "observation": observed.stdout.decode().strip(), "exit": observed.returncode,
            "visibility": "absent" if observed.returncode == 1 and not observed.stdout.strip() and not observed.stderr.strip() else "observed-or-unknown",
            "stderr_sha256": digest(observed.stderr)}


def compose(binding):
    argv = ["docker", "compose", "--project-name", binding["project"],
            "--project-directory", binding["working_directory"]]
    for profile in binding.get("profiles", []):
        require(re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.-]{0,63}", profile), "invalid explicit profile")
        argv += ["--profile", profile]
    for key, flag in (("env_files", "--env-file"), ("config_files", "--file")):
        for entry in binding[key]:
            require(digest(Path(entry["path"]).read_bytes()) == entry["sha256"], "bound configuration/env input changed")
            argv += [flag, entry["path"]]
    return argv


def resolved(binding):
    model = query([*compose(binding), "config", "--format", "json"], binding["working_directory"])
    require(model["name"] == binding["project"], "resolved project mismatch")
    return model


def inventory(root):
    ids = text(["docker", "ps", "-aq", "--no-trunc"], root).splitlines()
    networks = text(["docker", "network", "ls", "-q", "--no-trunc"], root).splitlines()
    volumes = text(["docker", "volume", "ls", "-q"], root).splitlines()
    images = sorted(set(text(["docker", "image", "ls", "-aq", "--no-trunc"], root).splitlines()))
    return {"containers": query(["docker", "inspect", *ids], root) if ids else [],
            "networks": query(["docker", "network", "inspect", *networks], root) if networks else [],
            "volumes": query(["docker", "volume", "inspect", *volumes], root) if volumes else [],
            "images": images}


def public_args(argv):
    """Keep argument identity without recording inline environment or build-argument values."""
    answer, sensitive = [], False
    for value in argv:
        if sensitive:
            answer.append({"sha256": digest(value.encode())})
            sensitive = False
        elif value in ("-e", "--env", "--build-arg"):
            answer.append(value)
            sensitive = True
        elif value.startswith(("--env=", "--build-arg=")):
            answer.append({"sha256": digest(value.encode())})
        else:
            answer.append(value)
    return answer


class Lifecycle:
    def __init__(self, root, receipt):
        self.root, self.path = absolute(str(root)), absolute(str(receipt))
        self.document = json.loads(private(self.path))
        self.state = self.document["lifecycle"]
        require(self.state["version"] == 1, "unsupported lifecycle version")
        self.subject = self.state["subject"]
        require(self.subject["checkout"] == str(self.root), "lifecycle checkout mismatch")
        require(text(["git", "remote", "get-url", "origin"], root) == self.subject["origin"], "origin mismatch")
        require(text(["git", "symbolic-ref", "HEAD"], root) == self.subject["ref"], "branch mismatch")
        require(text(["git", "rev-parse", "--path-format=absolute", "--git-common-dir"], root) == self.subject["git_common_dir"], "Git identity mismatch")
        trees = text(["git", "worktree", "list", "--porcelain"], root)
        require(trees.splitlines()[0] == "worktree " + self.subject["safe_checkout"], "canonical safe checkout changed")
        require("worktree " + str(root) + "\n" in trees, "checkout is not registered")
        store = Path(self.subject["safe_checkout"]) / ".runs/tasks" / self.subject["task"] / "runs"
        require(inside(self.path, store) and (self.subject["safe_checkout"] == str(root) or not inside(self.path, root)), "receipt must survive outside removal candidate")
        self.binding = self.state["runtime"]
        require(self.binding["working_directory"] == str(root), "runtime checkout mismatch")
        require(fingerprint(resolved(self.binding)) == self.binding["resolved_sha256"], "runtime/configuration/env drift; explicit replacement required")

    def save(self):
        atomic(self.path, self.document)

    @contextmanager
    def locked(self):
        # SAME persistent lock as cleanup; atomic replacement never changes its inode.
        with Path(str(self.path) + ".lock").open("a") as handle:
            fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
            current = json.loads(private(self.path))
            require(current == self.document, "receipt changed before lock; retry observation, not effects")
            yield

    def complete(self):
        pending = [op["id"] for op in self.state["operations"] if op["status"] != "recorded"]
        require(not pending, "pending allocation requires same-operation reconciliation: " + ",".join(pending))

    def begin(self, argv, kind):
        self.complete()
        if self.document.get("cleanup", {}).get("plan"):
            self.verification_return()
            require(kind == "compose-command", "verification return permits supervised reuse only; allocation remains frozen")
        if kind in ("run", "standalone-run"):
            require(len(self.state["resources"]["containers"]) < self.state["policy"]["budget"]["containers"], "container evidence budget exhausted before allocation")
        budget = self.state["policy"]["budget"]
        require(len(self.state["operations"]) < budget["operations"], "finite operation budget exhausted")
        before = inventory(self.root)
        known = {value["identity"]: value for value in self.state["resources"]["containers"]}
        for container in before["containers"]:
            if (container["Config"].get("Labels") or {}).get("com.docker.compose.project") == self.binding["project"]:
                require(container["Id"] in known and container_identity(container) == known[container["Id"]]["inspection_sha256"],
                        "unrecorded/changed project creator; no allocation or implicit replacement")
        model = query([*compose(self.binding), "--profile", "*", "config", "--format", "json"], self.root)
        intended = {"command_kind": kind, "services": {name: {"image": value.get("image"), "data": self.state["policy"]["services"].get(name, {}).get("data", "unknown")}
                    for name, value in model["services"].items()},
                    "networks": [{"name": value["name"], "external": value.get("external", False),
                                  "intent": "reuse" if any(v["Name"] == value["name"] for v in before["networks"]) else "create-if-needed"} for value in model.get("networks", {}).values()],
                    "volumes": [{"name": value["name"], "external": value.get("external", False),
                                 "intent": "reuse" if any(v["Name"] == value["name"] for v in before["volumes"]) else "create-if-needed"} for value in model.get("volumes", {}).values()]}
        identifier = uuid.uuid4().hex
        op = {"id": identifier, "kind": kind, "at": now(), "subject": self.subject, "status": "pending", "steps": [],
              "source": source(self.root), "argv": public_args(argv), "before": {
                  "containers": [v["Id"] for v in before["containers"]],
                  "networks": [v["Id"] for v in before["networks"]],
                  "volumes": {v["Name"]: volume_identity(v) for v in before["volumes"]},
                  "images": before["images"]},
              "runtime": self.binding, "intended": intended, "policy": self.state["policy"],
              "caller": process_identity(os.getpid(), self.root),
              "parent": process_identity(os.getppid(), self.root),
              "session": {"id": os.environ.get("PI_SESSION_ID"), "provider": os.environ.get("PI_PROVIDER"),
                          "model": os.environ.get("PI_MODEL"), "provider_reported_execution_identity": None}}
        intent = self.path.parent / ("allocation-" + identifier + ".json")
        atomic(intent, op, exclusive=True)
        op["intent"] = reference(intent)
        self.state["operations"].append(op)
        self.save()  # No effect before BOTH durable records exist.
        return op

    def original_commands_ended(self, op, require_outcome=False, cancelling_wait=False):
        """Observe original dispatches without killing or replaying unknown processes."""
        observations = []
        for step in op["steps"]:
            if cancelling_wait and step.get("argv") == ["docker", "wait", op.get("returned_container")]:
                continue  # An exact read-only wait cannot allocate/start; cancellation ends its workload.
            if step.get("status") == "returned" and isinstance(step.get("exit"), int):
                require(not require_outcome or (step["exit"] >= 0 and step.get("outcome") != "supervision-expired"),
                        "original CLI ended by signal/timeout; daemon effect outcome unresolved, no release/replay")
                continue  # The original supervisor waited on this child.
            original = step.get("process")
            require(original and original.get("exit") == 0 and original.get("observation"),
                    "original CLI identity unavailable; retain pending dispatch and recover through its owner")
            current = process_identity(original["pid"], self.root)
            observations.append({"original": original, "current": current, "at": now()})
            op.setdefault("process_recovery", []).append(observations[-1])
            self.save()
            require(current["exit"] == 1 and not current["observation"] and current.get("visibility") == "absent",
                    "original CLI still live, reused or visibility unknown; no reconciliation release or replay")
            require(not require_outcome, "original CLI ended but dispatch outcome unresolved; retain pending effect, never replay")
        return observations

    def step(self, op, argv, capture=True, input_data=None):
        step = {"argv": public_args(argv), "status": "pending", "at": now()}
        dispatch = self.path.parent / ("dispatch-" + op["id"] + "-" + str(len(op["steps"]) + 1) + ".json")
        atomic(dispatch, {"operation": op["id"], "subject": self.subject, "intent": op["intent"],
                          "runtime": op["runtime"], "argv": step["argv"], "at": step["at"],
                          "builder_observations": op.get("builder_observations", [])}, exclusive=True)
        step["dispatch"] = reference(dispatch)
        op["steps"].append(step)
        # Resume can enter from a recorded failed startup. Reopen durably before
        # dispatch so interruption/timeout cannot leave the new effect complete.
        op["status"] = "pending"
        self.save()
        # Do not use auto-removal. Even SIGKILL leaves the creator available to reconciliation.
        try:
            child = subprocess.Popen(argv, cwd=self.subject["safe_checkout"], stdin=subprocess.PIPE if input_data is not None else subprocess.DEVNULL,
                                     stdout=subprocess.PIPE if capture else None,
                                     stderr=subprocess.PIPE if capture else None,
                                     env=dict(os.environ, COMPOSE_DISABLE_ENV_FILE="1", COMPOSE_ENV_FILES=""))
            step["process"] = process_identity(child.pid, self.root)
            step["cwd"] = self.subject["safe_checkout"]
            self.save()
            try:
                stdout, stderr = child.communicate(input=input_data, timeout=self.state["policy"]["budget"]["seconds"])
            except subprocess.TimeoutExpired:
                # Popen still owns this unreaped child; never signal a recovered/reused PID.
                child.terminate()
                try:
                    stdout, stderr = child.communicate(timeout=10)
                except subprocess.TimeoutExpired:
                    child.kill()  # Still the original unreaped direct child, never a recovered PID.
                    try:
                        stdout, stderr = child.communicate(timeout=5)
                    except subprocess.TimeoutExpired:
                        step.update(status="unknown", outcome="supervision-expired-child-exit-unavailable")
                        self.save()
                        raise Hold("owned command exit unavailable; retain pending process identity") from None
                step.update(status="returned", exit=child.returncode, outcome="supervision-expired",
                            stdout_sha256=digest(stdout or b""), stderr_sha256=digest(stderr or b""))
                self.command_return(op, step)
                self.save()
                raise Hold("owning command budget expired; CLI ended, daemon workload outcome remains held") from None
            result = subprocess.CompletedProcess(argv, child.returncode, stdout, stderr)
        except (OSError, subprocess.SubprocessError):
            step["status"] = "unknown"
            self.save()
            raise Hold("command interrupted/unavailable; reconcile exact pending operation without reallocation") from None
        step.update(status="returned", exit=result.returncode,
                    stdout_sha256=digest(result.stdout or b""), stderr_sha256=digest(result.stderr or b""))
        if result.returncode < 0:
            step["outcome"] = "cli-signal; daemon outcome unknown"
        if capture:
            require(len(result.stdout or b"") <= 16384, "returned allocation output exceeds bound; retain pending creator")
        self.command_return(op, step, result if capture else None)
        self.save()
        # Every normal consumer shares this barrier, not just exec/recovery.
        # Preserve the original signal and pending daemon effect for legitimate
        # same-operation reconciliation; a local CLI exit is not a remote exit.
        require(result.returncode >= 0, "owning CLI terminated by signal; daemon outcome unknown, operation remains pending")
        return result

    def command_return(self, op, step, captured=None):
        output = self.path.parent / ("returned-" + op["id"] + "-" + str(len(op["steps"])) + ".json")
        atomic(output, {"operation": op["id"], "argv_sha256": fingerprint(step["argv"]),
                        "process": step.get("process"), "exit": step["exit"], "outcome": step.get("outcome", "returned"),
                        "stdout": (captured.stdout or b"").decode() if captured else "",
                        "stderr_redacted": sanitized(captured.stderr or b"", op["runtime"]) if captured else "",
                        "stdout_sha256": step["stdout_sha256"], "stderr_sha256": step["stderr_sha256"]}, exclusive=True)
        step["returned_output"] = reference(output)
        if op["kind"] == "compose-command":
            op.setdefault("observations", []).append(reference(output))

    def generation(self, op):
        model = query([*compose(self.binding), "--profile", "*", "config", "--format", "json"], self.root)
        network_names = dict(line.split(" ", 1)[::-1] for line in text(["docker", "network", "ls", "--no-trunc", "--format", "{{.ID}} {{.Name}}"], self.root).splitlines())
        override = {"services": {name: {"labels": {LABEL: op["id"]}} for name in model["services"]}, "networks": {}, "volumes": {}}
        for kind in ("networks", "volumes"):
            for key, value in model.get(kind, {}).items():
                if value.get("external"):
                    continue
                existing = network_names.get(value["name"]) if kind == "networks" else value["name"] if value["name"] in op["before"]["volumes"] else None
                original = next((v for v in self.state["resources"][kind] if v["identity"] == existing and v["creation"]["kind"] == "created"), None)
                require(not existing or original is not None, "internal network/volume exists without original allocation; declare external reuse or recover proof before allocation")
                # Stable resource labels survive service generations. Changing a network label
                # makes Compose replace it; never mutate a reused network to label a new creator.
                label = original["operation"] if original else op["id"]
                override[kind][key] = {"labels": {LABEL: label}}
        directory = self.root / "var/lifecycle"
        directory.mkdir(mode=0o700, parents=True, exist_ok=True)
        path = directory / (op["id"] + ".json")
        atomic(path, override, exclusive=True)
        binding = dict(self.binding, config_files=[*self.binding["config_files"], reference(path)])
        binding["resolved_sha256"] = fingerprint(resolved(binding))
        op["runtime"] = binding
        self.state["private_paths"].append({"path": str(path), "operation": op["id"], "disposition": "preserve", "sha256": digest(path.read_bytes())})
        self.save()
        return binding

    def add(self, kind, item, op):
        values = self.state["resources"][kind]
        previous = next((v for v in values if v["identity"] == item["identity"]), None)
        if previous:
            # Never upgrade reuse/unknown into ownership, or erase original creation.
            if kind == "containers" and previous["inspection_sha256"] != item["inspection_sha256"]:
                require(previous["operation"] == op["id"], "existing resource identity/configuration changed outside original startup")
                current = query(["docker", "inspect", item["identity"]], self.root)[0]
                originals = []
                for entry in op.get("observations", []):
                    value = json.loads(private(Path(entry["path"])))
                    if isinstance(value, dict):
                        originals.extend(value.get("containers", []) if isinstance(value.get("containers"), list) else [])
                        if value.get("original_creator"):
                            originals.append(value["original_creator"])
                original = next((v for v in originals if v["Id"] == item["identity"] and container_identity(v) == previous["inspection_sha256"]), None)
                require(original is not None and original["HostConfig"].get("OomKillDisable") in (None, False) and current["HostConfig"].get("OomKillDisable") in (None, False),
                        "creator configuration changed; retain original startup evidence")
                original["HostConfig"]["OomKillDisable"] = current["HostConfig"]["OomKillDisable"]
                require(container_identity(original) == item["inspection_sha256"] and any(step.get("status") == "returned" for step in op["steps"]),
                        "unexpected creator configuration drift; reconciliation held")
                previous["history"].append({"at": now(), "state": "startup-normalization", "before": previous["inspection_sha256"],
                                            "after": item["inspection_sha256"], "field": "HostConfig.OomKillDisable: engine null/false normalization"})
                previous["inspection_sha256"] = item["inspection_sha256"]
                previous["runtime"] = item["runtime"]
            require(previous["inspection_sha256"] == item["inspection_sha256"], "existing resource identity/configuration changed")
            previous["history"].append({"operation": op["id"], "state": "reused", "at": now(),
                                        "processes_sha256": item.get("processes_sha256")})
            if kind == "containers":
                previous["processes_sha256"] = item["processes_sha256"]
            elif kind == "volumes" and previous["creation"]["kind"] == "created" and not previous["creation"].get("container") and item["creation"].get("container"):
                previous["creation"].update(container=item["creation"]["container"], destination=item["creation"]["destination"],
                                            creator_source_revision=op["source"]["revision"])
                previous["history"].append({"at": now(), "state": "first-creator-observed", "operation": op["id"], "creator": item["creation"]["container"]})
                if previous["data"] == item["data"] == "disposable" and previous["authority"] == item["authority"]:
                    previous.update(disposition="remove", reason="original allocation plus first creator/mount observed; fresh guards remain required")
            return previous
        require(len(values) < self.state["policy"]["budget"][kind], "finite resource budget exhausted; reconcile allocated resource")
        item["consumers"] = None  # Creation evidence is not a fresh no-consumer assertion.
        item["history"] = [{"operation": op["id"], "state": item["creation"]["kind"], "at": now()}]
        item["operation"] = op["id"]
        item["dependencies"] = item.get("dependencies", [])
        values.append(item)
        return item

    def creation(self, op, created):
        return {"kind": "created" if created else "reused", "intent": op["intent"]["path"],
                "intent_sha256": op["intent"]["sha256"], "source_revision": op["source"]["revision"]}

    def volume_usage(self, names):
        unavailable = None
        try:
            rows = query(["docker", "system", "df", "--verbose", "--format", "{{json .Volumes}}"], self.root) if names else []
            require(isinstance(rows, list), "logical volume usage table unavailable")
        except (Hold, OSError, ValueError, subprocess.SubprocessError):
            # Docker's global size scan can be busy while independent worktrees run.
            # This metric is not creator/ownership proof. Preserve unknown, never zero;
            # retirement still requires the existing helper's fresh successful size read.
            rows, unavailable = [], "logical size unavailable at creation; fresh retirement observation still mandatory"
            print("Lifecycle warning: volume logical size unavailable; identities/mount proof retained; no size/reclaim claim", file=sys.stderr)
        return {name: {"docker_size": next((v["Size"] for v in rows if v["Name"] == name), None),
                       "links": next((v["Links"] for v in rows if v["Name"] == name), None), "unavailable": unavailable,
                       "observed_at": now(), "physical_reclaimed_bytes": None} for name in names}

    def creator_processes(self, container, op):
        if not container["State"]["Running"]:
            return None
        result = observe(["docker", "top", container["Id"], "-eo", "pid,ppid,args"], self.root, codes=(0, 1))
        if result.returncode:
            latest = query(["docker", "inspect", container["Id"]], self.root)[0]
            require(latest["Id"] == container["Id"] and not latest["State"]["Running"], "creator process visibility unavailable while running")
            op.setdefault("process_transitions", []).append({"container": container["Id"], "top_exit": result.returncode, "observed": "exited-before-top", "at": now()})
            container.clear()
            container.update(latest)
            return None
        return result.stdout

    def capture(self, op):
        """Synchronously retain creator evidence; labels corroborate original command, not names/deltas alone."""
        current = inventory(self.root)
        binding = op["runtime"]
        model = query([*compose(binding), "--profile", "*", "config", "--format", "json"], self.root)
        observation = {"operation": op["id"], "source": op["source"], "at": now(),
                       "containers": [], "networks": [], "volumes": [], "images": []}
        known = {v["identity"] for v in self.state["resources"]["containers"]}
        returned = op.get("returned_container")
        returned_proven = any(returned and returned in json.loads(private(Path(step["returned_output"]["path"]))).get("stdout", "")
                              for step in op["steps"] if step.get("returned_output"))
        creators = [v for v in current["containers"] if (v["Config"].get("Labels") or {}).get(LABEL) == op["id"] or
                    (returned_proven and v["Id"] == returned and v["Name"] == "/" + op.get("creator_name", "") and returned not in op["before"]["containers"])]
        implicit_profiles = sorted({profile for v in creators for profile in model["services"][v["Config"]["Labels"]["com.docker.compose.service"]].get("profiles", [])})
        if implicit_profiles:
            binding = dict(binding, profiles=sorted(set(binding.get("profiles", []) + implicit_profiles)))
            binding["resolved_sha256"] = fingerprint(resolved(binding))
            op["runtime"] = binding
            self.save()
        if op.get("returned_container"):
            require(any(v["Id"] == op["returned_container"] for v in creators), "returned creator unavailable; retain pending allocation")
        for container in creators:
            identifier = container["Id"]
            for old in self.state["resources"]["containers"]:
                if old["name"] == container["Name"] and old["identity"] != identifier and not any(v["Id"] == old["identity"] for v in current["containers"]):
                    old["history"].append({"at": now(), "state": "replaced", "by": identifier, "operation": op["id"]})
            labels = container["Config"]["Labels"]
            require(labels.get("com.docker.compose.project") == binding["project"] and
                    labels.get("com.docker.compose.project.working_dir") == str(self.root), "creator subject mismatch")
            service = labels["com.docker.compose.service"]
            policy = self.state["policy"]["services"].get(service)
            require(policy and policy["data"] in ("disposable", "durable", "unknown") and policy["authority"], "service data/authority missing")
            created = identifier not in op["before"]["containers"]
            require(created or identifier in known, "uninstrumented creator requires an evidence hold")
            processes = self.creator_processes(container, op)
            item = {"identity": identifier, "name": container["Name"], "service": service,
                    "inspection_sha256": container_identity(container), "processes_sha256": digest(processes) if processes else None,
                    "creation": self.creation(op, created), "ownership": "proven" if created else "unknown",
                    "data": policy["data"], "authority": policy["authority"], "disposition": "remove" if created and policy["data"] == "disposable" else "hold",
                    "reason": "original owning allocation; fresh consumers/publication still required" if created else "reused resource is not owned",
                    "consumers": [], "bind_data": {v["Source"]: "preserve" for v in container["Mounts"] if v["Type"] == "bind" and v.get("RW") and inside(Path(v["Source"]), self.root)},
                    "runtime": binding, "dependencies": []}
            observation["containers"].append(container)  # Private original; never print raw configuration/environment.
            for mount in container["Mounts"]:
                if mount["Type"] != "volume":
                    continue
                volume = next(v for v in current["volumes"] if v["Name"] == mount["Name"])
                declared = [v for v in model["services"][service].get("volumes", []) if v["target"] == mount["Destination"] and v["type"] == "volume"]
                external = bool(declared and declared[0].get("source") and model.get("volumes", {}).get(declared[0]["source"], {}).get("external"))
                absent = volume["Name"] not in op["before"]["volumes"]
                labelled = (volume.get("Labels") or {}).get(LABEL) == op["id"]
                # Anonymous volumes are corroborated by the returned exact creator's original mount and image declaration.
                anonymous = not declared or not declared[0].get("source")
                image = query(["docker", "image", "inspect", container["Config"]["Image"]], self.root)[0]
                declared_anonymous = mount["Destination"] in (image["Config"].get("Volumes") or {}) or bool(declared)
                owned = created and absent and not external and (labelled or (anonymous and declared_anonymous))
                creation = dict(self.creation(op, owned), container=identifier, destination=mount["Destination"])
                volume_item = {"identity": volume["Name"], "inspection_sha256": volume_identity(volume), "creation": creation,
                               "ownership": "proven" if owned else "unknown", "data": policy["data"], "authority": policy["authority"],
                               "disposition": "remove" if owned and policy["data"] == "disposable" else "hold",
                               "reason": "original creator/mount and pre-allocation absence" if owned else "shared/external or original allocation unknown",
                               "consumers": [], "dependencies": [identifier]}
                self.add("volumes", volume_item, op)
                observation["volumes"].append(volume)
                item["dependencies"].append(volume["Name"])
            for network in container["NetworkSettings"]["Networks"].values():
                if network.get("NetworkID"):
                    item["dependencies"].append(network["NetworkID"])
            self.add("containers", item, op)
            # Durable provenance matches the existing executor's schema, even after creator disappearance.
            self.document.setdefault("cleanup", {"version": 1}).setdefault("mount_provenance", {})[identifier] = {
                "container": identifier, "container_identity": item["inspection_sha256"], "image": container["Image"],
                "mounts": [{key: v.get(key) for key in ("Type", "Name", "Source", "Destination", "RW")} for v in container["Mounts"]],
                "runtime_sha256": binding["resolved_sha256"]}
        for network in current["networks"]:
            labels = network.get("Labels") or {}
            if labels.get("com.docker.compose.project") != binding["project"]:
                continue
            owned = labels.get(LABEL) == op["id"] and network["Id"] not in op["before"]["networks"]
            self.add("networks", {"identity": network["Id"], "name": network["Name"], "key": labels.get("com.docker.compose.network"),
                     "created": network["Created"], "inspection_sha256": network_identity(network), "creation": self.creation(op, owned),
                     "ownership": "proven" if owned else "unknown", "disposition": "remove" if owned else "hold",
                     "reason": "original labelled creation" if owned else "reused/shared original allocation unknown", "consumers": [], "runtime": binding}, op)
            observation["networks"].append(network)
        # Compose can allocate a declared volume before any container mounts it. Record
        # that fact now; later first-mount capture completes, never invents, its provenance.
        for volume in current["volumes"]:
            if (volume.get("Labels") or {}).get(LABEL) != op["id"] or volume["Name"] in op["before"]["volumes"]:
                continue
            if any(v["identity"] == volume["Name"] for v in self.state["resources"]["volumes"]):
                continue
            keys = {key for key, value in model.get("volumes", {}).items() if value.get("name") == volume["Name"] and not value.get("external")}
            services = [name for name, value in model["services"].items() if any(mount.get("source") in keys for mount in value.get("volumes", []))]
            policies = [self.state["policy"]["services"][name] for name in services]
            policy = policies[0] if len(policies) == 1 else {"data": "unknown", "authority": "multiple/unknown original data owners; hold"}
            self.add("volumes", {"identity": volume["Name"], "inspection_sha256": volume_identity(volume),
                     "creation": dict(self.creation(op, True), container=None, destination=None), "ownership": "proven",
                     "data": policy["data"], "authority": policy["authority"], "disposition": "hold", "consumers": [],
                     "reason": "original labelled allocation recorded; first creating-container mount not yet observed"}, op)
            observation["volumes"].append(volume)
        # Image use is not creation. Retain exact reused roots; never claim their shared layers or caches.
        for image_ref in sorted({v["Config"]["Image"] for v in creators}):
            image = query(["docker", "image", "inspect", image_ref], self.root)[0]
            self.add("images", {"identity": image["Id"], "inspection_sha256": fingerprint(image), "creation": self.creation(op, False),
                     "ownership": "unknown", "disposition": "retain", "reason": "explicitly reused image; no ownership of shared root/layers", "consumers": [],
                     "size_bytes": image["Size"], "references": sorted((image.get("RepoTags") or []) + (image.get("RepoDigests") or []))}, op)
            observation["images"].append(image)
        observation["volume_usage"] = self.volume_usage({v["Name"] for v in observation["volumes"]})
        for item in self.state["resources"]["volumes"]:
            if item["identity"] in observation["volume_usage"]:
                item["storage"] = observation["volume_usage"][item["identity"]]
        path = self.path.parent / ("observation-" + op["id"] + "-" + uuid.uuid4().hex + ".json")
        atomic(path, observation, exclusive=True)
        op.setdefault("observations", []).append(reference(path))
        if op["kind"] == "run":
            returned = next((step["returned_output"] for step in op["steps"] if step.get("returned_output") and
                             any(v["Id"] in json.loads(private(Path(step["returned_output"]["path"]))).get("stdout", "") for v in creators)), None)
            dispatch = next((step["dispatch"] for step in op["steps"] if step.get("dispatch")), None)
            if returned or dispatch:
                for creator in creators:
                    proof_path = self.path.parent / ("oneoff-proof-" + op["id"] + "-" + uuid.uuid4().hex + ".json")
                    atomic(proof_path, {"version": 1, "task": self.subject["task"], "origin": self.subject["origin"],
                        "operation": op["id"], "container": creator["Id"], "runtime_sha256": binding["resolved_sha256"],
                        "original_intent": op["intent"], "original_return": returned, "original_dispatch": dispatch,
                        "mode": "returned-id" if returned else "dispatch-reconciliation", "original_observation": reference(path)}, exclusive=True)
                    resource = next(v for v in self.state["resources"]["containers"] if v["identity"] == creator["Id"])
                    resource["oneoff_proof"] = reference(proof_path)
                    resource["history"].append({"at": now(), "state": "oneoff-creation-proof", "proof": reference(proof_path)})
        self.save()
        return creators

    def cache_snapshot(self, op, label):
        builder = self.state["policy"].get("builder")
        require(isinstance(builder, str) and re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.-]{0,63}", builder), "explicit already-running local Docker builder required before build")
        builders = observe(["docker", "buildx", "ls", "--format", "{{json .}}"], self.root).stdout
        rows = [json.loads(v) for v in builders.splitlines()]
        context = query(["docker", "context", "inspect"], self.root)
        require(len(context) == 1 and context[0]["Endpoints"]["docker"]["Host"].startswith("unix://") and
                (not os.environ.get("DOCKER_HOST") or os.environ.get("DOCKER_CONTEXT") or os.environ["DOCKER_HOST"] == context[0]["Endpoints"]["docker"]["Host"]),
                "builder/local CLI engine binding unavailable; no remote or fallback builder")
        matches = [value for value in rows if value["Name"] == builder]
        require(matches and all(value["Driver"] == "docker" and all(node["Status"] == "running" and node["Endpoint"] == context[0]["Name"] and node["IDs"] for node in value["Nodes"]) for value in matches),
                "builder is not the already-running same-context Docker worker; no bootstrap/allocation fallback")
        usage = observe(["docker", "buildx", "du", "--builder", builder, "--format", "json"], self.root, timeout=60).stdout
        path = self.path.parent / ("builder-" + op["id"] + "-" + label + ".json")
        value = {"builder": builder, "builders": matches, "context": context[0]["Name"],
                 "engine_id": query(["docker", "info", "--format", "{{json .}}"], self.root)["ID"],
                 "usage": [json.loads(v) for v in usage.splitlines()], "at": now()}
        require(len(value["usage"]) <= 4096, "builder observation exceeds finite record budget; retain pending operation")
        atomic(path, value, exclusive=True)
        op.setdefault("builder_observations", []).append(reference(path))
        self.save()
        return reference(path)

    def pull_image(self, image_ref):
        require(image_ref and not image_ref.startswith("-"), "one image reference, not a pull option, required")
        op = self.begin(["docker", "pull", image_ref], "pull-shared-image")
        images = self.state["resources"]["images"]
        # A local mutable tag can resolve differently when pulled. Only an exact
        # immutable reference, inspected and already represented, needs no slot.
        image, represented = None, False
        if re.fullmatch(r"[^@\s]+@sha256:[0-9a-f]{64}", image_ref):
            try:
                image = query(["docker", "image", "inspect", image_ref], self.root)[0]
                expected = image_ref.split("@", 1)[1]
                represented = (image["Id"] == expected or image_ref in (image.get("RepoDigests") or [])) and any(
                    value["identity"] == image["Id"] and value["inspection_sha256"] == fingerprint(image) for value in images)
            except (Hold, OSError, ValueError, KeyError, TypeError, IndexError, subprocess.SubprocessError):
                pass  # Unavailable/ambiguous identity reserves a new entry, never guessed reuse.
        required = len(images) + (0 if represented else 1)
        admission = self.path.parent / ("pull-admission-" + op["id"] + ".json")
        atomic(admission, {"operation": op["id"], "reference": image_ref, "inspection": image,
                           "represented": represented, "historical_entries": len(images), "required_entries": required,
                           "original_capacity": self.state["policy"]["budget"]["images"]}, exclusive=True)
        op["pull_admission"] = reference(admission)
        self.save()
        require(required <= self.state["policy"]["budget"]["images"], "image evidence budget insufficient before pull")
        result = self.step(op, ["docker", "pull", image_ref])
        sys.stdout.buffer.write(result.stdout)
        sys.stderr.write(sanitized(result.stderr, op["runtime"]))
        require(result.returncode == 0, "pull failed; original pending operation retained")
        self.image_use(op, image_ref)
        op.update(status="recorded", exit=0, outcome="shared-image-acquired")
        self.save()
        return 0

    def image_use(self, op, image_ref, created=False):
        image = query(["docker", "image", "inspect", image_ref], self.root)[0]
        require(not created or (image["Config"].get("Labels") or {}).get(LABEL) == op["id"], "build root lacks original operation binding")
        observation_id = op["id"] + "-" + uuid.uuid4().hex
        path = self.path.parent / ("image-" + observation_id + ".json")
        atomic(path, image, exclusive=True)
        allocation = self.path.parent / ("image-allocation-" + observation_id + ".json")
        atomic(allocation, {"source_revision": op["source"]["revision"], "operation": op["id"],
                           "root": image["Id"], "returned_iid": op.get("returned_iid"), "inspection_sha256": fingerprint(image),
                           "references": sorted((image.get("RepoTags") or []) + (image.get("RepoDigests") or []))}, exclusive=True)
        item = {"identity": image["Id"], "inspection_sha256": fingerprint(image), "creation": self.creation(op, created),
                "ownership": "proven" if created else "unknown", "disposition": "hold" if created else "retain",
                "reason": "created root; immutable reference retirement proof required" if created else "explicit shared/reused image policy; never remove by association",
                "consumers": [], "size_bytes": image["Size"], "data": "disposable" if created else "shared",
                "authority": self.state["policy"].get("image_authority", ""),
                "references": sorted((image.get("RepoTags") or []) + (image.get("RepoDigests") or []))}
        if created and item["authority"]:
            try:
                graph = image_root_graph(self.root, image)
                proof_path = self.path.parent / ("root-proof-" + observation_id + ".json")
                atomic(proof_path, {"version": 1, "task": self.subject["task"], "origin": self.subject["origin"],
                    "root": image["Id"], "data": "disposable", "authority": item["authority"], "original_image": reference(path),
                    "references": item["references"], "graph": graph,
                    "basis": {"source_revision": op["source"]["revision"], "evidence": [op["intent"], reference(allocation)]}}, exclusive=True)
                item.update(disposition="remove", reason="original created root/reference proof; fresh shared-child/consumer guards still apply",
                            retirement={"kind": "owned-root-references", "proof": reference(proof_path)})
            except Hold as error:
                item["reason"] = str(error)
        item["creation"]["local_acquisition"] = "shared-root-pull" if op["kind"] == "pull-shared-image" else "build" if created else "already-used-root"
        self.add("images", item, op)
        op.setdefault("observations", []).extend([reference(path), reference(allocation)])
        self.save()

    def record_builder_effect(self, op, before, after):
        item = next((v for v in self.state["resources"]["caches"] if v["identity"] == "builder-operation-" + op["id"]), None)
        if item is not None:
            item["history"].append({"at": now(), "state": "held", "observation": after})
        else:
            self.add("caches", {"identity": "builder-operation-" + op["id"], "inspection_sha256": fingerprint([before, after]),
                     "creation": {"kind": "unknown", "intent": op["intent"]["path"], "intent_sha256": op["intent"]["sha256"]},
                     "ownership": "unknown", "consumers": None, "disposition": "hold",
                     "reason": "exact build cache association/retirement unqualified; preserve original builder/worker and every observed ID for storage owner; never prune",
                     "observations": [before, after]}, op)
        op.setdefault("observations", []).extend([before, after])
        self.save()

    def prepare_build(self, options):
        require(self.state["policy"].get("image_authority") and self.state["policy"].get("builder"), "explicit build/discard authority and already-running builder binding missing")
        require(len(self.state["resources"]["caches"]) < self.state["policy"]["budget"]["caches"] and
                len(self.state["resources"]["images"]) < self.state["policy"]["budget"]["images"], "image/cache evidence budget exhausted before build")
        # Reject unsupported aliases/outputs before forwarding anything to Docker. One
        # explicit tag is the supported contract, not authority for subsequent outputs.
        options = list(options)
        require(options and not options[-1].startswith("--"), "explicit finite build context required")
        flags, index = {}, 0
        while index < len(options) - 1:
            flag = options[index]
            if flag in ("--pull=false", "--no-cache"):
                index += 1
                continue
            require(flag in ("--tag", "-t", "--file", "-f", "--build-arg", "--target") and index + 1 < len(options) - 1,
                    "unsupported build option/output alias; use one explicit --tag, --file and qualified inputs")
            key = {"-t": "--tag", "-f": "--file"}.get(flag, flag)
            require(key == "--build-arg" or key not in flags, "multiple build outputs/Dockerfiles/targets unsupported before effects")
            flags[key] = index
            index += 2
        require("--tag" in flags, "exact task-owned image tag required")
        tag_index = flags["--tag"] + 1
        tag = options[tag_index]
        require("@" not in tag, "build requires an explicit local tag, not a guessed digest reference")
        if tag.rfind(":") <= tag.rfind("/"):
            tag += ":latest"
            options[tag_index] = tag
        require(re.fullmatch(re.escape(self.binding["project"]) + r"-[a-z0-9][a-z0-9_.-]*:[A-Za-z0-9_][A-Za-z0-9_.-]{0,127}", tag),
                "build cannot replace foreign/shared or ambiguous image reference")
        prior = observe(["docker", "image", "inspect", tag], self.root, codes=(0, 1))
        require(prior.returncode == 1 and prior.stdout.strip() == b"[]" and b"No such image:" in prior.stderr,
                "image reference already present or lookup ambiguous; explicit replacement not qualified")
        require(options, "explicit finite build context required")
        options = list(options)
        input_data, input_records = None, []
        if options[-1] == "-":
            input_data = sys.stdin.buffer.read(1024 * 1024 + 1)
            require(0 < len(input_data) <= 1024 * 1024, "stdin Dockerfile must be nonempty and at most 1 MiB")
            input_path = self.path.parent / ("build-input-" + uuid.uuid4().hex + ".json")
            atomic(input_path, {"kind": "stdin-dockerfile", "sha256": digest(input_data), "bytes_hex": input_data.hex()}, exclusive=True)
            input_records.append(reference(input_path))
        else:
            context = absolute(str((self.root / options[-1]).resolve()))
            require(inside(context, self.root) and context.is_dir(), "only checkout-local explicit build contexts are qualified")
            options[-1] = str(context)
            file_flag = "--file" if "--file" in options else "-f" if "-f" in options else None
            dockerfile = absolute(str((self.root / options[options.index(file_flag) + 1]).resolve())) if file_flag else context / "Dockerfile"
            require(inside(dockerfile, self.root) and dockerfile.is_file(), "original Dockerfile unavailable")
            if file_flag:
                options[options.index(file_flag) + 1] = str(dockerfile)
            input_path = self.path.parent / ("build-input-" + uuid.uuid4().hex + ".json")
            atomic(input_path, {"kind": "context-dockerfile", "path": str(dockerfile), "sha256": digest(dockerfile.read_bytes()),
                                "bytes_hex": dockerfile.read_bytes().hex(), "context": str(context), "source": source(self.root)}, exclusive=True)
            input_records.append(reference(input_path))
        dockerfile_text = bytes.fromhex(json.loads(private(Path(input_records[0]["path"]))) ["bytes_hex"]).decode()
        require(not re.search(r"(?im)^\s*#\s*syntax\s*=", dockerfile_text), "custom build frontend needs its own qualified original allocation capability")
        build_args = {}
        for index, value in enumerate(options):
            if value == "--build-arg":
                require(index + 1 < len(options) and "=" in options[index + 1], "explicit build argument values required")
                key, value = options[index + 1].split("=", 1)
                build_args[key] = value
        defaults = dict((match[0], match[1]) for match in re.findall(r"(?m)^\s*ARG\s+([A-Za-z_][A-Za-z0-9_]*)=(.*)$", dockerfile_text))
        defaults.update(build_args)
        def expanded(value):
            value = re.sub(r"\$\{([A-Za-z_][A-Za-z0-9_]*)\}|\$([A-Za-z_][A-Za-z0-9_]*)", lambda match: defaults.get(match[1] or match[2], "$unresolved"), value)
            require("$" not in value and "\\" not in value, "unresolved/multiline build image source; refuse implicit allocation")
            return value
        stages, bases = set(), set()
        for line in dockerfile_text.splitlines():
            if re.match(r"(?i)^\s*FROM\s+", line):
                parts = line.split()
                require(len(parts) in (2, 4) and not parts[1].startswith("--"), "unsupported FROM/platform input; no implicit pull")
                base_image = expanded(parts[1])
                if base_image.lower() != "scratch" and base_image not in stages:
                    bases.add(base_image)
                if len(parts) == 4:
                    require(parts[2].upper() == "AS", "unknown build stage declaration")
                    stages.add(parts[3])
        for value in re.findall(r"--from=([^\s]+)", dockerfile_text):
            value = expanded(value)
            if value not in stages and not value.isdigit():
                bases.add(value)
        require(not any(re.fullmatch(r"sha256:[a-f0-9]{64}", value) for value in bases),
                "raw root IDs are not Dockerfile image references; builder may interpret them as remote repositories")
        base_images = [{"reference": value, "inspection": query(["docker", "image", "inspect", value], self.root)[0]} for value in sorted(bases)]
        known_images = {value["identity"] for value in self.state["resources"]["images"]}
        require(len(known_images | {value["inspection"]["Id"] for value in base_images}) + 1 <= self.state["policy"]["budget"]["images"], "base/output image evidence budget exhausted before build")
        base_path = self.path.parent / ("build-bases-" + uuid.uuid4().hex + ".json")
        atomic(base_path, base_images, exclusive=True)
        input_records.append(reference(base_path))
        return {"options": options, "tag": tag, "input_data": input_data, "input_records": input_records,
                "base_images": base_images, "base_path": base_path}

    def qualify_build_inputs(self, plan):
        for entry in plan["input_records"]:
            require(reference(Path(entry["path"])) == entry, "original prepared build evidence changed")
        record = json.loads(private(Path(plan["input_records"][0]["path"])))
        if record["kind"] == "context-dockerfile":
            require(source(self.root) == record["source"] and digest(Path(record["path"]).read_bytes()) == record["sha256"],
                    "prepared build source/Dockerfile changed; preserve original inputs, no effect/replay")
        require(all(query(["docker", "image", "inspect", value["reference"]], self.root)[0]["Id"] == value["inspection"]["Id"]
                    for value in plan["base_images"]), "prepared base reference changed; no implicit acquisition/replacement")

    def build_image(self, options, prepared=None):
        plan = prepared if prepared is not None else self.prepare_build(options)
        self.qualify_build_inputs(plan)
        options, tag = plan["options"], plan["tag"]
        input_data, input_records = plan["input_data"], plan["input_records"]
        base_images, base_path = plan["base_images"], plan["base_path"]
        # Requalify absence after multi-service preflight, immediately before dispatch.
        prior = observe(["docker", "image", "inspect", tag], self.root, codes=(0, 1))
        require(prior.returncode == 1 and prior.stdout.strip() == b"[]" and b"No such image:" in prior.stderr,
                "qualified output reference appeared before dispatch; no overwrite")
        op = self.begin(["docker", "build", *options, "original-input-record=" + input_records[0]["path"], "original-input-sha256=" + input_records[0]["sha256"],
                         "original-bases-record=" + str(base_path), "original-bases-sha256=" + input_records[1]["sha256"]], "build")
        op["image_reference"] = tag
        op["build_inputs"] = input_records
        self.save()
        for value in base_images:
            self.image_use(op, value["reference"])
        before = self.cache_snapshot(op, "before")
        self.qualify_build_inputs(plan)
        iid = self.path.parent / ("iid-" + op["id"])
        op["iidfile"] = str(iid)
        self.save()
        result = self.step(op, ["docker", "build", "--builder", self.state["policy"]["builder"], "--iidfile", str(iid), "--label", LABEL + "=" + op["id"], *options], capture=False, input_data=input_data)
        after = self.cache_snapshot(op, "after")
        original_builder = json.loads(private(Path(before["path"])))
        current_builder = json.loads(private(Path(after["path"])))
        require(original_builder["engine_id"] == current_builder["engine_id"] and
                sorted({identifier for value in original_builder["builders"] for node in value["Nodes"] for identifier in node["IDs"]}) ==
                sorted({identifier for value in current_builder["builders"] for node in value["Nodes"] for identifier in node["IDs"]}), "builder/worker engine changed during effect; preserve original uncertain allocation")
        # A delta never establishes cache ownership; preserve the exact complete observations.
        self.record_builder_effect(op, before, after)
        require(result.returncode == 0 and iid.exists(), "build failed/uncertain; reconcile exact iid/operation, never repeat build")
        self.qualify_build_inputs(plan)
        require(all(query(["docker", "image", "inspect", value["reference"]], self.root)[0]["Id"] == value["inspection"]["Id"] for value in base_images),
                "base image reference drift; preserve original build output, never invent actual inputs")
        op["returned_iid"] = iid.read_text().strip()
        self.image_use(op, tag, created=True)
        op.update(status="recorded", exit=result.returncode, outcome="completed-cache-held")
        self.state["cache_policy"] = {"state": "builder-observed-association-held", "reason": "exact storage capability remains separately owned"}
        self.save()
        return result.returncode

    def standalone_preflight(self, options):
        """Bound supported standalone storage/network effects before docker create."""
        require(self.state["policy"].get("oneoff_authority"), "original standalone data/discard authority required")
        flags = {"--user", "--cap-drop", "--security-opt", "--pids-limit", "--memory", "--cpus", "--tmpfs",
                 "--shm-size", "--env", "-e", "--mount", "--workdir", "--network", "--publish", "--entrypoint"}
        index, network, covered, mounts, pull_never = 0, "bridge", set(), [], False
        while index < len(options) and options[index].startswith("-"):
            token = options[index]
            if token in ("--rm", "--read-only", "--pull=never"):
                pull_never |= token == "--pull=never"
                index += 1
                continue
            flag, equals, value = token.partition("=")
            require(flag in flags, "unsupported standalone option/allocation form before create")
            if not equals:
                require(index + 1 < len(options), "missing standalone option value")
                index += 1
                value = options[index]
            require(value, "empty standalone option value")
            if flag == "--network":
                require(network == "bridge" and not any(v[0] == "network" for v in mounts), "multiple standalone networks unsupported")
                network = value
                mounts.append(("network", value))
            elif flag == "--mount":
                fields = value.split(",")
                require(all(fields) and len({v.split("=", 1)[0] for v in fields}) == len(fields), "ambiguous mount fields")
                values = dict(v.split("=", 1) if "=" in v else (v, "") for v in fields)
                require(set(values) <= {"type", "source", "target", "readonly"} and values.get("type") == "bind",
                        "only explicit existing bind mounts supported; volume/driver allocation must be qualified separately")
                require(values.get("source") and values.get("target", "").startswith("/") and Path(values["source"]).is_absolute()
                        and Path(values["source"]).exists(), "bind source must exist before create; no implicit host allocation")
                covered.add(values["target"])
                mounts.append(("bind", values["source"], values["target"]))
            elif flag == "--tmpfs":
                require(value.startswith("/"), "explicit absolute tmpfs destination required")
                covered.add(value.split(":", 1)[0])
            elif flag in ("--env", "-e"):
                require("=" in value, "standalone env must not inherit ambient values")
            index += 1
        require(pull_never and index < len(options), "explicit --pull=never and image required before create")
        image = query(["docker", "image", "inspect", options[index]], self.root)[0]
        anonymous = sorted(set(image["Config"].get("Volumes") or {}) - covered)
        networks = []
        if network not in ("none", "host"):
            require(not network.startswith("container:"), "foreign container network namespace unsupported")
            networks = query(["docker", "network", "inspect", network], self.root)
            require(len(networks) == 1, "exact existing network required; never create a fallback")
        budget = self.state["policy"]["budget"]
        resources = self.state["resources"]
        require(len(resources["containers"]) + 1 <= budget["containers"], "container evidence budget exhausted before create")
        require(len(resources["volumes"]) + len(anonymous) <= budget["volumes"], "anonymous volume evidence budget exhausted before create")
        require(len({v["identity"] for v in resources["images"]} | {image["Id"]}) <= budget["images"], "image evidence budget exhausted before create")
        require(len({v["identity"] for v in resources["networks"]} | {v["Id"] for v in networks}) <= budget["networks"], "network evidence budget exhausted before create")
        path = self.path.parent / ("standalone-preflight-" + uuid.uuid4().hex + ".json")
        atomic(path, {"image": image, "networks": networks, "anonymous_destinations": anonymous, "mounts": mounts,
                      "historical_counts": {kind: len(resources[kind]) for kind in ("containers", "volumes", "images", "networks")}}, exclusive=True)
        normalized = list(options)
        normalized[index] = image["Id"]
        return normalized, reference(path)

    def standalone_run(self, options):
        self.complete()
        options, preflight = self.standalone_preflight(options)
        op = self.begin(["docker", "run", *options, "original-preflight=" + preflight["path"], "original-preflight-sha256=" + preflight["sha256"]], "standalone-run")
        op["standalone_preflight"] = preflight
        name = self.subject["task"].lower() + "-" + op["id"]
        op["creator_name"] = name
        self.save()
        result = self.step(op, ["docker", "create", "--name", name, "--label", LABEL + "=" + op["id"], *[v for v in options if v != "--rm"]])
        require(result.returncode == 0 and re.fullmatch(rb"[a-f0-9]{64}", result.stdout.strip()), "standalone create failed; retain exact pending name/operation")
        op["returned_container"] = result.stdout.decode().strip()
        self.save()
        self.capture_standalone(op)
        result = self.step(op, ["docker", "start", "--attach", op["returned_container"]], capture=False)
        self.capture_standalone(op)
        op.update(status="recorded", exit=result.returncode, outcome="completed" if result.returncode == 0 else "failed")
        self.save()
        return result.returncode

    def capture_standalone(self, op):
        current = query(["docker", "inspect", op.get("returned_container", op["creator_name"])], self.root)[0]
        require((current["Config"].get("Labels") or {}).get(LABEL) == op["id"] and current["Id"] not in op["before"]["containers"], "pending standalone creator lacks original allocation binding")
        op["returned_container"] = current["Id"]
        image = query(["docker", "image", "inspect", current["Config"]["Image"]], self.root)[0]
        declarations, volume_items, volume_observations = [], [], []
        for mount in current["Mounts"]:
            if mount["Type"] != "volume":
                continue
            volume = query(["docker", "volume", "inspect", mount["Name"]], self.root)[0]
            volume_observations.append(volume)
            absent = volume["Name"] not in op["before"]["volumes"]
            declared = mount["Destination"] in (image["Config"].get("Volumes") or {})
            owned = absent and declared
            declarations.append({"type": "volume", "target": mount["Destination"], "allocated_name": mount["Name"], "created_absent": owned})
            volume_items.append({"identity": mount["Name"], "inspection_sha256": volume_identity(volume),
                "creation": dict(self.creation(op, owned), container=current["Id"], destination=mount["Destination"]),
                "ownership": "proven" if owned else "unknown", "data": "disposable", "authority": self.state["policy"].get("oneoff_authority", ""),
                "consumers": [], "disposition": "remove" if owned else "hold", "reason": "original short-lived creator/declaration/mount" if owned else "merely mounted storage; allocation unproven",
                "dependencies": [current["Id"]]})
        networks = {}
        for name, attached in current["NetworkSettings"]["Networks"].items():
            if name in ("none", "host"):
                continue
            network = query(["docker", "network", "inspect", attached["NetworkID"] or name], self.root)[0]
            networks[network["Id"]] = {"name": network["Name"], "created": network["Created"], "inspection_sha256": network_identity(network), "creation": "reused"}
        for identifier, network in networks.items():
            self.add("networks", {"identity": identifier, "name": network["name"], "inspection_sha256": network["inspection_sha256"],
                "creation": self.creation(op, False), "ownership": "unknown", "disposition": "retain",
                "reason": "explicit existing shared network; no creation/removal authority", "dependencies": [current["Id"]]}, op)
        processes = self.creator_processes(current, op)
        proof = {"name": current["Name"], "image": current["Image"], "inspection_sha256": container_identity(current),
                 "mounts_sha256": fingerprint(current["Mounts"]), "volume_declarations": declarations,
                 "workload": {"kind": "oneoff", "processes_sha256": digest(processes) if processes else None}}
        allocation = self.path.parent / ("standalone-allocation-" + op["id"] + "-" + uuid.uuid4().hex + ".json")
        atomic(allocation, {"source_revision": op["source"]["revision"], "returned_id": current["Id"], "proof": proof,
                            "volume_declarations_sha256": fingerprint(declarations), "original_creator": current, "image": image, "networks": networks,
                            "volumes": volume_observations, "volume_usage": self.volume_usage({v["Name"] for v in volume_observations})}, exclusive=True)
        record = {"version": 1, "task": self.subject["task"], "origin": self.subject["origin"],
                  "source": {"revision": op["source"]["revision"], "workload": "bounded owning one-off"},
                  "data": "disposable", "authority": self.state["policy"].get("oneoff_authority", ""),
                  "basis": {"kind": "allocation", "evidence": [op["intent"], reference(allocation)]},
                  "containers": {current["Id"]: proof}, "networks": networks}
        require(record["authority"], "one-off data/discard authority missing; retain original creator")
        record_path = self.path.parent / ("standalone-" + op["id"] + "-" + uuid.uuid4().hex + ".json")
        atomic(record_path, record, exclusive=True)
        binding = {"kind": "standalone", "ownership": "proven", "creation_record": reference(record_path), "resolved_sha256": fingerprint(record)}
        item = {"identity": current["Id"], "name": current["Name"], "service": "oneoff", "inspection_sha256": container_identity(current),
                "creation": self.creation(op, True), "ownership": "proven", "data": "disposable", "authority": record["authority"],
                "consumers": [], "disposition": "remove", "reason": "original owning one-off; only stopped workload retirement qualified",
                "processes_sha256": proof["workload"]["processes_sha256"], "runtime": binding,
                "bind_data": {v["Source"]: "preserve" for v in current["Mounts"] if v["Type"] == "bind" and v.get("RW") and inside(Path(v["Source"]), self.root)}}
        previous = self.add("containers", item, op)
        # The second observation retains the original immutable creator and a new stopped-workload binding.
        binding = previous["runtime"]
        for volume in volume_items:
            self.add("volumes", volume, op)
        self.document.setdefault("cleanup", {"version": 1}).setdefault("mount_provenance", {})[current["Id"]] = {
            "container": current["Id"], "container_identity": item["inspection_sha256"], "image": current["Image"],
            "mounts": [{key: v.get(key) for key in ("Type", "Name", "Source", "Destination", "RW")} for v in current["Mounts"]],
            "runtime_sha256": binding["resolved_sha256"]}
        if not op.get("observations"):
            self.image_use(op, current["Config"]["Image"])
        op.setdefault("observations", []).extend([reference(allocation), reference(record_path)])
        self.save()

    def compose_budget(self, op, binding, services, dependencies):
        model = resolved(binding)
        selected = set(services)
        if dependencies:
            frontier = list(selected)
            while frontier:
                service = frontier.pop()
                for dependency in model["services"][service].get("depends_on", {}):
                    if dependency not in selected:
                        selected.add(dependency)
                        frontier.append(dependency)
        budget = self.state["policy"]["budget"]
        require(len(self.state["resources"]["containers"]) + len(selected) <= budget["containers"], "container budget insufficient before allocation")
        # The bound limits evidence entries, not only newly allocated storage.
        # Compose may initialize declared internal volumes before mounting them;
        # all used external/reused names also need room in the same history.
        named = {v["identity"] for v in self.state["resources"]["volumes"]}
        named.update(v["name"] for v in model.get("volumes", {}).values() if not v.get("external"))
        anonymous, images = 0, set()
        for name in selected:
            service = model["services"][name]
            image = query(["docker", "image", "inspect", service.get("image", binding["project"] + "-" + name)], self.root)[0]
            images.add(image["Id"])
            mounts = service.get("volumes", [])
            named.update(model["volumes"][v["source"]]["name"] for v in mounts if v["type"] == "volume" and v.get("source"))
            covered = {v["target"] for v in mounts if v.get("source") or v["type"] != "volume"}
            covered.update(value.split(":", 1)[0] for value in service.get("tmpfs", []))
            anonymous += len((set(image["Config"].get("Volumes") or {}) | {v["target"] for v in mounts if v["type"] == "volume" and not v.get("source")}) - covered)
        require(len(named) + anonymous <= budget["volumes"], "volume budget insufficient before creation")
        require(len({v["identity"] for v in self.state["resources"]["images"]} | images) <= budget["images"], "image evidence budget insufficient before creation")
        network_names = {line.split(" ", 1)[1] for line in text(["docker", "network", "ls", "--format", "{{.ID}} {{.Name}}"], self.root).splitlines()}
        missing = {v["name"] for v in model.get("networks", {}).values() if not v.get("external") and v["name"] not in network_names}
        require(len(self.state["resources"]["networks"]) + len(missing) <= budget["networks"], "network budget insufficient before creation")

    def service_closure(self, model, services, dependencies):
        selected, frontier = set(services), list(services)
        require(selected and selected <= set(model["services"]), "explicit enabled service selection required")
        while dependencies and frontier:
            service = frontier.pop()
            for dependency, contract in model["services"][service].get("depends_on", {}).items():
                if dependency not in model["services"] and isinstance(contract, dict) and not contract.get("required", True):
                    continue
                require(dependency in model["services"], "required dependency not enabled by actual profiles")
                if dependency not in selected:
                    selected.add(dependency)
                    frontier.append(dependency)
        require(len(selected) <= 8, "finite selected dependency budget exceeded")
        for name in selected:
            service = model["services"][name]
            require(not service.get("links") and not service.get("volumes_from") and
                    not any(str(service.get(key, "")).startswith("service:") for key in ("network_mode", "ipc", "pid")),
                    "implicit namespace/link dependencies unsupported; declare qualified depends_on inputs")
            require(service.get("scale", 1) == 1 and service.get("deploy", {}).get("replicas", 1) == 1,
                    "scaled allocation needs its own finite creator budget qualification")
        return sorted(selected)

    def compose_command(self, argv, profiles):
        action, options = argv[0], argv[1:]
        target = None
        if action == "exec":
            index = 0
            while index < len(options) and options[index].startswith("-"):
                flag = options[index]
                if flag == "-T":
                    index += 1
                else:
                    require(flag in ("--user", "-u", "--workdir", "-w", "--env", "-e") and index + 1 < len(options),
                            "unsupported exec option/detach; finite attached command required")
                    if flag in ("--env", "-e"):
                        require("=" in options[index + 1], "explicit exec env value required")
                    index += 2
            require(index + 1 < len(options) and "-T" in options[:index], "explicit noninteractive exec service/command required")
            target = options[index]
        op = self.begin(["docker", "compose", *profiles, *argv], "compose-command")
        op["command_action"] = action
        self.save()
        if target is not None:
            identifiers = text([*compose(self.binding), *profiles, "ps", "--all", "--quiet", target], self.root).splitlines()
            require(identifiers, "original exec target unavailable")
            containers = query(["docker", "inspect", *identifiers], self.root)
            known = {v["identity"]: v for v in self.state["resources"]["containers"]}
            require(all(v["Id"] in known and container_identity(v) == known[v["Id"]]["inspection_sha256"] for v in containers),
                    "exec target identity unrecorded/changed")
            op["command_containers"] = [{"id": v["Id"], "inspection_sha256": container_identity(v)} for v in containers]
        self.save()
        result = self.step(op, [*compose(self.binding), *profiles, *argv], capture=False)
        require(action != "exec" or result.returncode >= 0, "exec CLI terminated by signal; daemon workload outcome remains pending")
        op.update(status="recorded", exit=result.returncode, outcome="completed" if result.returncode == 0 else "failed")
        self.save()
        return result.returncode

    def execute_compose(self, argv):
        require(argv, "missing compose command")
        profiles = []
        while argv and argv[0] == "--profile":
            require(len(argv) > 2, "missing profile/command")
            profiles += argv[:2]
            argv = argv[2:]
        action, options = argv[0], argv[1:]
        if action not in ("up", "create", "run"):
            require(action in ("config", "ps", "port", "logs", "exec", "top", "version", "images"),
                    "unsupported allocation/retirement command; use qualified lifecycle capability, never generic teardown/build/pull")
            return self.compose_command(argv, profiles)
        require(not any(v in options for v in ("--force-recreate", "--renew-anon-volumes", "-V", "--remove-orphans")),
                "implicit replacement/removal refused; use recorded generation replacement")
        self.complete()
        selection_binding = dict(self.binding, profiles=sorted(set(self.binding.get("profiles", []) + profiles[1::2])))
        model = resolved(selection_binding)
        services = []
        if action != "run":
            index = 0
            while index < len(options):
                value = options[index]
                if value in model["services"]:
                    services.append(value)
                elif value in ("--detach", "-d", "--build", "--no-build", "--wait", "--no-deps"):
                    require(value != "--no-deps" or action == "up", "create dependency suppression unsupported by Compose; no effects")
                elif value in ("--wait-timeout", "--pull"):
                    require(index + 1 < len(options), "missing startup option value")
                    index += 1
                    require((value == "--pull" and options[index] == "never") or
                            (value == "--wait-timeout" and options[index].isdigit() and 0 < int(options[index]) <= 120),
                            "unsupported pull/wait policy")
                else:
                    raise Hold("unsupported Compose startup option/service; no implicit effects")
                index += 1
            services = self.service_closure(model, services, dependencies="--no-deps" not in options)
        if "--build" in options:
            require(action != "run" and "--no-build" not in options, "explicit startup --build only; conflicting/oneoff builds unsupported")
            plans = []
            # Preflight the whole enabled closure before the first build, not just
            # explicitly named services. Each output remains absent/task-authorized.
            for service in services:
                build = model["services"][service].get("build")
                if build:
                    require(isinstance(build, dict) and set(build) <= {"context", "dockerfile", "args", "target"}, "unsupported Compose build inputs")
                    build_args = ["--tag", model["services"][service].get("image", self.binding["project"] + "-" + service),
                                  "--file", str(Path(build["context"]) / build.get("dockerfile", "Dockerfile"))]
                    for key, value in build.get("args", {}).items():
                        require(value is not None, "ambient Compose build arguments unsupported")
                        build_args += ["--build-arg", key + "=" + str(value)]
                    if build.get("target"):
                        build_args += ["--target", build["target"]]
                    plans.append(self.prepare_build([*build_args, build["context"]]))
            budget = self.state["policy"]["budget"]
            images = {v["identity"] for v in self.state["resources"]["images"]} | {v["inspection"]["Id"] for plan in plans for v in plan["base_images"]}
            require(len({plan["tag"] for plan in plans}) == len(plans), "multiple services target the same build output; unsupported replacement")
            require(len(images) + len(plans) <= budget["images"] and len(self.state["resources"]["caches"]) + len(plans) <= budget["caches"]
                    and len(self.state["operations"]) + len(plans) + 1 <= budget["operations"], "closure build evidence budget insufficient before effects")
            for plan in plans:
                self.build_image(plan["options"], prepared=plan)
            options = [v for v in options if v != "--build"]
        op = self.begin(["docker", "compose", *profiles, *argv], action)
        binding = self.generation(op)
        if profiles:
            binding["profiles"] = sorted(set(binding.get("profiles", []) + profiles[1::2]))
            binding["resolved_sha256"] = fingerprint(resolved(binding))
            op["runtime"] = binding
            self.save()
        base = compose(binding)
        if action == "run":
            # An explicit task/operation name plus the returned ID survives interruption. Never --rm.
            require(not any(v.startswith("--name") or v in ("-d", "--detach") for v in options), "one-off name/detach is lifecycle-owned")
            all_model = query([*compose(binding), "--profile", "*", "config", "--format", "json"], self.root)
            index = 0
            while index < len(options) and options[index].startswith("-"):
                flag = options[index]
                if flag in ("--user", "-u", "-e", "--env", "--entrypoint", "--workdir", "-w"):
                    require(index + 1 < len(options), "missing one-off option value")
                    if flag in ("-e", "--env"):
                        require("=" in options[index + 1], "inline env requires an explicit value, not ambient inheritance")
                    index += 2
                else:
                    require(flag in ("--rm", "--no-deps", "-T"), "unsupported one-off option; refuse before allocation")
                    index += 1
            require(index < len(options) and options[index] in all_model["services"], "explicit known one-off service required")
            selected = options[index]
            implicit = all_model["services"][selected].get("profiles", [])
            if implicit:
                binding = dict(binding, profiles=sorted(set(binding.get("profiles", []) + implicit)))
                binding["resolved_sha256"] = fingerprint(resolved(binding))
                op["runtime"] = binding
                base = compose(binding)
            options = [v for v in options[:index] if v != "--rm"] + options[index:]
            name = self.subject["task"].lower() + "-" + op["id"]
            op["creator_name"] = name
            self.compose_budget(op, binding, [selected], dependencies=False)
            result = self.step(op, [*base, "run", "--detach", "--no-deps", "--pull", "never", "--name", name, *options])
            if result.returncode == 0:
                identifiers = re.findall(r"\b[a-f0-9]{64}\b", result.stdout.decode())
                require(len(identifiers) == 1, "returned container ID unavailable; reconcile pending creator from retained original output")
                op["returned_container"] = identifiers[0]
                self.save()
            creators = self.capture(op)
            require(result.returncode == 0 and len(creators) == 1, "setup failed; observed partial allocation retained for reconciliation")
            waited = self.step(op, ["docker", "wait", op["returned_container"]])
            require(waited.returncode == 0 and waited.stdout.strip().isdigit(), "one-off outcome unavailable")
            code = int(waited.stdout.strip())
            # Logs are the command's ordinary output, not resource evidence/configuration.
            logged = self.step(op, ["docker", "logs", op["returned_container"]], capture=False)
            require(logged.returncode == 0, "original workload logs unavailable; retain operation")
            self.capture(op)
            op.update(status="recorded", exit=code, outcome="completed" if code == 0 else "failed")
        else:
            op["selected_services"] = services
            self.save()
            self.compose_budget(op, binding, services, dependencies=False)
            # Compose create has no --no-deps. up --no-start is its supported
            # stopped-creator equivalent when the caller suppresses dependencies.
            creation = ["up", "--no-start", "--no-deps"] if "--no-deps" in options else ["create"]
            result = self.step(op, [*base, *creation, "--no-build", "--pull", "never", *services])
            self.capture(op)
            require(result.returncode == 0, "create failed; original partial allocation retained")
            code = 0
            if action == "up":
                started = self.step(op, [*base, "start", "--wait", "--wait-timeout", "120", *services])
                code = started.returncode
                self.capture(op)
            op.update(status="recorded", exit=code, outcome="completed" if code == 0 else "failed")
            self.state["runtime"] = binding
            self.binding = binding
        self.save()
        return code

    def paths(self, paths):
        targets = [absolute(path) for path in paths]
        require(1 <= len(targets) <= 16 and all(inside(path, self.root) and path != self.root for path in targets), "bounded checkout-local directory preparation only")
        allowed = [Path(v["path"]) for v in self.state["private_paths"]]
        require(all(any(inside(path, parent) or inside(parent, path) for parent in allowed) for path in targets), "path missing original private-path intent")
        op = self.begin(["prepare-paths", *[str(v) for v in targets]], "paths")
        planned = sorted({parent for target in targets for parent in [target, *target.parents] if inside(parent, self.root) and parent != self.root}, key=lambda v: len(v.parts))
        op["planned_paths"] = [{"path": str(path), "existed": path.exists()} for path in planned]
        self.save()
        records = []
        for path in planned:
            existed = path.exists()
            if not existed:
                path.mkdir(mode=0o700)
            require(path.is_dir() and not path.is_symlink(), "directory path changed or unsafe")
            records.append({"path": str(path), "state": "reused" if existed else "created", "record": file_record(path), "disposition": "preserve"})
        evidence = self.path.parent / ("paths-" + op["id"] + ".json")
        atomic(evidence, records, exclusive=True)
        op.update(status="recorded", outcome="directories-prepared", observations=[reference(evidence)])
        self.save()

    def qualify_image(self, identifier):
        item = next(value for value in self.state["resources"]["images"] if value["identity"] == identifier)
        require(item["ownership"] == "proven" and item["creation"]["kind"] == "created" and item["data"] == "disposable", "only originally proven created disposable images can gain a retirement capability")
        op = next(value for value in self.state["operations"] if value["id"] == item["operation"])
        require(reference(Path(op["intent"]["path"])) == op["intent"], "original intent changed")
        intent = json.loads(private(Path(op["intent"]["path"])))
        require(intent["subject"]["task"] == self.subject["task"] and intent["subject"]["origin"] == self.subject["origin"] and
                intent["kind"] == "build" and intent["policy"]["image_authority"] == item["authority"], "original creation subject/data authority unavailable; never retrofit ownership")
        original, allocation = None, None
        for entry in op["observations"]:
            require(reference(Path(entry["path"])) == entry, "original image observation changed")
            value = json.loads(private(Path(entry["path"])))
            if isinstance(value, dict) and value.get("Id") == identifier and fingerprint(value) == item["inspection_sha256"]:
                original = entry
            if isinstance(value, dict) and value.get("root") == identifier and value.get("source_revision") == op["source"]["revision"] and value.get("inspection_sha256") == item["inspection_sha256"]:
                allocation = entry
        require(original is not None and allocation is not None, "original full image/allocation observation missing; preserve hold")
        current = query(["docker", "image", "inspect", identifier], self.root)[0]
        require(fingerprint(current) == item["inspection_sha256"] and (current["Config"].get("Labels") or {}).get(LABEL) == op["id"], "original immutable image/reference set changed")
        argv = intent["argv"]
        flag = "--tag" if "--tag" in argv else "-t"
        requested = argv[argv.index(flag) + 1]
        normalized = requested if requested.rfind(":") > requested.rfind("/") else requested + ":latest"
        references = sorted((current.get("RepoTags") or []) + (current.get("RepoDigests") or []))
        require(current["RepoTags"] == [normalized] and references == item["references"] and
                all(value == normalized.rsplit(":", 1)[0] + "@" + identifier for value in current.get("RepoDigests") or []),
                "additional/foreign/digest naming relationship requires original explicit owner proof")
        graph = image_root_graph(self.root, current)
        path = self.path.parent / ("qualified-root-" + op["id"] + "-" + uuid.uuid4().hex + ".json")
        atomic(path, {"version": 1, "task": self.subject["task"], "origin": self.subject["origin"], "root": identifier,
            "data": "disposable", "authority": item["authority"], "original_image": original, "references": references, "graph": graph,
            "basis": {"source_revision": op["source"]["revision"], "evidence": [op["intent"], original, allocation]}}, exclusive=True)
        item["history"].append({"at": now(), "state": "retirement-capability-qualified", "prior_disposition": item["disposition"], "proof": reference(path)})
        item.update(disposition="remove", reason="original creation/naming authority unchanged; newly qualified immutable representation, fresh retirement guards mandatory",
                    retirement={"kind": "owned-root-references", "proof": reference(path)})
        op["observations"].append(reference(path))
        self.save()  # Existing cleanup plans/attempts are untouched; explicit plan --refresh is required.

    def rebind(self, spec_path):
        spec = json.loads(private(spec_path))
        require(re.fullmatch(r"task" + self.subject["task"][5:] + r"-[a-z0-9-]{1,40}", spec["project"]), "replacement project not task-isolated")
        binding = {"kind": "compose", "ownership": "proven", "project": spec["project"], "working_directory": str(self.root)}
        for key in ("config_files", "env_files"):
            require(isinstance(spec[key], list) and len(spec[key]) <= 8, "explicit replacement inputs required")
            binding[key] = [{"path": str(absolute(path)), "sha256": digest(Path(path).read_bytes())} for path in spec[key]]
        require(binding["config_files"], "replacement configuration required")
        model = resolved(binding)
        all_model = query([*compose(binding), "--profile", "*", "config", "--format", "json"], self.root)
        require(set(self.state["policy"]["services"]) >= set(all_model["services"]), "replacement cannot introduce unclassified services")
        binding["resolved_sha256"] = fingerprint(model)
        op = self.begin(["rebind", str(spec_path), digest(private(spec_path))], "runtime-rebind")
        op.update(status="recorded", outcome="bound-no-allocation", observations=[reference(spec_path)], replacement_runtime=binding)
        self.state.setdefault("runtime_history", []).append(self.binding)
        self.state["runtime"] = binding
        self.state["private_paths"].extend(spec["private_paths"])
        self.binding = binding
        self.save()

    def reconcile(self, identifier):
        op = next(v for v in self.state["operations"] if v["id"] == identifier)
        require(op["status"] == "pending", "operation already recorded; never replay allocations")
        require(reference(Path(op["intent"]["path"])) == op["intent"], "original intent changed/unavailable")
        self.original_commands_ended(op, require_outcome=op["kind"] in ("up", "create", "build", "pull-shared-image"))
        require(not any(step.get("outcome") == "supervision-expired" for step in op["steps"]) or
                op["kind"] in ("run", "standalone-run", "compose-command"),
                "daemon allocation/start outcome unresolved after CLI timeout; retain pending original operation")
        if not op["steps"] and op["kind"] == "compose-command":
            op.update(status="recorded", outcome="failed-before-docker-effect", observations=[op["intent"]], reconciled_at=now())
            self.save()
            return
        if op["kind"] == "compose-command":
            stopped_observations = []
            if op["command_action"] == "exec" and any(step.get("status") != "returned" or step.get("outcome") == "supervision-expired" or
                                                       step.get("exit", 0) < 0 for step in op["steps"]):
                require(op.get("command_containers"), "original exec target identity unavailable; remote workload remains held")
                for entry in op["command_containers"]:
                    current = query(["docker", "inspect", entry["id"]], self.root)[0]
                    require(container_identity(current) == entry["inspection_sha256"] and not current["State"]["Running"],
                            "exec daemon workload outcome unknown; wait for exact original container to exit, never stop unrelated consumers")
                    stopped_observations.append({"id": current["Id"], "inspection_sha256": container_identity(current), "state": current["State"]})
            path = self.path.parent / ("command-reconciled-" + op["id"] + "-" + uuid.uuid4().hex + ".json")
            atomic(path, {"operation": op["id"], "original_exit": "unknown", "at": now(),
                          "original_steps": [{key: step.get(key) for key in ("process", "dispatch", "returned_output", "status", "exit", "outcome")} for step in op["steps"]],
                          "process_recovery": op.get("process_recovery", []), "stopped_containers": stopped_observations,
                          "qualification": "original CLI absent; exact exec containers stopped or command has no daemon workload"}, exclusive=True)
            op.setdefault("observations", []).append(reference(path))
            op.update(status="recorded", outcome="reconciled-command; original exit unknown", reconciled_at=now())
            self.save()
            return
        if not op["steps"] and op["kind"] in ("run", "up", "create", "standalone-run", "build", "pull-shared-image"):
            # This owning implementation persists a step BEFORE every Docker effect.
            # A pre-step inspection failure cannot have dispatched allocation.
            op.update(status="recorded", outcome="failed-before-docker-effect", observations=[op["intent"]], reconciled_at=now())
            self.save()
            return
        if op["kind"] == "pull-shared-image":
            image_ref = op["argv"][2]
            require(isinstance(image_ref, str) and "@sha256:" in image_ref, "uncertain mutable pull needs original digest evidence; never repeat allocation or invent the original root")
            image = query(["docker", "image", "inspect", image_ref], self.root)[0]
            expected = image_ref.split("@", 1)[1]
            require(image["Id"] == expected or any(v.endswith("@" + expected) for v in image.get("RepoDigests", [])), "original pinned pull root unavailable/changed")
            self.image_use(op, image_ref)
            op.update(status="recorded", outcome="reconciled-shared-pinned-root; original CLI exit may remain unknown", reconciled_at=now())
            self.save()
            return
        if op["kind"] == "build":
            require(op.get("builder_observations"), "original pre-build builder identity missing; no retrospective cache ownership")
            before = op["builder_observations"][0]
            after = self.cache_snapshot(op, "reconcile-" + uuid.uuid4().hex)
            self.record_builder_effect(op, before, after)
            iid = Path(op["iidfile"])
            if iid.exists():
                bases = json.loads(private(Path(op["build_inputs"][-1]["path"])))
                require(all(query(["docker", "image", "inspect", value["reference"]], self.root)[0]["Id"] == value["inspection"]["Id"] for value in bases),
                        "original base image binding changed; preserve output and recover original builder input proof")
                op["returned_iid"] = private(iid).decode().strip()
                self.image_use(op, op["image_reference"], created=True)
                op.update(status="recorded", outcome="reconciled-build-cache-held", reconciled_at=now())
            else:
                require(op["steps"][-1].get("status") == "returned" and op["steps"][-1].get("exit", 0) != 0,
                        "original build outcome unknown and iid unavailable; preserve pending operation, never repeat build")
                op.update(status="recorded", outcome="failed-build-cache-held", reconciled_at=now())
            self.save()
            return
        if op["kind"] == "standalone-run":
            self.capture_standalone(op)
            current = query(["docker", "inspect", op["returned_container"]], self.root)[0]
            require(not current["State"]["Running"], "original workload still running; no reallocation")
            op.update(status="recorded", outcome="reconciled-partial", exit=current["State"]["ExitCode"], reconciled_at=now())
            self.save()
            return
        if op["kind"] == "paths":
            require(op.get("planned_paths") and not op["steps"], "original directory intent missing")
            records = []
            for entry in op["planned_paths"]:
                path = absolute(entry["path"])
                require(inside(path, self.root) and path != self.root, "directory intent escaped checkout")
                if not path.exists():
                    require(not entry["existed"], "original reused directory disappeared; recover through its owner")
                    path.mkdir(mode=0o700)
                require(path.is_dir() and not path.is_symlink(), "directory identity changed; preserve partial preparation")
                records.append({"path": str(path), "state": "reused" if entry["existed"] else "created-or-reconciled",
                                "record": file_record(path), "disposition": "preserve"})
            path = self.path.parent / ("paths-reconciled-" + op["id"] + "-" + uuid.uuid4().hex + ".json")
            atomic(path, records, exclusive=True)
            op.setdefault("observations", []).append(reference(path))
            op.update(status="recorded", outcome="directories-reconciled", reconciled_at=now())
            self.save()
            return
        creators = self.capture(op)
        # Original labels and retained creators establish bounded observation, never a new create.
        if op.get("creator_name") and creators:
            matches = [v for v in creators if v["Name"] == "/" + op["creator_name"]]
            require(len(matches) == 1, "exact pending creator unavailable; no duplicate allocation")
            require(not matches[0]["State"]["Running"], "original workload still running; wait outside this operation")
            op["returned_container"] = matches[0]["Id"]
            op["exit"] = matches[0]["State"]["ExitCode"]
        if not creators:
            current = inventory(self.root)
            require(op["steps"] and all(step.get("status") == "returned" for step in op["steps"]) and op["steps"][-1].get("exit", 0) != 0 and
                    {v["Id"] for v in current["containers"]} == set(op["before"]["containers"]) and
                    {v["Id"] for v in current["networks"]} == set(op["before"]["networks"]) and
                    {v["Name"]: volume_identity(v) for v in current["volumes"]} == op["before"]["volumes"] and
                    current["images"] == op["before"]["images"],
                    "no corroborated creator and uncertain effects; retain pending operation")
            op.update(status="recorded", outcome="failed-observed-absent", exit=op["steps"][-1]["exit"], reconciled_at=now())
            self.save()
            return
        op.update(status="recorded", outcome="reconciled-partial", reconciled_at=now())
        self.save()

    def resume(self, identifier):
        op = next(v for v in self.state["operations"] if v["id"] == identifier)
        require(op["kind"] == "up" and (op["status"] == "pending" or op.get("exit") != 0), "only incomplete original startup can resume")
        self.original_commands_ended(op, require_outcome=True)
        require(not any(step.get("outcome") == "supervision-expired" for step in op["steps"]),
                "original daemon startup outcome unresolved; no duplicate start")
        require(op["steps"] and op["steps"][0].get("exit") == 0, "original create outcome uncertain; reconcile before startup")
        creators = self.capture(op)
        require(creators, "original creators unavailable; never reallocate on resume")
        model = resolved(op["runtime"])
        services = op.get("selected_services") or [v for v in op["argv"] if isinstance(v, str) and v in model["services"]]
        require(services, "original service selection unavailable")
        result = self.step(op, [*compose(op["runtime"]), "start", "--wait", "--wait-timeout", "120", *services])
        self.capture(op)
        op.update(status="recorded", exit=result.returncode, outcome="resumed" if result.returncode == 0 else "failed")
        self.state["runtime"] = op["runtime"]
        self.binding = op["runtime"]
        self.save()
        return result.returncode

    def cancel(self, identifier):
        require(self.state["policy"].get("cancel_authority"), "explicit original workload cancellation authority missing")
        op = next(v for v in self.state["operations"] if v["id"] == identifier)
        require(op["kind"] in ("run", "standalone-run"), "only an original owning one-off can be cancelled")
        self.original_commands_ended(op, cancelling_wait=True)
        identifier = op.get("returned_container")
        item = next((v for v in self.state["resources"]["containers"] if v["identity"] == identifier and v["operation"] == op["id"]), None)
        require(item and item["ownership"] == "proven" and item["data"] == "disposable", "original exact disposable creator evidence required")
        current = query(["docker", "inspect", identifier], self.root)[0]
        require(container_identity(current) == item["inspection_sha256"] and not current["HostConfig"].get("AutoRemove"), "creator changed/reused; preserve workload")
        result = self.step(op, ["docker", "stop", "--time", "10", identifier]) if current["State"]["Running"] else None
        require(result is None or result.returncode == 0, "cancellation uncertain; retain original operation")
        if op["kind"] == "standalone-run":
            self.capture_standalone(op)
        else:
            self.capture(op)
        current = query(["docker", "inspect", identifier], self.root)[0]
        require(not current["State"]["Running"], "original workload still running")
        for _ in range(20):
            try:
                self.original_commands_ended(op)
                break
            except Hold:
                time.sleep(.25)
        self.original_commands_ended(op)  # No completeness release while even the original wait is live/unknown.
        op.update(status="recorded", outcome="cancelled", exit=current["State"]["ExitCode"], cancelled_at=now())
        self.save()

    def verification_return(self):
        """Prove the active immutable return without granting allocation or removal."""
        cleanup = self.document["cleanup"]
        entry = cleanup.get("verification_return")
        require(entry and reference(Path(entry["path"])) == entry, "plan frozen; explicit return-to-verification required")
        event = json.loads(private(Path(entry["path"])))
        require(event["version"] in (1, 2) and event["kind"] == "return-to-verification" and event["subject"] == self.subject,
                "verification transition subject/version changed")
        original = event["original_receipt"]
        require(reference(Path(original["path"])) == original, "original transition snapshot changed")
        previous = json.loads(private(Path(original["path"])))
        require(verify_plan_history(cleanup) == event["plan_sha256"], "suspended plan/history/attempts changed")
        if event["version"] == 2:
            _, predecessor = verification_snapshot(event["predecessor"], cleanup["plan"])
            self.verification_allocation(predecessor["lifecycle"])
        self.verification_allocation(previous["lifecycle"])
        return entry

    def verification_allocation(self, old):
        """Preserve original allocation, budget and uncertainty across verification returns."""
        require(old["subject"] == self.subject and old["runtime"] == self.binding and old["policy"] == self.state["policy"] and
                self.state["operations"][:len(old["operations"])] == old["operations"], "original allocation/runtime/budget changed")
        for kind in KINDS:
            require(len(self.state["resources"][kind]) == len(old["resources"][kind]), "verification cannot allocate resources")
            for before, after in zip(old["resources"][kind], self.state["resources"][kind]):
                require({k: v for k, v in before.items() if k != "history"} == {k: v for k, v in after.items() if k != "history"} and
                        after["history"][:len(before["history"])] == before["history"], "original resource state/unknowns changed")

    def recover_verification(self, receipt_sha256, legacy_revision, protected_history):
        """Bind one fully corroborated legacy early-hold append to an audited successor."""
        original_bytes = private(self.path)
        require(digest(original_bytes) == receipt_sha256, "incident receipt changed; requalify exact evidence")
        cleanup = self.document["cleanup"]
        predecessor = cleanup["verification_return"]
        event, before = verification_snapshot(predecessor, cleanup["plan"])
        require(event["version"] == 1 and "published_setup_failure" in event["publication_observations"],
                "only original published no-effect suspension can recover")
        self.verification_allocation(before["lifecycle"])
        mutable = {"operations", "stages", "resources", "processes", "monitor_attempts"}
        require({k: v for k, v in self.state.items() if k not in mutable} ==
                {k: v for k, v in before["lifecycle"].items() if k not in mutable} and
                all(self.state[key][:len(before["lifecycle"][key])] == before["lifecycle"][key]
                    for key in ("stages", "processes", "monitor_attempts")), "original private state/history changed")
        require({k: v for k, v in cleanup.items() if k not in ("attempts", "verification_return")} ==
                {k: v for k, v in before["cleanup"].items() if k != "attempts"} and
                len(cleanup["attempts"]) == len(before["cleanup"]["attempts"]) + 1 and
                cleanup["attempts"][:-1] == before["cleanup"]["attempts"], "not an exact single legacy append")
        require(re.fullmatch(r"[a-f0-9]{40}", legacy_revision), "exact legacy Git revision required")
        observe(["git", "merge-base", "--is-ancestor", legacy_revision, cleanup["plan"]["oid"]], self.root)
        observe(["git", "merge-base", "--is-ancestor", cleanup["plan"]["oid"], "HEAD"], self.root)
        legacy_bytes = observe(["git", "show", legacy_revision + ":.pi/skills/land/scripts/cleanup.py"], self.root).stdout
        require(digest(legacy_bytes) == LEGACY_NO_EFFECT_OPERATION, "unqualified legacy source revision")
        images = {}
        for item in cleanup["plan"]["images"]:
            result = observe(["docker", "image", "inspect", item["identity"]], self.root)
            require(not result.stderr.strip(), "legacy image observation ambiguous")
            images[item["identity"]] = result.stdout
        qualify_legacy_append(cleanup["plan"], cleanup["attempts"][-1], legacy_bytes, images)
        require(datetime.fromisoformat(cleanup["attempts"][-1]["time"]) > datetime.fromisoformat(event["at"]),
                "legacy append predates suspension")
        # Capture is revalidated without saving or dispatching a workload.
        self.validate("verification-recovery", persist=False)
        require(reference(Path(self.state["original_spec"]["path"])) == self.state["original_spec"], "original spec changed")
        workers = self.verification_workers(cleanup["plan"])
        require(workers["original_monitor"] == event["original_monitor"] and workers["exit_records"] == event["exit_records"],
                "original worker evidence changed")
        reports = []
        require(isinstance(protected_history, dict), "explicit original protected-report locators required")
        for report in cleanup["plan"]["protected_files"]:
            current = file_record(absolute(report["path"]))
            if current != report["record"]:
                require(report["path"] in protected_history, "changed protected evidence lacks original retained record")
                archive = absolute(protected_history[report["path"]])
                require(file_record(archive) == report["record"], "original protected report not preserved")
                reports.append({"path": report["path"], "original": reference(archive), "current_record": current})
        require(set(protected_history) == {entry["path"] for entry in reports}, "extra/unknown protected replacement")
        # Corroborate present versus previously nonlisted resources using the last
        # immutable pre-incident noncreation intent, never a retrospective empty list.
        baseline = self.state["operations"][-1]
        intent = json.loads(private(Path(baseline["intent"]["path"])))
        require(reference(Path(baseline["intent"]["path"])) == baseline["intent"] and
                baseline["kind"] == intent["kind"] == "compose-command" and baseline["status"] == "recorded" and
                intent["subject"] == self.subject and intent["runtime"] == self.binding and
                intent["before"] == baseline["before"] and
                datetime.fromisoformat(intent["at"]) < datetime.fromisoformat(cleanup["attempts"][-1]["time"]),
                "original pre-incident resource census unavailable")
        current = inventory(self.root)
        for kind, key in (("containers", "Id"), ("networks", "Id"), ("volumes", "Name")):
            known = {v["identity"] for v in self.state["resources"][kind]}
            require({v[key] for v in current[kind]} & known == set(intent["before"][kind]) & known,
                    "resource presence changed since original pre-incident observation")
        for item in self.state["resources"]["images"]:
            require(item["identity"] in images and fingerprint(json.loads(images[item["identity"]])[0]) == item["inspection_sha256"],
                    "recorded reused image changed")
        identifier = uuid.uuid4().hex
        def retain(label, raw):
            path = self.path.parent / ("verification-recovery-" + identifier + "-" + label)
            atomic(path, raw, exclusive=True)
            return reference(path)
        successor = dict(event, version=2, at=now(), source=source(self.root), predecessor=predecessor,
            original_receipt=retain("original.json", original_bytes),
            legacy_source={"revision": legacy_revision, "record": retain("legacy.py", legacy_bytes)},
            image_observations={key: retain("image-" + str(index) + ".json", raw) for index, (key, raw) in enumerate(images.items())},
            protected_history=reports, **workers)
        successor["identity_observation"] = {"baseline_intent": baseline["intent"], "current": retain("inventory.json", current)}
        successor_path = self.path.parent / ("verification-recovery-" + identifier + ".json")
        atomic(successor_path, successor, exclusive=True)
        # Independently qualify the immutable successor before publishing its binding.
        verification_snapshot(reference(successor_path), cleanup["plan"])
        cleanup["verification_return"] = reference(successor_path)
        self.verification_return()
        self.save()

    def verification_workers(self, plan):
        """Observe original bound Land exits and both absent, unreused process identities."""
        attempt_path = absolute(plan["original_land_attempt"])
        require(inside(attempt_path, Path(self.subject["safe_checkout"]) / ".runs/build" / self.subject["task"]) and
                (attempt_path / "dispatch.lock").is_dir(), "original Land monitor scope/evidence missing")
        original_monitor = [entry for entry in self.state.get("monitor_attempts", []) if entry["stage"] == "land" and entry["attempt"] == str(attempt_path)]
        require(len(original_monitor) == 1 and reference(attempt_path / "process.json") == original_monitor[0]["original_process_record"],
                "original Land process binding changed/missing")
        processes = json.loads(private(attempt_path / "process.json"))
        pids = [processes[key] for key in ("monitor_pid", "pi_pid")]
        require(len(set(pids)) == 2 and all(type(pid) is int and pid > 0 for pid in pids) and
                sorted(entry["pid"] for entry in plan["exited_workers"]) == sorted(pids), "original workers not bound")
        exits = []
        for name in ("exit-code", "pi-exit-code"):
            path = attempt_path / name
            require(re.fullmatch(r"-?\d+", private(path).decode().strip()), "original worker exit unavailable")
            exits.append(reference(path))
        workers = [process_identity(pid, self.root) for pid in pids]
        require(all(entry["exit"] == 1 and entry["visibility"] == "absent" and not entry["observation"] for entry in workers),
                "original worker live/reused/visibility unknown")
        return {"workers": workers, "original_monitor": original_monitor[0], "exit_records": exits}

    def failed_setup_publication(self, plan, run_id):
        """Observe the exact still-open published candidate's completed setup failure."""
        publication = plan["publication"]
        repository = publication["repository"]
        require(type(run_id) is int and run_id > 0 and publication["delivery_verified"] is True and
                type(publication["number"]) is int and publication["number"] > 0 and
                publication["url"] == f"https://github.com/{repository}/pull/{publication['number']}" and
                publication["merge_oid"] is None and publication["merged_at"] is None,
                "exact published-open failed-setup subject required")
        require(plan["oid"] == text(["git", "rev-parse", "HEAD"], self.root), "failed setup is not the current local head")
        remote = text(["git", "ls-remote", "--heads", "origin", self.subject["ref"]], self.root)
        require(remote == plan["oid"] + "\t" + self.subject["ref"], "published failed-setup remote/head changed")
        pr = query(["gh", "pr", "view", publication["url"], "--repo", repository, "--json",
                    "url,number,state,isDraft,baseRefName,baseRefOid,headRefName,headRefOid,headRepository,headRepositoryOwner,mergedAt,mergeCommit"], self.root)
        owner, name = repository.split("/")
        base_ref = "refs/heads/" + publication["base"]
        require(publication["base_ref"] in (base_ref, "refs/remotes/origin/" + publication["base"]), "ambiguous failed-setup base ref")
        require(pr["url"] == publication["url"] and pr["number"] == publication["number"] and pr["state"] == "OPEN" and
                pr["isDraft"] is False and pr["baseRefName"] == publication["base"] and
                pr["headRefName"] == self.subject["ref"].removeprefix("refs/heads/") and pr["headRefOid"] == plan["oid"] and
                pr["headRepository"]["name"] == name and pr["headRepositoryOwner"]["login"] == owner and
                pr["mergedAt"] is None and pr["mergeCommit"] is None, "failed-setup PR identity/state changed")
        base_remote = text(["git", "ls-remote", "--heads", "origin", base_ref], self.root)
        require(re.fullmatch(r"[0-9a-f]{40}", pr["baseRefOid"]) and
                base_remote == pr["baseRefOid"] + "\t" + base_ref and
                text(["git", "rev-parse", publication["base_ref"]], self.root) == pr["baseRefOid"], "failed-setup base changed/unavailable")
        run = query(["gh", "api", f"repos/{repository}/actions/runs/{run_id}"], self.root)
        require(run["id"] == run_id and run["event"] == "pull_request" and run["status"] == "completed" and
                run["conclusion"] == "failure" and run["head_sha"] == plan["oid"] and run["head_branch"] == pr["headRefName"] and
                run["path"] == ".github/workflows/build.yml" and run["repository"]["full_name"] == repository and
                run["head_repository"]["full_name"] == repository, "current-head hosted setup failure unavailable")
        require(len(run["pull_requests"]) == 1 and run["pull_requests"][0]["number"] == publication["number"] and
                run["pull_requests"][0]["head"]["sha"] == plan["oid"] and
                run["pull_requests"][0]["head"]["ref"] == pr["headRefName"] and
                run["pull_requests"][0]["base"]["sha"] == pr["baseRefOid"] and
                run["pull_requests"][0]["base"]["ref"] == publication["base"], "hosted PR head/base mismatch")
        jobs = query(["gh", "api", f"repos/{repository}/actions/runs/{run_id}/jobs?per_page=100"], self.root)
        require(jobs["total_count"] == 1 and len(jobs["jobs"]) == 1, "single owning setup/gate job required; incomplete coverage held")
        job = jobs["jobs"][0]
        require(type(job["id"]) is int and job["id"] > 0 and job["run_id"] == run_id and job["head_sha"] == plan["oid"] and
                job["status"] == "completed" and job["conclusion"] == "failure" and
                all(step["status"] == "completed" and step["conclusion"] in ("success", "failure", "skipped") for step in job["steps"]),
                "hosted job pending/unknown or mismatched")
        for name, conclusion in (("Explicit locked tooling and dependency setup", "failure"), ("Run the sole canonical gate", "skipped")):
            steps = [step for step in job["steps"] if step["name"] == name]
            require(len(steps) == 1 and steps[0]["conclusion"] == conclusion, "owning setup failure/gate skip not established")
        return {"pr": pr, "run": run, "jobs": jobs, "remote": remote, "base_remote": base_remote}

    def return_to_verification(self, expected_hash, failed_setup_run=None):
        """Suspend a finished no-effect plan; published return needs exact live setup-failure proof."""
        cleanup = self.document["cleanup"]
        require(not cleanup.get("verification_return"), "already returned; reuse the SAME verification binding")
        require(set(cleanup) <= {"version", "mount_provenance", "plan", "plan_sha256", "lifecycle_sha256", "attempts", "plan_history"},
                "unknown cleanup state; no return authority")
        require(cleanup["version"] == 1 and verify_plan_history(cleanup) == expected_hash, "wrong original plan hash/version")
        plan = cleanup["plan"]
        for candidate in [entry["plan"] for entry in cleanup.get("plan_history", [])] + [plan]:
            require(all(candidate[key] == self.subject[key] for key in ("task", "safe_checkout", "git_common_dir", "origin", "ref")) and
                    candidate["worktree"]["identity"] == str(self.root) and candidate["branch"]["identity"] == self.subject["ref"] and
                    candidate["runtime"] == self.binding, "original plan subject/runtime mismatch")
            publication = candidate["publication"]
            unpublished = publication["delivery_verified"] is False and all(publication[key] is None for key in ("url", "number", "merge_oid", "merged_at"))
            require(unpublished or (failed_setup_run is not None and publication == plan["publication"] and candidate["oid"] == plan["oid"]),
                    "published/delivery-qualified or ambiguous plan cannot return without exact failed-setup proof")
            require(not candidate.get("pending_operations"), "original pending allocation must be reconciled first")
            for item in [candidate["worktree"], candidate["branch"], *[item for kind in KINDS for item in candidate[kind]]]:
                require(item.get("consumers") in (None, []), "known original consumer prerequisite unresolved")
        attempts = cleanup.get("attempts")
        require(isinstance(attempts, list) and attempts, "finished non-destructive preview required")
        hashes = {expected_hash, *[entry["plan_sha256"] for entry in cleanup.get("plan_history", [])]}
        for attempt in attempts:
            require(set(attempt) == {"time", "apply", "operation_sha256", "plan_sha256", "results", "complete"} and
                    type(attempt["apply"]) is bool and (attempt["apply"] is False or
                        (failed_setup_run is not None and attempt["plan_sha256"] == expected_hash)) and
                    attempt["complete"] is False and attempt["plan_sha256"] in hashes,
                    "apply/effect/unknown cleanup attempt; return held")
            results = attempt["results"]
            attempt_plan = next((entry["plan"] for entry in cleanup.get("plan_history", []) if entry["plan_sha256"] == attempt["plan_sha256"]), plan)
            expected_results = [(kind[:-1], item["identity"]) for kind in KINDS for item in attempt_plan[kind]] + [
                ("worktree", attempt_plan["worktree"]["identity"]), ("ref", attempt_plan["branch"]["identity"])]
            require(sorted((entry["kind"], entry["identity"]) for entry in results[:-1]) == sorted(expected_results),
                    "preview resource coverage missing/contradictory")
            require(results and results[-1]["kind"] == "final-verification" and results[-1]["status"] == "failed",
                    "preview interruption/completion unknown; return held")
            for result in results:
                require(set(result) <= {"kind", "identity", "status", "commands", "recovery", "reason", "creation", "storage"} and
                        (result["status"] in ("held", "retained") or result is results[-1]),
                        "effect/unknown preview outcome; return held")
                for command in result.get("commands", []):
                    argv = command["argv"]
                    require(argv[:3] in (["git", "worktree", "list"], ["git", "remote", "get-url"], ["git", "rev-parse", "--path-format=absolute"],
                                          ["docker", "image", "inspect"]) or
                            (argv and argv[0] in ("ps", "ls", "xattr") and (argv[0] != "xattr" or len(argv) == 2 or argv[1] == "-p")),
                            "preview command not qualified read-only; return held")
        original_bytes = private(self.path)
        # Validate original immutable capture and current creator/storage identities.
        # No intermediate receipt write: failed guards preserve the original bytes.
        self.validate("return-verification", persist=False)
        require(reference(Path(self.state["original_spec"]["path"])) == self.state["original_spec"], "original spec changed")
        for report in plan["protected_files"]:
            require(file_record(Path(report["path"])) == report["record"], "protected original changed")
        worker_evidence = self.verification_workers(plan)
        repository = plan["publication"]["repository"]
        require(re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", repository) and
                self.subject["origin"] in (f"git@github.com:{repository}.git", f"https://github.com/{repository}.git"), "hosting subject mismatch")
        if failed_setup_run is not None:
            publication_observations = {"published_setup_failure": self.failed_setup_publication(plan, failed_setup_run)}
        else:
            remote = observe(["git", "ls-remote", "--heads", "origin", self.subject["ref"]], self.root)
            prs = observe(["gh", "pr", "list", "--repo", repository, "--state", "all", "--head", self.subject["ref"].removeprefix("refs/heads/"),
                           "--limit", "1", "--json", "url"], self.root)
            require(not remote.stdout.strip() and json.loads(prs.stdout) == [], "subject already published; return held")
            publication_observations = {"remote_stdout_sha256": digest(remote.stdout), "prs_stdout_sha256": digest(prs.stdout)}
        identifier = uuid.uuid4().hex
        snapshot = self.path.parent / ("verification-original-" + identifier + ".json")
        atomic(snapshot, original_bytes, exclusive=True)
        event = self.path.parent / ("verification-return-" + identifier + ".json")
        atomic(event, {"version": 1, "kind": "return-to-verification", "at": now(), "subject": self.subject,
            "source": source(self.root), "plan_sha256": expected_hash, "original_receipt": reference(snapshot),
            **worker_evidence, "publication_observations": publication_observations,
            "authority": "supervised SAME-runtime verification only; no allocation/removal; explicit fresh qualified Land plan required"}, exclusive=True)
        cleanup["verification_return"] = reference(event)
        self.save()  # Atomic exclusion of stale cleanup and admission of verification on SAME persistent lock.

    def validate(self, stage, attempt=None, persist=True):
        self.complete()
        require(len(self.state["stages"]) < 128, "finite stage-observation budget exhausted")
        pid, seen = os.getpid(), set()
        for _ in range(8):
            if pid <= 0 or pid in seen:
                break
            seen.add(pid)
            identity = process_identity(pid, self.root)
            cwd = observe(["lsof", "-a", "-p", str(pid), "-d", "cwd", "-Fn"], self.root, codes=(0, 1))
            record = dict(identity, cwd_observation=cwd.stdout.decode().strip(), visibility_exit=cwd.returncode,
                          stage=stage, at=now(), disposition="protected", creation="reused/not claimed")
            if not any(v.get("observation") == identity["observation"] and v.get("pid") == pid for v in self.state["processes"]):
                self.state["processes"].append(record)
            fields = identity["observation"].split()
            pid = int(fields[1]) if identity["exit"] == 0 and len(fields) >= 2 else 0
        if attempt is not None:
            attempt = absolute(attempt)
            require(inside(attempt, Path(self.subject["safe_checkout"]) / ".runs/build" / self.subject["task"]), "wrong original monitor attempt scope")
            process_file = attempt / "process.json"
            processes = json.loads(private(process_file))
            self.state.setdefault("monitor_attempts", []).append({"stage": stage, "attempt": str(attempt), "original_process_record": reference(process_file),
                "identities": [process_identity(processes[key], self.root) for key in ("monitor_pid", "pi_pid")], "disposition": "protected; no termination authority"})
        for op in self.state["operations"]:
            require(reference(Path(op["intent"]["path"])) == op["intent"], "original intent unavailable")
            for entry in op.get("builder_observations", []) + op.get("build_inputs", []):
                require(reference(Path(entry["path"])) == entry, "original builder observation changed")
            for step in op["steps"]:
                if step.get("dispatch"):
                    require(reference(Path(step["dispatch"]["path"])) == step["dispatch"], "original dispatch evidence changed")
                if step.get("returned_output"):
                    require(reference(Path(step["returned_output"]["path"])) == step["returned_output"], "original returned identity output unavailable")
            require(op.get("observations"), "required creation observation missing")
            for entry in op["observations"]:
                require(reference(Path(entry["path"])) == entry, "original observation unavailable/changed")
        current = inventory(self.root)
        known = {v["identity"] for v in self.state["resources"]["containers"]}
        for item in self.state["resources"]["containers"]:
            match = next((v for v in current["containers"] if v["Id"] == item["identity"]), None)
            if match:
                require(container_identity(match) == item["inspection_sha256"], "runtime generation changed; explicit replacement evidence required")
            else:
                replacements = {event["by"] for event in item["history"] if event["state"] == "replaced"}
                require(not any(v["Name"] == item["name"] and v["Id"] not in replacements for v in current["containers"]), "creator name reused by unknown replacement")
                item["history"].append({"at": now(), "state": "absent", "stage": stage})
        known = {v["identity"] for v in self.state["resources"]["containers"]}
        require(not any((v["Config"].get("Labels") or {}).get("com.docker.compose.project") == self.binding["project"] and v["Id"] not in known for v in current["containers"]), "uninstrumented project allocation; hold advancement")
        for kind, key, identity in (("networks", "Id", network_identity), ("volumes", "Name", volume_identity)):
            known = {value["identity"] for value in self.state["resources"][kind]}
            for item in self.state["resources"][kind]:
                match = next((value for value in current[kind] if value[key] == item["identity"]), None)
                require(match is None or identity(match) == item["inspection_sha256"], "recorded storage/network identity changed; hold advancement")
            require(not any((value.get("Labels") or {}).get("com.docker.compose.project") == self.binding["project"] and value[key] not in known
                            for value in current[kind]), "uninstrumented project storage/network allocation; hold advancement")
        self.state["stages"].append({"stage": stage, "at": now(), "source": source(self.root),
                                     "session": {"id": os.environ.get("PI_SESSION_ID"), "file": os.environ.get("PI_SESSION_FILE"),
                                                 "provider": os.environ.get("PI_PROVIDER"), "model": os.environ.get("PI_MODEL"), "provider_reported_execution_identity": None},
                                     "resources_sha256": fingerprint(self.state["resources"])})
        if persist:
            self.save()

    def plan(self, delivery, refresh=False):
        cleanup = self.document.get("cleanup", {})
        old_plan = cleanup.get("plan")
        returned = cleanup.get("verification_return")
        if returned:
            self.verification_return()
            event = json.loads(private(Path(returned["path"])))
            if "published_setup_failure" in event["publication_observations"]:
                require(delivery["oid"] != old_plan["oid"], "failed-setup candidate needs repaired, freshly accepted/published source; stale replan held")
            self.complete()
            require(refresh and delivery["oid"] == text(["git", "rev-parse", "HEAD"], self.root) and
                    delivery["publication"]["delivery_verified"] is True and delivery["publication"]["url"] and
                    type(delivery["publication"]["number"]) is int, "verification return requires explicit fresh qualified current-source Land plan")
            publication = delivery["publication"]
            repository = old_plan["publication"]["repository"]
            require(publication["repository"] == repository and publication["url"] == f"https://github.com/{repository}/pull/{publication['number']}" and
                    publication["base"] == old_plan["publication"]["base"] and
                    publication["base_ref"] in (f"refs/heads/{publication['base']}", f"refs/remotes/origin/{publication['base']}") and
                    publication["merge_oid"] is None and publication["merged_at"] is None,
                    "fresh delivery subject/base mismatch; only actual published-open replanning supported")
            remote = text(["git", "ls-remote", "--heads", "origin", self.subject["ref"]], self.root)
            require(remote == delivery["oid"] + "\t" + self.subject["ref"], "current source not published; no fresh plan")
            pr = query(["gh", "pr", "view", publication["url"], "--repo", repository, "--json",
                        "url,state,baseRefName,headRefName,headRefOid,headRepository,headRepositoryOwner,mergedAt,mergeCommit"], self.root)
            owner, name = repository.split("/")
            require(pr["url"] == publication["url"] and pr["state"] == "OPEN" and pr["baseRefName"] == publication["base"] and
                    pr["headRefName"] == self.subject["ref"].removeprefix("refs/heads/") and pr["headRefOid"] == delivery["oid"] and
                    pr["headRepository"]["name"] == name and pr["headRepositoryOwner"]["login"] == owner and
                    pr["mergedAt"] is None and pr["mergeCommit"] is None, "fresh PR/current-source provenance mismatch")
        if old_plan and not refresh:
            require(old_plan["oid"] == delivery["oid"] and old_plan["publication"] == delivery["publication"], "delivery changed; explicitly refresh while preserving original plan/attempts")
            return
        required = ("publication", "oid", "exited_workers", "protected_files")
        require(all(key in delivery for key in required), "delivery binding incomplete; ownership inventory is generated, not authored")
        plan = {key: self.subject[key] for key in ("task", "safe_checkout", "git_common_dir", "origin", "ref")}
        plan.update({key: delivery[key] for key in required})
        if "original_land_attempt" in delivery:
            plan["original_land_attempt"] = delivery["original_land_attempt"]
        plan["runtime"] = self.binding
        candidate = Path(self.subject["checkout"])
        safe = Path(self.subject["safe_checkout"])
        for kind, identity in (("worktree", str(candidate)), ("branch", self.subject["ref"])):
            options = delivery.get(kind, {})
            require(options.get("identity", identity) == identity, "delivery cannot change lifecycle resource identity")
            disposition = options.get("disposition", "retain" if candidate == safe else "remove")
            require(candidate != safe or disposition == "retain", "canonical checkout/ref must remain protected")
            plan[kind] = {"identity": identity, "ownership": "proven", "consumers": None, "consumer_policy": "fresh",
                          "disposition": disposition, "reason": options.get("reason", "recorded task-owned candidate; fresh qualification required")}
        plan["worktree"].update(inode=self.subject.get("checkout_inode", candidate.stat().st_ino),
            preserve_to=delivery.get("worktree", {}).get("preserve_to", str(self.path.parent / ("preserved-" + candidate.name))),
            exclusions=delivery.get("worktree", {}).get("exclusions", []))
        plan.update({kind: json.loads(json.dumps(self.state["resources"][kind])) for kind in KINDS})
        for kind in KINDS:
            for item in plan[kind]:
                if item["disposition"] == "remove" and item.get("consumers") in (None, []):
                    item.update(consumers=None, consumer_policy="fresh")
        pending = [op for op in self.state["operations"] if op["status"] != "recorded"]
        plan["pending_operations"] = [{"identity": op["id"], "kind": op["kind"], "intent": op["intent"],
                                       "project": op["runtime"]["project"], "reason": "original allocation outcome/evidence incomplete; reconcile only this operation"} for op in pending]
        pending_projects = {op["runtime"]["project"] for op in pending}
        creators = {v["identity"]: v for v in plan["containers"]}
        for kind in ("containers", "networks", "volumes", "images"):
            for item in plan[kind]:
                runtime = item.get("runtime", creators.get(item.get("creation", {}).get("container"), {}).get("runtime", {}))
                associated = runtime.get("project") in pending_projects or (kind == "images" and any(reference.startswith(project + "-") for project in pending_projects for reference in item.get("references", [])))
                if associated and item["disposition"] == "remove":
                    item.update(disposition="hold", reason="dependent original allocation pending; reconcile and refresh this versioned plan")
        if returned:
            # New independent owners may replace canonical reports; explicit current
            # delivery records replace those paths, never the immutable old records.
            current_paths = {entry["path"] for entry in plan["protected_files"]}
            plan["protected_files"].extend(entry for entry in old_plan["protected_files"] if entry["path"] not in current_paths)
            for entry in plan["protected_files"]:
                require(file_record(Path(entry["path"])) == entry["record"], "fresh protected-file binding missing/changed")
        for entry in self.state["protected_files"]:
            require(file_record(Path(entry["path"])) == entry["record"], "protected original changed")
            if entry not in plan["protected_files"]:
                plan["protected_files"].append(entry)
        cleanup = self.document.setdefault("cleanup", {"version": 1})
        if old_plan:
            old_hash = fingerprint(old_plan)
            require(cleanup.get("plan_sha256", old_hash) == old_hash, "prior immutable plan changed; retain it and recover original evidence")
            cleanup.setdefault("plan_history", []).append({"plan": old_plan, "plan_sha256": old_hash,
                "next_plan_sha256": fingerprint(plan), "at": now(), "attempt_count": len(cleanup.get("attempts", [])),
                **({"verification_return": returned} if returned else {})})
        if returned:
            del cleanup["verification_return"]
        cleanup["plan"] = plan
        cleanup["plan_sha256"] = fingerprint(plan)
        cleanup["lifecycle_sha256"] = fingerprint(self.state)
        self.save()


def initialize(root, task, receipt, spec_path):
    require(re.fullmatch(r"TASK-\d{5}", task), "invalid task")
    require(not (root / "var/resource-lifecycle.json").exists(), "runtime already bound; reuse/reconcile original receipt")
    spec = json.loads(private(absolute(spec_path)))
    safe = Path(text(["git", "worktree", "list", "--porcelain"], root).splitlines()[0].split(" ", 1)[1])
    store = safe / ".runs/tasks" / task / "runs"
    require(inside(receipt, store) and (root == safe or not inside(receipt, root)), "receipt must be in canonical exact-task evidence outside candidate")
    require(receipt.parent.is_dir(), "receipt requires existing private run directory")
    ref = text(["git", "symbolic-ref", "HEAD"], root)
    require(re.fullmatch(r"refs/heads/feature/task-" + task[5:] + r"-[a-z0-9-]+", ref), "wrong task branch")
    project = spec["project"]
    require(re.fullmatch(r"task" + task[5:] + r"-[a-z0-9-]{1,40}", project), "project must be exact-task isolated")
    binding = {"kind": "compose", "ownership": "proven", "project": project, "working_directory": str(root)}
    for key in ("config_files", "env_files"):
        require(isinstance(spec[key], list) and len(spec[key]) <= 8, "explicit ordered config/env files required")
        binding[key] = [{"path": str(absolute(path)), "sha256": digest(Path(path).read_bytes())} for path in spec[key]]
    require(binding["config_files"], "explicit configuration required")
    model = resolved(binding)
    binding["resolved_sha256"] = fingerprint(model)
    policy = spec["policy"]
    all_model = query([*compose(binding), "--profile", "*", "config", "--format", "json"], root)
    require(set(policy["services"]) >= set(all_model["services"]), "classify every service/profile before allocation")
    for service in policy["services"].values():
        require(service["data"] in ("disposable", "durable", "unknown") and service["authority"], "data/authority missing")
    for key, maximum in {"operations": 128, "seconds": 1800, "containers": 32, "networks": 32, "volumes": 32, "images": 32, "caches": 32}.items():
        require(type(policy["budget"][key]) is int and 0 <= policy["budget"][key] <= maximum, "invalid finite budget")
    require(policy["budget"]["seconds"] > 0, "positive timeout required")
    subject = {"task": task, "safe_checkout": str(safe), "checkout": str(root), "checkout_inode": root.stat().st_ino, "ref": ref,
               "origin": text(["git", "remote", "get-url", "origin"], root),
               "git_common_dir": text(["git", "rev-parse", "--path-format=absolute", "--git-common-dir"], root),
               "source": source(root)}
    document = {"lifecycle": {"version": 1, "subject": subject, "runtime": binding, "policy": policy,
                 "original_spec": reference(Path(spec_path)), "operations": [], "stages": [],
                 "resources": {kind: [] for kind in KINDS}, "protected_files": [
                     {"path": str(absolute(path)), "record": file_record(Path(path))} for path in spec["protected_files"]],
                 "private_paths": spec["private_paths"], "processes": [],
                 "binding_intent": {"path": str(root / "var/resource-lifecycle.json"), "mode": "0600", "data": "private runtime locator", "disposition": "preserve"},
                 "observers": {"state": "none created by this owning interface; command subprocesses are synchronously supervised from the safe checkout"},
                 "cache_policy": {"state": "no-build-requested", "reason": "image reuse/pulls grant no cache/layer ownership; any build adds explicit builder observations and association holds"}}}
    # Receipt intent precedes the first runtime directory/binding allocation. If that
    # write fails, retry ONLY this original initialization, never a second receipt.
    with Path(str(receipt) + ".lock").open("a") as handle:
        fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        if receipt.exists():
            original = json.loads(private(receipt))
            state = original["lifecycle"]
            require(state["version"] == 1 and state["subject"] == subject and state["runtime"] == binding and
                    state["original_spec"] == document["lifecycle"]["original_spec"] and not state["operations"],
                    "existing initialization differs; retain original receipt and recover exact inputs")
        else:
            atomic(receipt, document, exclusive=True)
        (root / "var").mkdir(mode=0o700, exist_ok=True)
        require((root / "var").is_dir() and not (root / "var").is_symlink(), "runtime directory changed")
        atomic(root / "var/resource-lifecycle.json", {"version": 1, "task": task, "receipt": str(receipt), "checkout": str(root)}, exclusive=True)


def main():
    global SAFE_CWD
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", default=os.getcwd())
    sub = parser.add_subparsers(dest="action", required=True)
    init = sub.add_parser("init")
    init.add_argument("--task", required=True)
    init.add_argument("--receipt", required=True)
    init.add_argument("--spec", required=True)
    docker = sub.add_parser("docker")
    docker.add_argument("argv", nargs=argparse.REMAINDER)
    validate = sub.add_parser("validate")
    validate.add_argument("--stage", required=True)
    validate.add_argument("--attempt", help="Existing original stage monitor attempt; records protected reuse, never creation or exit authority")
    qualify_image = sub.add_parser("qualify-image")
    qualify_image.add_argument("--identity", required=True)
    image_name = sub.add_parser("image-name")
    image_name.add_argument("name")
    paths = sub.add_parser("paths")
    paths.add_argument("paths", nargs="+")
    rebind = sub.add_parser("rebind")
    rebind.add_argument("--spec", required=True)
    reconcile = sub.add_parser("reconcile")
    reconcile.add_argument("--operation", required=True)
    resume = sub.add_parser("resume")
    resume.add_argument("--operation", required=True)
    cancel = sub.add_parser("cancel")
    cancel.add_argument("--operation", required=True)
    verification = sub.add_parser("return-to-verification", help="Suspend a finished no-effect plan; reuse only, no allocation/removal authority")
    verification.add_argument("--plan-sha256", required=True, help="Exact original immutable cleanup plan hash")
    verification.add_argument("--failed-setup-run", type=int, help="Explicit published-open return: exact current-head hosted setup FAILURE and skipped gate required")
    recovery = sub.add_parser("recover-verification", help="Audit one qualified legacy no-effect append; preserve attempts and frozen plan")
    recovery.add_argument("--receipt-sha256", required=True, help="Exact incident receipt bytes, not a reset or replacement")
    recovery.add_argument("--legacy-source", required=True, help="Exact ancestor Git revision of the qualified legacy cleanup operation (read only)")
    recovery.add_argument("--protected-history", required=True, help="Private JSON mapping changed canonical report paths to preserved original files")
    plan = sub.add_parser("plan")
    plan.add_argument("--delivery", required=True)
    plan.add_argument("--refresh", action="store_true", help="Archive the old immutable plan in this SAME receipt; preserve every prior attempt")
    args = parser.parse_args()
    try:
        root = absolute(args.root)
        SAFE_CWD = absolute(text(["git", "worktree", "list", "--porcelain"], root).splitlines()[0].split(" ", 1)[1])
        if args.action == "init":
            initialize(root, args.task, absolute(args.receipt), str(absolute(args.spec)))
            return 0
        locator = root / "var/resource-lifecycle.json"
        if not locator.exists():
            # Existing non-TASK/CI use is not silently enrolled or claimed as captured.
            branch = observe(["git", "symbolic-ref", "--quiet", "--short", "HEAD"], root, codes=(0, 1)).stdout.decode().strip()
            require(args.action in ("docker", "paths") and not re.match(r"feature/task-\d{5}-", branch), "TASK runtime requires original lifecycle init before owning commands")
            if args.action == "paths":
                for path in args.paths:
                    Path(path).mkdir(parents=True, exist_ok=True)
                return 0
            return subprocess.run(["docker", *args.argv], cwd=root).returncode
        binding = json.loads(private(locator))
        require(binding["version"] == 1 and binding["checkout"] == str(root), "invalid runtime binding")
        lifecycle = Lifecycle(root, binding["receipt"])
        require(binding["task"] == lifecycle.subject["task"], "binding subject mismatch")
        with lifecycle.locked():
            if lifecycle.document.get("cleanup", {}).get("verification_return") and args.action != "recover-verification":
                lifecycle.verification_return()
                require(args.action in ("validate", "docker", "plan", "image-name"), "verification return cannot rebind/recover/mutate allocation; retain original holds")
            if args.action == "docker":
                require(args.argv, "missing Docker command")
                if args.argv[0] == "inspect" or args.argv[:2] in (["image", "inspect"], ["image", "ls"], ["network", "inspect"], ["network", "ls"], ["volume", "inspect"], ["volume", "ls"]):
                    return subprocess.run(["docker", *args.argv], cwd=SAFE_CWD).returncode
                if args.argv[0] == "run":
                    return lifecycle.standalone_run(args.argv[1:])
                if args.argv[0] == "build":
                    return lifecycle.build_image(args.argv[1:])
                if args.argv[0] == "pull":
                    require(len(args.argv) == 2, "one exact shared image per pull")
                    return lifecycle.pull_image(args.argv[1])
                require(args.argv[0] == "compose", "unsupported owning Docker effect; no allocation")
                return lifecycle.execute_compose(args.argv[1:])
            if args.action == "image-name":
                require(re.fullmatch(r"[a-z0-9][a-z0-9.-]{0,48}", args.name), "invalid task image suffix")
                lifecycle.complete()
                print(lifecycle.binding["project"] + "-" + args.name)
                return 0
            if args.action == "validate":
                lifecycle.validate(args.stage, args.attempt)
            elif args.action == "qualify-image":
                lifecycle.qualify_image(args.identity)
            elif args.action == "paths":
                lifecycle.paths([str((root / path).resolve()) if not Path(path).is_absolute() else path for path in args.paths])
            elif args.action == "rebind":
                lifecycle.rebind(absolute(args.spec))
            elif args.action == "reconcile":
                lifecycle.reconcile(args.operation)
            elif args.action == "resume":
                return lifecycle.resume(args.operation)
            elif args.action == "cancel":
                lifecycle.cancel(args.operation)
            elif args.action == "recover-verification":
                lifecycle.recover_verification(args.receipt_sha256, args.legacy_source, json.loads(private(absolute(args.protected_history))))
            elif args.action == "return-to-verification":
                lifecycle.return_to_verification(args.plan_sha256, args.failed_setup_run)
            elif args.action == "plan":
                lifecycle.plan(json.loads(private(absolute(args.delivery))), args.refresh)
        print(json.dumps({"task": lifecycle.subject["task"], "operation": args.action, "recorded": True}))
        return 0
    except (Hold, OSError, ValueError, KeyError, TypeError, StopIteration, subprocess.SubprocessError) as error:
        print(json.dumps({"complete": False, "hold": str(error) if isinstance(error, Hold) else type(error).__name__,
                          "recovery": "inspect original exact-operation receipt; no duplicate allocation or destructive fallback"}), file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
