"""Owning native regression: package tarball + separate Git repository/worktree, no provider requests

Run from the provider checkout: python3 harness/pi/tests/native/installed-observation.py EVIDENCE
Uses a genuine native Pi and its unchanged !! executor. Only the monitor's JSON child is
controlled; no authentication, global installation, other sessions or Herdr panes are used.
"""
import argparse
import fcntl
import json
import os
from pathlib import Path
import pty
import secrets
import select
import shlex
import shutil
import socket
import struct
import subprocess
import tarfile
import termios
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('evidence', type=Path)
args = parser.parse_args()
provider = Path.cwd().resolve()
run = args.evidence.resolve()
run.mkdir(parents=True, exist_ok=False)
pi = shutil.which('pi')
assert pi, 'Installed Pi required'
# Pack exactly the installable manifest, not a checkout masquerading as an installation.
packed = subprocess.check_output(['npm', 'pack', '--ignore-scripts', '--json', '--pack-destination', str(run)], cwd=provider / 'harness/pi')
archive = run / json.loads(packed)[0]['filename']
with tarfile.open(archive) as bundle:
    assert all(member.name.startswith('package/') and '..' not in Path(member.name).parts and not member.issym() and not member.islnk() for member in bundle.getmembers())
    bundle.extractall(run / 'installed')
package = run / 'installed/package'
root, linked, home = (run / name for name in ('target', 'linked', 'home'))
root.mkdir(); home.mkdir()
def git(*args):
    return subprocess.check_output(['git', '-C', str(root), *args], stderr=subprocess.DEVNULL)
git('init', '-b', 'main'); git('-c', 'user.name=Harness Fixture', '-c', 'user.email=fixture@example.test', 'commit', '--allow-empty', '-m', 'fixture')
git('worktree', 'add', '-b', 'fixture-linked', str(linked))
(run / 'git-worktrees.txt').write_bytes(git('worktree', 'list', '--porcelain'))
bin_dir = run / 'bin'; bin_dir.mkdir()
child = bin_dir / 'pi'
child.write_text('#!/usr/bin/env python3\nimport json, time\nprint(json.dumps(dict(type="agent_start")), flush=True)\ntime.sleep(5)\nprint(json.dumps(dict(type="message_end", message=dict(role="assistant", stopReason="stop", content=[dict(type="text", text="OWNED_CHILD_READY")]))), flush=True)\nprint(json.dumps(dict(type="agent_settled", aborted=False)), flush=True)\n')
child.chmod(0o700)
master, slave = pty.openpty()
fcntl.ioctl(slave, termios.TIOCSWINSZ, struct.pack('HHHH', 40, 156, 0, 0))
env = {k: os.environ[k] for k in ('PATH', 'TMPDIR', 'LANG', 'LC_ALL', 'LC_CTYPE') if k in os.environ}
env.update(HOME=str(home), PI_CODING_AGENT_DIR=str(home / '.pi/agent'), PI_OFFLINE='1', TERM='xterm-256color')
command = [pi, '--offline', '--approve', '-e', str(package), '--session-dir', str(run / 'sessions'), '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high']
def setup():
    os.setsid(); fcntl.ioctl(0, termios.TIOCSCTTY, 0)
p = subprocess.Popen(command, cwd=root, env=env, stdin=slave, stdout=slave, stderr=slave, preexec_fn=setup)
raw, daemons, results = bytearray(), {}, []
passed = False
def pump():
    if select.select([master], [], [], .05)[0]:
        try:
            raw.extend(os.read(master, 65536))
        except OSError:
            pass
def until(predicate, label, seconds=15):
    deadline = time.monotonic() + seconds
    while time.monotonic() < deadline:
        result = predicate()
        if result:
            return result
        if p.poll() is not None:
            result = predicate()  # Exit can race the first predicate evaluation.
            if result:
                return result
            raise AssertionError('Native Pi exited: ' + label)
        pump()
    raise AssertionError('Timed out: ' + label)
def route():
    for path in (root / 'var/terminal/native').glob(f'{p.pid}-*.json'):
        value = json.loads(path.read_text()); daemons[value['pid']] = value
        return value

def snapshot(value):
    endpoint = root / 'var/terminal/s' / (value['generation'].replace('-', '')[:24] + '.sock')
    channel = socket.socket(socket.AF_UNIX); channel.settimeout(.5)
    try:
        # Keep Unix socket paths within the native platform limit without changing the monitor cwd.
        old = Path.cwd(); os.chdir(root)
        try:
            channel.connect(str(endpoint.relative_to(root)))
        finally:
            os.chdir(old)
        channel.sendall((json.dumps(dict(nonce=secrets.token_hex(32))) + '\n').encode())
        reply = b''
        while b'\n' not in reply:
            reply += channel.recv(65536)
        return json.loads(json.loads(reply)['body'])
    finally:
        channel.close()
