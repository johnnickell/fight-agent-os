"""Owning TASK-00544 regression: real offline native Pi, sequential fixed monitors

Run from a registered checkout with an unused canonical evidence directory:
  python3 -B harness/pi/tests/native/sequential-observation.py EVIDENCE PACKAGE

PACKAGE is an explicitly selected installed-layout Harness copy. No installation,
provider prompt, independent role, Herdr pane or application runtime is started.
Only the monitor's JSON child is controlled, as in installed-observation.py. Invoke
PACKAGE's fixed monitor directly: this fixture tests monitor EOF/history, not provider
role dispatch or its real native inventory/registration. The controlled executor must
not impersonate those preflights. Native !! execution, ancestry, process births and
admission are unchanged. This qualifies monitor admission, NOT independent stages,
signed session-file/reader, visual or runtime acceptance.
No transcript, progress history or pane stream is retained or sent to the model.
"""
import argparse
import fcntl
import json
import os
from pathlib import Path
import pty
import select
import shlex
import shutil
import socket
import struct
import subprocess
import termios
import time
import secrets

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('evidence', type=Path)
parser.add_argument('package', type=Path)
args = parser.parse_args()
run = args.evidence.resolve(); run.mkdir(parents=True, exist_ok=False)
root = Path.cwd().resolve(); package = args.package.resolve(strict=True)
assert b'worktree ' + os.fsencode(root) + b'\0' in subprocess.check_output(['git', 'worktree', 'list', '--porcelain', '-z'])
pi = shutil.which('pi'); assert pi, 'Installed Pi required'
home, bin_dir = run / 'home', run / 'bin'; home.mkdir(); bin_dir.mkdir()
child = bin_dir / 'pi'
child.write_text('''#!/usr/bin/env python3
import json, pathlib, sys, time
session = pathlib.Path(sys.argv[sys.argv.index('--session-dir') + 1])
code = 3 if session.parent.name == 'attempt-2' else 0
print(json.dumps(dict(type="agent_start")), flush=True)
time.sleep(2)
print(json.dumps(dict(type="message_end", message=dict(role="assistant", stopReason="stop", content=[dict(type="text", text="CONTROLLED_CHILD")]))), flush=True)
print(json.dumps(dict(type="agent_settled", aborted=False)), flush=True)
sys.exit(code)
'''); child.chmod(0o700)
master, slave = pty.openpty()
fcntl.ioctl(slave, termios.TIOCSWINSZ, struct.pack('HHHH', 40, 156, 0, 0))
env = {k: os.environ[k] for k in ('PATH', 'TMPDIR', 'LANG', 'LC_ALL', 'LC_CTYPE') if k in os.environ}
env.update(HOME=str(home), PI_CODING_AGENT_DIR=str(home / '.pi/agent'), PI_OFFLINE='1', TERM='xterm-256color')
command = [pi, '--offline', '--no-approve', '--no-extensions', '-e', str(package), '--session-dir', str(run / 'sessions'), '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high']
def setup():
    os.setsid(); fcntl.ioctl(0, termios.TIOCSCTTY, 0)
p = subprocess.Popen(command, cwd=root, env=env, stdin=slave, stdout=slave, stderr=slave, preexec_fn=setup)
daemons, results = {}, []
passed, failure = False, None

def pump():
    if select.select([master], [], [], .02)[0]:
        try:
            os.read(master, 65536)  # Human-only presentation: discard, never forward
        except OSError:
            pass

def until(predicate, label, seconds=15):
    deadline = time.monotonic() + seconds
    while time.monotonic() < deadline:
        value = predicate()
        if value:
            return value
        if p.poll() is not None:
            value = predicate()
            if value:
                return value
            raise AssertionError('Native Pi exited: ' + label)
        pump()
    raise AssertionError('Timed out: ' + label)

def route():
    found = []
    for path in (root / 'var/terminal/native').glob(f'{p.pid}-*.json'):
        value = json.loads(path.read_text()); daemons[value['pid']] = value; found.append(value)
    assert len(found) <= 1
    return found[0] if found else None

def snapshot(value):
    endpoint = root / 'var/terminal/s' / (value['generation'].replace('-', '')[:24] + '.sock')
    channel = socket.socket(socket.AF_UNIX); channel.settimeout(.5)
    try:
        channel.connect(str(endpoint.relative_to(root)))
        channel.sendall((json.dumps(dict(nonce=secrets.token_hex(32))) + '\n').encode())
        channel.shutdown(socket.SHUT_WR)  # Same half-close used by the owning reader
        reply = b''
        while b'\n' not in reply and len(reply) <= 2097152:
            part = channel.recv(65536); assert part, 'query closed'; reply += part
        return json.loads(json.loads(reply)['body'])
    finally:
        channel.close()

