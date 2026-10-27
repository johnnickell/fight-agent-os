#!/usr/bin/env python3
"""Observe an original Land monitor's exit, then run only exact-receipt cleanup."""

import argparse
import json
from pathlib import Path
import subprocess
import sys

sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "land" / "scripts"))
from cleanup import Hold, absolute, command, require


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--attempt", required=True, help="Original completed run-stage.py attempt, never a new publication")
    parser.add_argument("--receipt", required=True)
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    try:
        attempt = absolute(args.attempt)
        receipt = absolute(args.receipt)
        document = json.loads(receipt.read_text())
        plan = document["cleanup"]["plan"]
        require(Path.cwd().resolve() == absolute(plan["safe_checkout"]), "continuation must run from canonical safe checkout")
        require(absolute(plan["original_land_attempt"]) == attempt, "wrong original Land attempt")
        require(attempt.is_dir() and (attempt / "dispatch.lock").is_dir(), "original monitor evidence missing")
        for name in ("exit-code", "pi-exit-code"):
            require((attempt / name).read_text().strip().lstrip("-").isdigit(), "original worker exit not recorded")
        processes = json.loads((attempt / "process.json").read_text())
        require({entry["pid"] for entry in plan["exited_workers"]} == {processes["monitor_pid"], processes["pi_pid"]}, "original worker identity not bound in receipt")
        for pid in (processes["monitor_pid"], processes["pi_pid"]):
            result = command(["ps", "-p", str(pid), "-o", "lstart="], Path.cwd(), codes=(0, 1))
            require(result.returncode == 1 and not result.stdout.strip(), "original worker still live or PID reused; preserve it")
        # Reuse the owning operation's lock and atomic receipt handling. No push, PR,
        # setup, source edit, or alternate destructive operation exists in this caller.
        helper = Path(__file__).resolve().parents[2] / "land" / "scripts" / "cleanup.py"
        invocation = [sys.executable, str(helper), "--receipt", str(receipt)]
        if args.apply:
            invocation.append("--apply")
        return subprocess.run(invocation, check=False).returncode
    except (Hold, OSError, ValueError, KeyError, TypeError):
        print(json.dumps({"complete": False, "hold": "original attempt/exit/subject unavailable or still active; inspect exact receipt and monitor evidence"}))
        return 1


if __name__ == "__main__":
    sys.exit(main())