try:
    current = until(route, 'installed native route')
    time.sleep(.1)  # Context/executor birth must be unambiguously separated.
    scenarios = [(root, package, True), (linked, package, True), (root, provider / 'harness/pi', False)]
    for index, (target, selected_package, expected) in enumerate(scenarios):
        attempt = run / f'attempt-{index}'; attempt.mkdir(); (attempt / 'request.md').write_text('Controlled no-provider child fixture.\n')
        invocation = ['python3', '-u', str(provider / '.pi/skills/build/scripts/run-stage.py'), '--harness-package', str(selected_package), '--attempt', str(attempt), '--task', 'TASK-00538', '--role', 'work', '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high', '--heartbeat', '1', '--timeout', '12']
        shell = f'cd {shlex.quote(str(target))} && PATH={shlex.quote(str(bin_dir))}:$PATH ' + shlex.join(invocation)
        os.write(master, ('!!' + shell + '\r').encode())
        until(lambda: (attempt / 'process.json').exists(), 'ordinary stage launch')
        deadline = time.monotonic() + 4
        observed = None
        while time.monotonic() < deadline:
            children = snapshot(current)['children']
            observed = next((entry for entry in children if entry['identity']['worktree'] == str(target) and entry['identity']['attempt'] == str(attempt)), None)
            if observed:
                break
            pump()
        until(lambda: (attempt / 'exit-code').exists(), 'unchanged stage completion')
        code = int((attempt / 'exit-code').read_text())
        results.append(dict(worktree=str(target), expectedAssociation=expected, observed=observed, stageExit=code))
        assert code == 0, 'Observation must not affect stage success'
        assert bool(observed) == expected, 'Installed package association did not match the selected trusted package'
        if not expected:
            assert 'Child observation unavailable (native-package-or-lifetime-mismatch)' in (attempt / 'stderr.log').read_text() or b'Child observation unavailable (native-package-or-lifetime-mismatch)' in raw
        # The receipt precedes final observer/pipe cleanup and native shell settlement.
        # Drain PTY output before submitting the next native command.
        settle = time.monotonic() + 1.5
        while time.monotonic() < settle:
            pump()
        if expected:
            completed = next(entry for entry in snapshot(current)['children'] if entry['identity']['attempt'] == str(attempt))
            assert completed['lifecycle'] == 'completed' and completed['exitCode'] == 0
            assert 'OWNED_CHILD_READY' in completed['history'], 'Safe activity must reach the installed custodian'
            results[-1]['completed'] = completed
    # A same-user monitor in the same checkout but outside this native ancestry is not a child.
    attempt = run / 'unrelated-attempt'; attempt.mkdir(); (attempt / 'request.md').write_text('Controlled unrelated monitor.\n')
    invocation = ['python3', '-u', str(provider / '.pi/skills/build/scripts/run-stage.py'), '--harness-package', str(package), '--attempt', str(attempt), '--task', 'TASK-00538', '--role', 'work', '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high', '--heartbeat', '1', '--timeout', '12']
    unrelated = subprocess.run(invocation, cwd=root, env=dict(env, PATH=str(bin_dir) + os.pathsep + env['PATH']), capture_output=True, timeout=20)
    (run / 'unrelated-monitor.log').write_bytes(unrelated.stdout + unrelated.stderr)
    assert unrelated.returncode == 0
    assert b'Child observation unavailable (no-current-native-route)' in unrelated.stderr
    assert not any(entry['identity']['attempt'] == str(attempt) for entry in snapshot(current)['children'])
    results.append(dict(case='unrelated same-checkout monitor', expectedAssociation=False, stageExit=unrelated.returncode))
    os.write(master, b'\x04'); until(lambda: p.poll() is not None, 'normal native exit')
    assert p.wait() == 0
    passed = True
    print(json.dumps(dict(result='passed', cases=len(results), providerRequests=0)))
finally:
    if p.poll() is None:
        p.terminate()
        deadline = time.monotonic() + 5
        while p.poll() is None and time.monotonic() < deadline:
            pump()
        if p.poll() is None:
            p.kill()
        p.wait(timeout=5)
    (run / 'native.pty').write_bytes(raw)
    cleanup = []
    for pid, value in daemons.items():
        try:
            identity = subprocess.check_output(['ps', '-p', str(pid), '-o', 'lstart=', '-o', 'command='], stderr=subprocess.DEVNULL).decode().strip()
            if identity == value['process']:
                os.kill(pid, 15); cleanup.append(dict(pid=pid, exactLifetime=True, observationOnly=True))
        except (ProcessLookupError, subprocess.CalledProcessError):
            pass
    (run / 'summary.json').write_text(json.dumps(dict(result='passed' if passed else 'failed', cases=results, nativePid=p.pid, nativeExit=p.returncode, providerRequests=0, cleanup=cleanup), indent=2) + '\n')
    os.close(master); os.close(slave)
