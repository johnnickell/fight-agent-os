#!/usr/bin/env python3
"""Direct bounded creation/handoff/cleanup qualification; never a product-suite test."""

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import signal
import subprocess
import sys
import tarfile
import time
import io

sys.dont_write_bytecode = True
from cleanup import file_record


def save(path, value):
    with path.open("x") as output:
        json.dump(value, output, indent=2)
        output.flush()
        os.fsync(output.fileno())


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence", required=True)
    parser.add_argument("--repair-boundaries", action="store_true", help="Also qualify cold dependency builds, standalone/Compose budgets, attach signal, live original CLI and reused exec timeout")
    args = parser.parse_args()
    os.umask(0o077)
    evidence = Path(args.evidence).resolve()
    evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
    source = Path(__file__).resolve().parents[4]
    real_git = shutil.which("git")
    commands = []
    deadline = time.monotonic() + 1800
    max_commands = 256

    def expired(signum, frame):
        raise TimeoutError("qualification total budget expired; preserve original receipts and resolve unfinished operations through their owners")

    signal.signal(signal.SIGALRM, expired)
    signal.alarm(1800)  # Also bounds polling, sleeps and direct child/cleanup waits.

    def invoke(argv, cwd, env=None, timeout=180):
        remaining = deadline - time.monotonic()
        assert remaining > 0 and len(commands) < max_commands, "qualification total time/command budget exhausted; preserve unfinished original operations"
        result = subprocess.run([str(v) for v in argv], cwd=cwd, capture_output=True,
                                env=dict(os.environ, **(env or {})), timeout=min(timeout, remaining))
        number = len(commands)
        (evidence / f"{number:03}-stdout.log").write_bytes(result.stdout)
        (evidence / f"{number:03}-stderr.log").write_bytes(result.stderr)
        commands.append({"argv": [str(v) for v in argv], "cwd": str(cwd), "exit": result.returncode,
                         "stdout": f"{number:03}-stdout.log", "stderr": f"{number:03}-stderr.log"})
        (evidence / "commands.json").write_text(json.dumps(commands, indent=2))
        return result

    def run(argv, cwd, expected=0, env=None, timeout=180):
        number = len(commands)
        result = invoke(argv, cwd, env=env, timeout=timeout)
        recovery_command = str(argv[0]) == "./bin/runtime" and len(argv) > 1 and str(argv[1]) in ("reconcile", "resume", "cancel")
        # Recovery failures are final. Only a failed original allocation may get
        # one observation; that observation can never invoke another fallback.
        if result.returncode != expected and expected in (0, 7) and not recovery_command and str(argv[0]) in ("./bin/exec", "./bin/runtime"):
            locator = Path(cwd) / "var/resource-lifecycle.json"
            if locator.exists():
                receipt = Path(json.loads(locator.read_text())["receipt"])
                last = json.loads(receipt.read_text())["lifecycle"]["operations"][-1]
                if last["status"] == "pending" and last["kind"] in ("run", "create") and last["steps"] and last["steps"][0].get("exit") == 0:
                    # Never repeat allocation or relabel its original nonzero exit.
                    observation = invoke(["./bin/runtime", "reconcile", "--operation", last["id"]], cwd, env=env, timeout=timeout)
                    if observation.returncode != 0:
                        raise RuntimeError(f"single recovery observation held operation {last['id']}; preserve {receipt}; prerequisite: original evidence/identity must be resolved through its owner; see {commands[-1]['stderr']}: {observation.stderr.decode().strip()}")
                    recovered = json.loads(receipt.read_text())["lifecycle"]["operations"][-1]
                    assert recovered["status"] == "recorded" and (last["kind"] == "create" or recovered.get("exit") == expected), f"recovery incomplete; preserve {receipt} and resolve original evidence through its owner"
                    commands[number]["bounded_capture_recovery"] = {"operation": last["id"], "outcome": recovered["outcome"], "workload_exit": recovered.get("exit")}
                    (evidence / "commands.json").write_text(json.dumps(commands, indent=2))
                    return result.stdout.decode().strip()
        assert result.returncode == expected, (number, result.returncode, expected, result.stderr.decode().strip(), "preserve original evidence; resolve the named prerequisite through its owner before continuation")
        return result.stdout.decode().strip()

    image = run(["docker", "image", "inspect", "postgres:17-bookworm", "--format", "{{.Id}}"], source)
    base_references = json.loads(run(["docker", "image", "inspect", "postgres:17-bookworm", "--format", "{{json .RepoDigests}}"], source))
    assert len(base_references) == 1 and "@sha256:" in base_references[0]
    base_reference = base_references[0]
    builder = run(["docker", "context", "show"], source)
    files = ["bin/runtime", "bin/exec", ".pi/skills/land/scripts/lifecycle.py", ".pi/skills/land/scripts/cleanup.py"]
    hashes = {name: hashlib.sha256((source / name).read_bytes()).hexdigest() for name in files}
    save(evidence / "original-intent.json", {"version": 1, "task": "TASK-00547", "source_files": hashes,
         "qualification_script_sha256": hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
         "source_revision": run([real_git, "rev-parse", "HEAD"], source),
         "authority": "John's bounded disposable Work qualification only; controlled hosting is NOT publication",
         "purpose": "two concurrent worktrees, original creation, failure, cancellation/interruption, write-denial/release only on owned fixture evidence/runtime directories, binding/directory recovery, replacement, independent effects and protected shared data",
         "budget": {"worktrees": 4 if args.repair_boundaries else 2, "containers": 15 if args.repair_boundaries else 10,
                    "volumes": 24 if args.repair_boundaries else 16, "networks": 2, "images_created": 3 if args.repair_boundaries else 1,
                    "builds": 3 if args.repair_boundaries else 1,
                    "build_source": "checkout-b/build-source; metadata-only FROM scratch/existing PostgreSQL root, no RUN/pull",
                    "builder": builder, "pulls": 0, "seconds_per_command": 180, "seconds_total": 1800, "commands_total": max_commands, "memory_per_container": "32m", "pids": 8},
         "repair_boundaries": args.repair_boundaries,
         "live_cli_limit": "pre-Docker-effect adapter pause only; original ambiguous dispatch remains held with no allocated resource",
         "image": {"identity": image, "base_reference": base_reference, "creation": "reused/shared; retain"},
         "protected": ["all actual project checkouts/runtimes", "canonical coordinator", "foreign Docker state", "original probe evidence"],
         "fixture_retirement": "same generated plan and owning cleanup helper only; no destructive fallback"})
    safe = evidence / "safe"
    safe.mkdir()
    for name in files:
        target = safe / name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source / name, target)
    (safe / ".gitignore").write_text("var/\n.runs/\n")
    run([real_git, "init", "--initial-branch=develop"], safe)
    run([real_git, "config", "user.name", "TASK-00547 fixture"], safe)
    run([real_git, "config", "user.email", "task00547@example.invalid"], safe)
    origin = "git@github.com:task00547/lifecycle-fixture.git"
    run([real_git, "remote", "add", "origin", origin], safe)
    run([real_git, "add", "."], safe)
    run([real_git, "commit", "-m", "TASK-00547 controlled owning-tool fixture"], safe)
    store = safe / ".runs/tasks/TASK-00547/runs"
    store.mkdir(parents=True)
    sentinel = store / "protected.txt"
    sentinel.write_text("independent protected evidence\n")
    sentinel_before = file_record(sentinel)
    subjects = {}
    for name in (("a", "b", "c", "d") if args.repair_boundaries else ("a", "b")):
        checkout = evidence / ("checkout-" + name)
        branch = "feature/task-00547-fixture-" + name
        run([real_git, "worktree", "add", "-b", branch, checkout, "develop"], safe)
        project = "task00547-fixture-" + evidence.name.lower().replace("_", "-")[-16:] + "-" + name
        subjects[name] = {"root": checkout, "project": project, "branch": branch, "receipt": store / (name + ".json")}

    def prepare(name, foreign=None):
        subject = subjects[name]
        service = {"image": image, "pull_policy": "never", "entrypoint": [], "command": ["true"],
                   "read_only": True, "mem_limit": "32m", "cpus": .25, "pids_limit": 8,
                   "volumes": ["named:/fixture-named"]}
        model = {"services": {"web": service}, "volumes": {"named": {}}}
        if foreign:
            service["volumes"].append("foreign:/foreign-shared:ro")
            model["volumes"]["foreign"] = {"external": True, "name": foreign}
        if args.repair_boundaries and name == "c":
            # Isolate reused-volume evidence capacity: no internal or anonymous
            # storage can mask the missing external mount in preflight.
            assert foreign
            service["volumes"] = ["foreign:/foreign-shared:ro"]
            service["tmpfs"] = ["/var/lib/postgresql/data"]
            model["volumes"] = {"foreign": {"external": True, "name": foreign}}
        config = store / (name + "-compose.json")
        save(config, model)
        spec = store / (name + "-spec.json")
        save(spec, {"project": subject["project"], "config_files": [str(config)], "env_files": [],
                    "policy": {"services": {"web": {"data": "disposable", "authority": "original bounded disposable TASK-00547 fixture; shared external storage is excluded"}},
                               "cancel_authority": "only exact original bounded fixture oneoffs may be cancelled",
                               "oneoff_authority": "bounded disposable stopped fixture oneoffs",
                               "builder": builder,
                               "image_authority": "original bounded metadata-only scratch fixture root/reference; no shared layers or cache deletion",
                               "budget": {"operations": 32 if args.repair_boundaries else 16,
                                          "seconds": 30 if args.repair_boundaries else 180,
                                          "containers": 12 if args.repair_boundaries else 8, "networks": 4,
                                          "volumes": 0 if name == "c" else 20 if args.repair_boundaries else 12,
                                          "images": 1 if name == "c" else 8 if args.repair_boundaries else 4,
                                          "caches": 4 if args.repair_boundaries else 1}},
                    "private_paths": [{"path": str(subject["root"] / "var"), "disposition": "preserve", "reason": "runtime binding/configuration evidence"}],
                    "protected_files": [str(sentinel)]})
        if args.repair_boundaries and name == "b":
            original = json.loads(spec.read_text())
            original["policy"]["services"].update({key: {"data": "disposable", "authority": "original bounded cold-profile metadata-only fixture"} for key in ("https", "https_php")})
            # Still before initialization/allocation: this is original intent, not
            # an upgrade to a receipt or existing resource's authority.
            spec.write_text(json.dumps(original, indent=2))
        initialize = ["./bin/runtime", "init", "--task", "TASK-00547", "--receipt", subject["receipt"], "--spec", spec]
        if name == "b":
            runtime = subject["root"] / "var"
            runtime.mkdir(mode=0o500)
            try:
                run(initialize, subject["root"], expected=1)
                original = subject["receipt"].read_bytes()
                assert not (runtime / "resource-lifecycle.json").exists()
            finally:
                runtime.chmod(0o700)  # Release only this declared fixture denial.
            run(initialize, subject["root"])
            assert subject["receipt"].read_bytes() == original
        else:
            run(initialize, subject["root"])

    def document(name):
        return json.loads(subjects[name]["receipt"].read_text())

    prepare("b")
    denied_directory = subjects["b"]["root"] / "var/denied"
    denied_directory.mkdir(mode=0o500)
    try:
        run(["./bin/runtime", "paths", "var/prepared", "var/denied/child"], subjects["b"]["root"], expected=1)
        pending_directory = document("b")["lifecycle"]["operations"][-1]
        assert pending_directory["status"] == "pending" and (subjects["b"]["root"] / "var/prepared").is_dir()
    finally:
        denied_directory.chmod(0o700)
    run(["./bin/runtime", "reconcile", "--operation", pending_directory["id"]], subjects["b"]["root"])
    assert (denied_directory / "child").is_dir() and len(document("b")["lifecycle"]["operations"]) == 1
    run(["./bin/exec", "sh", "-c", "printf protected-shared-fixture > /fixture-named/sentinel"], subjects["b"]["root"])
    b = document("b")["lifecycle"]["resources"]
    shared = next(v["identity"] for v in b["volumes"] if v["creation"]["destination"] == "/fixture-named")
    b_id = b["containers"][0]["identity"]

    def data_sentinel():
        result = subprocess.run(["docker", "cp", b_id + ":/fixture-named/sentinel", "-"], capture_output=True, timeout=30)
        assert result.returncode == 0
        with tarfile.open(fileobj=io.BytesIO(result.stdout)) as archive:
            value = archive.extractfile(archive.getmembers()[0]).read()
        assert value == b"protected-shared-fixture"
        return hashlib.sha256(value).hexdigest()

    before_shared = data_sentinel()
    prepare("a", shared)
    # Actual separate owning wrappers allocate concurrently in the two registered worktrees.
    child = subprocess.Popen(["./bin/exec", "sleep", "1"], cwd=subjects["b"]["root"], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    run(["./bin/exec", "true"], subjects["a"]["root"])
    out, err = child.communicate(timeout=30)
    assert child.returncode == 0
    (evidence / "concurrent-b-stdout.log").write_bytes(out)
    (evidence / "concurrent-b-stderr.log").write_bytes(err)
    run(["./bin/exec", "sh", "-c", "exit 7"], subjects["a"]["root"], expected=7)
    # Interrupt only the original owning wrapper, never a coordinator or foreign PID.
    child = subprocess.Popen(["./bin/exec", "sleep", "30"], cwd=subjects["a"]["root"], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    operation = None
    for _ in range(200):
        value = document("a")["lifecycle"]["operations"][-1]
        wait = next((step for step in value["steps"] if step["argv"] == ["docker", "wait", value.get("returned_container")]), None)
        process = wait.get("process") if wait else None
        if value["status"] == "pending" and value.get("returned_container") and process and process.get("exit") == 0 and process.get("observation") and wait["status"] == "pending":
            operation = value["id"]
            save(evidence / "original-interrupt-boundary.json", {"operation": value, "receipt": str(subjects["a"]["receipt"]),
                 "boundary": "original docker wait process identity durably captured before signalling the owned wrapper"})
            break
        assert child.poll() is None, "owning wrapper exited before durable wait identity; preserve original receipt"
        time.sleep(.1)
    assert operation and child.poll() is None, "durable original wait identity unavailable; preserve original receipt, do not manufacture readiness"
    child.send_signal(signal.SIGTERM)
    out, err = child.communicate(timeout=15)
    assert child.returncode == -signal.SIGTERM
    (evidence / "interrupt-stdout.log").write_bytes(out)
    (evidence / "interrupt-stderr.log").write_bytes(err)
    count = len(document("a")["lifecycle"]["resources"]["containers"])
    run(["./bin/exec", "true"], subjects["a"]["root"], expected=1)
    assert len(document("a")["lifecycle"]["resources"]["containers"]) == count
    run(["./bin/runtime", "cancel", "--operation", operation], subjects["a"]["root"])
    run(["./bin/runtime", "validate", "--stage", "work-probe-resume"], subjects["a"]["root"])
    assert document("a")["lifecycle"]["operations"][-1]["outcome"] == "cancelled"
    assert data_sentinel() == before_shared and file_record(sentinel) == sentinel_before

    # A real filesystem failure AFTER Docker creates a retained creator. The shim only
    # forwards the real command and denies this fixture's own evidence directory.
    denial = evidence / "write-denial"
    denial.mkdir()
    real_docker = shutil.which("docker")
    (denial / "docker").write_text("#!/usr/bin/env python3\nimport os,subprocess,sys\nfrom pathlib import Path\nr=subprocess.run([" + repr(real_docker) + ",*sys.argv[1:]],capture_output=True)\nif sys.argv[1:2]==['compose'] and 'run' in sys.argv and r.returncode==0:\n p=Path(" + repr(str(denial / "original-creation-return.json")) + ");p.write_text(__import__('json').dumps({'argv':sys.argv[1:],'exit':r.returncode,'stdout':r.stdout.decode()}));p.chmod(0o600)\n os.chmod(" + repr(str(store)) + ",0o500)\nsys.stdout.buffer.write(r.stdout);sys.stderr.buffer.write(r.stderr);sys.exit(r.returncode)\n")
    (denial / "docker").chmod(0o700)
    try:
        run(["./bin/exec", "true"], subjects["a"]["root"], expected=1, env={"PATH": str(denial) + os.pathsep + os.environ["PATH"]})
        failed_op = document("a")["lifecycle"]["operations"][-1]
        assert failed_op["status"] == "pending" and failed_op["steps"][0]["dispatch"]
        allocation_count = len(document("a")["lifecycle"]["operations"])
        run(["./bin/exec", "true"], subjects["a"]["root"], expected=1)
        assert len(document("a")["lifecycle"]["operations"]) == allocation_count
    finally:
        # Explicit release of the owned probe condition, NEVER recovery of a real resource hold.
        store.chmod(0o700)
    run(["./bin/runtime", "reconcile", "--operation", failed_op["id"]], subjects["a"]["root"])
    assert document("a")["lifecycle"]["operations"][-1]["outcome"] == "reconciled-partial"
    run(["./bin/runtime", "docker", "compose", "create", "web"], subjects["a"]["root"])
    run(["./bin/runtime", "docker", "compose", "create", "web"], subjects["a"]["root"])
    assert any(event["state"] == "replaced" for item in document("a")["lifecycle"]["resources"]["containers"] for event in item["history"])
    assert data_sentinel() == before_shared and file_record(sentinel) == sentinel_before
    build_source = subjects["b"]["root"] / "build-source"
    build_source.mkdir()
    (build_source / "Dockerfile").write_text("FROM scratch\nLABEL qualification=task00547-metadata-only\n")
    run(["./bin/runtime", "docker", "build", "--pull=false", "--tag", subjects["b"]["project"] + "-root", str(build_source)], subjects["b"]["root"])
    built_images = [v for v in document("b")["lifecycle"]["resources"]["images"] if v["creation"]["kind"] == "created"]
    assert len(built_images) == 1 and built_images[0]["ownership"] == "proven"
    run(["./bin/runtime", "qualify-image", "--identity", built_images[0]["identity"]], subjects["b"]["root"])
    assert document("b")["lifecycle"]["resources"]["images"][-1]["retirement"]["kind"] == "owned-root-references"
    assert document("b")["lifecycle"]["resources"]["caches"][0]["disposition"] == "hold"

    repair_coverage = []
    if args.repair_boundaries:
        prior_operations = len(document("b")["lifecycle"]["operations"])
        for extra in (["--tag", "postgres:17-bookworm"], ["-t", "postgres:17-bookworm"],
                      ["-o", "type=local,dest=" + str(evidence / "forbidden-output")]):
            run(["./bin/runtime", "docker", "build", "--tag", subjects["b"]["project"] + "-rejected", *extra, str(build_source)], subjects["b"]["root"], expected=1)
        invalid_context = subjects["b"]["root"] / "invalid-base"
        invalid_context.mkdir()
        (invalid_context / "Dockerfile").write_text("FROM " + image + "\n")
        run(["./bin/runtime", "docker", "build", "--tag", subjects["b"]["project"] + "-invalid-base", str(invalid_context)], subjects["b"]["root"], expected=1)
        assert len(document("b")["lifecycle"]["operations"]) == prior_operations
        assert not (evidence / "forbidden-output").exists()
        assert run(["docker", "image", "inspect", "postgres:17-bookworm", "--format", "{{.Id}}"], safe) == image
        repair_coverage.append("repeated-output-aliases-reject-with-shared-tag-preserved")
        run(["./bin/runtime", "docker", "run", "--rm", "--pull=never", "--network=none", "--read-only", "--memory=32m", "--pids-limit=8", image, "true"], subjects["b"]["root"])
        standalone = document("b")["lifecycle"]["operations"][-1]
        assert standalone["kind"] == "standalone-run" and standalone["status"] == "recorded" and standalone["standalone_preflight"]
        repair_coverage.append("real-standalone-declared-volume-preflight-and-creator")
        # The exact original adapter self-signals AFTER forwarding a real finite
        # attach. This is a caller-outcome boundary, not a live daemon race.
        adapter = evidence / "attach-signal"
        adapter.mkdir()
        (adapter / "docker").write_text("#!/usr/bin/env python3\nimport os,signal,subprocess,sys,json\nfrom pathlib import Path\nr=subprocess.run([" + repr(real_docker) + ",*sys.argv[1:]])\nif sys.argv[1:3]==['start','--attach']:\n Path(" + repr(str(adapter / "original-docker-return.json")) + ").write_text(json.dumps({'argv':sys.argv[1:],'exit':r.returncode,'pid':os.getpid()}))\n os.kill(os.getpid(),signal.SIGTERM)\nsys.exit(r.returncode)\n")
        (adapter / "docker").chmod(0o700)
        run(["./bin/runtime", "docker", "run", "--pull=never", "--network=none", "--read-only", "--tmpfs", "/var/lib/postgresql/data", "--memory=32m", "--pids-limit=8", image, "true"], subjects["b"]["root"], expected=1,
            env={"PATH": str(adapter) + os.pathsep + os.environ["PATH"]})
        signalled = document("b")["lifecycle"]["operations"][-1]
        assert signalled["status"] == "pending" and signalled["steps"][-1]["exit"] == -signal.SIGTERM
        assert json.loads((adapter / "original-docker-return.json").read_text())["exit"] == 0
        run(["./bin/runtime", "validate", "--stage", "work"], subjects["b"]["root"], expected=1)
        run(["./bin/runtime", "reconcile", "--operation", signalled["id"]], subjects["b"]["root"])
        recovered_signal = document("b")["lifecycle"]["operations"][-1]
        assert recovered_signal["status"] == "recorded" and recovered_signal["steps"] == signalled["steps"]
        repair_coverage.append("real-attach-signal-hold-and-stopped-creator-same-operation-recovery")
        prepare("c", shared)
        # Real owning preflight/daemon observations; zero capacity must reject BEFORE
        # Docker allocation, including the image's declared anonymous PostgreSQL data.
        before_containers = run(["docker", "ps", "-aq", "--no-trunc"], safe).splitlines()
        before_volumes = run(["docker", "volume", "ls", "-q"], safe).splitlines()
        run(["./bin/runtime", "docker", "run", "--rm", "--pull=never", "--network=none", image, "true"], subjects["c"]["root"], expected=1)
        assert not document("c")["lifecycle"]["operations"]
        assert set(run(["docker", "ps", "-aq", "--no-trunc"], safe).splitlines()) <= set(before_containers)
        assert set(run(["docker", "volume", "ls", "-q"], safe).splitlines()) <= set(before_volumes)
        repair_coverage.append("standalone-zero-budget-no-effect")
        run(["./bin/runtime", "docker", "compose", "create", "web"], subjects["c"]["root"], expected=1)
        external_pending = document("c")["lifecycle"]["operations"][-1]
        assert external_pending["status"] == "pending" and not external_pending["steps"]
        assert "volume budget insufficient before creation" in (evidence / commands[-1]["stderr"]).read_text()
        assert not run(["docker", "ps", "-aq", "--filter", "label=com.docker.compose.project=" + subjects["c"]["project"]], safe)
        run(["./bin/runtime", "reconcile", "--operation", external_pending["id"]], subjects["c"]["root"])
        assert document("c")["lifecycle"]["policy"]["budget"]["volumes"] == 0
        used = next(v for v in document("a")["lifecycle"]["resources"]["volumes"] if v["identity"] == shared)
        assert used["creation"]["kind"] == "reused" and used["ownership"] == "unknown" and used["disposition"] == "hold"
        assert data_sentinel() == before_shared
        repair_coverage.append("compose-external-zero-budget-before-creator-and-adequate-reuse-unknown")

        # Cold owning startup: exact bin/up Compose argv, real enabled-profile
        # dependency closure and metadata-only builds; no TLS/HTTP readiness claim.
        for service in ("web", "https_php"):
            path = build_source / service
            path.mkdir()
            (path / "Dockerfile").write_text("FROM " + base_reference + "\nLABEL qualification=task00547-cold-" + service + "\n")
        model = {"services": {
            "web": {"image": subjects["b"]["project"] + "-web", "build": {"context": str(build_source / "web")}},
            "https": {"image": image, "profiles": ["https"], "depends_on": {"https_php": {"condition": "service_started"}}},
            "https_php": {"image": subjects["b"]["project"] + "-https-php", "profiles": ["https"],
                          "build": {"context": str(build_source / "https_php")}}}}
        for service in model["services"].values():
            service.update(pull_policy="never", entrypoint=[], command=["sleep", "65"], read_only=True,
                           mem_limit="32m", cpus=.25, pids_limit=8)
        cold_config = store / "b-cold-compose.json"
        save(cold_config, model)
        cold_spec = json.loads((store / "b-spec.json").read_text())
        # Services must be originally classified; original b intent already lists
        # the cold services when --repair-boundaries is selected.
        cold_spec["config_files"] = [str(cold_config)]
        cold_path = store / "b-cold-spec.json"
        save(cold_path, cold_spec)
        run(["./bin/runtime", "rebind", "--spec", cold_path], subjects["b"]["root"])
        for service in ("web", "https_php"):
            tag = model["services"][service]["image"]
            run(["docker", "image", "inspect", tag], safe, expected=1)
        run(["./bin/runtime", "docker", "compose", "--profile", "https", "up", "--detach", "--build", "--wait", "--wait-timeout", "120", "web", "https"], subjects["b"]["root"])
        cold = document("b")["lifecycle"]
        assert len([v for v in cold["resources"]["images"] if v["creation"]["kind"] == "created"]) == 3
        assert set(cold["operations"][-1]["selected_services"]) == {"web", "https", "https_php"}
        assert data_sentinel() == before_shared
        repair_coverage.append("cold-bin-up-compose-argv-builds-enabled-dependency")
        dependency_ids = {v["identity"] for v in cold["resources"]["containers"] if v["service"] in ("web", "https_php")}
        run(["./bin/runtime", "docker", "compose", "up", "--detach", "--no-deps", "--no-build", "--wait", "https"], subjects["b"]["root"])
        suppressed = document("b")["lifecycle"]
        assert suppressed["operations"][-1]["selected_services"] == ["https"]
        assert any(step["argv"][step["argv"].index("up") + 1:step["argv"].index("up") + 3] == ["--no-start", "--no-deps"]
                   for step in suppressed["operations"][-1]["steps"] if "up" in step["argv"])
        assert dependency_ids == {v["identity"] for v in suppressed["resources"]["containers"] if v["service"] in ("web", "https_php")}
        assert dependency_ids <= set(run(["docker", "ps", "-aq", "--no-trunc"], safe).splitlines())
        repair_coverage.append("real-no-deps-replacement-preserves-dependency-identities")
        ordinary = run(["./bin/runtime", "docker", "compose", "exec", "-T", "web", "sh", "-c", "printf ordinary-supervised-output"], subjects["b"]["root"])
        assert ordinary == "ordinary-supervised-output"
        run(["./bin/runtime", "docker", "compose", "exec", "-T", "web", "sleep", "999"], subjects["b"]["root"], expected=1, timeout=50)
        pending_exec = document("b")["lifecycle"]["operations"][-1]
        assert pending_exec["status"] == "pending" and pending_exec["steps"][-1]["outcome"] == "supervision-expired"
        assert pending_exec["steps"][-1]["process"]["exit"] == 0
        run(["./bin/runtime", "reconcile", "--operation", pending_exec["id"]], subjects["b"]["root"], expected=1)
        # This second command acquires the SAME lock but must not dispatch work.
        count = len(document("b")["lifecycle"]["operations"])
        run(["./bin/runtime", "docker", "compose", "ps"], subjects["b"]["root"], expected=1)
        assert len(document("b")["lifecycle"]["operations"]) == count
        time.sleep(38)  # Only the fixture's original finite service lifetime; no stop/kill.
        run(["./bin/runtime", "reconcile", "--operation", pending_exec["id"]], subjects["b"]["root"])
        recovered = document("b")["lifecycle"]["operations"][-1]
        proof = json.loads(Path(recovered["observations"][-1]["path"]).read_text())
        assert proof["original_exit"] == "unknown" and proof["original_steps"] and proof["stopped_containers"]
        assert all(not value["state"]["Running"] for value in proof["stopped_containers"])
        run(["./bin/runtime", "validate",  "--stage", "work-repair-exec-recovery"], subjects["b"]["root"])
        repair_coverage += ["reused-exec-output", "real-exec-timeout-cli-not-daemon-exit", "same-lock-recovery-no-replay", "exact-target-natural-exit-reconciliation"]

        # A real owning wrapper interrupted while its original adapter child is
        # still live. Pause BEFORE Docker's create effect; never seed an allocation
        # race or leave an unknown daemon resource just to demonstrate this guard.
        prepare("d")
        pause = evidence / "live-cli-pause"
        pause.mkdir()
        marker = pause / "original-child.json"
        (pause / "docker").write_text("#!/usr/bin/env python3\nimport os,subprocess,sys,time,json\nfrom pathlib import Path\nif sys.argv[1:2]==['compose'] and 'create' in sys.argv:\n Path(" + repr(str(marker)) + ").write_text(json.dumps({'pid':os.getpid(),'before_docker_effect':True}));time.sleep(8);sys.exit(7)\nr=subprocess.run([" + repr(real_docker) + ",*sys.argv[1:]]);sys.exit(r.returncode)\n")
        (pause / "docker").chmod(0o700)
        child = subprocess.Popen(["./bin/runtime", "docker", "compose", "up", "--detach", "--wait", "--wait-timeout", "120", "web"], cwd=subjects["d"]["root"],
                                 env=dict(os.environ, PATH=str(pause) + os.pathsep + os.environ["PATH"]), stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        for _ in range(200):
            current_operation = document("d")["lifecycle"]["operations"]
            if marker.exists() and current_operation and current_operation[-1]["steps"] and current_operation[-1]["steps"][-1].get("process"):
                break
            assert child.poll() is None
            time.sleep(.1)
        assert marker.exists()
        pending = document("d")["lifecycle"]["operations"][-1]
        assert pending["status"] == "pending" and pending["steps"][-1]["process"]["pid"] == json.loads(marker.read_text())["pid"]
        child.terminate()
        assert child.wait(timeout=5) == -signal.SIGTERM
        run(["./bin/runtime", "reconcile", "--operation", pending["id"]], subjects["d"]["root"], expected=1)
        run(["./bin/runtime", "resume", "--operation", pending["id"]], subjects["d"]["root"], expected=1)
        out, err = child.communicate(timeout=12)
        (evidence / "live-cli-stdout.log").write_bytes(out)
        (evidence / "live-cli-stderr.log").write_bytes(err)
        run(["./bin/runtime", "reconcile", "--operation", pending["id"]], subjects["d"]["root"], expected=1)
        run(["./bin/runtime", "validate", "--stage", "work-live-cli-hold"], subjects["d"]["root"], expected=1)
        assert not any(document("d")["lifecycle"]["resources"].values())
        assert document("d")["lifecycle"]["operations"][-1]["status"] == "pending"
        repair_coverage.append("original-live-cli-and-unresolved-exit-remain-held-before-effects")

    control = evidence / "controlled-hosting"
    control.mkdir()
    oid = run([real_git, "rev-parse", "HEAD"], safe)
    prs = {}
    for number, (name, subject) in enumerate(subjects.items(), 1):
        url = "https://github.com/task00547/lifecycle-fixture/pull/" + str(number)
        prs[url] = {"url": url, "state": "OPEN", "mergedAt": None, "mergeCommit": None, "baseRefName": "develop",
                    "headRefName": subject["branch"], "headRefOid": oid,
                    "headRepository": {"name": "lifecycle-fixture"}, "headRepositoryOwner": {"login": "task00547"}}
        delivery = store / (name + "-delivery.json")
        save(delivery, {"qualification": "controlled hosting only; NOT actual publication or independent acceptance",
             "oid": oid, "publication": {"url": url, "repository": "task00547/lifecycle-fixture", "number": number,
                 "base": "develop", "base_ref": "refs/heads/develop", "delivery_verified": True, "merge_oid": None, "merged_at": None},
             "exited_workers": [], "protected_files": [],
             "worktree": {"identity": str(subject["root"]), "disposition": "retain", "ownership": "proven", "consumers": [], "reason": "intentional retained direct-qualification source/evidence"},
             "branch": {"identity": "refs/heads/" + subject["branch"], "disposition": "retain", "ownership": "proven", "consumers": [], "reason": "intentional qualification replay evidence"}})
        run(["./bin/runtime", "plan", "--delivery", delivery], subject["root"])
    save(control / "prs.json", prs)
    (control / "gh").write_text("#!/usr/bin/env python3\nimport json,sys\nfrom pathlib import Path\np=json.loads((Path(__file__).parent/'prs.json').read_text());assert sys.argv[1:3]==['pr','view'];print(json.dumps(p[sys.argv[3]]))\n")
    refs = {"refs/heads/" + v["branch"]: oid for v in subjects.values()}
    (control / "git").write_text("#!/usr/bin/env python3\nimport os,sys\nr=" + repr(refs) + "\nif sys.argv[1:4]==['ls-remote','--heads','origin']:\n print(r[sys.argv[4]]+'\\t'+sys.argv[4])\nelse: os.execv(" + repr(real_git) + ",[" + repr(real_git) + ",*sys.argv[1:]])\n")
    for name in ("git", "gh"):
        (control / name).chmod(0o700)
    env = {"PATH": str(control) + os.pathsep + os.environ["PATH"]}
    helper = source / ".pi/skills/land/scripts/cleanup.py"
    run([sys.executable, helper, "--receipt", subjects["a"]["receipt"]], safe, expected=1, env=env)
    worker = subprocess.Popen([sys.executable, str(helper), "--receipt", str(subjects["a"]["receipt"]), "--apply"], cwd=safe,
                              env=dict(os.environ, **env), stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    save(evidence / "original-cleanup-worker.json", {"pid": worker.pid, "cwd": str(safe), "qualification": "owned direct fixture worker, not Land/Build"})
    interrupted = False
    for _ in range(600):
        attempts = document("a")["cleanup"].get("attempts", [])
        if attempts and any(v["kind"] == "container" and v["status"] == "removed" for v in attempts[-1]["results"]) and worker.poll() is None:
            worker.send_signal(signal.SIGTERM)
            interrupted = True
            break
        if worker.poll() is not None:
            break
        time.sleep(.1)
    stdout, stderr = worker.communicate(timeout=30)
    (evidence / "interrupted-cleanup-stdout.log").write_bytes(stdout)
    (evidence / "interrupted-cleanup-stderr.log").write_bytes(stderr)
    assert interrupted and worker.returncode == -signal.SIGTERM
    run([sys.executable, helper, "--receipt", subjects["a"]["receipt"], "--apply"], safe, expected=1, env=env)
    a_results = document("a")["cleanup"]["attempts"][-1]["results"]
    assert any(v["identity"] == shared and v["status"] == "held" for v in a_results)
    assert any(v["status"] in ("removed", "absent") for v in a_results if v["kind"] == "container")
    assert data_sentinel() == before_shared and file_record(sentinel) == sentinel_before
    run([sys.executable, helper, "--receipt", subjects["a"]["receipt"], "--apply"], safe, expected=1, env=env)
    resumed = document("a")["cleanup"]["attempts"][-1]["results"]
    assert all(v["status"] in ("removed", "absent") for v in resumed if v["kind"] in ("container", "network") or (v["kind"] == "volume" and v["identity"] != shared))
    run([sys.executable, helper, "--receipt", subjects["b"]["receipt"], "--apply"], safe, expected=1, env=env)
    b_results = document("b")["cleanup"]["attempts"][-1]["results"]
    replaced = {v["identity"]: v["name"] for v in document("b")["lifecycle"]["resources"]["containers"]
                if any(event["state"] == "replaced" for event in v["history"])}
    stale = [v for v in b_results if v["kind"] == "container" and v["status"] == "held" and v["identity"] in replaced]
    if stale:
        # The first pass correctly held an absent old ID while its independently
        # recorded replacement still occupied that name. Resume ONLY after fresh
        # observation proves that the eligible replacement was retired; no replay.
        present = set(run(["docker", "ps", "-a", "--format", "{{.Names}}"], safe).splitlines())
        require_absent = {replaced[v["identity"]].lstrip("/") for v in stale}
        assert not require_absent.intersection(present)
        run([sys.executable, helper, "--receipt", subjects["b"]["receipt"], "--apply"], safe, expected=1, env=env)
        b_results = document("b")["cleanup"]["attempts"][-1]["results"]
    assert any(v["kind"] == "cache" and v["status"] == "held" for v in b_results)
    created_roots = {v["identity"] for v in document("b")["lifecycle"]["resources"]["images"] if v["creation"]["kind"] == "created"}
    assert all(v["status"] in ("removed", "absent") for v in b_results if v["kind"] in ("container", "network", "volume") or v["identity"] in created_roots)
    for identifier in sorted(created_roots):
        run(["docker", "image", "inspect", identifier], safe, expected=1)
    assert not created_roots.intersection(run(["docker", "image", "ls", "--all", "--no-trunc", "--quiet"], safe).splitlines())
    run(["docker", "image", "inspect", image, "--format", "{{.Id}}"], safe)
    all_containers = set(run(["docker", "ps", "-aq", "--no-trunc"], safe).splitlines())
    all_volumes = set(run(["docker", "volume", "ls", "-q"], safe).splitlines())
    all_networks = set(run(["docker", "network", "ls", "-q", "--no-trunc"], safe).splitlines())
    for name in ("c", "d") if args.repair_boundaries else ():
        run([sys.executable, helper, "--receipt", subjects[name]["receipt"], "--apply"], safe, expected=1 if name == "d" else 0, env=env)
    for name in subjects:
        resources = document(name)["lifecycle"]["resources"]
        assert not all_containers.intersection(v["identity"] for v in resources["containers"])
        assert not all_volumes.intersection(v["identity"] for v in resources["volumes"])
        assert not all_networks.intersection(v["identity"] for v in resources["networks"])
    assert file_record(sentinel) == sentinel_before
    assert hashes == {name: hashlib.sha256((source / name).read_bytes()).hexdigest() for name in files}
    save(evidence / "result.json", {"pass": True, "qualification": "real owning wrappers/Docker/Git; controlled hosting, NOT actual role journeys/publication",
         "receipts": {name: str(v["receipt"]) for name, v in subjects.items()}, "independent_absence": True, "capture_recoveries": [value for value in commands if value.get("bounded_capture_recovery")],
         "protected_shared_data_verified_before_owner_retirement": before_shared, "protected_file_verified": sentinel_before,
         "a_aggregate": "incomplete: original external/unknown-volume hold retained even after B's independently authorized absence",
         "b_aggregate": "incomplete only for exact cache association/retirement hold; owned image/runtime absent; source/refs retained as qualification evidence",
         "repair_boundary_coverage": repair_coverage,
         "repair_held": "D original ambiguous pre-effect dispatch remains pending; no Docker resources allocated" if args.repair_boundaries else None,
         "coverage": ["binding-write-failure-same-intent-recovery", "partial-directory-preparation-recovery", "normal", "exit-7", "interruption", "blocked-dependent-allocation", "cancel-resume", "two-concurrent-worktrees", "shared-unknown-hold", "real-evidence-write-failure", "same-operation-reconciliation", "creator-replacement", "independent-cleanup-effects", "cleanup-interruption", "same-receipt-cleanup-resume", "metadata-only-image-build", "exact-owned-root-removal", "original-root-digest-capability-requalification", "builder-cache-observation-and-hold"],
         "not_qualified": ["actual independent role journeys", "actual publication", "exact cache retirement", "Git source/ref removal"]})
    print(json.dumps({"pass": True, "evidence": str(evidence), "qualification": "controlled hosting only"}))


if __name__ == "__main__":
    try:
        main()
    finally:
        signal.alarm(0)
