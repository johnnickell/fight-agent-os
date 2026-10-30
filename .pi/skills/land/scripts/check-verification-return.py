#!/usr/bin/env python3
"""Qualify preview-only return through owning tools; no Docker or hosting effects."""

import argparse
from copy import deepcopy
import json
import os
from pathlib import Path
import subprocess
import sys
from unittest.mock import patch

sys.dont_write_bytecode = True
import lifecycle as m
import cleanup as c


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence", required=True)
    args = parser.parse_args()
    os.umask(0o077)
    out = Path(args.evidence).resolve()
    out.mkdir(mode=0o700, parents=True, exist_ok=False)
    source = Path(__file__).resolve().parents[4]
    m.atomic(out / "original-intent.json", {
        "purpose": "Preview-only SAME-receipt return, stale cleanup exclusion and explicit regenerated plan",
        "authority": "bounded task-owned local Git/filesystem fixtures; no Docker, hosting or subject cleanup",
        "budget": {"git_repositories": 1, "worktrees": 1, "cases": 40, "seconds": 300, "docker_effects": 0},
        "source": {name: m.digest((source / name).read_bytes()) for name in
                   (".pi/skills/land/scripts/lifecycle.py", ".pi/skills/land/scripts/cleanup.py", ".pi/skills/land/scripts/check-verification-return.py")},
        "limits": "Real Git/paths/private records/constructor/lock/CLI/plan/preview; substituted Docker census, hosting, original-worker outcomes and finite host transport. Not independent QA or real delivery."
    }, exclusive=True)
    safe = out / "safe"
    safe.mkdir()
    subprocess.run(["git", "init", "-q", str(safe)], check=True)
    subprocess.run(["git", "-C", str(safe), "remote", "add", "origin", "git@github.com:fixture/verification.git"], check=True)
    (safe / "fixture.txt").write_text("owned fixture sentinel\n")
    subprocess.run(["git", "-C", str(safe), "add", "fixture.txt"], check=True)
    subprocess.run(["git", "-C", str(safe), "-c", "user.name=Fixture", "-c", "user.email=fixture@example.invalid", "commit", "-qm", "Owned verification fixture"], check=True)
    root = safe / ".runs/worktrees/verification"
    root.parent.mkdir(parents=True)
    subprocess.run(["git", "-C", str(safe), "worktree", "add", "-qb", "feature/task-00547-verification", str(root)], check=True)
    store = safe / ".runs/tasks/TASK-00547/runs"
    store.mkdir(parents=True)
    attempt = safe / ".runs/build/TASK-00547/land-original"
    attempt.mkdir(parents=True)
    (attempt / "dispatch.lock").mkdir()
    for name, value in (("process.json", {"monitor_pid": 2000000001, "pi_pid": 2000000002}), ("exit-code", 0), ("pi-exit-code", 0)):
        m.atomic(attempt / name, value, exclusive=True)
    env = root / "fixture.env"
    env.write_text("")
    config = root / "fixture.json"
    config.write_text("{}\n")
    sentinel = store / "protected.txt"
    sentinel.write_text("private original\n")
    spec = store / "spec.json"
    m.atomic(spec, {"project": "task00547-verification", "config_files": [str(config)], "env_files": [str(env)],
        "policy": {"services": {}, "budget": {"operations": 8, "seconds": 5, **{k: 0 for k in m.KINDS}}},
        "private_paths": [], "protected_files": [str(sentinel)]}, exclusive=True)
    model = {"name": "task00547-verification", "services": {}}
    actual_observe = m.observe
    hosting = {"published": False, "remote": False, "unavailable": False, "live": False, "replan": False, "wrong_pr": False}
    effect_calls = []

    def observe(argv, cwd, **kwargs):
        if argv[:2] == ["git", "ls-remote"] or argv[0] == "gh":
            m.require(not hosting["unavailable"], "substituted hosting unavailable")
            stdout = ("1" * 40 + "\trefs/heads/feature/task-00547-verification\n").encode() if hosting["remote"] else b""
            if hosting["replan"]:
                stdout = (delivery["oid"] + "\trefs/heads/feature/task-00547-verification\n").encode()
            if argv[:3] == ["gh", "pr", "view"]:
                stdout = json.dumps({"url": "https://github.com/fixture/verification/pull/1", "state": "OPEN", "baseRefName": "master",
                    "headRefName": "feature/task-00547-verification", "headRefOid": "0" * 40 if hosting["wrong_pr"] else delivery["oid"],
                    "headRepository": {"name": "verification"}, "headRepositoryOwner": {"login": "fixture"}, "mergedAt": None, "mergeCommit": None}).encode()
            elif argv[0] == "gh":
                stdout = json.dumps([{"url": "simulated-only"}] if hosting["published"] else []).encode()
            return subprocess.CompletedProcess(argv, 0, stdout, b"")
        if argv[0] == "docker":
            if "config" in argv:
                return subprocess.CompletedProcess(argv, 0, json.dumps(model).encode(), b"")
            effect_calls.append(argv)
            raise AssertionError("forbidden Docker dispatch")
        return actual_observe(argv, cwd, **kwargs)

    def identity(pid, cwd):
        if pid in (2000000001, 2000000002):
            return {"pid": pid, "exit": 0 if hosting["live"] else 1, "observation": "live" if hosting["live"] else "", "visibility": "observed-or-unknown" if hosting["live"] else "absent", "stderr_sha256": m.digest(b"")}
        return original_identity(pid, cwd)

    original_identity = m.process_identity
    empty = {"containers": [], "networks": [], "volumes": [], "images": []}
    results = []
    with patch.object(m, "observe", observe), patch.object(m, "inventory", lambda *_: deepcopy(empty)), patch.object(m, "process_identity", identity):
        base_path = store / "base.json"
        m.initialize(root, "TASK-00547", base_path, str(spec))
        base = m.Lifecycle(root, base_path)
        base.state["monitor_attempts"] = [{"stage": "land", "attempt": str(attempt), "original_process_record": m.reference(attempt / "process.json")}]
        delivery = {"oid": m.text(["git", "rev-parse", "HEAD"], root), "publication": {"url": None, "number": None, "repository": "fixture/verification", "base": "master", "base_ref": "refs/heads/master", "delivery_verified": False, "merge_oid": None, "merged_at": None},
                    "protected_files": [], "original_land_attempt": str(attempt), "exited_workers": [{"pid": 2000000001}, {"pid": 2000000002}]}
        base.save()  # Fixture-only simulated original monitor binding, before plan generation
        with base.locked():
            base.plan(deepcopy(delivery))
        previous = Path.cwd()
        os.chdir(safe)
        try:
            with patch.object(c.Cleanup, "subject_checks", side_effect=c.Hold("simulated unpublished preview")):
                c.Cleanup(str(base_path)).run()
        finally:
            os.chdir(previous)
        original = json.loads(base_path.read_text())
        cases = ["supported", "wrong-hash", "wrong-task", "wrong-runtime", "history-drift", "pending", "live-worker", "worker-mismatch", "missing-exit", "changed-process-record", "published-plan", "published-remote", "published-hosting", "hosting-unavailable", "apply", "effect", "unknown-result", "unknown-field", "interrupted-preview", "protected-drift", "lock", "missing-preview", "known-consumer", "destructive-command", "missing-resource-result", "wrong-history-link"]
        for label in cases:
            path = store / (label + ".json")
            value = deepcopy(original)
            if label == "wrong-task": value["cleanup"]["plan"]["task"] = "TASK-00546"
            if label == "wrong-runtime": value["cleanup"]["plan"]["runtime"]["project"] = "foreign"
            if label == "history-drift": value["cleanup"]["plan_history"] = [{"plan": {}, "plan_sha256": "wrong", "next_plan_sha256": value["cleanup"]["plan_sha256"]}]
            if label == "pending": value["lifecycle"]["operations"].append({"id": "unknown", "status": "pending"})
            if label == "worker-mismatch": value["cleanup"]["plan"]["exited_workers"] = []
            if label == "published-plan": value["cleanup"]["plan"]["publication"]["delivery_verified"] = True
            if label == "apply": value["cleanup"]["attempts"][0]["apply"] = True
            if label == "effect": value["cleanup"]["attempts"][0]["results"][0]["effect_intent"] = {"unknown": True}
            if label == "unknown-result": value["cleanup"]["attempts"][0]["results"][0]["status"] = "unknown"
            if label == "unknown-field": value["cleanup"]["uncertain_effect"] = True
            if label == "interrupted-preview": value["cleanup"]["attempts"][0]["results"].pop()
            if label == "missing-preview": value["cleanup"]["attempts"] = []
            if label == "known-consumer": value["cleanup"]["plan"]["worktree"]["consumers"] = ["foreign writer"]
            if label == "destructive-command": value["cleanup"]["attempts"][0]["results"][0]["commands"].append({"argv": ["docker", "rm", "unknown"]})
            if label == "missing-resource-result": value["cleanup"]["attempts"][0]["results"].pop(0)
            if label == "wrong-history-link": value["cleanup"]["plan_history"] = [{"plan": deepcopy(value["cleanup"]["plan"]), "plan_sha256": value["cleanup"]["plan_sha256"], "next_plan_sha256": "0" * 64}]
            if label in ("wrong-task", "wrong-runtime", "worker-mismatch", "published-plan", "known-consumer"):
                value["cleanup"]["plan_sha256"] = m.fingerprint(value["cleanup"]["plan"])
                for a in value["cleanup"]["attempts"]: a["plan_sha256"] = value["cleanup"]["plan_sha256"]
            hosting.update(published=label == "published-hosting", remote=label == "published-remote", unavailable=label == "hosting-unavailable", live=label == "live-worker", replan=False, wrong_pr=False)
            if label == "missing-exit": (attempt / "exit-code").rename(attempt / "exit-code.saved")
            if label == "changed-process-record": m.atomic(attempt / "process.json", {"monitor_pid": 1, "pi_pid": 2})
            if label == "protected-drift": sentinel.write_text("changed\n")
            m.atomic(path, value, exclusive=True)
            obj = m.Lifecycle(root, path)
            before = path.read_bytes()
            held, reason = False, None
            try:
                # Real persistent flock contention, including a competing cleanup caller.
                with path.with_name(path.name + ".lock").open("a") as lock:
                    if label == "lock": m.fcntl.flock(lock, m.fcntl.LOCK_EX | m.fcntl.LOCK_NB)
                    with obj.locked():
                        obj.return_to_verification("0" * 64 if label == "wrong-hash" else value["cleanup"]["plan_sha256"])
            except (AttributeError, m.Hold, OSError, ValueError, KeyError) as error:
                held, reason = True, str(error)
            checks = {"expected_admission": held == (label != "supported"), "no_docker_effects": not effect_calls}
            if label != "supported": checks["receipt_unchanged"] = path.read_bytes() == before
            if label == "supported" and not held:
                current = json.loads(path.read_text())
                checks["original_cleanup_unchanged"] = all(current["cleanup"][k] == v for k, v in value["cleanup"].items())
                checks["original_state_preserved"] = current["lifecycle"]["operations"] == value["lifecycle"]["operations"] and current["lifecycle"]["policy"] == value["lifecycle"]["policy"]
                ref = current["cleanup"]["verification_return"]
                event = json.loads(m.private(Path(ref["path"])))
                snapshot = Path(event["original_receipt"]["path"])
                checks["exact_original_bytes"] = snapshot.read_bytes() == before and m.reference(snapshot) == event["original_receipt"]
                try: c.Cleanup(str(path), True)
                except c.Hold: checks["stale_apply_refused"] = True
                else: checks["stale_apply_refused"] = False
                # Real cleanup CLI obtains the SAME flock and refuses before an attempt.
                with patch.object(sys, "argv", ["cleanup.py", "--receipt", str(path), "--apply"]):
                    checks["stale_cli_apply_refused"] = c.main() == 1 and json.loads(path.read_text()) == current
                event_path = Path(ref["path"])
                saved_event = event_path.read_bytes()
                m.atomic(event_path, dict(event, subject={}))
                try: obj.verification_return()
                except m.Hold: checks["tampered_transition_held"] = True
                else: checks["tampered_transition_held"] = False
                m.atomic(event_path, saved_event)
                with obj.locked(), patch.object(sys, "argv", ["cleanup.py", "--receipt", str(path), "--apply"]):
                    checks["concurrent_cleanup_lock_refused"] = c.main() == 1 and json.loads(path.read_text()) == current
                # Only finite verification transport is substituted, not admission/recording.
                step = obj.step
                obj.step = lambda op, argv, **kw: step(op, [sys.executable, "-c", "print('bounded verification')"], **kw)
                with obj.locked(): code = obj.execute_compose(["config"])
                checks["verification_runs"] = code == 0 and len(obj.state["operations"]) == 1
                try:
                    with obj.locked(): obj.begin(["docker", "pull", "not-allowed"], "pull-shared-image")
                except m.Hold: checks["allocation_still_frozen"] = True
                else: checks["allocation_still_frozen"] = False
                try:
                    with obj.locked(): obj.plan(deepcopy(delivery), refresh=True)
                except m.Hold: checks["unqualified_replan_held"] = True
                else: checks["unqualified_replan_held"] = False
                qualified = deepcopy(delivery)
                qualified["publication"].update(url="https://github.com/fixture/verification/pull/1", number=1, delivery_verified=True)
                try:
                    with obj.locked(): obj.plan(qualified, refresh=True)
                except m.Hold: checks["unpublished_qualified_assertion_held"] = True
                else: checks["unpublished_qualified_assertion_held"] = False
                hosting.update(replan=True, wrong_pr=True)
                try:
                    with obj.locked(): obj.plan(qualified, refresh=True)
                except m.Hold: checks["wrong_live_pr_held"] = True
                else: checks["wrong_live_pr_held"] = False
                hosting["wrong_pr"] = False
                with obj.locked(): obj.plan(qualified, refresh=True)
                updated = json.loads(path.read_text())["cleanup"]
                checks["regenerated_link"] = "verification_return" not in updated and updated["plan_history"][-1]["verification_return"] == ref and updated["plan_history"][-1]["plan"] == value["cleanup"]["plan"] and updated["plan_history"][-1]["next_plan_sha256"] == updated["plan_sha256"]
                checks["attempts_preserved_after_replan"] = updated["attempts"] == value["cleanup"]["attempts"]
                checks["regenerated_history_qualifies"] = c.verify_plan_history(updated) == updated["plan_sha256"]
                m.atomic(event_path, dict(event, subject={}))
                try: c.Cleanup(str(path), True)
                except c.Hold: checks["regenerated_history_tamper_held"] = True
                else: checks["regenerated_history_tamper_held"] = False
                m.atomic(event_path, saved_event)
            if label == "missing-exit": (attempt / "exit-code.saved").rename(attempt / "exit-code")
            if label == "changed-process-record": m.atomic(attempt / "process.json", {"monitor_pid": 2000000001, "pi_pid": 2000000002})
            if label == "protected-drift": sentinel.write_text("private original\n")
            results.append({"case": label, "pass": all(checks.values()), "checks": checks, "hold": reason})
    m.atomic(out / "result.json", {"passed": sum(x["pass"] for x in results), "total": len(results), "results": results, "docker_effects": effect_calls, "fixture_source_retained": str(root)}, exclusive=True)
    print(json.dumps({"passed": sum(x["pass"] for x in results), "total": len(results)}))
    return 0 if all(x["pass"] for x in results) else 1


if __name__ == "__main__":
    sys.exit(main())
