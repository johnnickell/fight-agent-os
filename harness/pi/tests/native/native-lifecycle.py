"""Qualify the passive adapter with genuine installed native Pi lifecycle operations

Run from the selected checkout with an unused evidence directory:
  python3 harness/pi/tests/native/native-lifecycle.py EVIDENCE [--case cancel-new] [--with-provider]

--adapter can select a retained pre-repair adapter for the same regression.

Default cases submit no provider prompt or child launch. --with-provider adds one
harmless parent turn to qualify persisted resume/fork; it is explicit and bounded.
This is an owning native integration check, not a product-suite tooling test or QA.
"""
import argparse
import fcntl
import json
import os
import pathlib
import pty
import select
import struct
import subprocess
import termios
import time
import uuid

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('evidence', type=pathlib.Path)
parser.add_argument('--case', choices=['cancel-new'])
parser.add_argument('--with-provider', action='store_true')
parser.add_argument('--adapter', type=pathlib.Path, default=pathlib.Path('harness/pi/src/native-observation-extension.ts'))
args = parser.parse_args()
root = pathlib.Path.cwd().resolve()
run = args.evidence.resolve()
run.mkdir(parents=True, exist_ok=False)
(run / 'sessions').mkdir()
# A deliberately invalid *target* tests validation before teardown. It cannot
# supply parent membership, and is never used as a successful/resumed session.
invalid = run / 'invalid-target.jsonl'
invalid.write_text(json.dumps(dict(type='session', version=3, id=str(uuid.uuid4()),
    timestamp=time.strftime('%Y-%m-%dT%H:%M:%S.000Z', time.gmtime()), cwd=str(run / 'absent-cwd'))) + '\n')
extension = run / 'lifecycle-extension.ts'
extension.write_text('''import { appendFileSync } from 'node:fs';
const output = process.env.FIGHT_LIFECYCLE_OUTPUT!;
const state = ctx => ({ id: ctx.sessionManager.getSessionId(), file: ctx.sessionManager.getSessionFile() });
const record = value => appendFileSync(output, JSON.stringify(value) + '\\n');
export default function(pi) {
  let cancel = false;
  pi.on('session_before_switch', () => cancel ? { cancel: true } : undefined);
  pi.on('session_before_fork', () => cancel ? { cancel: true } : undefined);
  for (const type of ['session_start', 'session_shutdown']) pi.on(type, (event, ctx) => record({ event, state: state(ctx) }));
  pi.registerCommand('lifecycle-probe', { handler: async (args, ctx) => {
    const action = args.trim(), before = state(ctx);
    cancel = action.startsWith('cancel-');
    try {
      let result, after;
      const withSession = async fresh => { after = state(fresh); };
      if (action === 'cancel-new' || action === 'new') result = await ctx.newSession({ withSession });
      else if (action === 'cancel-resume' || action === 'bad-resume' || action === 'resume')
        result = await ctx.switchSession(action === 'resume' ? process.env.FIGHT_LIFECYCLE_RESUME! : process.env.FIGHT_LIFECYCLE_INVALID!, { withSession });
      else if (action === 'cancel-fork' || action === 'fork') {
        const entry = action === 'fork' ? ctx.sessionManager.getEntries().find(e => e.type === 'message' && e.message.role === 'assistant')?.id : 'invalid-entry';
        result = await ctx.fork(entry, { position: 'at', withSession });
      } else if (action === 'reload') {
        record({ action, before, reloading: true });
        await ctx.reload(); return; // The old context is invalid after reload.
      } else result = { idle: true };
      record({ action, before, result, after: after ?? state(ctx) });
    } catch (error) { record({ action, before, error: String(error), after: state(ctx) }); }
    finally { cancel = false; }
  }});
}
''')
master, slave = pty.openpty()
fcntl.ioctl(slave, termios.TIOCSWINSZ, struct.pack('HHHH', 40, 156, 0, 0))
before_attributes = termios.tcgetattr(slave)
command = ['pi', '--session-dir', str(run / 'sessions'), '--name', 'TASK00502 Engineer lifecycle diagnostic',
    '-e', str(args.adapter.resolve()), '-e', str(extension),
    '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high']
