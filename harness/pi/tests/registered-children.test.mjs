import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, realpathSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { RegisteredChildren } from '../src/workspace/registered-children.mjs';
import { directory, sessionFileIdentity } from '../src/observation/custody.mjs';
import { recycledInode } from './helpers/session-file.mjs';

function fixture(t, preferences = false) {
  const cwd = realpathSync(mkdtempSync(join(tmpdir(), 'registered-'))), other = join(cwd, 'other'); mkdirSync(other);
  const file = join(cwd, 'session.jsonl'); writeFileSync(file, 'genuine-context-placeholder-for-unit-boundary');
  const generation = randomUUID(), parent = randomUUID(); let at = 100000;
  const identity = { task: 'TASK-00502', role: 'work', provider: 'openai-codex', model: 'configured-only', thinking: 'high', worktree: other, attempt: '/owned/attempt' };
  const child = (overrides = {}) => ({ id: randomUUID(), parent, generation, started: 1000, order: 1, identity, state: 'connected', lifecycle: 'running', exitCode: null,
    heartbeatSeconds: 2, sequence: 1, receivedAt: at, history: ['safe progress'], omitted: 0, ...overrides });
  let values = [child()]; let refusal = false;
  const witness = () => ({ parent, parentFile: sessionFileIdentity(file), generation, primary: cwd, at, children: values });
  const options = { preferences, now: () => at, discover: () => [{ path: '/candidate', descriptor: {} }], verify: async (_cwd, session) => { if (refusal || session !== parent) throw new Error('Cross-session/stale witness refused'); return witness(); } };
  const changes = []; const observe = new RegisteredChildren(cwd, () => changes.push(observe.verifiedWorktree), options);
  t.after(() => { observe.close(); rmSync(cwd, { force: true, recursive: true }); });
  return { cwd, other, file, parent, generation, child, options, observe, changes, values: () => values, set: next => { values = next; }, refuse: value => { refusal = value; }, advance: amount => { at += amount; } };
}

test('only actual-session matching launch custody admits a canonical worktree; legacy payloads cannot create membership', async t => {
  const f = fixture(t); await f.observe.start(f.parent, f.file);
  assert.equal(f.observe.sources.size, 1); assert.equal(f.observe.verifiedWorktree, f.other);
  assert.match(f.observe.view(), /AUTOMATIC/); assert.doesNotMatch(f.observe.view(), /parent membership unverified/);
  await f.observe.sessionChanged(randomUUID(), f.file); await f.observe.refresh();
  assert.equal(f.observe.sources.size, 0); assert.equal(f.observe.pin, null); assert.equal(f.observe.verifiedWorktree, null);
});
test('loss at the bounded monitor pipe remains visibly distinct from retained-history eviction', async t => {
  const f = fixture(t); f.set([{ ...f.values()[0], sequence: 5, gaps: 3, omitted: 0 }]);
  await f.observe.start(f.parent, f.file); assert.match(f.observe.view(), /gaps 3/); assert.match(f.observe.view(), /omitted 0/);
});

test('a custody-launched starting child is selected before its first progress frame, then a failed spawn becomes idle', async t => {
  const f = fixture(t); f.set([{ ...f.values()[0], state: 'starting', lifecycle: 'starting', sequence: 0, history: [] }]);
  await f.observe.start(f.parent, f.file); assert.notEqual(f.observe.selected, null); assert.equal(f.observe.verifiedWorktree, f.other);
  f.set([{ ...f.values()[0], state: 'disconnected' }]); await f.observe.refresh(); assert.equal(f.observe.selected, null);
});