try:
    current = until(route, 'installed native route')
    # Custody only appears when Pi naturally persists its file. Never force it with
    # a setup entry, fabricated file or provider prompt merely for this probe.
    parent_file_present = snapshot(current)['parentFile'] is not None
    time.sleep(.1)  # Separate actual context/executor births beyond the safety band
    for index in range(12):
        attempt = run / f'attempt-{index}'; attempt.mkdir(); (attempt / 'request.md').write_text('Controlled no-provider child, not an independent role.\n')
        invocation = ['python3', '-u', '-B', str(package / 'src/observation/run-stage.py'), '--harness-package', str(package), '--attempt', str(attempt), '--task', 'TASK-00544', '--role', 'work', '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high', '--heartbeat', '1', '--timeout', '10']
        os.write(master, ('!!PATH=' + shlex.quote(str(bin_dir)) + ':$PATH ' + shlex.join(invocation) + '\r').encode())
        until(lambda: (attempt / 'process.json').exists(), 'fixed monitor launch')
        deadline, observed = time.monotonic() + 4, None
        while time.monotonic() < deadline:
            observed = next((entry for entry in snapshot(current)['children'] if entry['identity']['attempt'] == str(attempt)), None)
            if observed:
                break
            pump()
        until(lambda: (attempt / 'exit-code').exists(), 'unchanged stage completion')
        # Receipt precedes stream closure and native shell settlement.
        settle = time.monotonic() + 1.5
        while time.monotonic() < settle:
            pump()
        state = snapshot(current)
        completed = next((entry for entry in state['children'] if entry['identity']['attempt'] == str(attempt)), None)
        expected_code = 3 if index == 2 else 0
        code = int((attempt / 'exit-code').read_text())
        # Only fixed schema metadata leaves the probe. No returned history/text.
        results.append(dict(attempt=index + 1, associated=bool(observed), stageExit=code, expectedExit=expected_code,
            childCount=len(state['children']), lifecycle=completed['lifecycle'] if completed else None,
            observedExit=completed['exitCode'] if completed else None, disconnected=completed['state'] == 'disconnected' if completed else None))
        assert code == expected_code, 'Observation changed the fixture stage result'
    passed = all(row['associated'] and row['disconnected'] and row['observedExit'] == row['expectedExit'] and
        row['lifecycle'] == ('failed' if row['expectedExit'] else 'completed') and row['childCount'] <= 8 for row in results)
    os.write(master, b'\x04'); until(lambda: p.poll() is not None, 'normal native exit')
    assert p.wait() == 0
except Exception as error:
    passed = False
    failure = type(error).__name__  # Never expose raw subprocess/presentation errors
finally:
    if p.poll() is None:
        p.terminate()
        deadline = time.monotonic() + 5
        while p.poll() is None and time.monotonic() < deadline:
            pump()
        if p.poll() is None:
            p.kill()  # Exact owned diagnostic parent, never a real role worker
        p.wait(timeout=5)
    cleanup = []
    for pid, value in daemons.items():
        try:
            identity = subprocess.check_output(['ps', '-p', str(pid), '-o', 'lstart=', '-o', 'command='], stderr=subprocess.DEVNULL).decode().strip()
            if identity == value['process']:
                os.kill(pid, 15)
                deadline = time.monotonic() + 3
                route_path = root / 'var/terminal/native' / f"{p.pid}-{value['generation']}.json"
                endpoint = root / 'var/terminal/s' / (value['generation'].replace('-', '')[:24] + '.sock')
                while (route_path.exists() or endpoint.exists()) and time.monotonic() < deadline:
                    time.sleep(.02)
                cleanup.append(dict(pid=pid, exactLifetime=True, observationOnly=True, routeRemoved=not route_path.exists()))
        except (ProcessLookupError, subprocess.CalledProcessError):
            cleanup.append(dict(pid=pid, alreadyExited=True))
    runtime_removed = all(not (root / 'var/terminal/native' / f"{p.pid}-{value['generation']}.json").exists() and
        not (root / 'var/terminal/s' / (value['generation'].replace('-', '')[:24] + '.sock')).exists() for value in daemons.values())
    passed = passed and runtime_removed
    os.close(master); os.close(slave)
    # Only this probe's private HOME, controlled JSON output and sessions are disposable.
    for path in [home, bin_dir, run / 'sessions', *run.glob('attempt-*')]:
        if path.is_dir():
            shutil.rmtree(path)
    summary = dict(result='passed' if passed else 'failed', cases=results, failure=failure, nativePid=p.pid,
        nativeExit=p.returncode, parentFilePresent=locals().get('parent_file_present', False), providerRequests=0,
        package=str(package), checkout=str(root), cleanup=cleanup, observationRuntimeRemoved=runtime_removed)
    (run / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n')
    print(json.dumps(summary))
raise SystemExit(0 if passed else 1)
