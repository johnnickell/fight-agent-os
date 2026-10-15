#!/usr/bin/env python3
"""Run one Pi role with visible progress and private evidence (Python 3.9+)."""

import argparse
from datetime import datetime
import json
import math
import os
from pathlib import Path
import re
import selectors
import signal
import subprocess
import sys
import time
import unicodedata


def positive_seconds(value):
    seconds = float(value)
    if not math.isfinite(seconds) or seconds <= 0:
        raise argparse.ArgumentTypeError("must be a finite positive number")
    return seconds


def display(value, limit=320):
    """Keep a bounded single terminal line without control sequences."""
    value = re.sub(r"\x1b\[[0-?]*[ -/]*[@-~]", "", str(value))
    value = "".join(c if not unicodedata.category(c).startswith("C") else " " for c in value)
    value = " ".join(value.split())
    return value if len(value) <= limit else value[:limit] + "…"


class Progress:
    def __init__(self, role, log, safe=False):
        self.safe = safe
        self.role, self.log = display(role, 60), log
        self.started = time.monotonic()
        self.last_output = self.started
        self.activity = "waiting for Pi"
        self.tools = {}
        self.final = ""
        self.stop_reason = None
        self.settled = False
        self.aborted = False

    def emit(self, message):
        self.last_output = time.monotonic()
        elapsed = int(self.last_output - self.started)
        line = f"[{self.role} {elapsed // 60:02d}:{elapsed % 60:02d}] {display(message, 480)}"
        self.log.write(line + "\n")
        self.log.flush()
        if not self.safe:
            print(line, flush=True)

    def event(self, event):
        kind = event.get("type")
        if kind == "agent_start":
            self.settled = False
            self.stop_reason = None
            self.activity = "agent working"
        elif kind == "message_start" and event.get("message", {}).get("role") == "assistant":
            self.activity = "waiting for agent response"
        elif kind == "message_update":
            # Never render thinking, arguments, partial source or tool results.
            self.activity = "agent responding"
        elif kind == "message_end":
            message = event.get("message", {})
            if message.get("role") == "assistant":
                self.stop_reason = message.get("stopReason")
                self.final = "\n".join(block.get("text", "") for block in message.get("content", [])
                                       if block.get("type") == "text")
                if self.final.strip():
                    self.emit(self.final)
                if self.stop_reason in ("error", "aborted"):
                    self.emit(f"Agent response {self.stop_reason}; awaiting Pi retry/settlement")
        elif kind == "tool_execution_start":
            name = display(event.get("toolName", "tool"), 60)
            args = event.get("args", {})
            # File targets are useful; shell commands and other arguments may contain secrets.
            target = args.get("path", args.get("file_path", "")) if name in ("read", "write", "edit") else ""
            label = f"{name} {display(target, 160)}".strip()
            self.tools[event.get("toolCallId")] = (label, time.monotonic())
            self.activity = label
            self.emit(f"Started {label}")
        elif kind == "tool_execution_end":
            label, started = self.tools.pop(event.get("toolCallId"), (display(event.get("toolName", "tool")), time.monotonic()))
            status = "failed" if event.get("isError") else "finished"
            self.emit(f"{label}: {status} ({time.monotonic() - started:.1f}s)")
            self.activity = "agent working"
        elif kind in ("auto_retry_start", "auto_retry_end", "compaction_start", "compaction_end"):
            self.activity = kind.replace("_", " ")
            self.emit(self.activity)
        elif kind == "agent_settled":
            self.settled = True
            self.aborted = event.get("aborted") is not False
            self.activity = "Pi settled; waiting for process exit"

    def screening(self, line):
        """Relay only declared safe fields, never arbitrary child stderr or source bodies."""
        prefix = "Fight screening: "
        status_prefix = "Fight screening status: "
        try:
            if line.startswith(prefix):
                item = json.loads(line[len(prefix):])
                kinds = {"screen-start", "screen-complete", "cache-reuse", "receipt-admission", "exception", "blocked-read", "blocked-write", "session-bound"}
                if item.get("version") != "fight-file-screen-observation-v1" or item.get("kind") not in kinds:
                    return
                session = item.get("session", "")
                if not re.fullmatch(r"[a-f0-9]{64}", session):
                    return
                fields = [f"Screening {item['kind']}", f"session={session[:12]}"]
                for key in ("reason", "decision"):
                    value = item.get(key)
                    if isinstance(value, str) and re.fullmatch(r"[a-z-]{1,80}", value):
                        fields.append(f"{key}={value}")
                for key in ("pathId", "needId"):
                    value = item.get(key)
                    if isinstance(value, str) and re.fullmatch(r"[a-f0-9]{64}", value):
                        fields.append(f"{key}={value[:12]}")
                operation = item.get("operationId")
                if isinstance(operation, str) and re.fullmatch(r"[a-f0-9-]{36}", operation):
                    fields.append(f"operation={operation[:12]}")
                if item["kind"] == "cache-reuse":
                    fields.append("provider_calls=0")
                cost = item.get("costUsd")
                fields.append(f"cost_usd={cost}" if isinstance(cost, (int, float)) and not isinstance(cost, bool) and math.isfinite(cost) and cost >= 0 else "cost_usd=unknown")
                self.emit(" ".join(fields))
                if self.safe:
                    safe = {"version": item["version"], "kind": item["kind"], "session": session}
                    sequence = item.get("sequence")
                    if isinstance(sequence, int) and not isinstance(sequence, bool) and 0 < sequence <= 9007199254740991:
                        safe["sequence"] = sequence
                    if isinstance(item.get("phase"), str) and re.fullmatch(r"[A-Za-z0-9-]{1,48}", item["phase"]):
                        safe["phase"] = item["phase"]
                    for key in ("reason", "decision"):
                        if isinstance(item.get(key), str) and re.fullmatch(r"[a-z-]{1,80}", item[key]):
                            safe[key] = item[key]
                    for key in ("pathId", "needId"):
                        if isinstance(item.get(key), str) and re.fullmatch(r"[a-f0-9]{64}", item[key]):
                            safe[key] = item[key]
                    if isinstance(operation, str) and re.fullmatch(r"[a-f0-9-]{36}", operation):
                        safe["operationId"] = operation
                    for key, maximum in (("elapsedMs", 1800000), ("offset", 1000000000), ("lines", 1000)):
                        value = item.get(key)
                        if isinstance(value, int) and not isinstance(value, bool) and 0 <= value <= maximum:
                            safe[key] = value
                    safe["costUsd"] = cost if isinstance(cost, (int, float)) and not isinstance(cost, bool) and math.isfinite(cost) and cost >= 0 else None
                    print(prefix + json.dumps(safe), flush=True)
            elif line.startswith(status_prefix):
                item = json.loads(line[len(status_prefix):])
                state, reason = item.get("state"), item.get("reason")
                if state not in ("active", "degraded", "disabled"):
                    return
                fields = [f"Screening status={state}"]
                if isinstance(reason, str) and re.fullmatch(r"[a-z-]{1,80}", reason):
                    fields.append(f"reason={reason}")
                budget = item.get("budget") or {}
                for key in ("availableMicroUsd", "reservedMicroUsd", "settledMicroUsd"):
                    value = budget.get(key)
                    if isinstance(value, int) and not isinstance(value, bool) and value >= 0:
                        fields.append(f"{key}={value}")
                reset = budget.get("resetsAt")
                if isinstance(reset, str) and re.fullmatch(r"[0-9TZ:.+-]{20,32}", reset):
                    fields.append(f"reset={reset}")
                if state == "degraded":
                    fields.append("next=operator-/file-screening-inspect")
                self.emit(" ".join(fields))
                if self.safe:
                    safe = {"state": state, "reason": reason if isinstance(reason, str) and re.fullmatch(r"[a-z-]{1,80}", reason) else None}
                    safe["budget"] = {key: budget[key] for key in ("availableMicroUsd", "reservedMicroUsd", "settledMicroUsd") if isinstance(budget.get(key), int) and not isinstance(budget[key], bool) and 0 <= budget[key] <= 9007199254740991}
                    if isinstance(reset, str) and re.fullmatch(r"\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z", reset):
                        datetime.strptime(reset, "%Y-%m-%dT%H:%M:%S.%fZ")
                        safe["budget"]["resetsAt"] = reset
                    if isinstance(item.get("session"), str) and re.fullmatch(r"[a-f0-9]{64}", item["session"]):
                        safe["session"] = item["session"]
                    if isinstance(item.get("phase"), str) and re.fullmatch(r"[A-Za-z0-9-]{1,48}", item["phase"]):
                        safe["phase"] = item["phase"]
                    if item.get("nextActionCode") in ("enroll-project", "reconcile-legacy", "configure-credentials", "wait-month-reset", "inspect-reload", "screen-then-read"):
                        safe["nextActionCode"] = item["nextActionCode"]
                    print(status_prefix + json.dumps(safe), flush=True)
        except (ValueError, TypeError, AttributeError):
            return

    def heartbeat(self):
        if self.tools:
            label, started = next(iter(self.tools.values()))
            self.emit(f"Still running {label} ({time.monotonic() - started:.0f}s); {len(self.tools)} active tool(s)")
        else:
            self.emit(f"Still running — {self.activity}")