test('trusted start order selects newest initially, concurrent output/new siblings never steal an active choice', async t => {
  const f = fixture(t), older = f.values()[0], newer = f.child({ started: 2000, order: 2 }); f.set([newer, older]);
  await f.observe.start(f.parent, f.file); const choice = f.observe.selected;
  assert.equal(f.observe.sources.get(choice).id, newer.id);
  f.set([older, { ...newer, sequence: 2, history: ['new text'] }, f.child({ started: 3000, order: 3 })]);
  await f.observe.refresh(); assert.equal(f.observe.selected, choice);
  f.observe.action('next'); assert.notEqual(f.observe.selected, choice);
  const explicit = f.observe.selected; await f.observe.refresh(); assert.equal(f.observe.selected, explicit);
});
test('completion selects an active sibling by trusted start; same start uses stable child attempt ID', async t => {
  const f = fixture(t), first = f.child({ started: 2000 }), second = f.child({ started: 2000 });
  f.set([second, first]); await f.observe.start(f.parent, f.file);
  const expected = [first.id, second.id].sort()[0]; assert.equal(f.observe.sources.get(f.observe.selected).id, expected);
  const completed = f.observe.selected;
  f.set([first, second].map(item => item.id === expected ? { ...item, sequence: 2, lifecycle: 'completed', exitCode: 0 } : item));
  await f.observe.refresh(); assert.notEqual(f.observe.selected, completed);
});
test('pin survives completion/disconnection with verified context; unpin returns honest idle and primary context', async t => {
  const f = fixture(t); await f.observe.start(f.parent, f.file); f.observe.action('pin'); const pinned = f.observe.pin;
  f.set([{ ...f.values()[0], sequence: 2, state: 'disconnected', lifecycle: 'completed', exitCode: 0 }]);
  await f.observe.refresh(); assert.equal(f.observe.selected, pinned); assert.equal(f.observe.verifiedWorktree, f.other);
  assert.match(f.observe.view(), /PINNED/); assert.match(f.observe.view(), /exit 0; not acceptance/);
  f.observe.action('pin'); assert.equal(f.observe.selected, null); assert.equal(f.observe.verifiedWorktree, null); assert.match(f.observe.view(), /IDLE/);
});
test('SDK completion remains readable/selectable without inventing a process exit', async t => {
  const f = fixture(t), first = f.child({ source: 'pi-subagents', upstreamStatus: 'running' });
  f.set([first]); await f.observe.start(f.parent, f.file);
  f.set([{ ...first, sequence: 2, lifecycle: 'completed', upstreamStatus: 'steered', history: ['FINAL'] }]);
  await f.observe.refresh();
  assert.notEqual(f.observe.selected, null); assert.match(f.observe.view(), /upstream steered/);
  assert.match(f.observe.view(), /FINAL/); assert.doesNotMatch(f.observe.view(), /exit 0/);
  f.set([{ ...first, sequence: 3, lifecycle: 'completed', upstreamStatus: 'completed', exitCode: 0 }]);
  await f.observe.refresh(); assert.equal(f.observe.sources.size, 0);
});

