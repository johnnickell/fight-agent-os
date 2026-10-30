#!/usr/bin/env python3
"""Qualify actual Git/runtime path admission without Docker or hosting effects."""

import argparse
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys

sys.dont_write_bytecode = True
import lifecycle as m


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence", required=True)
    args = parser.parse_args()
    os.umask(0o077)
    out = Path(args.evidence).resolve()
    out.mkdir(parents=True, mode=0o700, exist_ok=False)
    source = Path(__file__).resolve().parents[4]
    names = ("bin/runtime", ".pi/skills/land/scripts/lifecycle.py", ".pi/skills/land/scripts/cleanup.py")
    m.atomic(out / "original-intent.json", {
        "purpose": "Actual unbound Git named/detached/merge and unavailable/malformed runtime-path observations",
        "authority": "One finite local Git fixture; retain all source/refs/evidence; no Docker or hosting",
        "budget": {"repositories": 1, "cases": 10, "seconds": 120, "command_seconds": 10},
        "source": {name: m.digest((source / name).read_bytes()) for name in names},
    }, exclusive=True)
    root = out / "fixture"
    root.mkdir()
    commands = []

    def run(argv, cwd=root, env=None):
        result = subprocess.run([str(v) for v in argv], cwd=cwd,
                                env=dict(os.environ, GIT_CEILING_DIRECTORIES=str(out)) if env is None else dict(env, GIT_CEILING_DIRECTORIES=str(out)),
                                capture_output=True, timeout=10)
        commands.append({"argv": [str(v) for v in argv], "cwd": str(cwd), "exit": result.returncode,
                         "stdout": result.stdout.decode(errors="replace"), "stderr": result.stderr.decode(errors="replace")})
        return result

    def git(*argv):
        result = run(["git", *argv])
        m.require(result.returncode == 0, "fixture Git preparation failed")
        return result.stdout.decode().strip()

    git("init", "-q", "-b", "main")
    git("config", "user.name", "Fixture")
    git("config", "user.email", "fixture@example.invalid")
    (root / "sentinel").write_text("original\n")
    git("add", "sentinel")
    git("commit", "-qm", "Original local fixture")
    first = git("rev-parse", "HEAD")
    for name in names:
        target = root / name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source / name, target)
    results = []

    def case(label, allowed, env=None):
        target = root / "var" / label
        result = run([root / "bin/runtime", "paths", str(target)], env=env)
        checks = {"expected_exit": (result.returncode == 0) == allowed, "path_admission": target.is_dir() == allowed,
                  "binding_not_created": (root / "var/resource-lifecycle.json").exists() == (label == "malformed-bound")}
        results.append({"case": label, "pass": all(checks.values()), "checks": checks, "exit": result.returncode})

    case("ordinary", True)
    git("checkout", "-qb", "feature/task-00547-unbound")
    case("unbound-task", False)
    git("checkout", "--detach", "-q", first)
    # Record actual Git semantics, not a substituted exit code.
    run(["git", "symbolic-ref", "--short", "HEAD"])
    run(["git", "symbolic-ref", "--quiet", "--short", "HEAD"])
    case("detached", True)
    git("checkout", "-qb", "pr-head", first)
    (root / "head").write_text("PR change\n")
    git("add", "head")
    git("commit", "-qm", "PR head")
    git("checkout", "-q", "main")
    (root / "base").write_text("base change\n")
    git("add", "base")
    git("commit", "-qm", "Base advance")
    git("merge", "--no-ff", "-qm", "Synthetic PR merge", "pr-head")
    merge = git("rev-parse", "HEAD")
    git("checkout", "--detach", "-q", merge)
    case("detached-pr-merge", True)
    # Existing bound ownership is never downgraded to ordinary detached CI.
    (root / "var/resource-lifecycle.json").write_text("{}\n")
    case("malformed-bound", False)
    (root / "var/resource-lifecycle.json").unlink()  # Only this declared fixture precondition
    head = root / ".git/HEAD"
    original = head.read_bytes()
    head.write_text("malformed HEAD\n")
    case("malformed-head", False)
    head.write_bytes(original)
    no_git = out / "no-git"
    no_git.mkdir()
    for name, target in (("python3", sys.executable), ("dirname", shutil.which("dirname")), ("sh", "/bin/sh")):
        (no_git / name).symlink_to(target)
    case("git-unavailable", False, dict(os.environ, PATH=str(no_git)))
    m.atomic(out / "commands.json", commands, exclusive=True)
    m.atomic(out / "result.json", {"passed": sum(v["pass"] for v in results), "total": len(results),
        "results": results, "first_oid": first, "synthetic_merge_oid": merge,
        "limits": "Actual local Git and owning bin/runtime paths; no dependency install, Docker dispatch or hosted run. Fixture retained."}, exclusive=True)
    print(json.dumps({"passed": sum(v["pass"] for v in results), "total": len(results)}))
    return 0 if all(v["pass"] for v in results) else 1


if __name__ == "__main__":
    sys.exit(main())
