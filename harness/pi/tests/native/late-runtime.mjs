// Owning real-Docker integration; run explicitly from a provider checkout with unused EVIDENCE.
// All container lifecycle actions below belong to this fixture, never to the observer.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { mkdirSync, realpathSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { DiscoveredLogs } from '../../src/herdr/discovered-logs.mjs';

const evidence = resolve(process.argv[2]); mkdirSync(evidence, { recursive: true });
const root = join(evidence, 'target'), linked = join(evidence, 'linked'); mkdirSync(root);
const execute = (command, args, options = {}) => execFileSync(command, args, { encoding: 'utf8', timeout: 20000, ...options }).trim();
const git = args => execute('git', ['-C', root, ...args]);
git(['init', '-b', 'main']); git(['-c', 'user.name=Harness Fixture', '-c', 'user.email=fixture@example.test', 'commit', '--allow-empty', '-m', 'fixture']);
git(['worktree', 'add', '-b', 'fixture-linked', linked]);
const checkout = realpathSync(linked), project = `task00538-${randomUUID().slice(0, 8)}`;
const image = 'node@sha256:dd9d21971ec4395903fa6143c2b9267d048ae01ca6d3ea96f16cb30df6187d94';
const containers = [], cases = [], cleanups = [];
const logs = new DiscoveredLogs();
const marker = 'OWNED_RUNTIME_RECORD';
function create(service = 'web', mount = checkout) {
  const id = execute('docker', ['run', '--detach', '--pull=never', '--network=none', '--read-only', '--cap-drop=ALL', '--security-opt=no-new-privileges:true', '--pids-limit=32', '--memory=64m', '--cpus=.25',
    '--label', `com.docker.compose.project=${project}`, '--label', `com.docker.compose.service=${service}`, '--label', `com.docker.compose.project.working_dir=${checkout}`,
    '--mount', `type=bind,source=${mount},target=/workspace,readonly`, image, 'node', '-e', `setInterval(() => console.log('${marker}'), 200)`]);
  containers.push(id); return id;
}
async function until(predicate, label, timeout = 30000) {
  const started = Date.now();
  while (Date.now() - started < timeout) {
    await logs.refresh();
    if (predicate()) { cases.push({ label, elapsedMs: Date.now() - started }); return; }
    await new Promise(done => setTimeout(done, 250));
  }
  throw Error(`Timed out: ${label}`);
}
try {
  await logs.rebind(checkout); assert.equal(logs.current, null);
  const first = create();
  await until(() => logs.current?.state === 'following' && logs.current.body().includes(marker), 'late startup auto attaches');
  assert.equal(logs.current.container, first);
  await logs.action('pause'); const old = logs.current;
  execute('docker', ['stop', '--time', '1', first]);
  await until(() => logs.current === null, 'stop clears stale output');
  assert.deepEqual(old.history, []); assert.equal(old.processes.size, 0);
  execute('docker', ['start', first]);
  await until(() => logs.current?.state === 'following', 'restart freshly verifies');
  assert.equal(logs.current.paused, true); assert.deepEqual(logs.current.frozen, []);
  execute('docker', ['rm', '--force', first]); const second = create();
  await until(() => logs.current?.container === second && logs.current.state === 'following', 'replacement auto attaches same verified service');
  const secondReader = logs.current;
  await logs.action('toggle'); const count = logs.readers.length;
  execute('docker', ['rm', '--force', second]); const third = create();
  await new Promise(done => setTimeout(done, 3300)); await logs.refresh();
  assert.equal(logs.current, secondReader); assert.equal(logs.current.open, false); assert.equal(logs.readers.length, count);
  await logs.action('toggle'); assert.equal(logs.current.container, third);
  const wrong = create('worker', realpathSync(root));
  await logs.action('select'); assert.equal(logs.choice().count, 1, 'wrong-checkout mount must not enter choices');
  await logs.action('cancel');
  execute('docker', ['rm', '--force', third]); const fourth = create();
  await new Promise(done => setTimeout(done, 3300)); await logs.refresh(); assert.equal(logs.current, null);
  await logs.action('select'); await logs.action('choose'); assert.equal(logs.current.container, fourth);
  const duplicate = create();
  await until(() => logs.current === null, 'replicated service revokes source');
  execute('docker', ['rm', '--force', duplicate, wrong]);
  await until(() => logs.current?.container === fourth, 'ambiguity resolves only to prior pinned service');
  // A source-context generation change always fences the old reader before any new discovery.
  logs.hold(); assert.equal(logs.current, null); await logs.rebind(realpathSync(root)); assert.equal(logs.current, null);
  cases.push({ label: 'manual close/cancel, wrong mounts, duplicate services and checkout change', passed: true });
  console.log(JSON.stringify({ result: 'passed', cases }));
} finally {
  await logs.close();
  for (const id of containers) {
    try { execute('docker', ['rm', '--force', id]); cleanups.push({ id, removed: true }); }
    catch { cleanups.push({ id, alreadyAbsent: true }); }
  }
  writeFileSync(join(evidence, 'summary.json'), JSON.stringify({ project, image, checkout, containers, cases, cleanups, trackedReadersAfterClose: logs.readers.flatMap(reader => [...reader.processes].map(child => child.pid)) }, null, 2) + '\n');
}