test('heartbeat expiry, lost/replaced custody and regressed sequences cannot retain log authority or infer completion', async t => {
  const f = fixture(t); await f.observe.start(f.parent, f.file); f.advance(7000); f.observe.recompute(); assert.equal(f.observe.selected, null);
  f.set([{ ...f.values()[0], sequence: 2, receivedAt: 107000 }]); await f.observe.refresh(); f.observe.action('pin');
  f.refuse(true); await f.observe.refresh(); assert.equal(f.observe.verifiedWorktree, null); assert.match(f.observe.view(), /CUSTODY LOST/);
  assert.match(f.observe.view(), /running/); assert.doesNotMatch(f.observe.view(), /completed/);
  f.refuse(false); f.set([{ ...f.values()[0], sequence: 1 }]); await f.observe.refresh(); assert.equal(f.observe.verifiedWorktree, null);
});
test('attempt identity replacement is refused; old progress cannot be attributed to a replacement generation', async t => {
  const f = fixture(t); await f.observe.start(f.parent, f.file); f.observe.action('pin');
  f.set([{ ...f.values()[0], identity: { ...f.values()[0].identity, worktree: f.cwd }, sequence: 2, history: ['replacement-injection'] }]);
  await f.observe.refresh(); assert.equal(f.observe.verifiedWorktree, null); assert.doesNotMatch(f.observe.view(), /replacement-injection/);
});
test('in-flight old-session challenges cannot repopulate a changed session or a closed pane', async t => {
  const f = fixture(t); let complete;
  f.observe.verify = () => new Promise(resolve => { complete = resolve; });
  const starting = f.observe.start(f.parent, f.file); await Promise.resolve();
  const old = { parent: f.parent, parentFile: sessionFileIdentity(f.file), generation: f.generation, at: 100000, children: f.values() };
  f.observe.verify = async () => { throw new Error('New session has no custody'); };
  const replacement = f.observe.sessionChanged(randomUUID(), f.file); complete(old); await starting; await replacement;
  assert.equal(f.observe.sources.size, 0); assert.equal(f.observe.selected, null);
  f.observe.close(); assert.equal(f.observe.verifiedWorktree, null);
});
test('session replacement cancels a pending refresh and discovers the new session without waiting for old replies', async t => {
  const f = fixture(t); await f.observe.start(f.parent, f.file); f.observe.action('pin');
  const oldWitness = { parent: f.parent, parentFile: sessionFileIdentity(f.file), generation: f.generation, at: 100000, children: f.values() };
  let oldSignal, completeOld;
  f.observe.verify = (_cwd, _session, _path, _descriptor, options) => {
    oldSignal = options?.signal; return new Promise(resolve => { completeOld = resolve; });
  };
  const oldRefresh = f.observe.refresh(); await Promise.resolve();
  const newParent = randomUUID(), newChild = f.child({ parent: newParent });
  let completeNew, newSignal, newCalls = 0;
  f.observe.verify = (_cwd, session, _path, _descriptor, options) => {
    newCalls++; newSignal = options?.signal;
    assert.equal(session, newParent);
    return new Promise(resolve => { completeNew = () => resolve({ ...oldWitness, parent: newParent, children: [newChild] }); });
  };
  const replacement = f.observe.sessionChanged(newParent, f.file); await Promise.resolve();
  assert.equal(oldSignal?.aborted, true, 'old transport cancelled synchronously');
  assert.equal(f.observe.verifiedWorktree, null); assert.equal(f.observe.pin, null);
  assert.equal(newCalls, 1, 'new discovery is not serialized behind old work');
  const currentPending = f.observe.pending;
  completeOld(oldWitness); await oldRefresh;
  assert.equal(f.observe.pending, currentPending, 'late finalizer cannot release the new refresh lock');
  assert.equal(newSignal.aborted, false); assert.equal(f.observe.sources.size, 0);
  completeNew(); await replacement;
  assert.equal(f.observe.sources.get(f.observe.selected).id, newChild.id);
});
for (const operation of ['pane-close', 'close']) test(`${operation} cancels in-flight discovery and immediately removes pinned log authority`, async t => {
  const f = fixture(t); await f.observe.start(f.parent, f.file); f.observe.action('pin');
  let signal, complete;
  f.observe.verify = (_cwd, _session, _path, _descriptor, options) => {
    signal = options?.signal; return new Promise(resolve => { complete = resolve; });
  };
  const waiting = f.observe.refresh(); await Promise.resolve();
  if (operation === 'close') f.observe.close(); else f.observe.action('toggle');
  assert.equal(signal?.aborted, true); assert.equal(f.observe.pending, null);
  assert.equal(f.observe.verifiedWorktree, null);
  complete({ parent: f.parent, parentFile: sessionFileIdentity(f.file), generation: f.generation, at: 100000, children: f.values() });
  await waiting; assert.equal(f.observe.verifiedWorktree, null);
});

