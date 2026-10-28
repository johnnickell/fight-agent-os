#!/usr/bin/env python3
"""Qualify exact-subject cleanup with a bounded real-Docker fixture, not a product test/gate."""

import argparse
from datetime import datetime, timezone
import importlib.util
import io
import tarfile
import json
import os
from pathlib import Path
import signal
import subprocess
import sys
import time
import uuid

sys.dont_write_bytecode = True


def run(args, cwd, env=None):
    result = subprocess.run([str(arg) for arg in args], cwd=cwd, env=env,
                            stdin=subprocess.DEVNULL, capture_output=True, timeout=90)
    if result.returncode:
        raise RuntimeError(f"{args[0]} {args[1]} exit {result.returncode}; retain fixture; inspect private evidence")
    return result.stdout.decode().strip()


def private_json(path, value):
    with path.open("w") as output:
        json.dump(value, output, indent=2)
        output.write("\n")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence", required=True, help="New absolute task-owned evidence directory, outside removal candidates")
    parser.add_argument("--helper", default=str(Path(__file__).with_name("cleanup.py")))
    parser.add_argument("--resume", action="store_true", help="Reuse this fixture/receipt; never recreate missing resources")
    parser.add_argument("--prepare-only", action="store_true", help="Create and record the bounded fixture for actual Pi caller qualification; do not invoke cleanup")
    parser.add_argument("--case", choices=("normal", "running", "failure", "cancel", "durable", "shared-stopped", "image", "unavailable", "denied", "changed-identity"), default="normal")
    parser.add_argument("--release-consumer", action="store_true", help="Resume only: linked new fixture receipt permits its owned stopped consumer retirement")
    parser.add_argument("--interrupt", action="store_true", help="Terminate only this helper subprocess after its first persisted container removal; resume separately")
    args = parser.parse_args()
    if args.release_consumer and not args.resume:
        parser.error("--release-consumer requires --resume")
    os.umask(0o077)
    evidence = Path(args.evidence).resolve()
    helper = Path(args.helper).resolve()
    spec = importlib.util.spec_from_file_location("subject_cleanup", helper)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    root = evidence / "safe"
    receipt = root / ".runs/tasks/TASK-00542/runs/fixture.json"
    env = dict(os.environ, COMPOSE_DISABLE_ENV_FILE="1", COMPOSE_ENV_FILES="")
    if not args.resume or not receipt.exists():
        origin = "git@github.com:task00542/fixture.git"
        config = root / "compose.json"
        if not args.resume:
            evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
            root.mkdir()
            run(["git", "init", "--initial-branch=develop"], root)
            run(["git", "-c", "user.name=Lifecycle fixture", "-c", "user.email=fixture@example.invalid",
                 "commit", "--allow-empty", "-m", "Bounded TASK-00542 disposable fixture"], root)
            run(["git", "remote", "add", "origin", origin], root)
            token = uuid.uuid4().hex
            project = "task00542-" + token[:12]
            # Use an already-present image; no pull, build, dependency install or global setup.
            image = json.loads(run(["docker", "image", "inspect", "postgres:17-bookworm"], root))[0]
            model = {"services": {"fixture": {"image": image["Id"], "pull_policy": "never",
                     "command": ["sleep", "600"], "volumes": ["scratch:/scratch"]}},
                     "volumes": {"scratch": {"labels": {"io.fight.fixture": token}}}}
            if args.case == "failure":
                model["services"]["fixture"]["command"] = ["sh", "-c", "exit 7"]
            if args.case in ("shared-stopped", "durable"):
                model["services"]["consumer"] = {"image": image["Id"], "pull_policy": "never",
                    "command": ["sh", "-c", "printf 'TASK00542-protected-sentinel' > /var/lib/postgresql/data/sentinel; exec sleep 600"],
                    "volumes": ["scratch:/var/lib/postgresql/data"]}
            private_json(config, model)
            named = project + "_scratch"
            before = run(["docker", "volume", "ls", "--format", "{{.Name}}"], root).splitlines()
            if named in before:
                raise RuntimeError("exact fixture name already exists; no creation authority")
            compose = ["docker", "compose", "--project-name", project, "--project-directory", root, "--file", config]
            # Durable intent precedes creation, so interrupted creation has a finite subject locator.
            private_json(evidence / "creation-intent.json", {"project": project, "config": str(config),
                "config_sha256": module.digest(config.read_bytes()), "image": image["Id"],
                "named_volume": named, "named_before_absent": True, "token": token,
                "argv": [str(arg) for arg in compose] + ["create"], "source_sha256": module.digest(helper.read_bytes())})
            run([*compose, "create"], root, env)
            workload = {"case": args.case}
            if args.case in ("running", "failure", "cancel"):
                run([*compose, "start", "fixture"], root, env)
            if args.case == "failure":
                workload["exit"] = run(["docker", "wait", run([*compose, "ps", "--all", "--quiet", "fixture"], root, env)], root, env)
                failed = json.loads(run(["docker", "inspect", run([*compose, "ps", "--all", "--quiet", "fixture"], root, env)], root))[0]
                if failed["State"]["ExitCode"] != 7:
                    raise RuntimeError("failure fixture did not actually fail with exit 7")
            if args.case == "cancel":
                run([*compose, "stop", "fixture"], root, env)
                workload["cancellation"] = "owning Compose stop completed before cleanup"
            if args.case == "durable":
                run([*compose, "start", "consumer"], root, env)
                consumer_id = run([*compose, "ps", "--all", "--quiet", "consumer"], root, env)
                workload["sentinel"] = run(["docker", "exec", consumer_id, "sha256sum", "/var/lib/postgresql/data/sentinel"], root)
            private_json(evidence / "workload.json", workload)
        else:
            intent = json.loads((evidence / "creation-intent.json").read_text())
            project = intent["project"]
            if module.digest(config.read_bytes()) != intent["config_sha256"]:
                raise RuntimeError("interrupted fixture configuration changed; hold")
            image = json.loads(run(["docker", "image", "inspect", intent["image"]], root))[0]
        oid = run(["git", "rev-parse", "HEAD"], root)
        compose = ["docker", "compose", "--project-name", project, "--project-directory", root, "--file", config]
        identifier = run([*compose, "ps", "--all", "--quiet", "fixture"], root, env)
        container = json.loads(run(["docker", "inspect", identifier], root))[0]
        resolved = json.loads(run([*compose, "config", "--format", "json"], root, env))
        common = {"disposition": "remove", "ownership": "proven", "consumers": [],
                  "reason": "explicit bounded TASK-00542 fixture; newly created empty disposable data"}
        volumes = []
        for mount in container["Mounts"]:
            if mount["Type"] != "volume":
                continue
            inspected = json.loads(run(["docker", "volume", "inspect", mount["Name"]], root))[0]
            identity = {key: inspected.get(key) for key in ("Name", "Driver", "Mountpoint", "CreatedAt", "Labels", "Options", "Scope")}
            volumes.append(dict(common, identity=mount["Name"], inspection_sha256=module.fingerprint(identity),
                data="disposable", authority="John's bounded task fixture discard grant", size_bytes=None,
                size_reason="empty fixture; Docker per-volume byte observation not yet available",
                creation={"kind": "created", "container": identifier, "destination": mount["Destination"],
                          "intent": str(evidence / "creation-intent.json"),
                          "intent_sha256": module.digest((evidence / "creation-intent.json").read_bytes()),
                          "source_sha256": module.digest(helper.read_bytes())}))
        networks = []
        network_ids = run(["docker", "network", "ls", "--no-trunc", "--filter",
                           "label=com.docker.compose.project=" + project, "--format", "{{.ID}}"], root).splitlines()
        for network_id in network_ids:
            network = json.loads(run(["docker", "network", "inspect", network_id], root))[0]
            networks.append(dict(common, identity=network["Id"], name=network["Name"],
                                 key=network["Labels"]["com.docker.compose.network"], created=network["Created"],
                                 creation={"kind": "created", "intent": str(evidence / "creation-intent.json"),
                                           "intent_sha256": module.digest((evidence / "creation-intent.json").read_bytes())}))
        retained = dict(disposition="retain", ownership="proven", consumers=[], reason="fixture source/evidence, not a product delivery checkout")
        plan = {"task": "TASK-00542", "safe_checkout": str(root), "git_common_dir": str(root / ".git"),
            "origin": origin, "ref": "refs/heads/feature/task-00542-fixture", "oid": oid,
            "publication": {"url": "https://github.com/task00542/fixture/pull/1", "repository": "task00542/fixture",
                "number": 1, "base": "develop", "base_ref": "refs/heads/develop", "delivery_verified": True,
                "merge_oid": oid, "merged_at": "2026-10-28T00:00:00Z"},
            "protected_files": [], "exited_workers": [],
            "runtime": {"project": project, "working_directory": str(root), "ownership": "proven", "env_files": [],
                "config_files": [{"path": str(config), "sha256": module.digest(config.read_bytes())}],
                "resolved_sha256": module.fingerprint(resolved)},
            "containers": [dict(common, identity=identifier, name=container["Name"], service="fixture",
                inspection_sha256=module.container_identity(container),
                processes_sha256=module.digest(subprocess.check_output(["docker", "top", identifier, "-eo", "pid,ppid,args"])) if container["State"]["Running"] else None,
                data="disposable", bind_data={}, creation={"kind": "created", "intent": str(evidence / "creation-intent.json"),
                    "intent_sha256": module.digest((evidence / "creation-intent.json").read_bytes())})],
            "networks": networks, "volumes": volumes,
            "images": [dict(retained, identity=image["Id"], size_bytes=image["Size"],
                creation={"kind": "reused"}, reason="pre-existing image and shared layers; never remove by association")],
            "caches": [], "worktree": dict(retained, identity=str(root / "no-removal-candidate")),
            "branch": dict(retained, identity="refs/heads/feature/task-00542-fixture")}
        if args.case == "image":
            # Import a unique empty task image: no build cache, registry or shared base layers.
            image_intent = evidence / "image-intent.json"
            private_json(image_intent, {"token": project, "operation": "docker image import empty tar",
                                       "authority": "owned disposable fixture image; no shared image mutation"})
            archive = io.BytesIO()
            with tarfile.open(fileobj=archive, mode="w"):
                pass
            imported = subprocess.run(["docker", "image", "import", "--change", "LABEL io.fight.fixture=" + project, "-"],
                                      input=archive.getvalue(), capture_output=True, check=True).stdout.decode().strip()
            observed_image = json.loads(run(["docker", "image", "inspect", imported], root))[0]
            plan["images"].append(dict(common, identity=imported, data="disposable", authority="explicit task fixture image discard",
                inspection_sha256=module.fingerprint(observed_image), size_bytes=observed_image["Size"],
                creation={"kind": "created", "intent": str(image_intent), "intent_sha256": module.digest(image_intent.read_bytes())}))
        if "consumer" in resolved["services"]:
            consumer_id = run([*compose, "ps", "--all", "--quiet", "consumer"], root, env)
            consumer = json.loads(run(["docker", "inspect", consumer_id], root))[0]
            plan["containers"].append(dict(common, identity=consumer_id, name=consumer["Name"], service="consumer",
                inspection_sha256=module.container_identity(consumer), processes_sha256=None, data="disposable", bind_data={},
                disposition="retain", reason="owned stopped shared-consumer fixture; preserve until explicit linked release",
                creation={"kind": "created", "intent": str(evidence / "creation-intent.json"),
                          "intent_sha256": module.digest((evidence / "creation-intent.json").read_bytes())}))
        receipt.parent.mkdir(mode=0o700, parents=True)
        private_json(receipt, {"qualification": "controlled hosting response; real Docker/Git; NOT live publication or fleet proof",
                               "cleanup": {"version": 1, "plan": plan}})
        shim = evidence / "controlled-hosting"
        shim.mkdir()
        response = {"url": plan["publication"]["url"], "state": "MERGED", "mergedAt": plan["publication"]["merged_at"],
            "mergeCommit": {"oid": oid}, "baseRefName": "develop", "headRefName": "feature/task-00542-fixture",
            "headRefOid": oid, "headRepository": {"name": "fixture"}, "headRepositoryOwner": {"login": "task00542"}}
        (shim / "gh").write_text("#!/usr/bin/env python3\nprint(" + repr(json.dumps(response)) + ")\n")
        (shim / "gh").chmod(0o700)
    if args.case == "durable" and not args.resume:
        document = json.loads(receipt.read_text())
        named = next(item for item in document["cleanup"]["plan"]["volumes"] if item["identity"].endswith("_scratch"))
        named.update(disposition="retain", data="durable", reason="explicit protected fixture sentinel; no discard during preservation probe")
        private_json(receipt, document)
    if args.prepare_only:
        print(json.dumps({"prepared": True, "receipt": str(receipt), "helper_sha256": module.digest(helper.read_bytes())}))
        return 0
    env["PATH"] = str(evidence / "controlled-hosting") + os.pathsep + env["PATH"]
    if args.release_consumer:
        if not args.resume:
            raise RuntimeError("consumer release requires the existing fixture and original receipt")
        original = receipt
        receipt = receipt.with_name("released-consumer.json")
        if not receipt.exists():
            released = json.loads(original.read_text())
            consumers = [item for item in released["cleanup"]["plan"]["containers"] if item["service"] == "consumer"]
            if len(consumers) != 1:
                raise RuntimeError("no exact owned fixture consumer to release")
            observed_consumer = json.loads(run(["docker", "inspect", consumers[0]["identity"]], root))[0]
            if module.container_identity(observed_consumer) != consumers[0]["inspection_sha256"]:
                raise RuntimeError("fixture consumer identity changed; preserve original hold")
            consumers[0].update(disposition="remove", reason="explicit bounded fixture consumer release; original held evidence preserved",
                processes_sha256=module.digest(subprocess.check_output(["docker", "top", consumers[0]["identity"], "-eo", "pid,ppid,args"])) if observed_consumer["State"]["Running"] else None)
            for volume in released["cleanup"]["plan"]["volumes"]:
                if volume.get("data") == "durable":
                    volume.update(disposition="remove", data="disposable", reason="explicit fixture release after verified sentinel preservation; no real durable data")
            released["prior_receipt"] = {"path": str(original), "sha256": module.digest(original.read_bytes())}
            released["cleanup"].pop("plan_sha256", None)
            released["cleanup"]["attempts"] = []
            private_json(receipt, released)
    document = json.loads(receipt.read_text())
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")
    invocation = [sys.executable, str(helper), "--receipt", str(receipt), "--apply"]
    previous_attempts = len(document["cleanup"].get("attempts", []))
    interrupted = False
    expected_probe_hold = False
    protected_before = None
    probe_case = json.loads((evidence / "workload.json").read_text()).get("case") if (evidence / "workload.json").exists() else "normal"
    if not args.resume and probe_case in ("unavailable", "denied", "changed-identity"):
        protected_before = json.loads(run(["docker", "inspect", *[item["identity"] for item in document["cleanup"]["plan"]["containers"]]], root))
        if probe_case == "unavailable":
            env["DOCKER_HOST"] = "unix://" + str(evidence / "deliberately-unavailable-fixture.sock")
            env.pop("DOCKER_CONTEXT", None)
        elif probe_case == "denied":
            receipt.chmod(0o000)  # Explicit owned fixture denial; restored only as declared probe teardown.
        else:
            run(["docker", "rename", document["cleanup"]["plan"]["containers"][0]["identity"], project + "-changed"], root)
    if args.interrupt:
        process = subprocess.Popen(invocation, cwd=root, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        deadline = time.monotonic() + 30
        while process.poll() is None and time.monotonic() < deadline:
            observed = json.loads(receipt.read_text())
            attempts = observed["cleanup"].get("attempts", [])
            if len(attempts) > previous_attempts and any(item["kind"] == "container" and item["status"] == "removed"
                                for item in attempts[-1]["results"]):
                process.terminate()  # Only this newly created fixture helper, never a Pi/coordinator/foreign process.
                interrupted = True
                break
            time.sleep(0.01)
        stdout, stderr = process.communicate(timeout=180)
        result = subprocess.CompletedProcess(invocation, process.returncode, stdout, stderr)
        interrupted = interrupted and result.returncode == -signal.SIGTERM
    else:
        result = subprocess.run(invocation, cwd=root, env=env, capture_output=True, timeout=180)
    if protected_before is not None:
        if probe_case == "denied":
            receipt.chmod(0o600)
        env.pop("DOCKER_HOST", None)
        protected_after = json.loads(run(["docker", "inspect", *[item["identity"] for item in document["cleanup"]["plan"]["containers"]]], root))
        unchanged = all(module.container_identity(a) == module.container_identity(b) for a, b in zip(protected_before, protected_after))
        expected_probe_hold = result.returncode != 0 and (unchanged or probe_case == "changed-identity")
        if probe_case == "denied":
            expected_probe_hold = expected_probe_hold and b"PermissionError" in result.stdout + result.stderr
        private_json(evidence / (stamp + ".protection.json"), {"case": probe_case, "before": [module.container_identity(a) for a in protected_before],
            "after": [module.container_identity(a) for a in protected_after], "helper_exit": result.returncode, "expected_hold": expected_probe_hold})
        if probe_case == "changed-identity":
            run(["docker", "rename", document["cleanup"]["plan"]["containers"][0]["identity"], protected_before[0]["Name"].lstrip("/")], root)
    (evidence / (stamp + ".stdout")).write_bytes(result.stdout)
    (evidence / (stamp + ".stderr")).write_bytes(result.stderr)
    remaining = set(run(["docker", "volume", "ls", "--format", "{{.Name}}"], root).splitlines())
    leftover = [item["identity"] for item in document["cleanup"]["plan"]["volumes"] if item["identity"] in remaining]
    attempts = json.loads(receipt.read_text())["cleanup"].get("attempts", [])
    latest = attempts[-1] if attempts else {"results": []}
    consumer_entries = [item for item in document["cleanup"]["plan"]["containers"]
                        if item.get("service") == "consumer" and item["disposition"] == "retain"]
    expected_hold = False
    if consumer_entries:
        consumer = json.loads(run(["docker", "inspect", consumer_entries[0]["identity"]], root))[0]
        named_entry = next(item for item in document["cleanup"]["plan"]["volumes"] if item["identity"].endswith("_scratch"))
        named_current = json.loads(run(["docker", "volume", "inspect", named_entry["identity"]], root))[0]
        workload = json.loads((evidence / "workload.json").read_text()) if (evidence / "workload.json").exists() else {}
        protected = workload.get("case") == "durable"
        sentinel_ok = not protected or run(["docker", "exec", consumer["Id"], "sha256sum", "/var/lib/postgresql/data/sentinel"], root) == workload["sentinel"]
        expected_hold = ((protected or not consumer["State"]["Running"]) and sentinel_ok and
                         module.container_identity(consumer) == consumer_entries[0]["inspection_sha256"] and
                         module.volume_identity(named_current) == named_entry["inspection_sha256"] and
                         any(item["kind"] == "volume" and item["identity"] == named_entry["identity"] and
                             (item["status"] == "retained" if protected else item["status"] == "held" and "consumer" in item["reason"])
                             for item in latest["results"]))
    summary = {"helper_sha256": module.digest(helper.read_bytes()), "helper_exit": result.returncode,
               "receipt": str(receipt), "required_volume_leftovers": leftover,
               "pass": result.returncode == 0 and not leftover,
               "expected_stopped_shared_hold_verified": expected_hold,
               "workload": json.loads((evidence / "workload.json").read_text()) if (evidence / "workload.json").exists() else {"case": "historical stopped fixture"},
               "interrupted_after_container_effect": interrupted,
               "expected_probe_hold_verified": expected_probe_hold,
               "scope": "bounded real-Docker Compose fixture, image-declared anonymous and named storage; controlled hosting",
               "unrun": ["fleet owning callers", "installed Pi Land/Build (separate exact attempt evidence)",
                         "other interruption points", "foreign consumers",
                         "recreation/inspection/denial", "task-created image/cache retirement"]}
    private_json(evidence / (stamp + ".result.json"), summary)
    print(json.dumps(summary, indent=2))
    return 0 if summary["pass"] or expected_hold or interrupted or expected_probe_hold else 1


if __name__ == "__main__":
    sys.exit(main())
