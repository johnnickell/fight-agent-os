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
import re
import signal
import socket
import threading
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


def standalone(args, module, evidence, helper):
    """Own a new docker-run tmpfs subject; never adopt resources by name."""
    root = evidence / "safe"
    receipt = root / ".runs/tasks/TASK-00542/runs/fixture.json"
    if not args.resume:
        evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
        root.mkdir()
        run(["git", "init", "--initial-branch=develop"], root)
        run(["git", "-c", "user.name=Lifecycle fixture", "-c", "user.email=fixture@example.invalid",
             "commit", "--allow-empty", "-m", "Bounded standalone fixture"], root)
        origin = "git@github.com:task00542/fixture.git"
        run(["git", "remote", "add", "origin", origin], root)
        oid = run(["git", "rev-parse", "HEAD"], root)
        image = json.loads(run(["docker", "image", "inspect", "mysql:8.4.11" if args.case == "mysql" else "postgres:17-bookworm"], root))[0]
        name = "task00542-standalone-" + uuid.uuid4().hex[:12]
        source = {"revision": oid, "workload": "bounded database tmpfs qualification" if args.case in ("database", "mysql") else "bounded idle tmpfs qualification",
                  "helper_sha256": module.digest(helper.read_bytes()),
                  "checker_sha256": module.digest(Path(__file__).read_bytes())}
        intent = evidence / "creation-intent.json"
        private_json(intent, {"source": source, "name": name, "image": image["Id"],
                              "authority": "explicit TASK-00542 disposable fixture grant",
                              "data": "disposable", "tmpfs": "/var/lib/mysql" if args.case == "mysql" else "/var/lib/postgresql/data"})
        network_id = run(["docker", "network", "create", name], root)
        network = json.loads(run(["docker", "network", "inspect", network_id], root))[0]
        database = args.case in ("database", "mysql")
        if args.case == "mysql":
            workload_args = ["--env", "MYSQL_ALLOW_EMPTY_PASSWORD=yes", image["Id"]]
            tmpfs = "/var/lib/mysql"
        else:
            workload_args = ["--env", "POSTGRES_HOST_AUTH_METHOD=trust", image["Id"]] if database else [image["Id"], "sleep", "3600"]
            tmpfs = "/var/lib/postgresql/data"
        identifier = run(["docker", "run", "--detach", "--name", name, "--network", network_id,
                          "--tmpfs", tmpfs, "--pull=never", *workload_args], root)
        if database:
            health = ["mysqladmin", "ping", "--host=127.0.0.1", "--user=root"] if args.case == "mysql" else ["pg_isready", "-U", "postgres"]
            deadline = time.monotonic() + 60
            while subprocess.run(["docker", "exec", identifier, *health], capture_output=True).returncode:
                if time.monotonic() > deadline:
                    raise RuntimeError("owned database startup incomplete; retain original intent/ID")
                time.sleep(1)
            time.sleep(2)
        container = json.loads(run(["docker", "inspect", identifier], root))[0]
        processes = subprocess.check_output(["docker", "top", identifier, "-eo", "pid,ppid,args"])
        allocation = evidence / "allocation.json"
        private_json(allocation, {"container": identifier, "network": network_id,
            "container_identity": module.container_identity(container), "network_created": network["Created"],
            "source": source, "image": container["Image"], "mounts": container["Mounts"],
            "intent_sha256": module.digest(intent.read_bytes()), "intent": str(intent)})
        creation_record = evidence / "standalone-creation.json"
        refs = [{"path": str(path), "sha256": module.digest(path.read_bytes())} for path in (intent, allocation)]
        network_config = {key: network.get(key) for key in
                          ("Id", "Name", "Created", "Scope", "Driver", "EnableIPv4", "EnableIPv6", "IPAM", "Internal", "Attachable", "Ingress", "ConfigFrom", "ConfigOnly", "Options", "Labels")}
        private_json(creation_record, {"version": 1, "task": "TASK-00542", "origin": origin,
            "source": source, "authority": "explicit TASK-00542 fixture discard grant", "data": "disposable",
            "basis": {"kind": "allocation", "evidence": refs},
            "containers": {identifier: {"name": container["Name"], "image": container["Image"],
                "inspection_sha256": module.container_identity(container),
                "mounts_sha256": module.fingerprint(container["Mounts"]),
                "workload": {"kind": ("mysql" if args.case == "mysql" else "postgres") if database else "idle", "processes_sha256": module.digest(processes)},
                "volume_declarations": []}},
            "networks": {network_id: {"name": network["Name"], "created": network["Created"],
                "inspection_sha256": module.fingerprint(network_config)}}})
        common = {"disposition": "remove", "ownership": "proven", "consumers": [],
                  "reason": "new isolated disposable standalone fixture", "data": "disposable",
                  "authority": "explicit TASK-00542 fixture discard grant",
                  "creation": {"kind": "created", "intent": str(intent), "intent_sha256": module.digest(intent.read_bytes())}}
        retained = {"disposition": "retain", "ownership": "proven", "consumers": [],
                    "reason": "fixture source/evidence; no Git removal subject"}
        plan = {"task": "TASK-00542", "safe_checkout": str(root), "git_common_dir": str(root / ".git"),
            "origin": origin, "ref": "refs/heads/feature/task-00542-fixture", "oid": oid,
            "publication": {"url": "https://github.com/task00542/fixture/pull/1", "repository": "task00542/fixture",
                "number": 1, "base": "develop", "base_ref": "refs/heads/develop", "delivery_verified": True,
                "merge_oid": oid, "merged_at": "2026-10-28T00:00:00Z"},
            "protected_files": [], "exited_workers": [],
            "runtime": {"kind": "standalone", "ownership": "proven", "project": name,
                "working_directory": str(root), "env_files": [], "config_files": [],
                "creation_record": {"path": str(creation_record), "sha256": module.digest(creation_record.read_bytes())},
                "resolved_sha256": module.fingerprint(json.loads(creation_record.read_text()))},
            "containers": [dict(common, identity=identifier, name=container["Name"], service="fixture",
                inspection_sha256=module.container_identity(container), processes_sha256=module.digest(processes), bind_data={})],
            "networks": [dict(common, identity=network_id, name=network["Name"], created=network["Created"], key="fixture")],
            "volumes": [], "images": [dict(retained, identity=image["Id"], creation={"kind": "reused"})], "caches": [],
            "worktree": dict(retained, identity=str(root / "no-removal-candidate")),
            "branch": dict(retained, identity="refs/heads/feature/task-00542-fixture")}
        receipt.parent.mkdir(mode=0o700, parents=True)
        private_json(receipt, {"qualification": "real standalone Docker; controlled hosting, not publication",
                               "cleanup": {"version": 1, "plan": plan}})
        shim = evidence / "controlled-hosting"
        shim.mkdir()
        response = {"url": plan["publication"]["url"], "state": "MERGED", "mergedAt": plan["publication"]["merged_at"],
            "mergeCommit": {"oid": oid}, "baseRefName": "develop", "headRefName": "feature/task-00542-fixture",
            "headRefOid": oid, "headRepository": {"name": "fixture"}, "headRepositoryOwner": {"login": "task00542"}}
        (shim / "gh").write_text("#!/usr/bin/env python3\nprint(" + repr(json.dumps(response)) + ")\n")
        (shim / "gh").chmod(0o700)
    if args.prepare_only:
        print(json.dumps({"prepared": True, "receipt": str(receipt)}))
        return 0
    env = dict(os.environ, PATH=str(evidence / "controlled-hosting") + os.pathsep + os.environ["PATH"])
    invocation = [sys.executable, str(helper), "--receipt", str(receipt), "--apply"]
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")
    result = subprocess.run(invocation, cwd=root, env=env, capture_output=True, timeout=180)
    (evidence / (stamp + ".stdout")).write_bytes(result.stdout)
    (evidence / (stamp + ".stderr")).write_bytes(result.stderr)
    private_json(evidence / (stamp + ".result.json"), {"argv": invocation, "exit": result.returncode,
        "helper_sha256": module.digest(helper.read_bytes()), "receipt": str(receipt), "runtime": "standalone"})
    print(json.dumps({"helper_exit": result.returncode, "receipt": str(receipt)}))
    return result.returncode