env = {k: os.environ[k] for k in ('HOME', 'PATH', 'TMPDIR', 'LANG', 'LC_ALL', 'LC_CTYPE', 'COLORTERM') if k in os.environ}
env.update(TERM='xterm-256color', FIGHT_LIFECYCLE_OUTPUT=str(run / 'state.jsonl'),
    FIGHT_LIFECYCLE_INVALID=str(invalid))
# The first native file's path is recorded by startup, not synthesized from an ID.
def setup():
    os.setsid()
    fcntl.ioctl(0, termios.TIOCSCTTY, 0)
p = subprocess.Popen(command, env=env, stdin=slave, stdout=slave, stderr=slave, preexec_fn=setup)
(run / 'process.json').write_text(json.dumps(dict(pid=p.pid, command=command)) + '\n')
raw = bytearray()
daemons = {}
results = []
route_root = root / 'var/terminal/native'

def pump(seconds=0.05):
    ready, _, _ = select.select([master], [], [], seconds)
    if ready:
        try:
            raw.extend(os.read(master, 65536))
        except OSError:
            pass

def until(predicate, label, timeout=20):
    end = time.monotonic() + timeout
    while time.monotonic() < end:
        result = predicate()
        if result:
            return result
        assert p.poll() is None, 'Native Pi exited during ' + label
        pump()
    raise AssertionError('Timed out: ' + label)

def states():
    path = run / 'state.jsonl'
    return [json.loads(line) for line in path.read_text().splitlines()] if path.exists() else []

def routes():
    found = []
    for path in route_root.glob(f'{p.pid}-*.json'):
        try:
            route = json.loads(path.read_text())
        except FileNotFoundError:
            continue
        daemons[route['pid']] = route
        found.append(route)
    return found

def current_route(parent=None, excluding=None):
    found = routes()
    return found[0] if len(found) == 1 and (parent is None or found[0]['parent'] == parent) and found[0]['generation'] != excluding else None

def action(name):
    prior = current_route()
    assert prior, 'No current native route before ' + name
    offset = len(states())
    os.write(master, ('/lifecycle-probe ' + name + '\r').encode())
    if name == 'bad-resume':
        until(lambda: b'Session cwd not found' in raw, 'native failed-target validation dialog')
        os.write(master, b'\x1b')  # Decline the native fallback; no cwd/session change.
    entry = until(lambda: next((row for row in states()[offset:] if row.get('action') == name), None), name)
    if name.startswith('cancel-') or name.startswith('bad-') or name == 'idle':
        if name.startswith('cancel-'):
            assert entry['result']['cancelled'] is True, entry
        if name.startswith('bad-'):
            assert entry.get('result', {}).get('cancelled') is True or 'error' in entry, entry
        assert entry['before'] == entry['after'], entry
        # Cancellation/failure must preserve the *same* route generation, not
        # rely on another notification, timer recovery or an operator restart.
        after = current_route()
        assert after and after['generation'] == prior['generation'], 'Current-session observation lost after ' + name
        assert not any(row.get('event', {}).get('type') == 'session_shutdown' for row in states()[offset:]), entry
    else:
        if name == 'reload':
            start = until(lambda: next((row for row in states()[offset:] if row.get('event', {}).get('type') == 'session_start'), None), 'reload start')
            expected = start['state']['id']
            assert start['state'] == entry['before']
        else:
            assert entry['result']['cancelled'] is False, entry
            assert entry['after']['id'] != entry['before']['id'], entry
            expected = entry['after']['id']
        after = until(lambda: current_route(expected, prior['generation']), 'replacement route')
        assert not any(row['generation'] == prior['generation'] for row in routes())
        events = [row['event'] for row in states()[offset:] if 'event' in row]
        assert [event['type'] for event in events] == ['session_shutdown', 'session_start'], events
    results.append(dict(action=name, state=entry, beforeGeneration=prior['generation'], afterGeneration=after['generation']))
    return after

