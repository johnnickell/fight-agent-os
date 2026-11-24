"""Exercise the monitor against finite owned children, without provider or workflow authority."""
import json
import os
from pathlib import Path
import signal
import socket
import stat
import subprocess
import sys
import tempfile
import time

package = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]
monitor = package / 'src/observation/run-stage.php'


def until(check, process, timeout=8):
    end = time.monotonic() + timeout
    while time.monotonic() < end:
        if check():
            return
        assert process.poll() is None, 'Monitor exited before fixture was ready'
        time.sleep(.02)
    raise AssertionError('Owned fixture did not become ready')


with tempfile.TemporaryDirectory(prefix='fph-stage-', dir='/tmp') as name:
    root = Path(name).resolve()
    child = root / 'pi'
    child.write_text('#!' + sys.executable + '\n' + '''import json, os, pathlib, signal, sys, time
mode = os.environ['FIXTURE']
attempt = pathlib.Path(sys.argv[sys.argv.index('--session-dir') + 1]).parent
with (attempt / 'binding').open('x') as f:
    json.dump([os.getpid(), os.getpgrp(), os.getsid(0), os.getcwd(), os.environ['FIGHT_WORKFLOW_PHASE'],
        os.environ['FIGHT_WORKFLOW_TASK'], os.environ['FIGHT_WORKFLOW_CHECKOUT'], sys.argv[1:]], f)
if mode == 'malformed':
    print('PRIVATE_INVALID_EVENT', flush=True)
    sys.exit(0)
print('{"type":"agent_start"}', flush=True)
if mode == 'timeout':
    signal.signal(signal.SIGTERM, signal.SIG_IGN)
(attempt / 'ready').touch()
if mode in ('cancel', 'timeout', 'socket'):
    end = time.monotonic() + 14
    while time.monotonic() < end and not (attempt / 'continue').exists():
        time.sleep(.02)
if mode in ('success', 'socket'):
    print(json.dumps({'type':'message_end','message':{'role':'assistant','stopReason':'stop',
        'content':[{'type':'text','text':'finished'},{'type':'thinking','thinking':'PRIVATE_THINKING'}]}}), flush=True)
    print('{"type":"agent_settled","aborted":false}', flush=True)
''')
    child.chmod(0o700)
    (root / 'var').mkdir(mode=0o700)
    endpoint = root / 'var/o.sock'
    env = dict(os.environ, PATH=str(root) + os.pathsep + os.environ['PATH'])
    for mode, expected in [('success', 0), ('unsettled', 1), ('malformed', 1), ('cancel', 143), ('timeout', 124), ('socket', 0)]:
        attempt = root / f'{mode} attempt'
        attempt.mkdir()
        (attempt / 'request.md').write_text('Finite test only; no provider or actual assignment.\n')
        command = ['php', str(monitor), '--attempt', str(attempt), '--task', 'TASK-00585', '--role', 'Release Manager',
                   '--provider', 'fixture;literal', '--model', 'controlled', '--thinking', 'off', '--heartbeat', '1',
                   '--timeout', '2' if mode == 'timeout' else '12', '--harness-package', str(package)]
        if mode == 'socket':
            command += ['--observe-socket', str(endpoint)]
        with subprocess.Popen(command, cwd=root, env=dict(env, FIXTURE=mode), stdout=subprocess.PIPE, stderr=subprocess.PIPE) as process:
            try:
                if mode == 'cancel':
                    until((attempt / 'ready').exists, process)
                    process.terminate()
                if mode == 'socket':
                    until((attempt / 'ready').exists, process)
                    assert stat.S_IMODE(endpoint.stat().st_mode) == 0o600
                    # A second attempt must not replace the owned endpoint.
                    collision = root / 'collision'; collision.mkdir()
                    (collision / 'request.md').write_text('Never launch.\n')
                    other = list(command); other[3] = str(collision)
                    refused = subprocess.run(other, cwd=root, env=env, capture_output=True, timeout=5)
                    assert refused.returncode == 1 and not (collision / 'process.json').exists()
                    with socket.socket(socket.AF_UNIX) as peer:
                        peer.settimeout(6)
                        peer.connect(str(endpoint))
                        with peer.makefile('rb') as records:
                            initial = json.loads(records.readline())
                            assert initial['kind'] == 'snapshot' and initial['identity']['worktree'] == str(root)
                            (attempt / 'continue').touch()
                            progress = [json.loads(line) for line in records]
                            assert progress[-1]['lifecycle'] == 'completed' and progress[-1]['exitCode'] == 0
                out, err = process.communicate(timeout=16)
            finally:
                if process.poll() is None:
                    # The exact owned stage process handles its own Pi group shutdown.
                    process.terminate()
                    process.communicate(timeout=24)
        assert process.returncode == expected, (mode, process.returncode, out, err)
        assert (attempt / 'exit-code').read_text() == f'{expected}\n'
        assert (attempt / 'final.txt').read_text() == ('finished' if expected == 0 else '')
        assert b'PRIVATE_' not in out + err
        evidence = json.loads((attempt / 'process.json').read_text())
        pid, group, session, cwd, role, task, checkout, argv = json.loads((attempt / 'binding').read_text())
        assert evidence == {'monitor_pid': process.pid, 'pi_pid': pid}
        assert pid == group == session and role == 'land' and task == 'TASK-00585' and cwd == checkout == str(root)
        assert argv[0:2] == ['-e', str(package)] and 'fixture;literal' in argv and argv[-1] == '@' + str(attempt / 'request.md')
        assert not endpoint.exists()
        pi_code = (attempt / 'pi-exit-code').read_text()
        if mode in ('timeout', 'cancel'):
            assert pi_code == ('-9\n' if mode == 'timeout' else '-15\n'), (mode, pi_code, out)
            assert b'leader-exited' in out  # Do not signal a reaped leader/group again.
        try:
            os.kill(pid, 0)
        except ProcessLookupError:
            pass
        else:
            raise AssertionError('Owned Pi fixture did not exit')
        assert stat.S_IMODE((attempt / 'events.jsonl').stat().st_mode) == 0o600
print('Monitor: six owned process cases passed (plus socket collision refusal); no provider or stage authority.')
