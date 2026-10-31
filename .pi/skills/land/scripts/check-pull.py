#!/usr/bin/env python3
"""Qualify pull admission/capture/recovery directly, with no Docker or registry effects."""

import argparse
from copy import deepcopy
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
from unittest.mock import patch

sys.dont_write_bytecode = True
import lifecycle as m


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence", required=True)
    args = parser.parse_args()
    os.umask(0o077)
    out = Path(args.evidence).resolve()
    out.mkdir(mode=0o700, parents=True, exist_ok=False)
    real = m.Lifecycle
    root_id, other_id = "sha256:" + "a" * 64, "sha256:" + "c" * 64
    manifest = "sha256:" + "b" * 64
    pinned = "example.invalid/task00547/pull@" + manifest
    image = {"Id": root_id, "Config": {"Labels": {}}, "Size": 0,
             "RepoTags": [], "RepoDigests": [pinned]}
    source = Path(__file__).resolve().parents[4]
    m.atomic(out / "original-intent.json", {
        "task": "TASK-00547", "purpose": "Pull evidence capacity before effect, original recovery and shared-root preservation",
        "authority": "bounded owning-tool host-only qualification; no Docker/network/image effects",
        "budget": {"cases": 24, "host_children": 24, "seconds_each": 5, "docker_effects": 0},
        "source": {name: hashlib.sha256((source / name).read_bytes()).hexdigest() for name in
                   (".pi/skills/land/scripts/lifecycle.py", ".pi/skills/land/scripts/check-pull.py")},
        "limits": "Real CLI dispatcher, begin, lock, supervisor, capture/add and reconcile; constructor, Git/source and Docker observations substituted. Not real acquisition or independent acceptance."
    }, exclusive=True)
    results = []
    # Admission is about possible NEW evidence, not whether the image is already local.
    cases = [
        ("zero-new", 0, [], pinned, None, False),
        ("full-new", 1, [other_id], pinned, None, False),
        ("exact-new", 1, [], pinned, None, True),
        ("historical-exact", 2, [other_id], pinned, None, True),
        ("full-known", 1, [root_id], pinned, image, True),
        ("full-known-local-absent", 1, [root_id], pinned, None, False),
        ("full-mutable-known", 1, [root_id], "example.invalid/task00547/pull:latest", image, False),
        ("spare-mutable-known", 2, [root_id], "example.invalid/task00547/pull:latest", image, True),
        ("full-wrong-repository", 1, [root_id], "other.invalid/task00547/pull@" + manifest, image, False),
        ("full-wrong-digest", 1, [root_id], "example.invalid/task00547/pull@" + other_id, image, False),
        ("full-malformed-digest", 1, [root_id], "example.invalid/task00547/pull@sha256:short", image, False),
        ("full-unverified-reference", 1, [root_id], pinned, dict(image, RepoDigests=[]), False),
        ("full-changed-inspection", 1, [root_id], pinned, dict(image, Size=1), False),
        ("full-local-id-not-pull-proof", 1, [root_id], root_id, image, False),
        ("capture-failure-pinned-recovery", 1, [], pinned, None, True),
        ("capture-failure-mutable-held", 1, [], "example.invalid/task00547/pull:latest", None, True),
        ("capture-failure-changed-pinned-held", 1, [], pinned, None, True),
        ("signal-outcome-held", 1, [], pinned, None, True),
    ]
    for label, capacity, known, requested, present, admitted in cases:
        directory = out / label
        (directory / "var").mkdir(parents=True)
        obj = object.__new__(real)
        obj.root, obj.path = directory, directory / "receipt.json"
        obj.subject = {"task": "TASK-00547", "safe_checkout": str(out), "origin": "fixture-only"}
        obj.binding = {"project": "task00547-pull-probe", "config_files": [], "env_files": [], "working_directory": str(directory)}
        obj.state = {"operations": [], "resources": {kind: [] for kind in m.KINDS},
                     "policy": {"services": {}, "budget": {"operations": 1, "seconds": 5, "images": capacity}}}
        obj.document = {"probe_only": True, "lifecycle": obj.state}
        for identity in known:
            obj.state["resources"]["images"].append({"identity": identity, "inspection_sha256": m.fingerprint(image if identity == root_id else dict(image, Id=identity)),
                "creation": {"kind": "reused"}, "ownership": "unknown", "data": "shared", "disposition": "retain", "history": []})
        originals = deepcopy(obj.state["resources"]["images"])
        original_budget = deepcopy(obj.state["policy"]["budget"])
        m.atomic(obj.path, obj.document, exclusive=True)
        m.atomic(directory / "var/resource-lifecycle.json", {"version": 1, "checkout": str(directory), "task": "TASK-00547", "receipt": str(obj.path)}, exclusive=True)
        dispatched, admissions = [], []
        recovery_phase = False

        def query(argv, cwd):
            if argv[:3] == ["docker", "image", "inspect"]:
                observed = image if dispatched else present
                if recovery_phase and label == "capture-failure-changed-pinned-held":
                    observed = dict(image, Id=other_id, RepoDigests=[])
                m.require(observed is not None, "fixture local image unavailable; reserve new evidence")
                return [deepcopy(observed)]
            return {"services": {}}

        def step(op, argv, **kwargs):
            assert argv == ["docker", "pull", requested]
            dispatched.append(argv)
            admission = op.get("pull_admission")
            recorded = json.loads(obj.path.read_text())["lifecycle"]["operations"][-1]
            admissions.append(bool(admission and recorded.get("pull_admission") == admission and
                                   m.reference(Path(admission["path"])) == admission and
                                   json.loads(m.private(Path(admission["path"])))['required_entries'] <= capacity))
            program = "import os,signal;os.kill(os.getpid(),signal.SIGTERM)" if label == "signal-outcome-held" else "print('finite substituted pull return')"
            return real.step(obj, op, [sys.executable, "-c", program], **kwargs)

        obj.step = step
        atomic = m.atomic

        def write_record(path, value, **kwargs):
            if label.startswith("capture-failure") and not recovery_phase and path.name.startswith("image-allocation-"):
                raise m.Hold("fixture evidence capture unavailable after original returned effect")
            return atomic(path, value, **kwargs)

        with patch.object(m, "Lifecycle", lambda *args: obj), patch.object(m, "inventory", lambda *args: {"containers": [], "networks": [], "volumes": [], "images": []}), \
                patch.object(m, "source", lambda *args: {"revision": "0" * 40}), patch.object(m, "text", lambda *args: "worktree " + str(out)), \
                patch.object(m, "query", query), patch.object(m, "atomic", write_record), \
                patch.object(sys, "argv", ["lifecycle.py", "--root", str(directory), "docker", "pull", requested]):
            code = m.main()
        op = obj.state["operations"][0]
        before_recovery = deepcopy(op)
        original_evidence = {entry[key]["path"]: m.reference(Path(entry[key]["path"])) for entry in op["steps"] for key in ("dispatch", "returned_output") if key in entry}
        recovery_phase = True
        recovered, reason = False, None
        if op["status"] == "pending":
            try:
                with patch.object(m, "query", query):
                    obj.reconcile(op["id"])
                recovered = True
            except m.Hold as error:
                reason = str(error)
        else:
            try:
                obj.reconcile(op["id"])
            except m.Hold as error:
                reason = str(error)
        normal = not label.startswith("capture-failure") and label != "signal-outcome-held"
        expected_recorded = not admitted or normal or label == "capture-failure-pinned-recovery"
        checks = {
            "admission": len(dispatched) == int(admitted),
            "caller_exit": code == (0 if admitted and normal else 1),
            "durable_admission_before_effect": all(admissions),
            "original_budget_unchanged": obj.state["policy"]["budget"] == original_budget,
            "no_replay": len(dispatched) <= 1 and op["steps"] == before_recovery["steps"],
            "original_intent_unchanged": m.reference(Path(op["intent"]["path"])) == op["intent"],
            "original_dispatch_return_unchanged": all(m.reference(Path(path)) == ref for path, ref in original_evidence.items()),
            "completeness": (op["status"] == "recorded") == expected_recorded,
            "image_capacity": len(obj.state["resources"]["images"]) <= capacity,
            "historical_identities_preserved": all(any(v["identity"] == prior["identity"] and v["creation"] == prior["creation"] for v in obj.state["resources"]["images"]) for prior in originals),
            "no_shared_ownership_upgrade": all(v["ownership"] == "unknown" and v["creation"]["kind"] == "reused" and v["disposition"] == "retain" for v in obj.state["resources"]["images"]),
        }
        if not admitted:
            checks["pre_effect_recovery"] = recovered and op.get("outcome") == "failed-before-docker-effect" and not op["steps"] and obj.state["resources"]["images"] == originals
        if label == "capture-failure-pinned-recovery":
            checks["same_operation_capture_recovery"] = recovered and len(obj.state["operations"]) == 1 and len(obj.state["resources"]["images"]) == 1
        if label == "signal-outcome-held":
            checks["unknown_original_outcome_preserved"] = op["steps"][0]["exit"] == -15 and not recovered
        results.append({"case": label, "pass": all(checks.values()), "checks": checks, "original_capacity": capacity,
                        "main_exit": code, "pull_dispatches": len(dispatched), "status": op["status"],
                        "image_entries": len(obj.state["resources"]["images"]), "reconcile_hold": reason})
    m.atomic(out / "result.json", {"results": results, "passed": sum(v["pass"] for v in results), "total": len(results),
        "docker_effects": 0, "limits": "Finite real host children substitute transport; actual owning CLI/begin/lock/supervisor/capture/reconcile. No Docker pull, registry access, image acquisition or independent QA."}, exclusive=True)
    print(json.dumps({"passed": sum(v["pass"] for v in results), "total": len(results)}))
    return 0 if all(v["pass"] for v in results) else 1


if __name__ == "__main__":
    sys.exit(main())
