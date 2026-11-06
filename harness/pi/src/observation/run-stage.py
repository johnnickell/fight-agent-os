#!/usr/bin/env python3
"""Run one Pi role with visible progress and private evidence (Python 3.9+)."""

import argparse
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

# Observation-only cross-runtime birth stamp. Never change the execution timeout clock.
try:
    OBSERVATION_STARTED = int(time.clock_gettime(time.CLOCK_MONOTONIC_RAW) * 1000)
except (AttributeError, OSError):
    OBSERVATION_STARTED = None

sys.dont_write_bytecode = True
from observation import Observation
try:
    from automatic_observation import AutomaticObservation
except Exception:
    AutomaticObservation = None  # Optional observation never disables execution.


def stage_role(value):
    """Normalize existing aliases from the single provider-owned vocabulary"""
    roles = json.loads(Path(__file__).resolve().parents[1].joinpath('stage-roles.json').read_text())
    key = '-'.join(value.strip().lower().split())
    for canonical, aliases in roles.items():
        if key in aliases:
            return canonical
    raise argparse.ArgumentTypeError('unknown stage role; use engineer, reviewer, qa or land')


def positive_seconds(value):
    seconds = float(value)
    if not math.isfinite(seconds) or seconds <= 0:
        raise argparse.ArgumentTypeError("must be a finite positive number")
    return seconds


def display(value, limit=320):
    """Keep a bounded single terminal line without control sequences"""
    value = re.sub(r"\x1b\[[0-?]*[ -/]*[@-~]", "", str(value))
    value = "".join(c if not unicodedata.category(c).startswith("C") else " " for c in value)
    value = " ".join(value.split())
    return value if len(value) <= limit else value[:limit] + "…"


class Progress:
    def __init__(self, role, log, observation=None):
        self.observation = observation
        self.lifecycle = "starting"
        self.exit_code = None
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
        if self.observation:
            self.observation.publish(display(message, 480), self.lifecycle, self.exit_code)
        print(line, flush=True)

    def event(self, event):
        kind = event.get("type")
        if kind == "agent_start":
            self.lifecycle = "running"
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
            self.lifecycle = "settled-awaiting-exit"
            self.emit(self.activity)

    def heartbeat(self):
        if self.tools:
            label, started = next(iter(self.tools.values()))
            self.emit(f"Still running {label} ({time.monotonic() - started:.0f}s); {len(self.tools)} active tool(s)")
        else:
            self.emit(f"Still running — {self.activity}")