def run(args):
    os.umask(0o077)
    attempt = Path(args.attempt).resolve(strict=True)
    if not (attempt / "request.md").is_file():
        raise ValueError("attempt must contain request.md")
    # Claim this attempt before launch; never replay a child into old evidence.
    (attempt / "dispatch.lock").mkdir(mode=0o700)
    names = ("events.jsonl", "progress.log", "stderr.log", "final.txt", "exit-code", "pi-exit-code", "process.json")
    if any((attempt / name).exists() for name in names) or (attempt / "sessions").exists():
        raise ValueError("attempt already contains execution evidence; use a fresh attempt")
    # Resolve resources beside the owning provider skill, not in a downstream target checkout.
    bootstrap = Path(__file__).resolve().parents[4] / "harness/pi/src/screening-bootstrap.mjs"
    prepared = subprocess.run(["node", str(bootstrap), "prepare", str(Path.cwd())],
                              stdin=subprocess.DEVNULL, capture_output=True, timeout=15)
    if prepared.returncode:
        print("Screening launcher unavailable: inspect /file-screening and the owning provider package before dispatch", file=sys.stderr)
        raise ValueError("screening preflight failed")
    screening = json.loads(prepared.stdout)
    resource_args = (["-e", screening["extension"], "--fight-screening-required", screening["profileIdentity"]]
                     if screening.get("enrolled") else [])
    command = ["pi", *resource_args, "--mode", "json", "--session-dir", str(attempt / "sessions"),
               "--name", f"{args.task} {args.role}", "--provider", args.provider,
               "--model", args.model, "--thinking", args.thinking, "--", f"@{attempt / 'request.md'}"]
    env = dict(os.environ, PI_PROVIDER=args.provider, PI_MODEL=args.model, PI_REASONING_LEVEL=args.thinking,
               FIGHT_SCREENING_PHASE=re.sub(r"[^A-Za-z0-9-]", "-", args.role)[:48])
    cancelled = [0]
    def on_signal(signum, _frame):
        cancelled[0] = 128 + signum
    for signum in (signal.SIGINT, signal.SIGTERM):
        signal.signal(signum, on_signal)

    with (attempt / "events.jsonl").open("xb", buffering=0) as raw, (attempt / "stderr.log").open("xb") as errors, (attempt / "progress.log").open("x") as log:
        progress = Progress(args.role, log, args.screening_dispatch)
        progress.emit(f"Starting {args.task} — {args.provider}/{args.model}, thinking={args.thinking}")
        child = None
        code = 1
        try:
            child = subprocess.Popen(command, env=env, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                                     stderr=subprocess.PIPE, start_new_session=True)
            (attempt / "process.json").write_text(json.dumps({"monitor_pid": os.getpid(), "pi_pid": child.pid}) + "\n")
            selector = selectors.DefaultSelector()
            selector.register(child.stdout, selectors.EVENT_READ, "events")
            selector.register(child.stderr, selectors.EVENT_READ, "diagnostics")
            buffer = b""
            diagnostic_buffer = b""
            stop_at = progress.started + args.timeout
            termination_at = None
            forced_code = 0
            while selector.get_map() or child.poll() is None:
                now = time.monotonic()
                if not forced_code and (cancelled[0] or now >= stop_at):
                    forced_code = cancelled[0] or 124
                    progress.emit("Cancellation requested" if cancelled[0] else "Stage timed out")
                    termination_at = now
                    stop_group(child, signal.SIGTERM)
                if termination_at is not None and now - termination_at >= 5:
                    stop_group(child, signal.SIGKILL)
                    # An escaped descendant is not guaranteed stopped; do not hang on its pipe.
                    break
                for key, _ in selector.select(timeout=0.2):
                    chunk = os.read(key.fd, 65536)
                    if not chunk:
                        selector.unregister(key.fileobj)
                        continue
                    if key.data == "diagnostics":
                        errors.write(chunk)
                        errors.flush()
                        diagnostic_buffer += chunk
                        while b"\n" in diagnostic_buffer:
                            line, diagnostic_buffer = diagnostic_buffer.split(b"\n", 1)
                            if len(line) <= 65536:
                                progress.screening(line.decode("utf-8", errors="replace"))
                        if len(diagnostic_buffer) > 65536:
                            diagnostic_buffer = b""
                        continue
                    raw.write(chunk)
                    buffer += chunk
                    while b"\n" in buffer:
                        line, buffer = buffer.split(b"\n", 1)
                        if len(line) > 16 * 1024 * 1024:
                            raise ValueError("oversized Pi event")
                        event = json.loads(line)
                        if not isinstance(event, dict):
                            raise ValueError("Pi event is not an object")
                        progress.event(event)
                    if len(buffer) > 16 * 1024 * 1024:
                        raise ValueError("oversized partial Pi event")
                if time.monotonic() - progress.last_output >= args.heartbeat:
                    progress.heartbeat()
            selector.close()
            if forced_code:
                stop_group(child, signal.SIGKILL)
            pi_code = child.wait(timeout=5)
            (attempt / "pi-exit-code").write_text(str(pi_code) + "\n")
            code = forced_code or (pi_code if pi_code >= 0 else 128 - pi_code)
            if not code and (buffer or progress.tools or not progress.settled or progress.aborted or progress.stop_reason != "stop"):
                code = 1
                progress.emit("Incomplete or failed Pi response; inspect private events and session")
        except Exception as error:
            # Do not render exception text, provider diagnostics or malformed source to the terminal.
            progress.emit(f"Stage monitor failed ({type(error).__name__}); inspect private logs")
            errors.write((type(error).__name__ + "\n").encode())
            code = cancelled[0] or 1
        finally:
            if child is not None:
                if code:
                    stop_group(child, signal.SIGTERM)
                    try:
                        child.wait(timeout=5)
                    except subprocess.TimeoutExpired:
                        stop_group(child, signal.SIGKILL)
                        child.wait(timeout=5)
                    # The leader may exit before children in its group.
                    stop_group(child, signal.SIGKILL)
                if child.stdout:
                    child.stdout.close()
                if child.stderr:
                    child.stderr.close()
                if not (attempt / "pi-exit-code").exists():
                    (attempt / "pi-exit-code").write_text(str(child.returncode) + "\n")
            (attempt / "final.txt").write_text(progress.final)
            (attempt / "exit-code").write_text(str(code) + "\n")
            progress.emit(f"Process complete (exit {code}); evidence still requires coordinator review")
    return code


def stop_group(child, signum):
    try:
        os.killpg(child.pid, signum)
    except ProcessLookupError:
        pass


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--attempt", required=True)
    parser.add_argument("--task", required=True)
    parser.add_argument("--role", required=True)
    parser.add_argument("--provider", required=True)
    parser.add_argument("--model", required=True)
    parser.add_argument("--thinking", required=True)
    parser.add_argument("--timeout", type=positive_seconds, default=1800)
    parser.add_argument("--heartbeat", type=positive_seconds, default=30)
    parser.add_argument("--screening-dispatch", action="store_true", help="Relay safe screening observations only; keep child prose private")
    args = parser.parse_args()
    for value in (args.task, args.role, args.provider, args.model, args.thinking):
        if not value.strip():
            parser.error("stage identity and model fields must not be empty")
    try:
        return run(args)
    except (OSError, ValueError, subprocess.SubprocessError) as error:
        print(f"Cannot start stage ({type(error).__name__}); verify fresh attempt and request.md", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