test('paused output is scoped to one selected child and never follows an explicit source switch', async t => {
  const f = fixture(t); f.set([...f.values(), f.child({ started: 2000, history: ['second source'] })]);
  await f.observe.start(f.parent, f.file); f.observe.action('pause'); assert.deepEqual(f.observe.frozen, ['second source']);
  f.observe.action('next'); assert.equal(f.observe.paused, false); assert.equal(f.observe.frozen, null);
});
test('same-session resume restores a pin only after fresh admission; forged preference cannot grant membership', async t => {
  const f = fixture(t, true); await f.observe.start(f.parent, f.file); f.observe.action('pin'); const pin = f.observe.pin; f.observe.close();
  const resumed = new RegisteredChildren(f.cwd, () => {}, f.options); t.after(() => resumed.close());
  await resumed.start(f.parent, f.file); assert.equal(resumed.pin, pin); assert.equal(resumed.verifiedWorktree, f.other);
  resumed.close();
  writeFileSync(join(directory(f.cwd, f.parent), 'preference.json'), JSON.stringify({ selected: 'forged', pin: 'forged' }));
  const forged = new RegisteredChildren(f.cwd, () => {}, f.options); t.after(() => forged.close());
  await forged.start(f.parent, f.file); assert.equal(forged.pin, null); assert.notEqual(forged.selected, 'forged'); forged.close();
});
for (const recycled of [false, true]) test(`same-ID session replacement refuses the old launch lifetime (${recycled ? 'controlled recycled inode' : 'native stat'})`, async t => {
  const f = fixture(t); if (recycled) recycledInode(t); const original = sessionFileIdentity(f.file); rmSync(f.file); writeFileSync(f.file, 'replacement');
  f.observe.verify = async () => ({ parent: f.parent, parentFile: original, generation: f.generation, at: 100000, children: f.values() });
  await f.observe.start(f.parent, f.file); assert.equal(f.observe.sources.size, 0); assert.equal(f.observe.verifiedWorktree, null);
});
for (const recycled of [false, true]) test(`same-path replacement clears a pin and rejects late admission (${recycled ? 'controlled recycled inode' : 'native stat'})`, async t => {
  const f = fixture(t); if (recycled) recycledInode(t); await f.observe.start(f.parent, f.file); f.observe.action('pin');
  const old = { parent: f.parent, parentFile: sessionFileIdentity(f.file), generation: f.generation, at: 100000, children: f.values() };
  let complete;
  f.observe.verify = () => new Promise(resolve => { complete = resolve; });
  const pending = f.observe.refresh(); await Promise.resolve();
  writeFileSync(f.file + '.new', 'new native file'); renameSync(f.file + '.new', f.file);
  f.observe.tick(); complete(old); await pending;
  assert.equal(f.observe.verifiedWorktree, null); assert.equal(f.observe.pin, null);
  assert.equal(f.observe.sources.size, 0); assert.match(f.observe.view(), /replaced|unavailable/i);
});
test('missing session files and legacy/null witnesses cannot admit children', async t => {
  const f = fixture(t);
  const actual = sessionFileIdentity(f.file);
  for (const parentFile of [null, { path: actual.path, dev: Number(actual.dev), ino: Number(actual.ino) }]) {
    f.observe.verify = async () => ({ parent: f.parent, parentFile, generation: f.generation, at: 100000, children: f.values() });
    await f.observe.start(f.parent, f.file); await f.observe.refresh();
    assert.equal(f.observe.sources.size, 0); assert.equal(f.observe.verifiedWorktree, null);
  }
  f.observe.close(); rmSync(f.file);
  const pending = new RegisteredChildren(f.cwd, () => {}, { ...f.options, verify: async () => ({ parent: f.parent, parentFile: null, generation: f.generation, at: 100000, children: f.values() }) });
  t.after(() => pending.close());
  await pending.start(f.parent, f.file);
  assert.equal(pending.sources.size, 0); assert.equal(pending.verifiedWorktree, null);
});

test('an automatic selection change clears a frozen display and exposes stale heartbeat truthfully', async t => {
  const f = fixture(t), first = f.values()[0]; await f.observe.start(f.parent, f.file);
  f.observe.action('pause');
  f.set([{ ...first, lifecycle: 'completed', exitCode: 0 }, f.child({ started: 2000, history: ['sibling'] })]);
  await f.observe.refresh(); assert.equal(f.observe.paused, false); assert.equal(f.observe.frozen, null);
  f.observe.action('pin'); f.advance(7000); f.observe.recompute();
  assert.match(f.observe.view(), /unknown|expired/i);
});
test('same-custodian start ties use trusted admission order before the stable attempt ID', async t => {
  const f = fixture(t), ids = [randomUUID(), randomUUID()].sort();
  const first = f.child({ id: ids[0], started: 2000, order: 1 }), second = f.child({ id: ids[1], started: 2000, order: 2 });
  f.set([first, second]); await f.observe.start(f.parent, f.file);
  assert.equal(f.observe.sources.get(f.observe.selected).id, second.id);
});

test('discovery and retention are bounded to eight sources, with an explicit overflow notice', async t => {
  const f = fixture(t); const values = Array.from({ length: 8 }, (_, i) => f.child({ started: 1000 + i }));
  f.set(values); await f.observe.start(f.parent, f.file); assert.equal(f.observe.sources.size, 8);
  f.set([...values, f.child()]); await f.observe.refresh(); assert.equal(f.observe.sources.size, 0); assert.match(f.observe.view(), /refused/);
});
