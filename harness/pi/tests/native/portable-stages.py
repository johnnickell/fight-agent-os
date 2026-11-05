"""Qualify installed process roles and native custody without granting real consumer Work

Run from the provider checkout: python3 -B portable-stages.py EVIDENCE INSTALLED_HARNESS [READ_ONLY_CONSUMER]
Uses real native Pi for parent, inventory and each stage startup. A private diagnostic
extension observes actual initialized workflow and handles the fixture input without a
provider turn. Monitor exit 1 is expected (no completed agent response), never acceptance.
No workflow selection is synthesized. All fixture mutations stay under EVIDENCE.
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
import termios
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('evidence', type=Path)
parser.add_argument('package', type=Path)
parser.add_argument('consumer', nargs='?', type=Path)
args = parser.parse_args()
provider, package, run = Path.cwd().resolve(), args.package.resolve(), args.evidence.resolve()
run.mkdir(parents=True, exist_ok=False)
root, linked, home, agent = (run / name for name in ('target', 'linked', 'home', 'agent'))
for directory in (root, home, agent):
    directory.mkdir()
pi = shutil.which('pi')
assert pi, 'Installed native Pi required'
def git(*arguments):
    return subprocess.check_output(['git', '-C', str(root), *arguments], stderr=subprocess.DEVNULL)
git('init', '-b', 'trunk')
(root / 'CLAUDE.md').write_text('PRIVATE_PORTABLE_TARGET: controlled startup fixture, no actual Work permission.\n')
(root / 'work-items').mkdir()
(root / 'work-items/README.md').write_text('Local work records use work-items, not provider planning/tasks. State: approved-to-code.\n')
git('add', '.'); git('-c', 'user.name=Harness Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Controlled target fixture')
git('worktree', 'add', '-b', 'topic/native-consumer', str(linked))
(run / 'git-worktrees.txt').write_bytes(git('worktree', 'list', '--porcelain'))
diag = run / 'startup.ts'
diag.write_text('''import { writeFileSync } from 'node:fs';
import { sessionWorkflow } from %s;
export default function(pi) {
  let requests = 0;
  pi.on('before_provider_request', () => { requests++; throw new Error('No provider execution in startup qualification'); });
  pi.on('input', async (_event, ctx) => {
    const output = process.env.FIGHT_PROBE_OUTPUT;
    if (!output) return;
    const workflow = sessionWorkflow(ctx.sessionManager);
    writeFileSync(output, JSON.stringify({ workflow, cwd: ctx.cwd, session: ctx.sessionManager.getSessionId(),
      provider: ctx.model?.provider, model: ctx.model?.id, thinking: ctx.thinkingLevel,
      fixtureInstructions: ctx.getSystemPrompt().includes('PRIVATE_PORTABLE_TARGET'),
      consumerInstructions: ctx.getSystemPrompt().includes('Fight Common project binding'), providerRequests: requests }));
    await new Promise(resolve => setTimeout(resolve, 4500));
    return { action: 'handled' };
  });
}
''' % json.dumps((package / 'src/build/authority.mjs').as_uri()))
(agent / 'settings.json').write_text(json.dumps(dict(packages=[str(package)], extensions=[str(diag)])))
shutil.copyfile(package / 'profiles/subagents.json', agent / 'subagents.json')
env = {key: os.environ[key] for key in ('PATH', 'TMPDIR', 'LANG', 'LC_ALL', 'LC_CTYPE') if key in os.environ}
env.update(HOME=str(home), PI_CODING_AGENT_DIR=str(agent), PI_OFFLINE='1', TERM='xterm-256color')
results, daemons, raw = [], {}, bytearray()
# Known consumer inspection is startup only: no borrowed TASK, launcher phase or source mutation.
if args.consumer:
    consumer = args.consumer.resolve()
    before = subprocess.check_output(['git', '-C', str(consumer), 'status', '--porcelain=v1', '--untracked-files=all'])
    head = subprocess.check_output(['git', '-C', str(consumer), 'rev-parse', 'HEAD']).decode().strip()
    output = run / 'consumer-startup.json'
    result = subprocess.run([pi, '--offline', '--no-approve', '--mode', 'json', '--session-dir', str(run / 'consumer-sessions'),
        '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high', '--', 'Read-only startup qualification; input is handled without execution.'],
        cwd=consumer, env=dict(env, FIGHT_PROBE_OUTPUT=str(output)), capture_output=True, timeout=20)
    (run / 'consumer-cli.log').write_bytes(result.stdout + result.stderr)
    assert result.returncode == 0, 'Known consumer native startup failed'
    status = json.loads(output.read_text())
    assert status['cwd'] == str(consumer) and status['workflow']['taskId'] is None and status['consumerInstructions']
    assert status['providerRequests'] == 0
    inventory = subprocess.run(['python3', '-B', str(provider / '.pi/skills/build/scripts/run-stage.py'), '--help'],
        cwd=consumer, env=env, capture_output=True, timeout=40)
    (run / 'consumer-provider-help.log').write_bytes(inventory.stdout + inventory.stderr)
    assert inventory.returncode == 0, 'Provider resource resolution from consumer failed'
    after = subprocess.check_output(['git', '-C', str(consumer), 'status', '--porcelain=v1', '--untracked-files=all'])
    assert before == after
    results.append(dict(case='known consumer read-only native startup and provider resolution', root=str(consumer), head=head,
        statusUnchanged=True, status=status, realWork=False))
master, slave = pty.openpty()
fcntl.ioctl(slave, termios.TIOCSWINSZ, struct.pack('HHHH', 40, 156, 0, 0))
def setup():
    os.setsid(); fcntl.ioctl(0, termios.TIOCSCTTY, 0)
p = subprocess.Popen([pi, '--offline', '--approve', '--session-dir', str(run / 'parent-sessions'), '--provider', 'openai-codex',
    '--model', 'gpt-6.1-sol', '--thinking', 'high'], cwd=root, env=env, stdin=slave, stdout=slave, stderr=slave, preexec_fn=setup)
def pump():
    if select.select([master], [], [], .05)[0]:
        try: raw.extend(os.read(master, 65536))
        except OSError: pass
def until(predicate, label, seconds=40):
    deadline = time.monotonic() + seconds
    while time.monotonic() < deadline:
        result = predicate()
        if result: return result
        if p.poll() is not None:
            result = predicate()  # Exit can race the first predicate evaluation.
            if result: return result
            raise AssertionError('Native parent exited: ' + label)
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
        old = Path.cwd(); os.chdir(root)
        try: channel.connect(str(endpoint.relative_to(root)))
        finally: os.chdir(old)
        channel.sendall((json.dumps(dict(nonce=secrets.token_hex(32))) + '\n').encode())
        reply = b''
        while b'\n' not in reply: reply += channel.recv(65536)
        return json.loads(json.loads(reply)['body'])
    finally: channel.close()
passed = False
try:
    current = until(route, 'installed native parent custody')
    aliases = [('engineer', 'engineer', 'work'), ('work', 'engineer', 'work'), ('software-engineer', 'engineer', 'work'),
        ('reviewer', 'reviewer', 'review'), ('review', 'reviewer', 'review'), ('senior-engineer', 'reviewer', 'review'),
        ('qa', 'qa', 'qa'), ('qa-engineer', 'qa', 'qa'), ('land', 'land', 'land'), ('release-manager', 'land', 'land')]
    for index, (role, canonical, skill) in enumerate(aliases):
        target = root if index % 2 == 0 else linked
        attempt = run / f'attempt-{index}'; attempt.mkdir()
        (attempt / 'request.md').write_text('Private startup fixture only. No actual TASK-00560 target Work, verdict or provider request.\n')
        status_file = attempt / 'native-status.json'
        invocation = ['python3', '-u', '-B', str(provider / '.pi/skills/build/scripts/run-stage.py'), '--harness-package', str(package),
            '--attempt', str(attempt), '--task', 'TASK-00560', '--role', role, '--provider', 'openai-codex', '--model', 'gpt-6.1-sol',
            '--thinking', 'high', '--heartbeat', '1', '--timeout', '20']
        shell = f'cd {shlex.quote(str(target))} && FIGHT_PROBE_OUTPUT={shlex.quote(str(status_file))} ' + shlex.join(invocation)
        os.write(master, ('!!' + shell + '\r').encode())
        until(lambda: status_file.exists(), 'actual native role startup')
        observed = until(lambda: next((child for child in snapshot(current)['children'] if child['identity']['attempt'] == str(attempt)), None), 'canonical custody identity')
        until(lambda: (attempt / 'exit-code').exists(), 'native diagnostic exit')
        status = json.loads(status_file.read_text())
        assert status['workflow']['role'] == canonical and status['workflow']['skill'] == skill
        assert status['workflow']['launcher'] == canonical and status['workflow']['taskId'] == 'TASK-00560'
        assert status['workflow']['checkout'] == str(target) and status['cwd'] == str(target)
        assert status['fixtureInstructions'] and status['providerRequests'] == 0
        assert (status['provider'], status['model'], status['thinking']) == ('openai-codex', 'gpt-6.1-sol', 'high')
        assert observed['identity']['role'] == canonical and observed['identity']['worktree'] == str(target)
        assert (attempt / 'pi-exit-code').read_text().strip() == '0'
        assert (attempt / 'exit-code').read_text().strip() == '1', 'No agent response must not become success'
        results.append(dict(case='installed native role startup', input=role, canonical=canonical, actual=status, observed=observed['identity'], piExit=0, monitorExit=1))
        until_time = time.monotonic() + 1
        while time.monotonic() < until_time: pump()
    # Unknown stage inputs refuse before even claiming an attempt or starting Pi.
    for index, role in enumerate(['build', 'team-lead', 'unknown']):
        attempt = run / f'refused-{index}'; attempt.mkdir(); (attempt / 'request.md').write_text('Never execute.\n')
        result = subprocess.run(['python3', '-B', str(package / 'src/observation/run-stage.py'), '--attempt', str(attempt),
            '--task', 'TASK-00560', '--role', role, '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high'],
            cwd=root, env=env, capture_output=True, timeout=5)
        assert result.returncode != 0 and not (attempt / 'dispatch.lock').exists() and not (attempt / 'process.json').exists()
        results.append(dict(case='unknown/coordinator role refused before launch', input=role))
    # Same native ancestry does not make a different repository this target.
    foreign = run / 'foreign'; foreign.mkdir()
    subprocess.run(['git', '-C', str(foreign), 'init', '-b', 'other/default'], check=True, capture_output=True)
    (foreign / 'CLAUDE.md').write_text('FOREIGN_PORTABLE_TARGET: diagnostic only.\n')
    attempt = run / 'wrong-target'; attempt.mkdir(); (attempt / 'request.md').write_text('Foreign startup fixture, no actual Work.\n')
    invocation = ['python3', '-B', str(provider / '.pi/skills/build/scripts/run-stage.py'), '--attempt', str(attempt),
        '--task', 'TASK-00560', '--role', 'reviewer', '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high', '--timeout', '20']
    os.write(master, ('!!cd ' + shlex.quote(str(foreign)) + ' && FIGHT_PROBE_OUTPUT=' + shlex.quote(str(attempt / 'native-status.json')) + ' ' + shlex.join(invocation) + ' 2>' + shlex.quote(str(attempt / 'monitor.stderr')) + '\r').encode())
    until(lambda: (attempt / 'exit-code').exists(), 'wrong-target monitor exit')
    status = json.loads((attempt / 'native-status.json').read_text())
    assert status['cwd'] == str(foreign) and not status['fixtureInstructions']
    assert (attempt / 'pi-exit-code').read_text().strip() == '0' and (attempt / 'exit-code').read_text().strip() == '1'
    assert 'no-current-native-route' in (attempt / 'monitor.stderr').read_text()
    assert not any(child['identity']['attempt'] == str(attempt) for child in snapshot(current)['children'])
    results.append(dict(case='foreign target cannot borrow native parent custody', actual=status, monitorExit=1))
    until_time = time.monotonic() + 1
    while time.monotonic() < until_time: pump()
    # A real monitor outside native ancestry cannot borrow this parent's custody.
    attempt = run / 'unrelated'; attempt.mkdir(); (attempt / 'request.md').write_text('Unrelated startup fixture.\n')
    result = subprocess.run(['python3', '-B', str(provider / '.pi/skills/build/scripts/run-stage.py'), '--attempt', str(attempt),
        '--task', 'TASK-00560', '--role', 'review', '--provider', 'openai-codex', '--model', 'gpt-6.1-sol', '--thinking', 'high', '--timeout', '20'],
        cwd=root, env=dict(env, FIGHT_PROBE_OUTPUT=str(attempt / 'native-status.json')), capture_output=True, timeout=40)
    (run / 'unrelated.log').write_bytes(result.stdout + result.stderr)
    assert result.returncode == 1 and b'no-current-native-route' in result.stderr
    assert not any(child['identity']['attempt'] == str(attempt) for child in snapshot(current)['children'])
    results.append(dict(case='unrelated native monitor refused by custody', monitorExit=1))
    os.write(master, b'\x04')
    until(lambda: p.poll() is not None, 'normal native parent exit')
    assert p.wait() == 0
    passed = True
finally:
    if p.poll() is None:
        p.terminate()
        deadline = time.monotonic() + 5
        while p.poll() is None and time.monotonic() < deadline: pump()
        if p.poll() is None:
            p.kill()
            deadline = time.monotonic() + 5
            while p.poll() is None and time.monotonic() < deadline: pump()
    cleanup = []
    for pid, value in daemons.items():
        try:
            identity = subprocess.check_output(['ps', '-p', str(pid), '-o', 'lstart=', '-o', 'command='], stderr=subprocess.DEVNULL).decode().strip()
            if identity == value['process']: os.kill(pid, 15); cleanup.append(dict(pid=pid, exactLifetime=True, observationOnly=True))
        except (ProcessLookupError, subprocess.CalledProcessError): pass
    (run / 'native.pty').write_bytes(raw)
    (run / 'summary.json').write_text(json.dumps(dict(result='passed' if passed else 'failed', cases=results,
        parentExit=p.returncode, providerRequests=0, realWork=False, nativeHerdr=False, cleanup=cleanup), indent=2) + '\n')
    os.close(master); os.close(slave)
print(json.dumps(dict(result='passed', cases=len(results), providerRequests=0, realWork=False)))
