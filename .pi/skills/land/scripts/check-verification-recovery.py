#!/usr/bin/env python3
"""Qualify bounded legacy-append recovery on private copies; never execute cleanup or workloads."""

import argparse
from copy import deepcopy
import json
import os
from pathlib import Path
import sys
from unittest.mock import patch

sys.dont_write_bytecode = True
import lifecycle as m
import cleanup as c


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence", required=True)
    parser.add_argument("--receipt", required=True, help="Original incident snapshot, preserved unchanged")
    parser.add_argument("--legacy-source", required=True)
    parser.add_argument("--protected-history", required=True)
    args = parser.parse_args()
    os.umask(0o077)
    out = Path(args.evidence).resolve()
    out.mkdir(mode=0o700, parents=True, exist_ok=False)
    raw = m.private(Path(args.receipt).resolve())
    original = json.loads(raw)
    root = Path(original["lifecycle"]["subject"]["checkout"])
    protected = json.loads(m.private(Path(args.protected_history).resolve()))
    m.atomic(out / "intent.json", {"purpose": "Direct owning recovery/adverse-state qualification on isolated receipt copies",
        "receipt": m.reference(Path(args.receipt).resolve()), "legacy_source": args.legacy_source,
        "budget": {"cases": 48, "seconds": 600, "docker_effects": 0},
        "source": {name: m.digest((Path(__file__).parent / name).read_bytes()) for name in
                   ("lifecycle.py", "cleanup.py", "check-verification-recovery.py")},
        "limits": "Real original evidence, Git, files, private snapshots, locks, read-only current identities and worker observations. Adversarial visibility/worker/identity faults substituted; repaired-publication replan hosting is substituted. No main writes, Docker/hosting effects or independent acceptance."}, exclusive=True)
    import signal
    signal.signal(signal.SIGALRM, lambda *_: (_ for _ in ()).throw(TimeoutError("finite qualifier budget exhausted")))
    signal.alarm(600)
    actual_observe, actual_inventory, actual_identity, actual_file_record = m.observe, m.inventory, m.process_identity, m.file_record
    fault = {"case": None}
    docker_effects = []

    def observe(argv, cwd, **kwargs):
        if argv[0] == "docker":
            allowed = argv[1] == "inspect" or argv[1:3] in (["image", "inspect"], ["image", "ls"],
                ["network", "inspect"], ["network", "ls"], ["volume", "inspect"], ["volume", "ls"]) or argv[1:3] == ["ps", "-aq"] or (argv[1] == "compose" and "config" in argv)
            if not allowed:
                docker_effects.append(argv)
                raise AssertionError("forbidden Docker effect")
            if fault["case"] == "denied-image" and argv[1:3] == ["image", "inspect"]:
                raise m.Hold("substituted image visibility denied")
        return actual_observe(argv, cwd, **kwargs)

    def inventory(root):
        m.require(fault["case"] != "denied-inventory", "substituted inventory visibility denied")
        value = actual_inventory(root)
        if fault["case"] == "changed-creator":
            known = {x["identity"] for x in original["lifecycle"]["resources"]["containers"]}
            next(x for x in value["containers"] if x["Id"] in known)["Name"] += "-changed"
        if fault["case"] in ("changed-volume", "changed-network"):
            kind = "volumes" if fault["case"] == "changed-volume" else "networks"
            key = "Name" if kind == "volumes" else "Id"
            known = {x["identity"] for x in original["lifecycle"]["resources"][kind]}
            next(x for x in value[kind] if x[key] in known)["Driver"] = "substituted-unknown-driver"
        if fault["case"] == "missing-present-resource":
            known = {x["identity"] for x in original["lifecycle"]["resources"]["containers"]}
            value["containers"].remove(next(x for x in value["containers"] if x["Id"] in known))
        return value

    def identity(pid, root):
        if pid in {x["pid"] for x in original["cleanup"]["plan"]["exited_workers"]}:
            if fault["case"] == "live-worker":
                return {"pid": pid, "exit": 0, "visibility": "observed-or-unknown", "observation": "substituted live worker"}
            if fault["case"] == "denied-worker":
                return {"pid": pid, "exit": 1, "visibility": "observed-or-unknown", "observation": ""}
        return actual_identity(pid, root)

    def file_record(path):
        value = actual_file_record(path)
        if fault["case"] == "protected-drift" and str(path) == original["cleanup"]["plan"]["protected_files"][2]["path"]:
            value["sha256"] = "0" * 64
        return value

    cases = ["wrong-receipt", "wrong-source", "unqualified-source", "unknown-cleanup", "old-attempt-changed", "effect-intent", "removed", "unknown-outcome",
             "interrupted", "unknown-attempt", "wrong-operation", "wrong-command", "unknown-command-field", "wrong-output", "duplicate-result",
             "missing-result", "wrong-final", "wrong-time", "extra-append", "changed-plan", "changed-history", "pending", "changed-budget",
             "changed-original-snapshot", "changed-process-binding", "live-worker", "denied-worker", "denied-image", "denied-inventory",
             "changed-creator", "changed-volume", "changed-network", "missing-present-resource", "missing-protected-original", "protected-drift",
             "missing-original-intent", "changed-spec", "unknown-private", "altered-stage", "altered-private-path", "extra-protected", "lock", "supported"]
    results = []
    with patch.object(m, "observe", observe), patch.object(m, "inventory", inventory), patch.object(m, "process_identity", identity), patch.object(m, "file_record", file_record):
        for label in cases:
            fault["case"] = label
            value, history = deepcopy(original), deepcopy(protected)
            append = value["cleanup"]["attempts"][-1]
            result = append["results"][0]
            if label == "unknown-cleanup": value["cleanup"]["unknown"] = True
            if label == "old-attempt-changed": value["cleanup"]["attempts"][0]["time"] = append["time"]
            if label == "effect-intent": result["effect_intent"] = {"unknown": True}
            if label == "removed": result["status"] = "removed"
            if label == "unknown-outcome": result["status"] = "unknown"
            if label == "interrupted": append["results"].pop()
            if label == "unknown-attempt": append["unknown"] = True
            if label == "wrong-operation": append["operation_sha256"] = "0" * 64
            command = next(v["commands"][0] for v in append["results"] if v.get("commands"))
            if label == "wrong-command": command["argv"] = ["docker", "image", "rm", "unknown"]
            if label == "unknown-command-field": command["effect"] = "unknown"
            if label == "wrong-output": command["stdout_sha256"] = "0" * 64
            if label == "duplicate-result": append["results"].insert(0, deepcopy(result))
            if label == "missing-result": append["results"].pop(0)
            if label == "wrong-final": append["results"][-1]["reason"] = "unknown"
            if label == "wrong-time": append["time"] = "2000-01-01T00:00:00+00:00"
            if label == "extra-append": value["cleanup"]["attempts"].append(deepcopy(append))
            if label == "changed-plan": value["cleanup"]["plan"]["origin"] = "foreign"
            if label == "changed-history": value["cleanup"]["plan_history"][0]["next_plan_sha256"] = "0" * 64
            if label == "pending": value["lifecycle"]["operations"].append({"id": "unknown", "status": "pending"})
            if label == "changed-budget": value["lifecycle"]["policy"]["budget"]["operations"] += 1
            if label == "changed-process-binding": value["lifecycle"]["monitor_attempts"] = []
            if label == "missing-original-intent": value["lifecycle"]["operations"][-1]["intent"]["sha256"] = "0" * 64
            if label == "changed-spec": value["lifecycle"]["original_spec"]["sha256"] = "0" * 64
            if label == "unknown-private": value["lifecycle"]["unknown"] = True
            if label == "altered-stage": value["lifecycle"]["stages"][0]["stage"] = "changed"
            if label == "altered-private-path": value["lifecycle"]["private_paths"].append({"path": "unknown"})
            if label == "missing-protected-original": history[next(iter(history))] = str(out / "missing.md")
            if label == "extra-protected": history[str(out / "unknown")] = str(out / "unknown")
            if label == "changed-original-snapshot":
                event_ref = value["cleanup"]["verification_return"]
                event = json.loads(m.private(Path(event_ref["path"])))
                changed = deepcopy(event)
                changed["original_receipt"] = dict(event["original_receipt"], sha256="0" * 64)
                event_path = out / "changed-event.json"
                m.atomic(event_path, changed, exclusive=True)
                value["cleanup"]["verification_return"] = m.reference(event_path)
            path = out / (label + ".json")
            m.atomic(path, value, exclusive=True)
            before = path.read_bytes()
            reason = None
            try:
                obj = m.Lifecycle(root, path)
                with Path(str(path) + ".lock").open("a") as lock:
                    if label == "lock": m.fcntl.flock(lock, m.fcntl.LOCK_EX | m.fcntl.LOCK_NB)
                    with obj.locked():
                        revision = "0" * 40 if label == "wrong-source" else (value["cleanup"]["plan"]["oid"] if label == "unqualified-source" else args.legacy_source)
                        obj.recover_verification("0" * 64 if label == "wrong-receipt" else m.digest(before), revision, history)
            except (m.Hold, OSError, ValueError, KeyError, TypeError, AttributeError, StopIteration) as error:
                reason = str(error)
            checks = {"expected_admission": (reason is None) == (label == "supported"), "no_docker_effects": not docker_effects}
            if label != "supported": checks["receipt_unchanged"] = path.read_bytes() == before
            if label == "supported" and reason is None:
                after = json.loads(path.read_bytes())
                checks["four_attempts_exact"] = after["cleanup"]["attempts"] == original["cleanup"]["attempts"]
                checks["cleanup_exact_except_successor"] = {k:v for k,v in after["cleanup"].items() if k != "verification_return"} == {k:v for k,v in original["cleanup"].items() if k != "verification_return"}
                successor = after["cleanup"]["verification_return"]
                event = json.loads(m.private(Path(successor["path"])))
                checks["exact_incident_snapshot"] = m.private(Path(event["original_receipt"]["path"])) == before
                checks["old_return_retained"] = event["predecessor"] == original["cleanup"]["verification_return"]
                checks["active_qualifies"] = obj.verification_return() == successor and c.verify_plan_history(after["cleanup"]) == after["cleanup"]["plan_sha256"]
                try: c.Cleanup(str(path), True)
                except c.Hold as error: checks["stale_apply_held"] = "suspended" in str(error)
                else: checks["stale_apply_held"] = False
                try:
                    with obj.locked(): obj.begin(["docker", "pull", "forbidden"], "pull-shared-image")
                except c.Hold: checks["allocation_frozen"] = True
                else: checks["allocation_frozen"] = False
                try:
                    with obj.locked(): obj.recover_verification(m.digest(path.read_bytes()), args.legacy_source, history)
                except c.Hold: checks["no_second_recovery"] = True
                else: checks["no_second_recovery"] = False
                delivery = {key: deepcopy(original["cleanup"]["plan"][key]) for key in
                    ("oid", "publication", "exited_workers", "protected_files", "original_land_attempt")}
                try:
                    with obj.locked(): obj.plan(delivery, refresh=True)
                except c.Hold as error: checks["failed_candidate_replan_held"] = "failed-setup candidate" in str(error)
                else: checks["failed_candidate_replan_held"] = False
                delivery["oid"] = m.text(["git", "rev-parse", "HEAD"], root)
                delivery["protected_files"] = [{"path": x["path"], "record": m.file_record(Path(x["path"]))} for x in delivery["protected_files"]]
                actual_text, actual_query = m.text, m.query
                def text(argv, cwd):
                    if argv[:2] == ["git", "ls-remote"]: return delivery["oid"] + "\t" + obj.subject["ref"]
                    return actual_text(argv, cwd)
                def query(argv, cwd):
                    if argv[:3] == ["gh", "pr", "view"]:
                        owner, name = delivery["publication"]["repository"].split("/")
                        return {"url": delivery["publication"]["url"], "state": "OPEN", "baseRefName": delivery["publication"]["base"],
                            "headRefName": obj.subject["ref"].removeprefix("refs/heads/"), "headRefOid": delivery["oid"],
                            "headRepository": {"name": name}, "headRepositoryOwner": {"login": owner}, "mergedAt": None, "mergeCommit": None}
                    return actual_query(argv, cwd)
                with patch.object(m, "text", text), patch.object(m, "query", query), obj.locked(): obj.plan(delivery, refresh=True)
                refreshed = json.loads(path.read_bytes())["cleanup"]
                checks["linked_replan_qualifies"] = c.verify_plan_history(refreshed) == refreshed["plan_sha256"] and refreshed["plan_history"][-1]["verification_return"] == successor
                checks["all_attempts_preserved_after_replan"] = refreshed["attempts"] == original["cleanup"]["attempts"]
                event_path = Path(successor["path"])
                saved = event_path.read_bytes()
                m.atomic(event_path, dict(event, subject={}))
                try: c.verify_plan_history(refreshed)
                except (c.Hold, KeyError): checks["successor_tamper_held_after_replan"] = True
                else: checks["successor_tamper_held_after_replan"] = False
                m.atomic(event_path, saved)
            results.append({"case": label, "pass": all(checks.values()), "checks": checks, "hold": reason})
    signal.alarm(0)
    m.atomic(out / "result.json", {"passed": sum(v["pass"] for v in results), "total": len(results), "results": results,
        "docker_effects": docker_effects, "input_unchanged": Path(args.receipt).read_bytes() == raw}, exclusive=True)
    print(json.dumps({"passed": sum(v["pass"] for v in results), "total": len(results)}))
    return 0 if all(v["pass"] for v in results) and Path(args.receipt).read_bytes() == raw else 1


if __name__ == "__main__":
    sys.exit(main())
