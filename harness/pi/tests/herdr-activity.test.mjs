import test from 'node:test';
import assert from 'node:assert/strict';
import { chmodSync, existsSync, linkSync, mkdtempSync, realpathSync, writeFileSync, renameSync, rmSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';
import net from 'node:net';
import { NativeWorkspaceContext, queryLocal, readBinding, validateContext, serveLocal } from '../src/herdr/context.mjs';
import { HerdrActivity, validateSelection } from '../src/herdr/activity.mjs';
import { RegisteredChildren } from '../src/workspace/registered-children.mjs';

async function fixture(t) {
  const cwd = realpathSync(mkdtempSync(join(tmpdir(), 'herdr-activity-'))), file = join(cwd, 'native.jsonl');
  writeFileSync(file, 'owned boundary fixture; not a native qualification');
  const primary = { cwd, sessionId: randomUUID(), sessionFile: file, pid: process.pid };
  let current = primary;
  const native = new NativeWorkspaceContext(primary, () => current), binding = await native.start();
  const generation = randomUUID();
  const child = (overrides = {}) => ({ id: randomUUID(), parent: primary.sessionId, generation, started: Date.now() - 100, order: 1,
    sequence: 1, receivedAt: Date.now(), state: 'connected', lifecycle: 'running', exitCode: null, heartbeatSeconds: 2,
    identity: { task: 'TASK-00511', role: 'work', provider: 'openai-codex', model: 'configured-only', thinking: 'high', worktree: cwd, attempt: '/owned/attempt' },
    history: ['safe progress not authority'], omitted: 0, gaps: 0, ...overrides });
  let values = [child()], refused = false;
  const activity = new HerdrActivity(binding, () => {}, { reader: (root, changed) => new RegisteredChildren(root, changed, {
    preferences: false, discover: () => [{ path: '/fixture', descriptor: {} }],
    verify: async () => { if (refused) throw Error('Custody lost'); return { parent: primary.sessionId, generation, parentFile: native.snapshot().file, at: Date.now(), children: values }; },
  }) });
  t.after(() => { activity.close(); native.close(); rmSync(cwd, { recursive: true, force: true }); });
  return { native, primary, binding, activity, child, values: () => values, set: next => { values = next; }, refuse: () => { refused = true; }, replace: next => { current = next; } };
}

test('native context is live, generation-bound and read-only; wrong token, parent and generation refuse', async t => {
  const f = await fixture(t);
  const value = await queryLocal(f.binding, { operation: 'context', token: f.binding.token });
  assert.deepEqual(validateContext(f.binding, value).primary, f.primary);
  await assert.rejects(queryLocal(f.binding, { operation: 'context', token: randomUUID() }));
  await assert.rejects(queryLocal(f.binding, { operation: 'execute', token: f.binding.token }));
  assert.throws(() => validateContext({ ...f.binding, generation: randomUUID() }, value));
  assert.throws(() => validateContext({ ...f.binding, primary: { ...f.primary, sessionId: randomUUID() } }, value));
});

test('only an owner-private single-link binding is admitted; cleanup never removes a replacement', async t => {
  const f = await fixture(t), file = join(f.primary.cwd, 'binding.json');
  f.native.publish(file, f.binding); assert.equal(readBinding(file).generation, f.binding.generation);
  chmodSync(file, 0o644); assert.throws(() => readBinding(file), /ownership/); chmodSync(file, 0o600);
  symlinkSync(file, file + '.symlink'); assert.throws(() => readBinding(file + '.symlink'));
  linkSync(file, file + '.link'); assert.throws(() => readBinding(file), /ownership/); rmSync(file + '.link');
  writeFileSync(file + '.replacement', 'unrelated replacement', { mode: 0o600 }); renameSync(file + '.replacement', file);
  f.native.close(); assert.equal(existsSync(file), true);
  const next = new NativeWorkspaceContext(f.primary), binding = await next.start();
  const owned = file + '.owned'; next.publish(owned, binding); next.close(); assert.equal(existsSync(owned), false);
});

test('stable concurrent follow, explicit pin, pause/drain, custody loss and idle publish no progress authority', async t => {
  const f = await fixture(t); await f.activity.tick();
  const selected = f.activity.children.selected;
  f.set([...f.values(), f.child({ started: Date.now() - 10, order: 2 })]); await f.activity.tick();
  assert.equal(f.activity.children.selected, selected);
  f.activity.action('next'); assert.notEqual(f.activity.children.selected, selected);
  f.activity.action('pin'); f.activity.action('pause');
  const pinned = f.activity.children.pin, frozen = [...f.activity.children.frozen];
  f.set(f.values().map(item => ({ ...item, sequence: 8, history: ['drained progress'], omitted: 5 })));
  await f.activity.tick(); assert.deepEqual(f.activity.children.frozen, frozen); assert.match(f.activity.view(), /7 subsequent records drained/);
  let metadata = validateSelection(f.binding, await f.activity.selection());
  assert.equal(metadata.child.pinned, true); assert.equal(metadata.worktree, f.primary.cwd);
  assert.doesNotMatch(JSON.stringify(metadata), /safe progress|drained progress|history|identity|attempt|task|role/);
  f.set(f.values().map(item => ({ ...item, sequence: 9, lifecycle: 'completed', exitCode: 0, state: 'disconnected' })));
  await f.activity.tick(); assert.equal(f.activity.children.pin, pinned); assert.equal((await f.activity.selection()).child.lifecycle, 'completed');
  f.refuse(); await f.activity.tick(); metadata = validateSelection(f.binding, await f.activity.selection());
  assert.equal(metadata.state, 'unavailable'); assert.equal(metadata.worktree, null); assert.match(f.activity.view(), /CUSTODY LOST/);
  f.activity.action('pin'); metadata = validateSelection(f.binding, await f.activity.selection());
  assert.equal(metadata.state, 'idle'); assert.equal(metadata.worktree, f.primary.cwd);
});

test('closing readers removes source authority, reopen observes existing work without a launch operation', async t => {
  const f = await fixture(t); await f.activity.tick(); f.activity.action('pin');
  f.activity.action('toggle');
  const closed = validateSelection(f.binding, await f.activity.selection());
  assert.equal(closed.state, 'closed'); assert.equal(closed.worktree, null);
  f.activity.action('toggle'); await f.activity.tick();
  assert.equal((await f.activity.selection()).state, 'selected');
  f.activity.close(); await assert.rejects(f.activity.selection());
});

for (const replacement of ['session', 'file', 'lifetime']) test(`${replacement} replacement invalidates admissions and rejects old metadata`, async t => {
  const f = await fixture(t); await f.activity.tick(); f.activity.action('pin');
  const old = await f.activity.selection();
  if (replacement === 'session') f.replace({ ...f.primary, sessionId: randomUUID() });
  if (replacement === 'file') { writeFileSync(f.primary.sessionFile + '.new', 'replacement'); renameSync(f.primary.sessionFile + '.new', f.primary.sessionFile); }
  if (replacement === 'lifetime') f.native.close();
  await f.activity.tick();
  assert.equal(f.activity.valid, false); assert.equal(f.activity.children.pin, null); assert.equal(f.activity.children.sources.size, 0);
  assert.match(f.activity.view(), /UNAVAILABLE/); await assert.rejects(f.activity.selection());
  assert.throws(() => validateSelection({ ...f.binding, generation: randomUUID() }, old));
});

test('pending native session file promotes once without forcing persistence', async t => {
  const cwd = realpathSync(mkdtempSync(join(tmpdir(), 'herdr-pending-'))), file = join(cwd, 'native.jsonl');
  const primary = { cwd, sessionId: randomUUID(), sessionFile: file, pid: process.pid };
  const native = new NativeWorkspaceContext(primary), binding = await native.start();
  t.after(() => { native.close(); rmSync(cwd, { recursive: true, force: true }); });
  assert.equal(native.snapshot().file, null);
  writeFileSync(file, 'native file would be persisted by Pi');
  const value = native.snapshot(); assert.ok(value.file); validateContext(binding, value);
  binding.file = value.file;
  writeFileSync(file + '.new', 'replacement'); renameSync(file + '.new', file);
  assert.throws(() => native.snapshot(), /replaced/);
});

test('old callbacks after companion close cannot restore metadata authority', async t => {
  const f = await fixture(t); let finish;
  f.activity.query = () => new Promise(resolve => { finish = resolve; });
  const pending = f.activity.tick(); f.activity.close(); finish(f.native.snapshot()); await pending;
  assert.equal(f.activity.valid, false); assert.equal(f.activity.children, undefined);
});

test('selection contract rejects cross-parent/session, progress redirect, stale and malformed metadata', async t => {
  const f = await fixture(t); await f.activity.tick(); const value = await f.activity.selection();
  for (const bad of [
    { ...value, primary: { ...value.primary, sessionId: randomUUID() } },
    { ...value, generation: randomUUID() }, { ...value, at: Date.now() - 2000 },
    { ...value, worktree: '/unverified/progress' },
    { ...value, child: { ...value.child, id: 'bad' } },
    { ...value, child: { ...value.child, state: 'guessed' } },
    { ...value, state: 'idle' }, { ...value, state: 'unavailable' },
  ]) assert.throws(() => validateSelection(f.binding, bad));
  const sanitized = validateSelection(f.binding, { ...value, progress: 'never authority', child: { ...value.child, history: ['private'] } });
  assert.equal(sanitized.progress, undefined); assert.equal(sanitized.child.history, undefined);
});

test('local context transport bounds trickle, oversized and incomplete responses', async t => {
  for (const behavior of ['trickle', 'oversized', 'close']) {
    const sockets = new Set(), timers = new Set();
    const server = net.createServer(socket => {
      sockets.add(socket); socket.on('error', () => {});
      if (behavior === 'trickle') timers.add(setInterval(() => socket.write(' '), 20));
      if (behavior === 'oversized') socket.write(' '.repeat(17000));
      if (behavior === 'close') socket.end('{}');
    });
    await new Promise(done => server.listen(0, '127.0.0.1', done));
    try {
      const start = performance.now(); await assert.rejects(queryLocal({ port: server.address().port }, 'query'));
      assert.ok(performance.now() - start < 1200);
    } finally { for (const timer of timers) clearInterval(timer); for (const socket of sockets) socket.destroy(); await new Promise(done => server.close(done)); }
  }
  const server = await serveLocal(() => ({ ok: true })); t.after(() => server.close());
  await assert.rejects(queryLocal(server, 'x'.repeat(600)));
});