try:
    first = until(current_route, 'initial native route')
    if args.case:
        action(args.case)
    else:
        for name in ['cancel-new', 'cancel-resume', 'cancel-fork', 'bad-resume', 'idle']:
            action(name)
        if args.with_provider:
            # One genuine bounded turn persists the native session without
            # fabricated conversation entries, fake files or child replay.
            original = states()[0]['state']['file']
            os.write(master, b'Reply exactly LIFECYCLE_READY. Do not use any tools or launch any child.\r')
            def persisted():
                path = pathlib.Path(original)
                if not path.exists():
                    return False
                entries = [json.loads(line) for line in path.read_text().splitlines()]
                messages = [e['message'] for e in entries if e.get('type') == 'message']
                assistants = [m for m in messages if m['role'] == 'assistant']
                assert not any(block.get('type') == 'toolCall' for m in assistants for block in m.get('content', []) if isinstance(block, dict))
                return assistants and assistants[-1].get('stopReason') == 'stop'
            until(persisted, 'single harmless parent turn', 120)
            # Set the target via a diagnostic command argument instead of mutating
            # process/runtime methods or using a stale post-replacement context.
            resume_extension = extension.read_text().replace("process.env.FIGHT_LIFECYCLE_RESUME!", json.dumps(original))
            extension.write_text(resume_extension)
            action('reload')
            action('fork')
            action('resume')
        action('new')
        action('reload')
        action('idle')
    os.write(master, b'\x04')
    until(lambda: p.poll() is not None, 'normal Ctrl+D exit')
    assert p.wait() == 0
    until_routes_end = time.monotonic() + 3
    while routes() and time.monotonic() < until_routes_end:
        pump()
    assert not routes(), 'Shutdown retained a native registration route'
    try:
        restored = termios.tcgetattr(slave) == before_attributes
        attribute_error = None
    except termios.error as error:
        restored = None
        attribute_error = str(error)  # Lifecycle proof is not terminal-restoration QA.
    (run / 'summary.json').write_text(json.dumps(dict(result='passed', nativePid=p.pid, cases=results,
        parentExit=p.returncode, providerPrompts=1 if args.with_provider else 0, observedChildLaunches=0,
        terminalAttributesRestored=restored, terminalAttributeError=attribute_error, shutdownRoutes=0), indent=2) + '\n')
    print(json.dumps(dict(result='passed', cases=len(results), parentExit=p.returncode, terminalAttributesRestored=restored)))
finally:
    (run / 'native.pty').write_bytes(raw)
    if p.poll() is None:
        os.write(master, b'\x03'); pump(.2); os.write(master, b'\x04')
        end = time.monotonic() + 5
        while p.poll() is None and time.monotonic() < end:
            pump()
        if p.poll() is None:
            p.terminate()
            end = time.monotonic() + 5
            while p.poll() is None and time.monotonic() < end:
                pump()
        if p.poll() is None:
            p.kill()  # Exact owned diagnostic parent only; never an observed child.
        p.wait(timeout=5)
    cleanup = []
    routes()
    for pid, route in daemons.items():
        try:
            identity = subprocess.check_output(['ps', '-p', str(pid), '-o', 'lstart=', '-o', 'command='], stderr=subprocess.DEVNULL).decode().strip()
            if identity == route['process']:
                os.kill(pid, 15)
                cleanup.append(dict(pid=pid, exactLifetime=True, signal='TERM', observationOnly=True))
        except ProcessLookupError:
            pass
        except subprocess.CalledProcessError:
            pass
    (run / 'cleanup.json').write_text(json.dumps(cleanup, indent=2) + '\n')
    os.close(slave)
    os.close(master)
