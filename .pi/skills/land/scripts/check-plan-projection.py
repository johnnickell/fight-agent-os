#!/usr/bin/env python3
"""Qualify whole-plan projection on a private original inventory; never retire resources."""

import argparse
from contextlib import redirect_stdout
from copy import deepcopy
import io
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
    parser.add_argument("--receipt", required=True, help="Immutable private original receipt snapshot, never the live receipt")
    parser.add_argument("--delivery", required=True, help="Original failed Land delivery facts (read only)")
    parser.add_argument("--evidence", required=True)
    args = parser.parse_args()
    os.umask(0o077)
    out = Path(args.evidence).resolve()
    out.mkdir(mode=0o700, parents=True, exist_ok=False)
    original_path = m.absolute(args.receipt)
    original_raw = m.private(original_path)
    original = json.loads(original_raw)
    original_delivery = json.loads(m.private(m.absolute(args.delivery)))
    state = original["lifecycle"]
    root = Path(state["subject"]["checkout"])
    locator = json.loads((root / "var/resource-lifecycle.json").read_text())
    live = Path(locator["receipt"])
    m.require(original_path != live and not c.inside(out, root), "use an external private snapshot/fixture directory")
    live_before = live.read_bytes()
    # Keep every original resource field, operation, policy and protected record.
    # Only unrelated full-copy manifests/stage history are omitted from fixture inputs;
    # the exact full original receipt remains immutable at --receipt.
    keys = ("version", "subject", "runtime", "resources", "policy", "operations", "readiness_required", "protected_files", "temporary_artifacts")
    base = {key: deepcopy(state[key]) for key in keys if key in state}
    latest = state["readiness"][-1]
    base["readiness"] = [{key: deepcopy(latest[key]) for key in ("source", "proof", "ready", "hold", "resource_checks", "exclusions") if key in latest}]
    m.atomic(out / "intent.json", {"original_receipt": m.reference(original_path), "original_delivery": m.reference(Path(args.delivery)),
        "original_resource_sha256": m.fingerprint(state["resources"]),
        "source": {name: m.digest(Path(name).read_bytes()) for name in (m.__file__, c.__file__, __file__)},
        "budget": {"cases": 32, "seconds": 300, "docker_effects": 0, "hosting_effects": 0},
        "seams": ["Lifecycle object uses copied original plan inputs, not constructor/config validation",
                  "Source observation is the original readiness source; this fixture does not assert readiness on a new head",
                  "External preservation verifier checks immutable original proof digest, not a new full preservation observation",
                  "Actual subject/protected-file/worker guards run with original Git/private records; hosting and negative worker/origin observations are controlled",
                  "Docker qualification/effect methods and independent storage result are intercepted; no Docker/hosting command can run",
                  "Added or changed resource shapes are explicit probe inputs, never original intent or authority"],
        "limits": "Whole generated plan/hash, Cleanup constructor, dispatch, persistent result/history and final aggregate are real; qualification sinks are controlled, not delivery/QA or actual retirement"}, exclusive=True)
    results = []
    actual_command = c.command
    actual_creation = c.Cleanup.creation_evidence
    sentinel = out / "protected-sentinel.txt"
    project = state["runtime"]["project"]
    creator = state["resources"]["containers"][0]["identity"]
    cases = ["actual-original", "pending-original", "explicit-runtime-string", "explicit-runtime-null-creation",
             "creator-fallback", "explicit-overrides-creator", "null-runtime", "string-runtime", "missing-creation",
             "null-creation", "unknown-creation", "legacy-creation", "unhashable-creator", "unknown-creator",
             "pending-image-reference", "mixed-references", "null-references", "string-references",
             "independent-image", "independent-volume", "protected-inputs", "live-worker", "protected-drift",
             "wrong-source", "reused-retained", "legacy-no-pending", "null-no-pending", "unknown-no-pending",
             "unknown-volume", "null-cache", "independent-reference", "unknown-reference-independent"]
    for label in cases:
        value = {"lifecycle": deepcopy(base)}
        obj = m.Lifecycle.__new__(m.Lifecycle)
        obj.root, obj.path, obj.document = root, out / (label + ".json"), value
        obj.state = value["lifecycle"]
        obj.subject, obj.binding = obj.state["subject"], obj.state["runtime"]
        pending = label not in ("actual-original", "protected-inputs", "live-worker", "protected-drift", "wrong-source",
                                "reused-retained", "legacy-no-pending", "null-no-pending", "unknown-no-pending", "unknown-volume", "null-cache")
        delivery = deepcopy(original_delivery)
        m.atomic(sentinel, b"original controlled protected sentinel\n")
        delivery["protected_files"].append({"path": str(sentinel), "record": c.file_record(sentinel)})
        if pending:
            obj.state["operations"].append({"id": "controlled-pending", "kind": "compose-up", "status": "pending",
                "intent": deepcopy(state["operations"][0]["intent"]), "runtime": {"project": project}})
        probe = None
        if label not in ("actual-original", "pending-original", "protected-inputs", "live-worker", "protected-drift", "wrong-source"):
            kind = "caches" if label == "null-cache" else "volumes" if label in ("creator-fallback", "explicit-overrides-creator", "independent-volume", "unknown-volume") else "images"
            probe = deepcopy(state["resources"][kind][0])
            probe.update(identity="probe-" + label, disposition="remove", ownership="proven", consumers=None, consumer_policy="fresh",
                         reason="controlled positive projection input, NOT original authority", runtime={"project": project},
                         creation={"kind": "created", "container": creator})
            probe["references"] = [project + "-probe"]
            if label == "reused-retained": probe.update(disposition="retain", ownership="unknown", creation={"kind": "reused"})
            if label == "legacy-no-pending": probe["creation"] = "legacy"
            if label in ("null-no-pending", "null-cache"): probe["creation"] = None
            if label in ("unknown-no-pending", "unknown-volume"): probe["creation"] = {"kind": "unknown"}
            if label == "independent-reference": del probe["runtime"]; del probe["creation"]["container"]; probe["references"] = ["controlled-independent-image"]
            if label == "explicit-runtime-string": probe["creation"] = "original-style-string"
            if label == "explicit-runtime-null-creation": probe["creation"] = None
            if label == "creator-fallback": del probe["runtime"]
            if label in ("explicit-overrides-creator", "independent-image", "independent-volume"): probe["runtime"] = {"project": "controlled-independent"}; probe["references"] = ["controlled-independent-image"]
            if label == "unknown-reference-independent": probe["runtime"] = {"project": "controlled-independent"}; probe["references"] = [None, "controlled-independent-image"]
            if label == "null-runtime": probe["runtime"] = None
            if label == "string-runtime": probe["runtime"] = "unknown"
            if label == "missing-creation": del probe["creation"]; del probe["runtime"]
            if label == "null-creation": probe["creation"] = None; del probe["runtime"]
            if label == "unknown-creation": probe["creation"] = {"kind": "unknown"}; del probe["runtime"]
            if label == "legacy-creation": probe["creation"] = "legacy"; del probe["runtime"]
            if label == "unhashable-creator": probe["creation"]["container"] = []; del probe["runtime"]
            if label == "unknown-creator": probe["creation"]["container"] = "unknown"; del probe["runtime"]
            if label == "pending-image-reference": probe["runtime"] = {"project": "controlled-independent"}
            if label == "mixed-references": probe["references"] = [None, {}, project + "-probe"]
            if label in ("null-references", "string-references"):
                probe["runtime"] = None
                probe["references"] = None if label == "null-references" else "legacy"
            obj.state["resources"][kind].append(probe)
        before_resources = deepcopy(obj.state["resources"])
        calls, storage_calls, forbidden = [], [], []

        def proof_check(_self, preservation):
            proof = Path(latest["proof"]["path"])
            m.require(m.digest(m.private(proof)) == latest["proof"]["sha256"], "original proof digest changed")

        def subject_command(argv, cwd, **kwargs):
            # Exercise actual shared guards with only hosting and adversarial
            # observations substituted. All other external commands are denied.
            if argv[:3] == ["git", "remote", "get-url"] and label == "wrong-source":
                return m.subprocess.CompletedProcess(argv, 0, b"git@github.com:foreign/project.git\n", b"")
            if argv[0] == "ps" and label == "live-worker":
                return m.subprocess.CompletedProcess(argv, 0, b"controlled live worker\n", b"")
            if argv[:3] == ["git", "ls-remote", "--heads"]:
                return m.subprocess.CompletedProcess(argv, 0, (delivery["oid"] + "\t" + obj.subject["ref"] + "\n").encode(), b"")
            if argv[:3] == ["gh", "pr", "view"]:
                owner, repository = delivery["publication"]["repository"].split("/")
                return m.subprocess.CompletedProcess(argv, 0, json.dumps({"url": delivery["publication"]["url"], "state": "OPEN",
                    "baseRefName": delivery["publication"]["base"], "headRefName": obj.subject["ref"].removeprefix("refs/heads/"),
                    "headRefOid": delivery["oid"], "headRepository": {"name": repository}, "headRepositoryOwner": {"login": owner},
                    "mergedAt": None, "mergeCommit": None}).encode(), b"")
            if argv[:3] in (["git", "worktree", "list"], ["git", "remote", "get-url"], ["git", "rev-parse", "--path-format=absolute"]) or argv[0] in ("ps", "xattr", "ls"):
                return actual_command(argv, cwd, **kwargs)
            return denied(argv, cwd, **kwargs)

        def sink(_self, item, result):
            if probe and item["identity"] == probe["identity"] and label in ("legacy-no-pending", "null-no-pending", "unknown-no-pending", "unknown-volume", "null-cache"):
                actual_creation(_self, item)  # Real evidence guards, no type/ownership upgrade
                raise AssertionError("unknown creation evidence authorized qualification")
            calls.append(item["identity"])
            result.update(status="qualified", reason="controlled no-effect qualification sink")

        def retained(_self, kind, item, result):
            calls.append("retained:" + item["identity"])

        def storage_run(storage, admit=True):
            storage_calls.append("dispatch")
            try:
                storage.check_subject()
                storage.attempt.update(complete=False, results=[{"status": "removed", "identity": "controlled-independent-storage"}])
                storage_calls.append("eligible")
            except c.Hold as error:
                storage.attempt.update(complete=False, hold=str(error), results=[])
            storage.save()
            return storage.attempt

        def denied(*args, **kwargs):
            forbidden.append(str(args[0]) if args else "external command")
            raise AssertionError("forbidden external command/effect")

        checks, error = {}, None
        try:
            with patch.object(m, "source", return_value=deepcopy(latest["source"])), patch.object(c.Cleanup, "verify_preservation", proof_check):
                obj.plan(delivery)
            plan = value["cleanup"]["plan"]
            checks["all_original_resources_retained"] = all(plan[k][:len(state["resources"][k])] and len(plan[k]) == len(before_resources[k]) for k in m.KINDS)
            checks["original_fields_and_unknowns_preserved"] = all(
                all({key: v for key, v in projected.items() if key not in ("disposition", "reason", "consumer_policy")} ==
                    {key: v for key, v in source.items() if key not in ("disposition", "reason", "consumer_policy")}
                    for source, projected in zip(before_resources[k], plan[k])) for k in m.KINDS)
            checks["no_original_hold_upgraded"] = all(projected["disposition"] == source["disposition"] for k in m.KINDS
                for source, projected in zip(state["resources"][k], plan[k]) if source["disposition"] in ("hold", "retain"))
            capability_holds = {v["identity"] for v in latest["resource_checks"] if v["status"] == "held"}
            checks["exact_original_dispositions"] = all(
                projected["disposition"] == ("hold" if source["disposition"] == "remove" and
                    (pending or k == "containers" and source["identity"] in capability_holds) else source["disposition"])
                for k in m.KINDS for source, projected in zip(state["resources"][k], plan[k]))
            checks["input_resources_unchanged"] = obj.state["resources"] == before_resources
            checks["plan_hash_saved"] = value["cleanup"]["plan_sha256"] == m.fingerprint(plan)
            checks["pending_records_complete"] = len(plan["pending_operations"]) == (1 if pending else 0)
            checks["source_ref_held"] = all(plan[k]["disposition"] == "hold" for k in ("worktree", "branch"))
            if probe:
                projected = next(v for v in plan[kind] if v["identity"] == probe["identity"])
                expected = "retain" if label == "reused-retained" else "remove" if not pending or label in ("explicit-overrides-creator", "independent-image", "independent-volume", "independent-reference") else "hold"
                checks["expected_association"] = projected["disposition"] == expected
            if label == "protected-drift": m.atomic(sentinel, b"controlled protected drift\n")
            # Whole owning preview followed by an isolated apply-result path. Every
            # external operation is intercepted; even a regression cannot dispatch.
            previous_cwd = Path.cwd()
            os.chdir(obj.subject["safe_checkout"])
            with patch.object(c, "command", subject_command), patch.object(m, "observe", denied), \
                    patch.object(c.Cleanup, "verify_preservation", proof_check), \
                    patch.object(c.Cleanup, "container", sink), patch.object(c.Cleanup, "volume", sink), \
                    patch.object(c.Cleanup, "network", sink), patch.object(c.Cleanup, "image", sink), \
                    patch.object(c.Cleanup, "cache", sink), patch.object(c.Cleanup, "checkout", sink), \
                    patch.object(c.Cleanup, "branch", sink), patch.object(c.Cleanup, "observe_retained", retained), \
                    patch.object(c.Cleanup, "verify_docker_results", lambda _self: None), patch.object(c.Storage, "run", storage_run):
                for apply in (False, True):
                    with redirect_stdout(io.StringIO()) as stream:
                        cleaner = c.Cleanup(str(obj.path), apply)
                        code = cleaner.run()
                    (out / (label + ("-apply" if apply else "-preview") + ".stdout")).write_text(stream.getvalue())
                    checks["complete_resource_results:" + str(apply)] = all(
                        len([r for r in cleaner.results if r["kind"] == singular]) == len(plan[plural])
                        for plural, singular in (("containers", "container"), ("volumes", "volume"), ("networks", "network"), ("images", "image"), ("caches", "cache")))
                    checks["incomplete_truthful:" + str(apply)] = code == 1 and cleaner.attempt["complete"] is False
                    checks["all_original_holds_preserved:" + str(apply)] = all(
                        next(r for r in cleaner.results if r["identity"] == v["identity"] and r["kind"] == singular)["status"] == "held"
                        for plural, singular in (("containers", "container"), ("images", "image"), ("caches", "cache"))
                        for v in state["resources"][plural] if v["disposition"] == "hold")
            os.chdir(previous_cwd)
            checks["independent_storage_dispatch"] = storage_calls == (["dispatch"] if label in ("live-worker", "protected-drift", "wrong-source") else ["dispatch", "eligible"])
            if probe:
                valid = label not in ("legacy-no-pending", "null-no-pending", "unknown-no-pending", "unknown-volume", "null-cache")
                checks["only_eligible_probe_reaches_sink"] = (probe["identity"] in calls) == (expected == "remove" and valid)
                if label == "reused-retained": checks["reused_observation"] = "retained:" + probe["identity"] in calls
                if not valid:
                    checks["unknown_creation_held_by_executor"] = next(r for r in cleaner.results if r["identity"] == probe["identity"])["status"] == "held"
            if label in ("live-worker", "protected-drift", "wrong-source"): checks["no_resource_sink"] = not calls
            checks["no_forbidden_commands"] = not forbidden
        except Exception as caught:
            error = {"type": type(caught).__name__, "message": str(caught), "forbidden_commands": forbidden}
        finally:
            os.chdir(root)
        results.append({"case": label, "checks": checks, "error": error, "pass": bool(checks) and all(checks.values()) and error is None})
    preserved = live.read_bytes() == live_before and original_path.read_bytes() == original_raw
    report = {"passed": sum(v["pass"] for v in results), "total": len(results), "main_and_original_unchanged": preserved, "results": results}
    report["pass"] = preserved and report["passed"] == report["total"]
    m.atomic(out / "result.json", report, exclusive=True)
    print(json.dumps({k: v for k, v in report.items() if k != "results"}, indent=2))
    return 0 if report["pass"] else 1


if __name__ == "__main__":
    sys.exit(main())