def standalone_matrix(args, module, evidence, helper):
    """Exercise real effects/holds with immutable linked plans and bounded owned peers."""
    evidence.mkdir(mode=0o700, parents=True, exist_ok=args.resume)
    rows = json.loads((evidence / "matrix.json").read_text()) if args.resume and (evidence / "matrix.json").exists() else []
    checker = Path(__file__).resolve()

    def prepare(name):
        directory = evidence / name
        receipt = directory / "safe/.runs/tasks/TASK-00542/runs/fixture.json"
        if not (args.resume and receipt.exists()):
            run([sys.executable, checker, "--runtime", "standalone", "--prepare-only", "--helper", helper,
                 "--evidence", directory], evidence)
        return directory, receipt, json.loads(receipt.read_text())

    def invoke(directory, receipt, label, env=None, preview=False, expected=0):
        environment = dict(os.environ, PATH=str(directory / "controlled-hosting") + os.pathsep + os.environ["PATH"])
        environment.update(env or {})
        argv = [sys.executable, str(helper), "--receipt", str(receipt)] + ([] if preview else ["--apply"])
        result = subprocess.run(argv, cwd=directory / "safe", env=environment, capture_output=True, timeout=180)
        (directory / (label + ".stdout")).write_bytes(result.stdout)
        (directory / (label + ".stderr")).write_bytes(result.stderr)
        report = json.loads(result.stdout) if result.stdout.startswith(b"{") else {}
        semantic = True
        reasons = " ".join(entry.get("reason", "") for entry in report.get("results", []))
        if expected == 1 and not preview:
            expected_reason = {"missing-proof": "FileNotFoundError", "contradictory-proof": "evidence missing/changed",
                "unavailable": "docker", "permission": "docker", "active-consumer": "processes changed",
                "stopped-shared-unknown": "consumer", "running-shared-unknown": "consumer",
                "legacy-missing-proof": "allocation/configuration proof absent",
                "recreated-name": "recreated under recorded name"}.get(directory.name)
            semantic = expected_reason is not None and expected_reason in reasons
            if directory.name != "recreated-name":
                original_plan = json.loads((directory / "safe/.runs/tasks/TASK-00542/runs/fixture.json").read_text())["cleanup"]["plan"]
                present = json.loads(run(["docker", "inspect", original_plan["containers"][0]["identity"]], directory))[0]
                semantic = semantic and module.container_identity(present) == original_plan["containers"][0]["inspection_sha256"]
                network = json.loads(run(["docker", "network", "inspect", original_plan["networks"][0]["identity"]], directory))[0]
                semantic = semantic and network["Created"] == original_plan["networks"][0]["created"]
        row = {"case": directory.name, "label": label, "argv": argv, "exit": result.returncode,
               "expected_exit": expected, "matched": result.returncode == expected and semantic,
               "semantic_hold_and_subject_preservation": semantic, "reasons": reasons,
               "helper_sha256": module.digest(helper.read_bytes()), "receipt": str(receipt)}
        private_json(directory / (label + ".result.json"), row)
        rows.append(row)
        private_json(evidence / "matrix.json", rows)
        if not row["matched"]:
            raise RuntimeError("owning cleanup result differs; retain exact fixture and evidence")
        return report

    def linked(receipt, document, name):
        result = receipt.with_name(name + ".json")
        document["prior_receipt"] = {"path": str(receipt), "sha256": module.digest(receipt.read_bytes())}
        document["cleanup"].pop("plan_sha256", None)
        document["cleanup"]["attempts"] = []
        private_json(result, document)
        return result

    def consumer(directory, document, running=False):
        plan = document["cleanup"]["plan"]
        network = plan["networks"][0]
        image = plan["images"][0]["identity"]
        intent = directory / "peer-intent.json"
        private_json(intent, {"network": network["identity"], "image": image, "data": "disposable",
            "authority": "owned bounded unknown-to-plan peer; linked normal cleanup after hold"})
        invocation = ["docker", "run", "--detach"] if running else ["docker", "create"]
        identifier = run([*invocation, "--network", network["identity"], "--tmpfs", "/var/lib/postgresql/data",
                          "--pull=never", image, "sleep", "3600"], directory)
        current = json.loads(run(["docker", "inspect", identifier], directory))[0]
        allocation = directory / "peer-allocation.json"
        private_json(allocation, {"id": identifier, "inspection_sha256": module.container_identity(current),
                                  "intent": str(intent), "source": plan["runtime"]["resolved_sha256"]})
        process_hash = module.digest(subprocess.check_output(["docker", "top", identifier, "-eo", "pid,ppid,args"])) if running else None
        proof = {"name": current["Name"], "image": current["Image"],
            "inspection_sha256": module.container_identity(current), "mounts_sha256": module.fingerprint(current["Mounts"]),
            "workload": {"kind": "idle", "processes_sha256": process_hash}, "volume_declarations": []}
        entry = dict(plan["containers"][0], identity=identifier, name=current["Name"],
            inspection_sha256=module.container_identity(current), processes_sha256=process_hash,
            creation={"kind": "created", "intent": str(intent), "intent_sha256": module.digest(intent.read_bytes())})
        return identifier, proof, entry, allocation

    protected_dir, protected_receipt, protected_doc = prepare("protected")
    protected_id = protected_doc["cleanup"]["plan"]["containers"][0]["identity"]
    if not args.resume:
        run(["docker", "exec", protected_id, "sh", "-c",
             "printf TASK00542-protected > /var/lib/postgresql/data/sentinel; chmod 640 /var/lib/postgresql/data/sentinel"], evidence)

    def protection():
        current = json.loads(run(["docker", "inspect", protected_id], evidence))[0]
        observation = run(["docker", "exec", protected_id, "sh", "-c",
            "sha256sum /var/lib/postgresql/data/sentinel; stat -c '%a %u %g' /var/lib/postgresql/data/sentinel"], evidence)
        return {"configuration": module.container_identity(current), "mounts": module.fingerprint(current["Mounts"]),
                "sentinel_digest_mode_uid_gid": observation,
                "receipt_mode": protected_receipt.stat().st_mode & 0o777}
    before = protection()
    private_json(evidence / "protected-before.json", before)
    for case in ("normal", "interrupt-stop", "interrupt-remove", "missing-proof", "contradictory-proof",
                 "unavailable", "permission", "active-consumer", "stopped-shared-unknown", "running-shared-unknown",
                 "legacy", "legacy-missing-proof", "recreated-name"):
        if args.resume and (evidence / case / "protected-after.json").exists():
            continue
        directory, receipt, original = prepare(case)
        document = json.loads(receipt.read_text())
        plan = document["cleanup"]["plan"]
        identifier = plan["containers"][0]["identity"]
        if case == "normal":
            invoke(directory, receipt, "preview", preview=True, expected=1)
            invoke(directory, receipt, "apply")
        elif case.startswith("interrupt-"):
            real_docker = run(["which", "docker"], evidence)
            shim = directory / "controlled-hosting/docker"
            operation = "stop" if case == "interrupt-stop" else "rm"
            marker = directory / "interrupted-effect.json"
            shim.write_text("#!/usr/bin/env python3\nimport os,sys,subprocess,json,signal\n"
                + "r=subprocess.run([" + repr(real_docker) + ",*sys.argv[1:]])\n"
                + "if sys.argv[1:2]==[" + repr(operation) + "] and " + repr(identifier) + " in sys.argv and r.returncode==0 and not os.path.exists(" + repr(str(marker)) + "):\n"
                + " open(" + repr(str(marker)) + ",'w').write(json.dumps({'operation':sys.argv[1:],'parent':os.getppid(),'exit':r.returncode}))\n os.kill(os.getppid(),signal.SIGTERM)\n"
                + "sys.exit(r.returncode)\n")
            shim.chmod(0o700)
            invoke(directory, receipt, "interrupted", expected=-signal.SIGTERM)
            invoke(directory, receipt, "same-receipt-resume")
        elif case in ("missing-proof", "contradictory-proof"):
            plan["runtime"]["creation_record"]["sha256"] = "0" * 64
            if case == "missing-proof":
                plan["runtime"]["creation_record"]["path"] = str(directory / "never-created.json")
            held = linked(receipt, document, "held-proof")
            invoke(directory, held, "hold", expected=1)
            invoke(directory, receipt, "original-proof-recovery")
        elif case in ("unavailable", "permission"):
            endpoint = directory / "probe.sock"
            listener = None
            if case == "permission":
                import socket
                import tempfile
                socket_dir = Path(tempfile.mkdtemp(prefix="s4-", dir=evidence.parents[1]))
                endpoint = socket_dir / "s"
                private_json(directory / "permission-endpoint.json", {"path": str(endpoint), "owner": os.getuid(),
                    "purpose": "owned denied inspection fixture, not Docker real socket"})
                listener = socket.socket(socket.AF_UNIX)
                listener.bind(str(endpoint))
                listener.listen(1)
                endpoint.chmod(0o000)  # Owned probe endpoint only; never Docker's real socket/protections.
            probe_env = dict(os.environ, DOCKER_CONTEXT="", DOCKER_HOST="unix://" + str(endpoint))
            observation = subprocess.run(["docker", "ps", "-a"], env=probe_env, capture_output=True, timeout=20)
            (directory / "endpoint-probe.stderr").write_bytes(observation.stderr)
            private_json(directory / "endpoint-probe.json", {"exit": observation.returncode,
                "stderr_sha256": module.digest(observation.stderr),
                "permission_denied": b"permission denied" in observation.stderr.lower()})
            if observation.returncode == 0 or (case == "permission" and b"permission denied" not in observation.stderr.lower()):
                raise RuntimeError("actual endpoint failure was not established; preserve fixture")
            invoke(directory, receipt, "hold", env={"DOCKER_CONTEXT": "", "DOCKER_HOST": "unix://" + str(endpoint)}, expected=1)
            if listener:
                listener.close()
            invoke(directory, receipt, "same-receipt-recovery")
        elif case == "active-consumer":
            child = subprocess.Popen(["docker", "exec", identifier, "sleep", "30"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            time.sleep(1)
            invoke(directory, receipt, "hold", expected=1)
            child.wait(timeout=45)  # Do not kill/stop a consumer to clear the hold.
            invoke(directory, receipt, "consumer-finished-resume")
        elif case in ("stopped-shared-unknown", "running-shared-unknown"):
            peer, proof, entry, allocation = consumer(directory, document, running=case.startswith("running"))
            invoke(directory, receipt, "hold", expected=1)
            record = json.loads(Path(plan["runtime"]["creation_record"]["path"]).read_text())
            record["containers"][peer] = proof
            record["basis"]["evidence"].append({"path": str(allocation), "sha256": module.digest(allocation.read_bytes())})
            new_record = directory / "linked-creation.json"
            private_json(new_record, record)
            plan["runtime"]["creation_record"] = {"path": str(new_record), "sha256": module.digest(new_record.read_bytes())}
            plan["runtime"]["resolved_sha256"] = module.fingerprint(record)
            plan["containers"].append(entry)
            released = linked(receipt, document, "owned-peer-release")
            invoke(directory, released, "linked-release")
        elif case in ("legacy", "legacy-missing-proof"):
            record = json.loads(Path(plan["runtime"]["creation_record"]["path"]).read_text())
            record["basis"]["kind"] = "legacy-adoption"
            record["basis"]["prior_receipt"] = {"path": str(receipt), "sha256": module.digest(receipt.read_bytes())}
            record["basis"]["prior_plan_sha256"] = module.fingerprint(original["cleanup"]["plan"])
            if case == "legacy-missing-proof":
                record["basis"]["evidence"] = record["basis"]["evidence"][:1]
            new_record = directory / "adopted-creation.json"
            private_json(new_record, record)
            plan["runtime"]["creation_record"] = {"path": str(new_record), "sha256": module.digest(new_record.read_bytes())}
            plan["runtime"]["resolved_sha256"] = module.fingerprint(record)
            for item in plan["containers"] + plan["networks"]:
                item["creation"] = {"kind": "legacy-created"}
            adopted = linked(receipt, document, "adopted")
            invoke(directory, adopted, "adoption", expected=1 if case.endswith("missing-proof") else 0)
            if case.endswith("missing-proof"):
                invoke(directory, receipt, "original-proof-recovery")
        else:
            invoke(directory, receipt, "original-retirement")
            # New creation has a new receipt/intent, then gets the old name. Old receipt must hold.
            replacement_dir, replacement_receipt, replacement = prepare("replacement")
            replacement_id = replacement["cleanup"]["plan"]["containers"][0]["identity"]
            run(["docker", "rename", replacement_id, plan["containers"][0]["name"].lstrip("/")], evidence)
            invoke(directory, receipt, "old-receipt-recreated-name", expected=1)
            run(["docker", "rename", replacement_id, replacement["cleanup"]["plan"]["containers"][0]["name"].lstrip("/")], evidence)
            invoke(replacement_dir, replacement_receipt, "new-owned-replacement-retirement")
            invoke(directory, receipt, "old-receipt-absence")
        after = protection()
        private_json(directory / "protected-after.json", {"observation": after, "unchanged": after == before})
        if after != before:
            raise RuntimeError("protected data/configuration/permissions changed")
    invoke(protected_dir, protected_receipt, "explicit-owned-sentinel-release")
    private_json(evidence / "summary.json", {"rows": len(rows), "all_expected_exits": all(row["matched"] for row in rows),
        "protected": before, "scope": "direct owning-tool standalone qualification, not independent acceptance"})
    print(json.dumps({"rows": len(rows), "all_expected_exits": True}))
    return 0


def owned_image_fixture(args, module, evidence, helper):
    """Load two empty owned OCI roots sharing a child; no pull/build/cache or real publication."""
    root = evidence / "safe"
    receipt = root / ".runs/tasks/TASK-00542/runs/fixture.json"
    def reference(path):
        return {"path": str(path), "sha256": module.digest(path.read_bytes())}
    if not args.resume:
        evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
        root.mkdir()
        run(["git", "init", "--initial-branch=develop"], root)
        run(["git", "-c", "user.name=Lifecycle fixture", "-c", "user.email=fixture@example.invalid",
             "commit", "--allow-empty", "-m", "Bounded owned OCI root fixture"], root)
        origin = "git@github.com:task00542/fixture.git"
        run(["git", "remote", "add", "origin", origin], root)
        oid = run(["git", "rev-parse", "HEAD"], root)
        token = uuid.uuid4().hex
        tags = ["task00542-owned-" + token + ":latest", "task00542-protected-" + token + ":latest"]
        project = "task00542-root-" + token[:12]
        intent = evidence / "creation-intent.json"
        require_absent = project + "_scratch"
        if require_absent in run(["docker", "volume", "ls", "--format", "{{.Name}}"], root).splitlines():
            raise RuntimeError("fixture named volume already exists")
        private_json(intent, {"source_revision": oid, "helper_sha256": module.digest(helper.read_bytes()),
            "references": tags, "operation": "docker image load; owning Compose create only",
            "named_volume": require_absent, "named_before_absent": True,
            "data": "disposable", "authority": "explicit TASK-00542 bounded fixture discard",
            "budget": {"roots": 2, "layers": 0, "containers": 1, "networks": 1, "volumes": 2,
                       "pulls": 0, "builds": 0, "shared_cache_growth": 0}})
        blobs = {}
        def blob(value, media):
            data = json.dumps(value, sort_keys=True, separators=(",", ":")).encode()
            identifier = "sha256:" + module.digest(data)
            blobs["blobs/sha256/" + identifier[7:]] = data
            return {"mediaType": media, "digest": identifier, "size": len(data)}
        children = []
        for architecture in ("arm64", "amd64"):
            config = blob({"architecture": architecture, "os": "linux", "config": {
                "Labels": {"io.fight.fixture": token}, "Volumes": {"/declared": {}}},
                "rootfs": {"type": "layers", "diff_ids": []}}, "application/vnd.oci.image.config.v1+json")
            child = blob({"schemaVersion": 2, "mediaType": "application/vnd.oci.image.manifest.v1+json",
                "config": config, "layers": []}, "application/vnd.oci.image.manifest.v1+json")
            child["platform"] = {"os": "linux", "architecture": architecture}
            children.append(child)
        descriptors = []
        for index, tag in enumerate(tags):
            descriptor = blob({"schemaVersion": 2, "mediaType": "application/vnd.oci.image.index.v1+json",
                "manifests": children if index == 0 else children[:1],
                "annotations": {"io.fight.fixture.root": token + str(index)}}, "application/vnd.oci.image.index.v1+json")
            descriptor["annotations"] = {"org.opencontainers.image.ref.name": tag}
            descriptors.append(descriptor)
        archive = evidence / "owned.oci.tar"
        with tarfile.open(archive, "w") as output:
            files = dict(blobs, **{"oci-layout": b'{"imageLayoutVersion":"1.0.0"}',
                "index.json": json.dumps({"schemaVersion": 2, "manifests": descriptors}).encode()})
            for name, data in files.items():
                info = tarfile.TarInfo(name)
                info.size, info.mode = len(data), 0o600
                output.addfile(info, io.BytesIO(data))
        argv = ["docker", "image", "load", "--input", str(archive)]
        loaded = subprocess.run(argv, cwd=root, capture_output=True, timeout=90)
        (evidence / "load.stdout").write_bytes(loaded.stdout)
        (evidence / "load.stderr").write_bytes(loaded.stderr)
        loaded.check_returncode()
        images = [json.loads(run(["docker", "image", "inspect", value["digest"]], root))[0] for value in descriptors]
        for index, image in enumerate(images):
            private_json(evidence / ("original-image-" + str(index) + ".json"), image)
        allocation = evidence / "allocation.json"
        private_json(allocation, {"source_revision": oid, "intent": reference(intent), "argv": argv,
            "archive_sha256": module.digest(archive.read_bytes()), "exit": loaded.returncode,
            "roots": [{"id": image["Id"], "references": image["RepoTags"] + image["RepoDigests"]} for image in images]})
        config = root / "compose.json"
        private_json(config, {"services": {"fixture": {"image": images[0]["Id"], "pull_policy": "never",
            "command": ["/fixture-never-started"], "volumes": ["scratch:/scratch"]}}, "volumes": {"scratch": {}}})
        compose = ["docker", "compose", "--project-name", project, "--project-directory", str(root), "--file", str(config)]
        run([*compose, "create"], root)
        identifier = run([*compose, "ps", "--all", "--quiet", "fixture"], root)
        current = json.loads(run(["docker", "inspect", identifier], root))[0]
        private_json(evidence / "original-containers.json", [current])
        platform = json.loads(run(["docker", "image", "inspect", "--platform", "linux/arm64", images[0]["Id"]], root))[0]
        private_json(evidence / "original-platform-images.json", [platform])
        model = json.loads(run([*compose, "config", "--format", "json"], root))
        common = {"disposition": "remove", "ownership": "proven", "consumers": [], "data": "disposable",
            "authority": "explicit TASK-00542 bounded fixture discard", "reason": "new owned disposable fixture",
            "creation": {"kind": "created", "intent": str(intent), "intent_sha256": module.digest(intent.read_bytes())}}
        retained = {"disposition": "retain", "ownership": "proven", "consumers": [], "reason": "protected fixture alias/evidence, no Git removal"}
        volumes = []
        for mount in current["Mounts"]:
            if mount["Type"] == "volume":
                observed = json.loads(run(["docker", "volume", "inspect", mount["Name"]], root))[0]
                volumes.append(dict(common, identity=mount["Name"], inspection_sha256=module.volume_identity(observed),
                    creation=dict(common["creation"], container=identifier, destination=mount["Destination"])))
        network_id = run(["docker", "network", "ls", "--no-trunc", "--filter", "label=com.docker.compose.project=" + project,
                          "--format", "{{.ID}}"], root)
        network = json.loads(run(["docker", "network", "inspect", network_id], root))[0]
        plan = {"task": "TASK-00542", "safe_checkout": str(root), "git_common_dir": str(root / ".git"),
            "origin": origin, "ref": "refs/heads/feature/task-00542-fixture", "oid": oid,
            "publication": {"url": "https://github.com/task00542/fixture/pull/1", "repository": "task00542/fixture",
                "number": 1, "base": "develop", "base_ref": "refs/heads/develop", "delivery_verified": True,
                "merge_oid": oid, "merged_at": "2026-10-28T00:00:00Z"}, "protected_files": [], "exited_workers": [],
            "runtime": {"project": project, "working_directory": str(root), "ownership": "proven", "env_files": [],
                "config_files": [reference(config)], "resolved_sha256": module.fingerprint(model)},
            "containers": [dict(common, identity=identifier, name=current["Name"], service="fixture", bind_data={},
                processes_sha256=None, inspection_sha256=module.container_identity(current))],
            "networks": [dict(common, identity=network_id, name=network["Name"], created=network["Created"], key="default")],
            "volumes": volumes, "images": [dict(common, identity=images[0]["Id"], inspection_sha256=module.fingerprint(images[0])),
                dict(retained, identity=images[1]["Id"], creation=dict(common["creation"]))], "caches": [],
            "worktree": dict(retained, identity=str(root / "no-removal-candidate")),
            "branch": dict(retained, identity="refs/heads/feature/task-00542-fixture")}
        if args.original_land_attempt:
            attempt = Path(args.original_land_attempt).resolve()
            processes = json.loads((attempt / "process.json").read_text())
            plan["original_land_attempt"] = str(attempt)
            plan["exited_workers"] = [{"pid": processes[key]} for key in ("monitor_pid", "pi_pid")]
        private_json(evidence / "original-plan.json", {"cleanup": {"plan": plan}})
        declaration = evidence / "original-declaration.json"
        private_json(declaration, {"version": 1, "task": plan["task"], "origin": origin, "container": identifier,
            "config_id": current["Image"], "root": images[0]["Id"], "platform": current["ImageManifestDescriptor"]["platform"],
            "original_containers": reference(evidence / "original-containers.json"),
            "original_platform_images": reference(evidence / "original-platform-images.json"),
            "original_root_plan": reference(evidence / "original-plan.json")})
        plan["containers"][0]["image_declaration"] = reference(declaration)
        for index, image in enumerate(images):
            proof = evidence / ("root-proof-" + str(index) + ".json")
            private_json(proof, {"version": 1, "task": plan["task"], "origin": origin, "root": image["Id"],
                "data": "disposable", "authority": common["authority"], "original_image": reference(evidence / ("original-image-" + str(index) + ".json")),
                "graph": module.image_root_graph(root, image), "references": sorted(image["RepoTags"] + image["RepoDigests"]),
                "basis": {"source_revision": oid, "evidence": [reference(intent), reference(allocation)]}})
        plan["images"][0]["retirement"] = {"kind": "owned-root-references", "proof": reference(evidence / "root-proof-0.json")}
        receipt.parent.mkdir(mode=0o700, parents=True)
        private_json(receipt, {"qualification": "real Docker, controlled hosting only; no live publication", "cleanup": {"version": 1, "plan": plan}})
        shim = evidence / "controlled-hosting"
        shim.mkdir()
        response = {"url": plan["publication"]["url"], "state": "MERGED", "mergedAt": plan["publication"]["merged_at"],
            "mergeCommit": {"oid": oid}, "baseRefName": "develop", "headRefName": "feature/task-00542-fixture",
            "headRefOid": oid, "headRepository": {"name": "fixture"}, "headRepositoryOwner": {"login": "task00542"}}
        (shim / "gh").write_text("#!/usr/bin/env python3\nprint(" + repr(json.dumps(response)) + ")\n")
        (shim / "gh").chmod(0o700)
        # Observe the actual stopped creator as a root consumer before any cleanup effect.
        operation = module.Cleanup(str(receipt))
        operation.save = lambda: (_ for _ in ()).throw(RuntimeError("unexpected save in read-only consumer probe"))
        try:
            operation.image(plan["images"][0], {})
            raise RuntimeError("stopped image consumer was not protected")
        except module.Hold as error:
            if "consumer" not in str(error):
                raise
            private_json(evidence / "stopped-consumer-hold.json", {"reason": str(error), "container": identifier,
                "configuration_sha256": module.container_identity(current), "effects": 0})
    if args.release_image_alias:
        if not args.resume:
            raise RuntimeError("alias release requires the original completed fixture receipt")
        release = receipt.with_name("alias-release.json")
        if not release.exists():
            document = json.loads(receipt.read_text())
            original = json.loads((evidence / "original-image-1.json").read_text())
            if module.inspect_image(root, original["Id"]) != original or module.inspect_image(root, document["cleanup"]["plan"]["images"][0]["identity"]) is not None:
                raise RuntimeError("owned alias release requires exact preserved alias and retired primary root")
            private_json(evidence / "alias-preserved-before-release.json", original)
            alias = dict(document["cleanup"]["plan"]["images"][0], identity=original["Id"], inspection_sha256=module.fingerprint(original),
                         retirement={"kind": "owned-root-references", "proof": reference(evidence / "root-proof-1.json")})
            document["prior_receipt"] = reference(receipt)
            document["cleanup"]["plan"]["images"] = [alias]
            document["cleanup"].pop("plan_sha256", None)
            document["cleanup"]["attempts"] = []
            private_json(release, document)
        receipt = release
    if args.prepare_only:
        print(json.dumps({"prepared": True, "receipt": str(receipt)}))
        return 0
    env = dict(os.environ, PATH=str(evidence / "controlled-hosting") + os.pathsep + os.environ["PATH"])
    argv = [sys.executable, str(helper), "--receipt", str(receipt), "--apply"]
    if args.original_land_attempt:
        caller = helper.parents[2] / "build/scripts/cleanup-continuation.py"
        argv = [sys.executable, str(caller), "--attempt", str(Path(args.original_land_attempt).resolve()),
                "--receipt", str(receipt), "--apply"]
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")
    process = subprocess.Popen(argv, cwd=root, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    interrupted = False
    if args.interrupt:
        deadline = time.monotonic() + 120
        while process.poll() is None and time.monotonic() < deadline:
            state = json.loads(receipt.read_text())["cleanup"].get("attempts", [])
            if state and any(value.get("reference_effects") for value in state[-1]["results"]):
                process.terminate()
                interrupted = True
                break
            time.sleep(0.005)
    stdout, stderr = process.communicate(timeout=180)
    (evidence / (stamp + ".stdout")).write_bytes(stdout)
    (evidence / (stamp + ".stderr")).write_bytes(stderr)
    plan = json.loads(receipt.read_text())["cleanup"]["plan"]
    # Independent Docker oracles, not the helper's inspection or reported status.
    inventory = subprocess.run(["docker", "image", "ls", "--all", "--no-trunc", "--quiet"], cwd=root, capture_output=True, timeout=90)
    (evidence / (stamp + ".all-images.stdout")).write_bytes(inventory.stdout)
    (evidence / (stamp + ".all-images.stderr")).write_bytes(inventory.stderr)
    observations, matches = {}, []
    for entry in plan["images"]:
        exact = subprocess.run(["docker", "image", "inspect", entry["identity"]], cwd=root, capture_output=True, timeout=90)
        prefix = stamp + ".image-" + entry["identity"][7:]
        (evidence / (prefix + ".stdout")).write_bytes(exact.stdout)
        (evidence / (prefix + ".stderr")).write_bytes(exact.stderr)
        absent = exact.returncode == 1 and exact.stdout.strip() == b"[]" and exact.stderr.decode().strip() == \
            "Error response from daemon: No such image: " + entry["identity"] and inventory.returncode == 0 and \
            entry["identity"] not in inventory.stdout.decode().splitlines()
        observations[entry["identity"]] = absent
        original = json.loads((evidence / "original-image-1.json").read_text())
        preserved = exact.returncode == 0 and json.loads(exact.stdout) == [original]
        matches.append(absent if entry["disposition"] == "remove" else preserved)
    containers = set(run(["docker", "ps", "-a", "--no-trunc", "--quiet"], root).splitlines())
    volumes = set(run(["docker", "volume", "ls", "--format", "{{.Name}}"], root).splitlines())
    networks = set(run(["docker", "network", "ls", "--no-trunc", "--quiet"], root).splitlines())
    disposable_absent = all(entry["identity"] not in containers for entry in plan["containers"]) and \
        all(entry["identity"] not in volumes for entry in plan["volumes"]) and \
        all(entry["identity"] not in networks for entry in plan["networks"])
    matched = all(matches) and disposable_absent
    result = {"argv": argv, "helper_exit": process.returncode, "container_network_volume_absence": disposable_absent, "helper_sha256": module.digest(helper.read_bytes()),
        "checker_sha256": module.digest(Path(__file__).read_bytes()), "receipt": str(receipt), "images_absent": observations,
        "interrupted_after_real_reference_effect": interrupted and process.returncode == -signal.SIGTERM,
        "pass": process.returncode == 0 and matched, "physical_reclaimed_bytes": None}
    private_json(evidence / (stamp + ".result.json"), result)
    print(json.dumps(result))
    return 0 if result["pass"] or result["interrupted_after_real_reference_effect"] else 1


def visibility_contracts(module, evidence, helper):
    """Exercise the executor boundary with controlled observations and zero external effects"""
    evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
    identifier = "a" * 64
    safe = evidence.resolve()
    rows = []
    original = {name: getattr(module, name) for name in ("command", "text", "json_command", "docker_inventory")}
    try:
        for case in ("missing-preview", "missing-apply", "denied", "incomplete", "unsupported",
                     "unresolved-peer", "hidden-worker-census", "changed-engine-census",
                     "changed-observation", "changed-process-evidence", "stale-identity", "positive-preview",
                     "positive-apply", "stopped-resume", "interrupt-resume"):
            calls = []
            state = {"running": case != "stopped-resume", "present": True, "probes": 0,
                     "census_reads": 0, "process_reads": 0, "interrupted": False}
            current = {"Id": identifier, "Created": "controlled", "Image": "sha256:" + "b" * 64,
                       "Name": "/fixture", "Config": {"Labels": {
                           "com.docker.compose.project": "fixture", "com.docker.compose.service": "web",
                           "com.docker.compose.project.working_dir": str(safe),
                           "com.docker.compose.project.config_files": "/fixture.yaml",
                           "com.docker.compose.config-hash": "hash"}}, "HostConfig": {}, "Mounts": []}
            def inspection():
                observed = json.loads(json.dumps(current))
                observed["State"] = {"Running": state["running"]}
                return observed
            item = {"identity": identifier, "name": "/fixture", "service": "web",
                    "inspection_sha256": module.container_identity(current),
                    "processes_sha256": module.digest(b"processes"), "data": "disposable", "bind_data": {}}
            def command(argv, cwd, **kwargs):
                calls.append(argv[:3])
                if argv[:2] == ["docker", "top"]:
                    raw = b"processes"
                    if argv[-1] == "pid,ppid":
                        state["census_reads"] += 1
                        raw = b"PID PPID\n100 0\n"
                        if case == "changed-engine-census" and state["census_reads"] > 1:
                            raw = b"PID PPID\n101 0\n"
                    else:
                        state["process_reads"] += 1
                        if case == "changed-process-evidence" and state["process_reads"] > 1:
                            raw = b"changed processes"
                    return subprocess.CompletedProcess(argv, 0, raw, b"")
                if argv[:2] == ["docker", "exec"]:
                    state["probes"] += 1
                    if case in ("missing-preview", "missing-apply", "denied", "unresolved-peer"):
                        raise module.Hold("controlled missing/denied/peer visibility")
                    visibility = {"complete": True, "workers": 1, "connected_peers": 0, "sha256": "c" * 64}
                    if case == "incomplete":
                        visibility["complete"] = False
                    elif case == "hidden-worker-census":
                        visibility["workers"] = 2
                    elif case == "unsupported":
                        visibility.pop("sha256")
                    elif case == "changed-observation" and state["probes"] > 1:
                        visibility["sha256"] = "d" * 64
                    return subprocess.CompletedProcess(argv, 0, json.dumps(visibility).encode(), b"")
                if argv[:2] == ["docker", "stop"]:
                    state["running"] = False
                    if case == "interrupt-resume" and not state["interrupted"]:
                        state["interrupted"] = True
                        raise module.Hold("controlled interruption after stop")
                    return subprocess.CompletedProcess(argv, 0, b"", b"")
                if argv[:2] == ["docker", "rm"]:
                    state["present"] = False
                    return subprocess.CompletedProcess(argv, 0, b"", b"")
                raise AssertionError("unexpected controlled command")
            def json_command(argv, cwd):
                observed = inspection()
                if case == "stale-identity":
                    observed["Name"] = "/recreated"
                return [observed]
            module.command = command
            module.text = lambda *args, **kwargs: "web hash"
            module.json_command = json_command
            module.docker_inventory = lambda *args: [inspection()] if state["present"] else []
            operation = module.Cleanup.__new__(module.Cleanup)
            operation.safe = safe
            operation.plan = {"runtime": {"kind": "compose"}, "worktree": {"identity": str(safe)}}
            operation.apply = case not in ("missing-preview", "positive-preview")
            operation.attempt = {}
            operation.creation_evidence = lambda *args: None
            operation.capture_mounts = lambda *args: None
            operation.host_consumers = lambda *args: None
            operation.subject_checks = lambda: None
            operation.save = lambda: None
            operation.runtime = lambda *args: ({"project": "fixture", "working_directory": str(safe),
                "config_files": [{"path": "/fixture.yaml"}]}, ["docker", "compose"], {"services": {"web": {}}})
            result, reason = {}, None
            try:
                operation.container(item, result)
            except module.Hold as error:
                reason = str(error)
            if case == "interrupt-resume":
                first_reason = reason
                operation.attempt = {}  # A fresh attempt requalifies the actual stopped state.
                operation.container(item, result)
                reason = first_reason
            stops = sum(argv[:2] == ["docker", "stop"] for argv in calls)
            removes = sum(argv[:2] == ["docker", "rm"] for argv in calls)
            positive = case in ("positive-preview", "positive-apply", "stopped-resume", "interrupt-resume")
            expected_status = "qualified" if case == "positive-preview" else "removed"
            passed = result.get("status") == expected_status if positive else reason is not None and stops == removes == 0
            if case == "positive-apply":
                passed = passed and stops == removes == 1 and state["probes"] == 2
            elif case == "stopped-resume":
                passed = passed and stops == state["probes"] == 0 and removes == 1
            elif case == "interrupt-resume":
                passed = passed and stops == removes == 1 and state["interrupted"]
            elif case == "positive-preview":
                passed = passed and stops == removes == 0
            rows.append({"case": case, "passed": passed, "result": result, "reason": reason,
                         "stop_calls": stops, "remove_calls": removes, "probe_calls": state["probes"]})
    finally:
        for name, value in original.items():
            setattr(module, name, value)
    summary = {"helper": str(helper), "helper_sha256": module.digest(helper.read_bytes()),
               "boundary": "controlled method-level executor; all external observations/effects replaced",
               "pass": all(row["passed"] for row in rows), "rows": rows,
               "unverified": ["real service procfs/peer parsing", "actual helper/installed callers", "fleet", "storage policy", "application gate"]}
    private_json(evidence / "visibility-contracts.json", summary)
    print(json.dumps(summary, indent=2))
    return 0 if summary["pass"] else 1


def plan_storage_contracts(module, evidence):
    """Keep shared plan guards distinct from resource-specific service holds"""
    from contextlib import redirect_stdout
    from unittest.mock import patch
    safe = evidence / "safe"
    safe.mkdir()
    run(["git", "init", "--initial-branch=develop"], safe)
    run(["git", "-c", "user.name=Lifecycle fixture", "-c", "user.email=fixture@example.invalid",
         "commit", "--allow-empty", "-m", "Bounded storage guard fixture"], safe)
    origin = "git@github.com:task00546/fixture.git"
    run(["git", "remote", "add", "origin", origin], safe)
    oid = run(["git", "rev-parse", "HEAD"], safe)
    common = run(["git", "rev-parse", "--path-format=absolute", "--git-common-dir"], safe)
    protected = evidence / "protected.txt"
    protected.write_text("unchanged private evidence\n")
    record = module.file_record(protected)
    root = "sha256:" + "b" * 64
    rows, previous = [], Path.cwd()
    try:
        os.chdir(safe)
        for case in ("plan-live-worker", "plan-protected-drift", "plan-origin-drift",
                     "plan-worker-live-after-inventory", "plan-service-hold-independent-storage"):
            plan = {"task": "TASK-00546", "safe_checkout": str(safe), "git_common_dir": common,
                    "origin": origin, "ref": "refs/heads/feature/task-00546-guard-fixture", "oid": oid,
                    "purpose": "disposable-runtime", "runtime": {"ownership": "proven", "disposable_authority": "original temporary-runtime authority"},
                    "exited_workers": [{"pid": os.getpid()}] if case == "plan-live-worker" else [],
                    "protected_files": [{"path": str(protected), "record": dict(record, sha256="0" * 64) if case == "plan-protected-drift" else record}],
                    "containers": [{"identity": "a" * 64, "disposition": "remove", "ownership": "proven", "consumers": [], "reason": "controlled disposable service"}],
                    "volumes": [], "networks": [], "images": [], "caches": [],
                    "recoverable_storage": {"retention_seconds": 1, "cache_ids": []},
                    "worktree": {"identity": str(safe / "candidate"), "disposition": "retain", "reason": "preserve source"},
                    "branch": {"identity": "refs/heads/feature/task-00546-guard-fixture", "disposition": "retain", "reason": "preserve ref"}}
            if case == "plan-origin-drift":
                plan["origin"] = "git@github.com:changed/fixture.git"
            operation = module.Cleanup.__new__(module.Cleanup)
            operation.path, operation.safe, operation.subject, operation.apply = evidence / (case + ".json"), safe, "TASK-00546", True
            operation.plan, operation.results = plan, []
            operation.attempt = {"results": operation.results, "complete": False}
            operation.state = [operation.attempt]
            operation.document = {"cleanup": {"plan": plan, "attempts": operation.state}}
            operation.save, operation.verify_docker_results = lambda: None, lambda: None
            def container(item, result):
                raise module.Hold("controlled missing service descriptor capability")
            operation.container = container
            effects = []
            def census(storage):
                if case == "plan-worker-live-after-inventory":
                    plan["exited_workers"] = [{"pid": os.getpid()}]
                return [{"id": root, "created": 1, "digests": ["busybox@" + root], "tags": []}]
            def image(storage, item, result):
                effects.append(item["id"])
                result.update(status="removed", reason="intercepted; zero real effects")
            with patch.object(module.Storage, "engine", lambda self: {"id": "controlled"}), \
                 patch.object(module.Storage, "host_space", lambda self: [{"free_bytes": 100}]), \
                 patch.object(module.Storage, "builder_identity", lambda self: {"name": "controlled"}), \
                 patch.object(module.Storage, "usage", lambda self: []), \
                 patch.object(module.Storage, "image_census", census), patch.object(module.Storage, "image", image), \
                 patch.object(module.Storage, "save", lambda self: None), redirect_stdout(io.StringIO()):
                code = operation.run()
            positive = case == "plan-service-hold-independent-storage"
            expected_reason = "controlled missing service descriptor capability" if positive else (
                "protected report/private evidence changed" if case == "plan-protected-drift" else
                "origin changed" if case == "plan-origin-drift" else "original worker PID still live or reused")
            reasons = [row.get("reason", "") for row in operation.results]
            reasons += [attempt.get("hold", "") for attempt in operation.document.get("storage_attempts", [])]
            reasons += [row.get("reason", "") for attempt in operation.document.get("storage_attempts", []) for row in attempt["results"]]
            rows.append({"case": case, "passed": code == 1 and effects == ([root] if positive else []) and any(expected_reason in reason for reason in reasons),
                         "intercepted_image_effects": len(effects), "results": operation.results,
                         "storage_results": operation.document.get("storage_attempts", [])})
    finally:
        os.chdir(previous)
    return rows


def storage_contracts(module, evidence, helper):
    """Challenge owning storage decisions with controlled observations, never product tests"""
    evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
    rows = []
    for case, free, expected in (("reserve-boundary", 24, True), ("reserve-below", 23, False),
                                 ("insufficient-reclaim", 1, False), ("measurement-unavailable", None, False),
                                 ("measurement-lost-after-reclaim", 24, False), ("reclaim-unavailable", 24, False),
                                 ("independent-hold-sufficient", 24, True)):
        operation = module.Storage.__new__(module.Storage)
        operation.reserve, operation.allocation = 20, 4
        operation.engine_identity = {"id": "controlled"}
        operation.engine = lambda: operation.engine_identity
        operation.attempt = {"admitted": False, "complete": False, "results": []}
        operation.save = lambda: None
        observations = []
        def space():
            observations.append("host")
            if free is None or case == "measurement-lost-after-reclaim" and len(observations) > 1:
                raise module.Hold("controlled host observation unavailable")
            return [{"free_bytes": free}]
        operation.host_space = space
        def reclaim():
            if case == "reclaim-unavailable":
                raise module.Hold("controlled reclaim observation unavailable")
            operation.attempt.update(complete=case != "independent-hold-sufficient")
        operation.reclaim = reclaim
        result = operation.run()
        rows.append({"case": case, "passed": result["admitted"] == expected, "admitted": result["admitted"]})
    original = {name: getattr(module, name) for name in
                ("command", "local_image_graphs", "docker_inventory", "inspect_image")}
    identifier = "a" * 24
    root = "sha256:" + "b" * 64
    cache = {"ID": identifier, "Type": "regular", "Shared": True, "InUse": False,
             "CreatedAt": "2020-01-01T00:00:00.123456789Z", "LastUsedAt": "2020-01-01T00:00:00.123456789Z",
             "Size": 1024, "UsageCount": 1}
    try:
        for case in ("cache-no-historical-owner", "cache-retention", "cache-stale", "builder-stale", "cache-denied",
                     "cache-private", "cache-mutable", "image-backing-changed", "engine-retained", "cache-subject-changed"):
            operation = module.Storage.__new__(module.Storage)
            operation.safe, operation.retention = evidence, 1
            operation.engine_identity = {"id": "controlled"}
            operation.builder_snapshot = {"name": "fixture", "workers": ["worker"], "endpoint": "fixture"}
            operation.protected_images = [{"id": root}]
            operation.attempt = {}
            operation.save = lambda: None
            present, calls = [True], []
            raw = dict(cache)
            if case == "cache-retention":
                raw["LastUsedAt"] = datetime.now(timezone.utc).isoformat()
            if case == "cache-private":
                raw["Shared"] = False
            operation.builder_identity = lambda: ({"name": "changed"} if case == "builder-stale" else operation.builder_snapshot)
            operation.engine = lambda: operation.engine_identity
            def subject_check():
                if case == "cache-subject-changed":
                    raise module.Hold("controlled shared prerequisite changed during cache qualification")
            operation.subject_check = subject_check
            operation.image_census = lambda: ([] if case == "image-backing-changed" else operation.protected_images)
            operation.usage = lambda: ([dict(raw, UsageCount=2) if case == "cache-stale" else raw] if present[0] else [])
            def command(argv, cwd, **kwargs):
                calls.append(argv)
                if case == "cache-denied":
                    raise module.Hold("controlled denied builder inspection")
                if argv[2] == "du":
                    value = {"ID": identifier, "Shared": True, "Mutable": case == "cache-mutable",
                             "Reclaimable": True, "Type": "regular"}
                    return subprocess.CompletedProcess(argv, 0, json.dumps(value).encode(), b"")
                if argv[2] == "prune":
                    assert "--force" not in argv and "--all" in argv and "id=^" + identifier + "$" in argv
                    assert "shared~=^$" in argv and "immutable~=^$" in argv and "type=regular" in argv and "until=1s" in argv
                    present[0] = case == "engine-retained"
                    return subprocess.CompletedProcess(argv, 0, b"", b"")
                raise AssertionError("unexpected controlled effect")
            module.command = command
            result, reason = {}, None
            try:
                operation.cache(raw, result)
            except module.Hold as error:
                reason = str(error)
            effects = sum(argv[2] == "prune" for argv in calls)
            passed = (result.get("status") == "removed" and effects == 1) if case == "cache-no-historical-owner" else (
                result.get("status") == "held" and effects == 1 if case == "engine-retained" else reason is not None and effects == 0)
            rows.append({"case": case, "passed": passed, "effects": effects, "reason": reason, "result": result})
        for case in ("image-active-consumer", "image-stopped-consumer", "image-unique-work"):
            current = {"Id": root, "Created": "2020-01-01T00:00:00Z", "RepoTags": ["unique:unrecoverable"], "RepoDigests": []}
            operation = module.Storage.__new__(module.Storage)
            operation.safe, operation.retention = evidence, 1
            module.inspect_image = lambda *args: current
            module.local_image_graphs = lambda *args: [{"Id": root, "Manifests": [{"ID": root}]}]
            module.docker_inventory = lambda *args: ([] if case == "image-unique-work" else
                [{"Image": "sha256:" + "c" * 64, "ImageManifestDescriptor": {"digest": root},
                  "State": {"Running": case == "image-active-consumer"}}])
            reason = None
            try:
                operation.image({"id": root}, {})
            except module.Hold as error:
                reason = str(error)
            rows.append({"case": case, "passed": reason is not None, "reason": reason, "effects": 0})
    finally:
        for name, value in original.items():
            setattr(module, name, value)
    operation = module.Storage.__new__(module.Storage)
    operation.safe, operation.retention, operation.limit = evidence, 1, 1
    operation.selection = {"cache_ids": [], "image_ids": None}
    operation.protected_inputs = [root]
    operation.engine_identity = {"id": "controlled"}
    operation.engine = lambda: operation.engine_identity
    operation.builder_identity = lambda: {"name": "fixture"}
    operation.usage = lambda: []
    operation.image_census = lambda: [{"id": root, "created": 1, "digests": ["node@" + root], "tags": []}]
    operation.attempt = {"results": []}
    operation.save = lambda: None
    effects = []
    operation.image = lambda item, result: (effects.append(item["id"]), result.update(status="removed"))
    operation.reclaim()
    rows.append({"case": "protected-input-image", "passed": not effects, "effects": len(effects)})
    operation.selection = {"cache_ids": [], "image_ids": ["sha256:" + "d" * 64]}
    operation.protected_inputs = []
    operation.document = {"storage_attempts": []}
    operation.attempt = {"results": []}
    operation.reclaim()
    rows.append({"case": "unproven-image-absence", "passed": operation.attempt["results"][0]["status"] == "held",
                 "result": operation.attempt["results"][0], "effects": 0})
    rows.append({"case": "raw-nanosecond-retention", "passed": module.docker_timestamp(cache["LastUsedAt"]).microsecond == 123457})
    for case in ("builder-unavailable-independent-image", "cache-time-unavailable-independent-image"):
        operation = module.Storage.__new__(module.Storage)
        operation.safe, operation.retention, operation.limit = evidence, 1, 8
        operation.engine_identity = {"id": "controlled"}
        operation.engine = lambda: operation.engine_identity
        def builder():
            if case == "builder-unavailable-independent-image":
                raise module.Hold("controlled builder visibility unavailable")
            return {"name": "controlled"}
        operation.builder_identity = builder
        operation.usage = lambda: [dict(cache, LastUsedAt="unavailable")]
        operation.image_census = lambda: [{"id": root, "created": 1, "tags": [], "digests": ["busybox@" + root]}]
        operation.selection = {"cache_ids": None, "image_ids": None}
        operation.protected_inputs, operation.attempt = [], {"results": []}
        operation.save = lambda: None
        effects = []
        operation.image = lambda item, result: (effects.append(item["id"]), result.update(status="removed"))
        operation.reclaim()
        rows.append({"case": case, "passed": effects == [root] and any(v["kind"] == "cache" and v["status"] == "held" for v in operation.attempt["results"]),
                     "image_effects": len(effects), "results": operation.attempt["results"]})
    from unittest.mock import patch
    for changed in (False, True):
        operation = module.Storage.__new__(module.Storage)
        operation.safe, operation.retention = evidence, 1
        operation.engine_identity = {"id": "controlled"}
        operation.engine = lambda: operation.engine_identity
        current = {"Id": root, "Created": "2020-01-01T00:00:00Z"}
        original = {"id": root}
        present, effects, checks = [True], [], []
        operation.image_census = lambda: [original] if present[0] else []
        operation.image_consumers = lambda current: None
        operation.downloadable = lambda current: {"root": root}
        operation.save = lambda: None
        def subject_check():
            checks.append(True)
            if changed:
                raise module.Hold("controlled protected evidence changed during image qualification")
        operation.subject_check = subject_check
        def command(argv, cwd, **kwargs):
            effects.append(argv)
            present[0] = False
            return subprocess.CompletedProcess(argv, 0, b"", b"")
        result, reason = {}, None
        with patch.object(module, "inspect_image", lambda *args: current if present[0] else None), patch.object(module, "command", command):
            try:
                operation.image(original, result)
            except module.Hold as error:
                reason = str(error)
        rows.append({"case": "image-final-subject-" + ("changed" if changed else "positive"),
                     "passed": (reason is not None and not effects) if changed else result.get("status") == "removed" and len(effects) == 1,
                     "subject_checks": len(checks), "intercepted_effects": len(effects), "reason": reason})
    for available in (False, True):
        operation = module.Storage.__new__(module.Storage)
        operation.safe, operation.retention, operation.limit = evidence, 1, 8
        operation.engine_identity = {"id": "controlled"}
        operation.engine = lambda: operation.engine_identity
        def builder():
            if not available:
                raise module.Hold("controlled builder inventory unavailable")
            return {"name": "controlled"}
        operation.builder_identity, operation.usage = builder, lambda: []
        operation.image_census = lambda: [{"id": root, "created": 1, "tags": [], "digests": ["busybox@" + root]}]
        operation.selection = {"cache_ids": [identifier], "image_ids": [root]}
        operation.protected_inputs, operation.attempt = [], {"results": []}
        operation.save = lambda: None
        effects = []
        operation.image = lambda item, result: (effects.append(item["id"]), result.update(status="removed"))
        operation.reclaim()
        selected = next(row for row in operation.attempt["results"] if row["identity"] == identifier)
        rows.append({"case": "selected-cache-" + ("successful-empty" if available else "inventory-unavailable"),
                     "passed": selected["status"] == ("absent" if available else "held") and effects == [root] and operation.attempt["complete"] == available,
                     "selected": selected, "results": operation.attempt["results"], "complete": operation.attempt["complete"]})
    rows.extend(plan_storage_contracts(module, evidence))
    summary = {"helper_sha256": module.digest(helper.read_bytes()), "boundary": "controlled owning admission/cache/image methods; real local Git/ps/file plan guards; no real Docker effects",
               "pass": all(row["passed"] for row in rows), "rows": rows}
    private_json(evidence / "storage-contracts.json", summary)
    print(json.dumps(summary, indent=2))
    return 0 if summary["pass"] else 1


def endpoint_contracts(module, evidence, helper):
    """Exercise actual endpoint admission with a real local Unix metadata server"""
    evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
    real, other = evidence / "r", evidence / "o"
    real.mkdir()
    other.mkdir()
    alias = evidence / "a"
    alias.symlink_to(real, target_is_directory=True)
    regular = evidence / "file"
    regular.write_text("not a socket")
    socket_alias = evidence / "s"
    socket_alias.symlink_to(real / "s")
    listener = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    path = real / "s"
    listener.bind(str(path))
    listener.listen(4)
    listener.settimeout(0.1)
    state, errors, rows = {}, [], []
    stopped = threading.Event()
    info = {"ID": "controlled-local-engine", "DockerRootDir": str(evidence), "OperatingSystem": "Controlled Linux shape"}
    def serve():
        while not stopped.is_set():
            try:
                connection, _ = listener.accept()
            except socket.timeout:
                continue
            try:
                with connection:
                    connection.settimeout(2)
                    raw = b""
                    while b"\r\n\r\n" not in raw:
                        chunk = connection.recv(4096)
                        if not chunk:
                            break
                        raw += chunk
                    state["requests"] += 1
                    case = state["case"]
                    changed = case == "api-engine-mismatch" or case == "api-engine-changed" and state["requests"] > 1
                    body = json.dumps(dict(info, ID="unassociated") if changed else info).encode()
                    status = b"403 Forbidden" if case == "api-denied" else b"200 OK"
                    if case == "malformed-api":
                        body = b"not-json"
                    if case == "alias-changed" and state["requests"] == 1:
                        alias.unlink()
                        alias.symlink_to(other, target_is_directory=True)
                    if case == "socket-replaced" and state["requests"] == 1:
                        path.unlink()
                        path.write_text("replaced socket")
                    connection.sendall(b"HTTP/1.1 " + status + b"\r\nContent-Length: " + str(len(body)).encode() + b"\r\nConnection: close\r\n\r\n" + body)
            except (OSError, ValueError) as error:
                errors.append(type(error).__name__)
    thread = threading.Thread(target=serve)
    original = module.json_command
    saved = {key: os.environ.pop(key, None) for key in ("DOCKER_HOST", "DOCKER_CONTEXT")}
    thread.start()
    try:
        for case in ("canonical", "parent-alias", "socket-alias", "host-alias", "context-precedence", "remote", "not-socket", "missing",
                     "api-denied", "api-engine-mismatch", "api-engine-changed", "cli-engine-changed", "context-changed", "malformed-api", "alias-changed", "socket-replaced"):
            state.update(case=case, requests=0)
            endpoint = "unix://" + str(alias / "s" if case in ("parent-alias", "alias-changed") else path)
            if case == "socket-alias":
                endpoint = "unix://" + str(socket_alias)
            if case == "remote":
                endpoint = "tcp://127.0.0.1:2375"
            if case in ("not-socket", "missing"):
                endpoint = "unix://" + str(regular if case == "not-socket" else evidence / "missing")
            if case == "host-alias":
                os.environ["DOCKER_HOST"] = "unix://" + str(alias / "s")
            if case == "context-precedence":
                os.environ.update(DOCKER_HOST="tcp://unselected:2375", DOCKER_CONTEXT="selected")
            calls = {"info": 0, "context": 0}
            def observed(argv, cwd):
                if argv[:2] == ["docker", "info"]:
                    calls["info"] += 1
                    return dict(info, ID="changed") if case == "cli-engine-changed" and calls["info"] > 2 else info
                if argv == ["docker", "context", "inspect"]:
                    calls["context"] += 1
                    host = "unix://" + str(other / "s") if case == "context-changed" and state["requests"] else endpoint
                    return [{"Endpoints": {"docker": {"Host": host}}}]
                raise AssertionError("unexpected external endpoint dependency")
            module.json_command = observed
            operation = module.Storage.__new__(module.Storage)
            operation.safe = evidence
            reason = None
            try:
                operation.engine()
            except module.Hold as error:
                reason = str(error)
            except OSError as error:
                reason = type(error).__name__
            expected = case in ("canonical", "parent-alias", "socket-alias", "host-alias", "context-precedence")
            rows.append({"case": case, "passed": (reason is None) == expected,
                         "qualified": reason is None, "reason": reason, "api_requests": state["requests"]})
            for key in saved:
                os.environ.pop(key, None)
            if case == "alias-changed":
                alias.unlink()
                alias.symlink_to(real, target_is_directory=True)
        try:
            module.absolute(alias / "s")
            global_path_held = False
        except module.Hold:
            global_path_held = True
        rows.append({"case": "global-provenance-path-stays-strict", "passed": global_path_held})
    finally:
        stopped.set()
        thread.join(timeout=3)
        listener.close()
        path.unlink()
        module.json_command = original
        for key, value in saved.items():
            if value is not None:
                os.environ[key] = value
    summary = {"helper_sha256": module.digest(helper.read_bytes()), "rows": rows,
               "pass": all(row["passed"] for row in rows) and not errors and not thread.is_alive(), "server_errors": errors,
               "boundary": "Actual Storage.engine/local_image_metadata, real owned Unix server; controlled CLI observations; no Docker effects",
               "resources": "Listener closed, server thread joined, exact socket/replacement removed; directories and alias retained",
               "unverified": ["hosted Ubuntu", "installed/fleet callers", "hostile-actor atomic fencing"]}
    private_json(evidence / "endpoint-contracts.json", summary)
    print(json.dumps(summary, indent=2))
    return 0 if summary["pass"] else 1


def startup_input_contracts(module, evidence, helper):
    """Challenge SAME-binding admission with real config-only Compose and intercepted effects"""
    from unittest.mock import patch
    import lifecycle
    evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
    root = helper.parents[4]
    gateway = "nginx@sha256:47928bf67eb62a1863cb08d9ecb52d3e704304ba614eaee17884204677702951"
    node = next(line.split()[1] for line in (root / "etc/docker/cli/Dockerfile").read_text().splitlines() if line.startswith("FROM node@"))
    rows = []
    original_query = lifecycle.query
    for case in ("digest-runtime", "selected-override", "selected-build", "original-policy-real-compose", "config-failed", "build-unavailable", "competing-receipt"):
        reference = "docker.io/library/nginx@" + gateway.split("@", 1)[1] if case == "selected-override" else gateway
        override = evidence / (case + ".json")
        model = {"services": {"https": {"image": reference}}}
        if case in ("selected-build", "build-unavailable"):
            dockerfile = evidence / (case + "-Dockerfile")
            if case == "selected-build":
                dockerfile.write_text("ARG BASE=" + gateway + "\nFROM ${BASE} AS input\nFROM input AS final\n")
            model["services"]["web"] = {"build": {"dockerfile": str(dockerfile)}}
        private_json(override, model)
        operation = lifecycle.Lifecycle.__new__(lifecycle.Lifecycle)
        operation.root, operation.path = root, evidence / (case + "-receipt.json")
        original_protected = "busybox@sha256:" + "c" * 64
        operation.document, operation.state = {}, {"policy": {"recoverable_storage": {"protected_images": [original_protected]}} if case == "original-policy-real-compose" else {}}
        operation.complete = lambda: None
        files = [root / "compose.yaml", override]
        operation.binding = {"project": "task00546-input-fixture", "working_directory": str(root), "env_files": [],
                             "config_files": [{"path": str(p), "sha256": module.digest(p.read_bytes())} for p in files]}
        observed, protected = [], []
        def query(argv, cwd):
            observed.append(argv)
            if case == "config-failed":
                raise module.Hold("controlled config unavailable")
            return original_query(argv, cwd)  # Real exact-bound config only, never allocation.
        def admitted(storage, admit=True):
            protected.extend(storage.protected_inputs)
            return {"admitted": True, "complete": False}
        reason = None
        # A hostile/irrelevant ambient Compose selection must not replace this binding.
        env = {"COMPOSE_FILE": str(evidence / "unrelated-missing-config.yaml")}
        env["FIGHT_STORAGE_RECEIPT"] = str(evidence / "other.json") if case == "competing-receipt" else str(operation.path)
        with patch.dict(os.environ, env), patch.object(lifecycle, "query", query), patch.object(lifecycle.Storage, "run", admitted):
            try:
                operation.storage_admit([])
            except (module.Hold, lifecycle.Hold, OSError, ValueError, lifecycle.Unavailable):
                reason = "expected unavailable/conflicting input hold"
        negative = case in ("config-failed", "build-unavailable", "competing-receipt")
        effects = []
        if not negative:
            storage = module.Storage.__new__(module.Storage)
            selected_reference = original_protected if case == "original-policy-real-compose" else reference
            identity = selected_reference.split("@", 1)[1]
            storage.safe, storage.retention, storage.limit = root, 1, 8
            storage.selection = {"cache_ids": [], "image_ids": [identity]}
            storage.protected_inputs, storage.engine_identity = protected, {"id": "controlled"}
            storage.engine = lambda: storage.engine_identity
            storage.builder_identity = lambda: {"name": "controlled"}
            storage.usage = lambda: []
            storage.image_census = lambda: [{"id": identity, "created": 1, "digests": [selected_reference], "tags": []}]
            storage.attempt, storage.save = {"results": []}, lambda: None
            storage.image = lambda item, row: (effects.append(item["id"]), row.update(status="removed"))
            storage.reclaim()
            passed = reason is None and node in protected and reference in protected and selected_reference in protected and not effects and any(str(override) in argv for argv in observed)
            if case == "digest-runtime":
                storage.protected_inputs = []
                storage.reclaim()
                rows.append({"case": "unprotected-no-consumer-control", "passed": effects == [identity]})
                effects.clear()
        else:
            passed = reason is not None and not protected
        rows.append({"case": case, "passed": passed, "protected_inputs": protected, "hold": reason, "effects": effects})
    original = "busybox@sha256:" + "c" * 64
    caller = "alpine@sha256:" + "d" * 64
    for case in ("original-policy", "caller-only", "policy-and-caller", "policy-malformed", "policy-entry-malformed", "policy-bound-exceeded", "combined-bound-exceeded", "deduplicated-bound"):
        operation = lifecycle.Lifecycle.__new__(lifecycle.Lifecycle)
        operation.root, operation.path = root, evidence / (case + "-receipt.json")
        values = [original] if case != "caller-only" else []
        if case == "policy-malformed":
            values = original
        elif case == "policy-entry-malformed":
            values = [None]
        elif case == "policy-bound-exceeded":
            values = ["protected:" + str(index) for index in range(65)]
        elif case in ("combined-bound-exceeded", "deduplicated-bound"):
            values = ["protected:" + str(index) for index in range(64)]
        policy = {"protected_images": values, "cache_ids": [], "image_ids": [original.split("@", 1)[1], caller.split("@", 1)[1]]}
        operation.state, operation.document = {"policy": {"recoverable_storage": policy}}, {}
        operation.complete = lambda: None
        operation.binding = {"project": "task00546-policy-fixture", "working_directory": str(root), "env_files": [], "config_files": []}
        captured, effects, reason = [], [], None
        def admitted(storage, admit=True):
            captured.extend(storage.protected_inputs)
            storage.engine_identity = {"id": "controlled"}
            storage.engine = lambda: storage.engine_identity
            storage.builder_identity = lambda: {"name": "controlled"}
            storage.usage = lambda: []
            storage.image_census = lambda: [{"id": ref.split("@", 1)[1], "created": 1, "tags": [], "digests": [ref]} for ref in (original, caller)]
            storage.save = lambda: None
            storage.image = lambda item, result: (effects.append(item["id"]), result.update(status="removed"))
            storage.reclaim()
            return {"admitted": True, "complete": storage.attempt["complete"]}
        inputs = [caller] if case in ("caller-only", "policy-and-caller") else []
        model_inputs = values if case == "deduplicated-bound" else (["current-runtime:tag"] if case == "combined-bound-exceeded" else [])
        with patch.dict(os.environ, {"FIGHT_STORAGE_RECEIPT": str(operation.path)}), \
             patch.object(lifecycle, "query", lambda *args: {}), patch.object(lifecycle, "storage_inputs", lambda *args: model_inputs), \
             patch.object(lifecycle.Storage, "run", admitted):
            try:
                operation.storage_admit(inputs)
            except (module.Hold, lifecycle.Hold, TypeError, ValueError):
                reason = "invalid or oversized protections refused before effects"
        negative = case in ("policy-malformed", "policy-entry-malformed", "policy-bound-exceeded", "combined-bound-exceeded")
        expected = [original.split("@", 1)[1]] if case == "caller-only" else ([caller.split("@", 1)[1]] if case == "original-policy" else [])
        if case == "deduplicated-bound":
            expected = [original.split("@", 1)[1], caller.split("@", 1)[1]]
        rows.append({"case": case, "passed": (reason is not None and not captured and not effects) if negative else
                     reason is None and effects == expected and policy["protected_images"] == values,
                     "hold": reason, "protected_inputs": captured, "intercepted_effects": effects})
    shim = evidence / "shim"; shim.mkdir()
    (shim / "python3").write_text("#!" + sys.executable + "\nimport sys\nsys.exit(79 if 'storage-admit' in sys.argv and any(v.endswith('/lifecycle.py') for v in sys.argv) else 80)\n")
    (shim / "python3").chmod(0o700)
    for argv in ([], ["--build-only"]):
        result = subprocess.run([str(root / "bin/up"), *argv], cwd=root, env=dict(os.environ, PATH=str(shim) + os.pathsep + os.environ["PATH"]), capture_output=True)
        rows.append({"case": "owning-wrapper-" + ("build-only" if argv else "startup"), "passed": result.returncode == 79, "exit": result.returncode})
    summary = {"pass": all(v["passed"] for v in rows), "rows": rows,
               "source": {str(p.relative_to(root)): module.digest(p.read_bytes()) for p in (helper, root / "bin/up", root / "scripts/compose_storage_inputs.py", Path(lifecycle.__file__), Path(__file__))},
               "boundary": "real config-only Compose and actual wrapper boundary; lifecycle receipt/storage/effects controlled, zero Docker allocation/removal"}
    private_json(evidence / "startup-input-contracts.json", summary)
    print(json.dumps(summary, indent=2))
    return 0 if summary["pass"] else 1


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence", required=True, help="New absolute task-owned evidence directory, outside removal candidates")
    parser.add_argument("--helper", default=str(Path(__file__).with_name("cleanup.py")))
    parser.add_argument("--runtime", choices=("compose", "standalone"), default="compose")
    parser.add_argument("--task", default="TASK-00542", help="Owning TASK for new Compose fixtures; historical resumes keep their original identity")
    parser.add_argument("--fixture-image", default="postgres:17-bookworm", help="Already-present image; no pull/build. Explicit anonymous declaration when overriding")
    parser.add_argument("--caller", choices=("helper", "runtime", "build"), default="helper", help="Actual owning caller; runtime retains source/ref and uses original disposable authority")
    parser.add_argument("--recoverable-cache", help="One explicit existing cache candidate; SAME automatic storage policy, not historical task ownership")
    parser.add_argument("--standalone-matrix", action="store_true", help="Bounded owning-tool effects, interruption, evidence and consumer probes")
    parser.add_argument("--visibility-contracts", action="store_true", help="Controlled executor regressions; no Docker allocation or external effects")
    parser.add_argument("--storage-contracts", action="store_true", help="Controlled admission/recoverability/retention decisions; no external effects")
    parser.add_argument("--endpoint-contracts", action="store_true", help="Real local Unix endpoint with controlled CLI; no Docker effects")
    parser.add_argument("--startup-input-contracts", action="store_true", help="Actual bin/up/Compose input mapping with intercepted admission; no Docker effects")
    parser.add_argument("--resume", action="store_true", help="Reuse this fixture/receipt; never recreate missing resources")
    parser.add_argument("--prepare-only", action="store_true", help="Create and record the bounded fixture for actual Pi caller qualification; do not invoke cleanup")
    parser.add_argument("--case", choices=("normal", "completed", "running", "failure", "cancel", "durable", "shared-stopped", "image", "unavailable", "denied", "changed-identity", "mismatched-binding", "database", "mysql", "image-root"), default="normal")
    parser.add_argument("--release-consumer", action="store_true", help="Resume only: linked new fixture receipt permits its owned stopped consumer retirement")
    parser.add_argument("--original-land-attempt", help="Image-root or --caller build: bind actual exited monitor evidence and execute the SAME cleanup-only caller; controlled hosting remains explicit")
    parser.add_argument("--release-image-alias", action="store_true", help="Resume image-root only: release its separately owned preserved alias with a linked plan")
    parser.add_argument("--interrupt", action="store_true", help="Terminate only this helper subprocess after its first persisted container removal; resume separately")
    args = parser.parse_args()
    if args.release_consumer and not args.resume:
        parser.error("--release-consumer requires --resume")
    if args.original_land_attempt and (args.interrupt or (args.case != "image-root" and args.caller != "build")):
        parser.error("original attempt requires image-root or Build caller, without interruption; never terminate its caller parent")
    if args.caller == "build" and not args.original_land_attempt:
        parser.error("Build caller requires actual original monitor evidence")
    if args.release_image_alias and (args.case != "image-root" or not args.resume):
        parser.error("--release-image-alias requires image-root --resume")
    os.umask(0o077)
    evidence = Path(args.evidence).resolve()
    helper = Path(args.helper).resolve()
    spec = importlib.util.spec_from_file_location("subject_cleanup", helper)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    if args.endpoint_contracts:
        return endpoint_contracts(module, evidence, helper)
    if args.startup_input_contracts:
        return startup_input_contracts(module, evidence, helper)
    if args.visibility_contracts:
        return visibility_contracts(module, evidence, helper)
    if args.storage_contracts:
        return storage_contracts(module, evidence, helper)
    if args.case == "image-root":
        return owned_image_fixture(args, module, evidence, helper)
    if args.standalone_matrix:
        return standalone_matrix(args, module, evidence, helper)
    if args.runtime == "standalone":
        return standalone(args, module, evidence, helper)
    if not re.fullmatch(r"TASK-\d{5}", args.task):
        parser.error("invalid TASK identity")
    task = args.task
    namespace = task.lower().replace("-", "")
    ref = "feature/" + task.lower() + "-fixture"
    root = evidence / "safe"
    receipt = root / (".runs/tasks/" + task + "/runs/fixture.json")
    env = dict(os.environ, COMPOSE_DISABLE_ENV_FILE="1", COMPOSE_ENV_FILES="")
    if not args.resume or not receipt.exists():
        origin = "git@github.com:" + namespace + "/fixture.git"
        config = root / "compose.json"
        if not args.resume:
            evidence.mkdir(mode=0o700, parents=True, exist_ok=False)
            root.mkdir()
            run(["git", "init", "--initial-branch=develop"], root)
            run(["git", "-c", "user.name=Lifecycle fixture", "-c", "user.email=fixture@example.invalid",
                 "commit", "--allow-empty", "-m", "Bounded " + task + " disposable fixture"], root)
            run(["git", "remote", "add", "origin", origin], root)
            token = uuid.uuid4().hex
            project = namespace + "-" + token[:12]
            # Use an already-present image; no pull, build, dependency install or global setup.
            image = json.loads(run(["docker", "image", "inspect", args.fixture_image], root))[0]
            model = {"services": {"fixture": {"image": image["Id"], "pull_policy": "never",
                     "command": ["sleep", "600"], "volumes": ["scratch:/scratch"]}},
                     "volumes": {"scratch": {"labels": {"io.fight.fixture": token}}}}
            if args.fixture_image != "postgres:17-bookworm":
                model["services"]["fixture"]["volumes"].append("/anonymous")
            if args.case in ("failure", "completed"):
                model["services"]["fixture"]["command"] = ["sh", "-c", "exit 7" if args.case == "failure" else "exit 0"]
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
            if args.case in ("running", "failure", "completed", "cancel"):
                run([*compose, "start", "fixture"], root, env)
            if args.case in ("failure", "completed"):
                workload["exit"] = run(["docker", "wait", run([*compose, "ps", "--all", "--quiet", "fixture"], root, env)], root, env)
                failed = json.loads(run(["docker", "inspect", run([*compose, "ps", "--all", "--quiet", "fixture"], root, env)], root))[0]
                if failed["State"]["ExitCode"] != (7 if args.case == "failure" else 0):
                    raise RuntimeError("fixture did not exit with its expected completion code")
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
                  "reason": "explicit bounded " + task + " fixture; newly created empty disposable data"}
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
        plan = {"task": task, "safe_checkout": str(root), "git_common_dir": str(root / ".git"),
            "origin": origin, "ref": "refs/heads/" + ref, "oid": oid,
            "publication": {"url": "https://github.com/" + namespace + "/fixture/pull/1", "repository": namespace + "/fixture",
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
            "branch": dict(retained, identity="refs/heads/" + ref)}
        if args.case == "image":
            # Import a unique empty task image: no build cache, registry or shared base layers.
            image_intent = evidence / "image-intent.json"
            private_json(image_intent, {"token": project, "operation": "docker image import empty tar",
                                       "data": "disposable", "budget": "one empty uniquely labelled image; no registry/build/cache",
                                       "source_sha256": module.digest(helper.read_bytes()),
                                       "consumers": [], "authority": "owned disposable fixture image; no shared image mutation"})
            archive = io.BytesIO()
            with tarfile.open(fileobj=archive, mode="w"):
                pass
            import_argv = ["docker", "image", "import", "--change", "LABEL io.fight.fixture=" + project, "-"]
            allocation = subprocess.run(import_argv, input=archive.getvalue(), capture_output=True)
            (evidence / "image-import.stdout").write_bytes(allocation.stdout)
            (evidence / "image-import.stderr").write_bytes(allocation.stderr)
            private_json(evidence / "image-allocation.json", {"argv": import_argv, "exit": allocation.returncode,
                "intent_sha256": module.digest(image_intent.read_bytes()), "archive_sha256": module.digest(archive.getvalue())})
            allocation.check_returncode()
            imported = allocation.stdout.decode().strip()
            observed_image = json.loads(run(["docker", "image", "inspect", imported], root))[0]
            private_json(evidence / "image-creation-inspect.json", observed_image)
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
        if args.recoverable_cache:
            if not re.fullmatch(r"[a-z0-9]{12,64}", args.recoverable_cache):
                raise RuntimeError("invalid exact fixture cache selection")
            plan["recoverable_storage"] = {"cache_ids": [args.recoverable_cache], "image_ids": [],
                                           "retention_seconds": 1, "max_candidates": 1}
        if args.caller == "runtime":
            plan["purpose"] = "disposable-runtime"
            plan["runtime"]["disposable_authority"] = "explicit bounded " + task + " empty fixture discard grant; original intent retained"
        if args.caller == "build":
            attempt = Path(args.original_land_attempt).resolve()
            processes = json.loads((attempt / "process.json").read_text())
            plan["original_land_attempt"] = str(attempt)
            plan["exited_workers"] = [{"pid": processes[key]} for key in ("monitor_pid", "pi_pid")]
        receipt.parent.mkdir(mode=0o700, parents=True)
        private_json(receipt, {"qualification": "controlled hosting response; real Docker/Git; NOT live publication or fleet proof",
                               "cleanup": {"version": 1, "plan": plan}})
        shim = evidence / "controlled-hosting"
        shim.mkdir()
        response = {"url": plan["publication"]["url"], "state": "MERGED", "mergedAt": plan["publication"]["merged_at"],
            "mergeCommit": {"oid": oid}, "baseRefName": "develop", "headRefName": "feature/task-00542-fixture",
            "headRefOid": oid, "headRepository": {"name": "fixture"}, "headRepositoryOwner": {"login": "task00542"}}
        response["headRefName"] = ref
        response["headRepositoryOwner"]["login"] = namespace
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
    if args.case == "mismatched-binding" and not args.resume:
        original_receipt = receipt
        mismatch = json.loads(receipt.read_text())
        mismatch["prior_receipt"] = {"path": str(receipt), "sha256": module.digest(receipt.read_bytes())}
        mismatch["cleanup"]["plan"]["runtime"]["project"] += "-mismatch"
        receipt = receipt.with_name("mismatched-binding.json")
        private_json(receipt, mismatch)
    document = json.loads(receipt.read_text())
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")
    invocation = [sys.executable, str(helper), "--receipt", str(receipt), "--apply"]
    if args.caller == "runtime":
        invocation = [str(helper.parents[4] / "bin/down"), "--receipt", str(receipt)]
    elif args.caller == "build":
        invocation = [sys.executable, str(helper.parents[2] / "build/scripts/cleanup-continuation.py"),
                      "--attempt", str(Path(args.original_land_attempt).resolve()), "--receipt", str(receipt), "--apply"]
    previous_attempts = len(document["cleanup"].get("attempts", []))
    interrupted = False
    expected_probe_hold = False
    protected_before = None
    probe_case = json.loads((evidence / "workload.json").read_text()).get("case") if (evidence / "workload.json").exists() else "normal"
    if not args.resume and probe_case in ("unavailable", "denied", "changed-identity", "mismatched-binding"):
        protected_before = json.loads(run(["docker", "inspect", *[item["identity"] for item in document["cleanup"]["plan"]["containers"]]], root))
        if probe_case == "unavailable":
            env["DOCKER_HOST"] = "unix://" + str(evidence / "deliberately-unavailable-fixture.sock")
            env.pop("DOCKER_CONTEXT", None)
        elif probe_case == "denied":
            receipt.chmod(0o000)  # Explicit owned fixture denial; restored only as declared probe teardown.
        elif probe_case == "changed-identity":
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
        if probe_case == "mismatched-binding":
            expected_probe_hold = expected_probe_hold and b"resolved runtime binding changed" in result.stdout
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
    # Independent owning-tool postconditions: never accept the helper's image status
    # or Docker's default (partial) image listing as proof of actual retirement.
    image_observations = []
    for item in document["cleanup"]["plan"]["images"]:
        identity = item["identity"]
        exact = subprocess.run(["docker", "image", "inspect", identity], cwd=root, capture_output=True, timeout=90)
        inventory = subprocess.run(["docker", "image", "ls", "--all", "--no-trunc", "--quiet"],
                                   cwd=root, capture_output=True, timeout=90)
        (evidence / (stamp + ".image-" + identity.removeprefix("sha256:") + ".stdout")).write_bytes(exact.stdout)
        (evidence / (stamp + ".image-" + identity.removeprefix("sha256:") + ".stderr")).write_bytes(exact.stderr)
        (evidence / (stamp + ".image-" + identity.removeprefix("sha256:") + ".all-inventory.stdout")).write_bytes(inventory.stdout)
        (evidence / (stamp + ".image-" + identity.removeprefix("sha256:") + ".all-inventory.stderr")).write_bytes(inventory.stderr)
        missing = (exact.returncode == 1 and exact.stdout.strip() == b"[]" and
                   exact.stderr.decode().strip() == "Error response from daemon: No such image: " + identity)
        present = (exact.returncode == 0 and len(json.loads(exact.stdout)) == 1 and
                   json.loads(exact.stdout)[0]["Id"] == identity)
        absent = missing and inventory.returncode == 0 and identity not in inventory.stdout.decode().splitlines()
        image_observations.append({"identity": identity, "disposition": item["disposition"],
            "inspect_exit": exact.returncode, "all_inventory_exit": inventory.returncode,
            "present": present, "absent": absent,
            "matched": absent if item["disposition"] == "remove" else present if item["disposition"] == "retain" else False})
    private_json(evidence / (stamp + ".image-postconditions.json"), image_observations)
    summary = {"helper_sha256": module.digest(helper.read_bytes()), "helper_exit": result.returncode,
               "receipt": str(receipt), "required_volume_leftovers": leftover,
               "image_postconditions": image_observations,
               "pass": result.returncode == 0 and not leftover and all(item["matched"] for item in image_observations),
               "expected_stopped_shared_hold_verified": expected_hold,
               "workload": json.loads((evidence / "workload.json").read_text()) if (evidence / "workload.json").exists() else {"case": "historical stopped fixture"},
               "interrupted_after_container_effect": interrupted,
               "expected_probe_hold_verified": expected_probe_hold,
               "scope": "bounded real-Docker Compose fixture, image-declared anonymous and named storage; controlled hosting",
               "unrun": ["fleet owning callers", "installed Pi Land/Build (separate exact attempt evidence)",
                         "other interruption points", "foreign consumers",
                         "recreation/inspection/denial", "task-created cache retirement"]}
    private_json(evidence / (stamp + ".result.json"), summary)
    print(json.dumps(summary, indent=2))
    return 0 if summary["pass"] or expected_hold or interrupted or expected_probe_hold else 1


if __name__ == "__main__":
    sys.exit(main())