def run(args):
    os.umask(0o077)
    args.role = stage_role(args.role)
    if not re.fullmatch(r'TASK-\d{5}', args.task):
        raise ValueError('invalid TASK identity')
    attempt = Path(args.attempt).resolve(strict=True)
    if not (attempt / "request.md").is_file():
        raise ValueError("attempt must contain request.md")
    (attempt / "dispatch.lock").mkdir(mode=0o700)
    names = ("events.jsonl", "progress.log", "stderr.log", "final.txt", "exit-code", "pi-exit-code", "process.json")
    if any((attempt / name).exists() for name in names) or (attempt / "sessions").exists():
        raise ValueError("attempt already contains execution evidence; use a fresh attempt")
    # One selected package supplies workflow binding and the fixed passive monitor.
    # No enrollment, evaluator state, credential resolution or provider preflight.
    package = Path(__file__).resolve().parents[2]
    if args.harness_package and Path(args.harness_package).resolve(strict=True) != package:
        raise ValueError("selected package must own this monitor")
    command = ["pi", "-e", str(package), "--mode", "json", "--session-dir", str(attempt / "sessions"),
               "--name", f"{args.task} {args.role}", "--provider", args.provider,
               "--model", args.model, "--thinking", args.thinking, "--", f"@{attempt / 'request.md'}"]
    env = dict(os.environ, PI_PROVIDER=args.provider, PI_MODEL=args.model, PI_REASONING_LEVEL=args.thinking,
               FIGHT_WORKFLOW_PHASE=args.role, FIGHT_WORKFLOW_TASK=args.task,
               FIGHT_WORKFLOW_CHECKOUT=str(Path.cwd().resolve()))
    cancelled = [0]
    def on_signal(signum, _frame):
        cancelled[0] = 128 + signum
    for signum in (signal.SIGINT, signal.SIGTERM):
        signal.signal(signum, on_signal)

    with (attempt / "events.jsonl").open("xb", buffering=0) as raw, (attempt / "stderr.log").open("xb") as errors, (attempt / "progress.log").open("x") as log:
        observation = None
        identity = {"task": display(args.task, 80), "role": display(args.role, 60),
                    "provider": display(args.provider, 120), "model": display(args.model, 120),
                    "thinking": display(args.thinking, 40), "worktree": display(Path.cwd().resolve(), 1024),
                    "attempt": display(attempt, 1024)}
        if args.observe_socket:
            endpoint = Path(args.observe_socket)
            runtime = Path.cwd().resolve() / "var"
            if not endpoint.is_absolute() or runtime not in endpoint.resolve().parents:
                raise ValueError("observation socket must be under this checkout's var directory")
            observation = Observation(str(endpoint), identity, args.heartbeat)
        progress = Progress(args.role, log, observation)
        progress.emit(f"Starting {args.task} — {args.provider}/{args.model}, thinking={args.thinking}")
        child = None
        selector = None
        forced_code = 0
        code = 1
        try:
            child = subprocess.Popen(command, env=env, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                                     stderr=subprocess.PIPE, start_new_session=True)
            (attempt / "process.json").write_text(json.dumps({"monitor_pid": os.getpid(), "pi_pid": child.pid}) + "\n")
            if observation is None:
                try:
                    if AutomaticObservation is None:
                        raise RuntimeError("optional adapter unavailable")
                    observation = AutomaticObservation(identity, args.heartbeat, OBSERVATION_STARTED)
                    progress.observation = observation
                except Exception:
                    try:
                        print("Child observation unavailable (adapter-setup-unavailable); stage execution unchanged", file=sys.stderr, flush=True)
                    except OSError:
                        pass
            selector = selectors.DefaultSelector()
            selector.register(child.stdout, selectors.EVENT_READ, "events")
            selector.register(child.stderr, selectors.EVENT_READ, "diagnostics")
            buffer = b""
            stop_at = progress.started + args.timeout
            termination_at = None
            while selector.get_map() or child.poll() is None:
                now = time.monotonic()
                if observation:
                    observation.poll()
                if not forced_code and (cancelled[0] or now >= stop_at):
                    forced_code = cancelled[0] or 124
                    progress.emit("Cancellation requested" if cancelled[0] else "Stage timed out")
                    termination_at = now
                    stop_group(child, signal.SIGTERM, progress, errors)
                if termination_at is not None and now - termination_at >= 5:
                    stop_group(child, signal.SIGKILL, progress, errors)
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
                stop_group(child, signal.SIGKILL, progress, errors)
            pi_code = child.wait(timeout=5)
            (attempt / "pi-exit-code").write_text(str(pi_code) + "\n")
            code = forced_code or (pi_code if pi_code >= 0 else 128 - pi_code)
            if not code and (buffer or progress.tools or not progress.settled or progress.aborted or progress.stop_reason != "stop"):
                code = 1
                progress.emit("Incomplete or failed Pi response; inspect private events and session")
        except Exception as error:
            progress.emit(f"Stage monitor failed ({type(error).__name__}); inspect private logs")
            errors.write((type(error).__name__ + "\n").encode())
            code = forced_code or cancelled[0] or 1
        finally:
            if selector is not None:
                selector.close()
            if child is not None:
                if code:
                    stop_group(child, signal.SIGTERM, progress, errors)
                    try:
                        child.wait(timeout=5)
                    except subprocess.TimeoutExpired:
                        stop_group(child, signal.SIGKILL, progress, errors)
                        try:
                            child.wait(timeout=5)
                        except subprocess.TimeoutExpired as error:
                            shutdown_diagnostic(progress, errors, child, "wait", "leader-unreaped", error=error)
                if child.stdout:
                    child.stdout.close()
                if child.stderr:
                    child.stderr.close()
                if not (attempt / "pi-exit-code").exists():
                    pi_code = "unknown" if child.returncode is None else str(child.returncode)
                    (attempt / "pi-exit-code").write_text(pi_code + "\n")
            (attempt / "final.txt").write_text(progress.final)
            (attempt / "exit-code").write_text(str(code) + "\n")
            progress.lifecycle = "completed" if code == 0 else "failed"
            progress.exit_code = code
            progress.emit(f"Process complete (exit {code}); evidence still requires coordinator review")
            if observation:
                observation.close()
    return code


def shutdown_diagnostic(progress, errors, child, operation, reason, signum=None, error=None):
    """Retain finite safe shutdown facts, never exception prose or an all-stopped claim"""
    record = {"operation": operation, "reason": reason, "pid": child.pid}
    if signum is not None:
        record["signal"] = int(signum)
    if error is not None:
        record.update(error=type(error).__name__, errno=getattr(error, "errno", None))
    errors.write((json.dumps(record) + "\n").encode())
    errors.flush()
    progress.emit(f"Process-group shutdown unverified ({reason}); descendants may remain; inspect private stderr.log")


def stop_group(child, signum, progress, errors):
    if child.returncode is not None:
        shutdown_diagnostic(progress, errors, child, "killpg", "leader-exited", signum)
        return
    try:
        if os.getpgid(child.pid) != child.pid:
            shutdown_diagnostic(progress, errors, child, "killpg", "group-changed", signum)
            return
        os.killpg(child.pid, signum)
    except ProcessLookupError:
        pass
    except OSError as error:
        shutdown_diagnostic(progress, errors, child, "killpg", "signal-unavailable", signum, error)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--attempt", required=True)
    parser.add_argument("--task", required=True)
    parser.add_argument("--role", required=True, type=stage_role)
    parser.add_argument("--provider", required=True)
    parser.add_argument("--model", required=True)
    parser.add_argument("--thinking", required=True)
    parser.add_argument("--timeout", type=positive_seconds, default=1800)
    parser.add_argument("--heartbeat", type=positive_seconds, default=30)
    parser.add_argument("--harness-package", help="Explicit trusted Harness package directory; match the installed resource identity to avoid duplicate loading")
    parser.add_argument("--observe-socket", help="Optional absolute owner-only live socket under checkout var/; never a transcript")
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
