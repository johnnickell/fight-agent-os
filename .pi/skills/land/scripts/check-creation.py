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
    args = parser.parse_args()
    os.umask(0o077)
    evidence = Path(args.evidence).resolve()
    evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
    source = Path(__file__).resolve().parents[4]
    real_git = shutil.which("git")
    commands = []

    def run(argv, cwd, expected=0, env=None, timeout=180):
        result = subprocess.run([str(v) for v in argv], cwd=cwd, capture_output=True,
                                env=dict(os.environ, **(env or {})), timeout=timeout)
        number = len(commands)
        (evidence / f"{number:03}-stdout.log").write_bytes(result.stdout)
        (evidence / f"{number:03}-stderr.log").write_bytes(result.stderr)
        commands.append({"argv": [str(v) for v in argv], "cwd": str(cwd), "exit": result.returncode,
                         "stdout": f"{number:03}-stdout.log", "stderr": f"{number:03}-stderr.log"})
        (evidence / "commands.json").write_text(json.dumps(commands, indent=2))
        if result.returncode != expected and expected in (0, 7) and str(argv[0]) in ("./bin/exec", "./bin/runtime"):
            locator = Path(cwd) / "var/resource-lifecycle.json"
            if locator.exists():
                receipt = Path(json.loads(locator.read_text())["receipt"])
                last = json.loads(receipt.read_text())["lifecycle"]["operations"][-1]
                if last["status"] == "pending" and last["kind"] in ("run", "create") and last["steps"] and last["steps"][0].get("exit") == 0:
                    # A concurrent Docker observation may be unavailable. Reconcile once;
                    # never repeat the allocation or relabel the original nonzero exit.
                    run(["./bin/runtime", "reconcile", "--operation", last["id"]], cwd)
                    recovered = json.loads(receipt.read_text())["lifecycle"]["operations"][-1]
                    assert recovered["status"] == "recorded" and (last["kind"] == "create" or recovered.get("exit") == expected)
                    commands[number]["bounded_capture_recovery"] = {"operation": last["id"], "outcome": recovered["outcome"], "workload_exit": recovered.get("exit")}
                    (evidence / "commands.json").write_text(json.dumps(commands, indent=2))
                    return result.stdout.decode().strip()
        assert result.returncode == expected, (number, result.returncode, expected)
        return result.stdout.decode().strip()

    image = run(["docker", "image", "inspect", "postgres:17-bookworm", "--format", "{{.Id}}"], source)
    builder = run(["docker", "context", "show"], source)
    files = ["bin/runtime", "bin/exec", ".pi/skills/land/scripts/lifecycle.py", ".pi/skills/land/scripts/cleanup.py"]
    hashes = {name: hashlib.sha256((source / name).read_bytes()).hexdigest() for name in files}
    save(evidence / "original-intent.json", {"version": 1, "task": "TASK-00547", "source_files": hashes,
         "source_revision": run([real_git, "rev-parse", "HEAD"], source),
         "authority": "John's bounded disposable Work qualification only; controlled hosting is NOT publication",
         "purpose": "two concurrent worktrees, original creation, failure, cancellation/interruption, write-denial/release only on owned fixture evidence/runtime directories, binding/directory recovery, replacement, independent effects and protected shared data",
         "budget": {"worktrees": 2, "containers": 10, "volumes": 16, "networks": 2, "images_created": 1,
                    "builds": 1, "build_source": "checkout-b/build-source/Dockerfile; FROM scratch plus one label only, no RUN/pull/base layers", "builder": builder, "pulls": 0, "seconds_per_command": 180, "memory_per_container": "32m", "pids": 8},
         "image": {"identity": image, "creation": "reused/shared; retain"},
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
    for name in ("a", "b"):
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
        config = store / (name + "-compose.json")
        save(config, model)
        spec = store / (name + "-spec.json")
        save(spec, {"project": subject["project"], "config_files": [str(config)], "env_files": [],
                    "policy": {"services": {"web": {"data": "disposable", "authority": "original bounded disposable TASK-00547 fixture; shared external storage is excluded"}},
                               "cancel_authority": "only exact original bounded fixture oneoffs may be cancelled",
                               "oneoff_authority": "bounded disposable stopped fixture oneoffs",
                               "builder": builder,
                               "image_authority": "original bounded metadata-only scratch fixture root/reference; no shared layers or cache deletion",
                               "budget": {"operations": 16, "seconds": 180, "containers": 8, "networks": 4, "volumes": 12, "images": 4, "caches": 1}},
                    "private_paths": [{"path": str(subject["root"] / "var"), "disposition": "preserve", "reason": "runtime binding/configuration evidence"}],
                    "protected_files": [str(sentinel)]})
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
        if value["status"] == "pending" and value.get("returned_container") and len(value["steps"]) >= 2:
            operation = value["id"]
            break
        time.sleep(.1)
    assert operation
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
    assert any(v["kind"] == "cache" and v["status"] == "held" for v in b_results)
    assert all(v["status"] in ("removed", "absent") for v in b_results if v["kind"] in ("container", "network", "volume") or v["identity"] == built_images[0]["identity"])
    run(["docker", "image", "inspect", built_images[0]["identity"]], safe, expected=1)
    assert built_images[0]["identity"] not in run(["docker", "image", "ls", "--all", "--no-trunc", "--quiet"], safe).splitlines()
    run(["docker", "image", "inspect", image, "--format", "{{.Id}}"], safe)
    all_containers = set(run(["docker", "ps", "-aq", "--no-trunc"], safe).splitlines())
    all_volumes = set(run(["docker", "volume", "ls", "-q"], safe).splitlines())
    all_networks = set(run(["docker", "network", "ls", "-q", "--no-trunc"], safe).splitlines())
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
         "coverage": ["binding-write-failure-same-intent-recovery", "partial-directory-preparation-recovery", "normal", "exit-7", "interruption", "blocked-dependent-allocation", "cancel-resume", "two-concurrent-worktrees", "shared-unknown-hold", "real-evidence-write-failure", "same-operation-reconciliation", "creator-replacement", "independent-cleanup-effects", "cleanup-interruption", "same-receipt-cleanup-resume", "metadata-only-image-build", "exact-owned-root-removal", "original-root-digest-capability-requalification", "builder-cache-observation-and-hold"],
         "not_qualified": ["actual independent role journeys", "actual publication", "exact cache retirement", "Git source/ref removal"]})
    print(json.dumps({"pass": True, "evidence": str(evidence), "qualification": "controlled hosting only"}))


if __name__ == "__main__":
    main()
