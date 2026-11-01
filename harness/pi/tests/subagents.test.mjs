import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { SubagentObserver } from '../src/observation/subagents.mjs';
import { admitSubagent } from '../src/observation/custodian.mjs';
import { NativeObservation } from '../src/observation/native-observation.mjs';

function fixture() {
  const parent = randomUUID(), id = randomUUID(), messages = [];
  let callback, unsubscribed = 0, now = 0;
  const session = { model: { provider: 'test', id: 'model' }, thinkingLevel: 'high',
    sessionManager: { getCwd: () => process.cwd() },
    subscribe(fn) { callback = fn; return () => { unsubscribed++; }; } };
  const record = { id, rootSessionId: parent, type: 'explore', status: 'running', description: 'Inspect code', session };
  const observer = new SubagentObserver(parent, value => messages.push(value), {
    registry: () => ({ getRecord: key => key === id ? record : undefined }), now: () => now });
  return { parent, id, record, observer, messages, session, event: value => callback?.(value),
    advance: () => { now += 6000; }, unsubscribed: () => unsubscribed };
}

test('live assistant activity is bounded and safe; completion removes only the reader', () => {
  const f = fixture(); f.observer.started({ id: f.id });
  f.event({ type: 'message_update', assistantMessageEvent: { type: 'text_delta', delta: '\u001b[31m' + 'a'.repeat(10000) } });
  f.observer.tick();
  assert.ok(f.messages.at(-1).text.length <= 600);
  assert.ok(!f.messages.at(-1).text.includes('\u001b'));
  f.record.status = 'completed'; f.record.result = 'Done'; f.observer.tick();
  assert.equal(f.messages.at(-1).lifecycle, 'completed'); assert.equal(f.unsubscribed(), 1);
  assert.equal(f.observer.runs.size, 0);
});

test('foreign, nested and workflow-owned records cannot join the pane', () => {
  for (const patch of [{ rootSessionId: randomUUID() }, { parentAgentId: randomUUID() }, { workflowId: randomUUID() }]) {
    const f = fixture(); Object.assign(f.record, patch); f.observer.started({ id: f.id });
    assert.equal(f.messages.length, 0); assert.equal(f.observer.runs.size, 0);
  }
});

test('late session creation is bounded; resume gets a fresh observation identity', () => {
  const f = fixture(); delete f.record.session; f.observer.started({ id: f.id });
  assert.equal(f.messages.length, 0); f.record.session = f.session; f.observer.tick();
  const first = f.messages.at(-1).id;
  f.observer.started({ id: f.id }); assert.notEqual(f.messages.at(-1).id, first);
  assert.equal(f.unsubscribed(), 1); f.observer.close(); assert.equal(f.unsubscribed(), 2);
  const g = fixture(); delete g.record.session; g.observer.started({ id: g.id }); g.advance(); g.observer.tick();
  assert.equal(g.observer.runs.size, 0);
});

test('replacement and disposal detach subscriptions without executing session commands', () => {
  const f = fixture(); f.observer.started({ id: f.id }); f.record.session = { ...f.session }; f.observer.tick();
  assert.equal(f.unsubscribed(), 1); assert.equal(f.observer.runs.size, 0);
  f.observer.close(); f.observer.started({ id: f.id }); assert.equal(f.observer.runs.size, 0);
});

test('transport failure can close the reader during its final sample without recursive emission', () => {
  const f = fixture(); let sends = 0;
  f.observer.send = () => { sends++; f.observer.close(); };
  f.observer.started({ id: f.id });
  assert.ok(sends <= 2); assert.equal(f.observer.runs.size, 0); assert.equal(f.observer.closed, true);
});

test('foreground resume without an upstream started event waits for the actual record run transition', () => {
  const f = fixture(); f.record.startedAt = 10; f.record.status = 'completed';
  f.observer.resuming(f.id); assert.equal(f.messages.length, 0);
  f.record.startedAt = 20; f.record.status = 'running'; f.observer.tick();
  assert.equal(f.messages.at(-1).lifecycle, 'running');
  f.record.status = 'completed'; f.session.messages = [{ role: 'assistant', content: [{ type: 'text', text: 'RESUMED_FINAL' }] }];
  f.observer.tick(); assert.match(f.messages.at(-1).text, /RESUMED_FINAL/);
  assert.equal(f.unsubscribed(), 1);
});

test('early assistant text is not overwritten by a tool start in the same sample', () => {
  const f = fixture(); f.observer.started({ id: f.id });
  f.event({ type: 'message_update', assistantMessageEvent: { type: 'text_delta', delta: 'EARLY_PROSE' } });
  f.event({ type: 'tool_execution_start', toolName: 'bash', args: { secret: 'never forward' } });
  f.observer.tick(); assert.match(f.messages.at(-1).text, /EARLY_PROSE/); assert.match(f.messages.at(-1).text, /Tool: bash/);
  assert.doesNotMatch(f.messages.at(-1).text, /never forward/);
});

test('early finalized text and final completion are recovered from the live SDK session', () => {
  const f = fixture();
  f.session.messages = [{ role: 'assistant', content: [{ type: 'thinking', thinking: 'private' }, { type: 'text', text: 'EARLY' }] }];
  f.observer.started({ id: f.id });
  assert.ok(f.messages.some(message => message.text.includes('EARLY')));
  f.session.messages.push({ role: 'assistant', content: [{ type: 'text', text: 'FINAL' }] });
  f.record.status = 'steered'; f.observer.tick();
  assert.match(f.messages.at(-1).text, /FINAL/);
  assert.equal(f.messages.at(-1).upstreamStatus, 'steered');
  assert.equal(f.messages.at(-1).exitCode, null);
  assert.ok(!f.messages.some(message => message.text.includes('private')));
});

test('a replaced record cannot inherit the old subscription even when it reuses the session', () => {
  const f = fixture(); f.observer.started({ id: f.id });
  f.observer.registry = () => ({ getRecord: () => ({ ...f.record }) });
  f.observer.tick(); assert.equal(f.observer.runs.size, 0); assert.equal(f.unsubscribed(), 1);
  assert.match(f.observer.inspect().notice, /record-replaced/);
});

test('rootless, missing package and delayed-session timeout have finite human diagnostics', () => {
  const f = fixture(); delete f.record.rootSessionId; f.observer.started({ id: f.id });
  assert.match(f.observer.inspect().notice, /root-association-unavailable/);
  assert.equal(f.messages.length, 0);
  f.observer.registry = () => undefined; assert.equal(f.observer.inspect().capability, 'manager-unavailable');
  const g = fixture(); delete g.record.session; g.observer.started({ id: g.id }); g.advance(); g.observer.tick();
  assert.match(g.observer.inspect().notice, /session-creation-timeout/);
});

test('final progress waits for native readiness and survives per-child backpressure', () => {
  const native = Object.create(NativeObservation.prototype), sent = [], callbacks = [];
  Object.assign(native, { ready: false, parentFile: {}, child: { connected: true,
    send(message, done) { sent.push(message); callbacks.push(done); } } });
  const id = randomUUID(); native.observeSubagent({ id, sequence: 1, lifecycle: 'running' });
  native.observeSubagent({ id, sequence: 2, lifecycle: 'completed' });
  native.ready = true; native.flushSubagents();
  assert.equal(sent.at(-1).lifecycle, 'completed'); callbacks.shift()();
  native.observeSubagent({ id, sequence: 3, lifecycle: 'running' });
  native.observeSubagent({ id, sequence: 4, lifecycle: 'completed' });
  callbacks.shift()(); assert.equal(sent.at(-1).sequence, 4);
});

test('native shutdown flushes a queued terminal frame before disconnect without waiting on an SDK agent', () => {
  const native = Object.create(NativeObservation.prototype), sent = [];
  Object.assign(native, { ready: true, parentFile: {}, child: { connected: true,
    send(message) { sent.push(message); }, disconnect() { sent.push('disconnect'); }, unref() {} } });
  const id = randomUUID(); native.observeSubagent({ id, sequence: 1, lifecycle: 'running' });
  native.observeSubagent({ id, sequence: 2, lifecycle: 'completed' }); native.close();
  assert.equal(sent.at(-2).lifecycle, 'completed'); assert.equal(sent.at(-1), 'disconnect');
});

test('custodian admits registered worktree progress and rejects identity changes or replay', () => {
  const f = fixture(); f.observer.started({ id: f.id });
  const children = new Map(), options = { parent: f.parent, generation: randomUUID(), children, order: 1,
    registrations: cwd => `worktree ${cwd}\0` };
  const message = f.messages[0]; admitSubagent(message, options);
  assert.equal(children.get(message.id).identity.worktree, realpathSync(process.cwd()));
  assert.throws(() => admitSubagent(message, options), /Invalid monitor progress/);
  assert.throws(() => admitSubagent({ ...message, parent: randomUUID() }, options), /Invalid subagent/);
  assert.throws(() => admitSubagent({ ...message, sequence: 2, identity: { ...message.identity, task: 'changed' } }, options), /identity changed/);
  assert.throws(() => admitSubagent({ ...message, id: randomUUID() }, { ...options, registrations: () => '' }), /Unregistered/);
});

for (const resume of [false, true]) test(`terminal history admits more than eight sequential ${resume ? 'same-session resumes' : 'Agent sessions'}`, () => {
  const parent = randomUUID(), generation = randomUUID(), children = new Map(), records = new Map();
  let order = 0;
  const observer = new SubagentObserver(parent, message => admitSubagent(message, { parent, generation, children, order: ++order }),
    { registry: () => ({ getRecord: id => records.get(id) }) });
  const id = randomUUID(); let previous;
  try {
    for (let i = 0; i < 12; i++) {
      const attempt = resume ? id : randomUUID();
      let record = records.get(attempt);
      if (!record) {
        record = { id: attempt, rootSessionId: parent, type: 'explore', status: 'running', startedAt: i,
          session: { sessionManager: { getCwd: () => process.cwd() }, subscribe: () => () => {}, messages: [] } };
        records.set(attempt, record); observer.started({ id: attempt });
      } else {
        observer.resuming(attempt); record.startedAt = i; record.status = 'running'; observer.tick();
      }
      record.status = 'completed'; record.session.messages = [{ role: 'assistant', content: [{ type: 'text', text: `FINAL_${i}` }] }];
      observer.tick();
      const latest = [...children.values()].find(child => child.identity.attempt === attempt && child.history.at(-1) === `FINAL_${i}`);
      assert.ok(latest, `sequential attempt ${i + 1} must be visible`);
      assert.notEqual(latest.id, previous); previous = latest.id;
      assert.equal(latest.lifecycle, 'completed'); assert.equal(observer.runs.size, 0);
      assert.ok(children.size <= 8);
    }
  } finally { observer.close(); }
});

test('history pressure preserves selected/pinned terminal entries and never retires active or monitor children', () => {
  const f = fixture(); f.observer.started({ id: f.id });
  const generation = randomUUID(), children = new Map(), base = f.messages[0];
  const options = { parent: f.parent, generation, children, order: 1, registrations: cwd => `worktree ${cwd}\0` };
  for (let i = 0; i < 8; i++) admitSubagent({ ...base, id: randomUUID() }, { ...options, order: i + 1 });
  const ids = [...children.keys()];
  const selected = children.get(ids[0]), pinned = children.get(ids[1]), disposable = children.get(ids[2]);
  for (const child of [selected, pinned, disposable]) admitSubagent({ ...base, id: child.id, sequence: 2,
    lifecycle: 'completed', upstreamStatus: 'completed' }, options);
  const retained = [selected, pinned].map(child => `${generation}:${child.id}`);
  const beforeInvalid = [...children.keys()];
  assert.throws(() => admitSubagent({ ...base, id: randomUUID(), sequence: 0 }, { ...options, retained }), /Invalid monitor progress/);
  assert.throws(() => admitSubagent({ ...base, id: randomUUID() }, { ...options, retained, registrations: () => '' }), /Unregistered/);
  assert.deepEqual([...children.keys()], beforeInvalid, 'invalid admissions cannot evict real history');
  // A terminal monitor still belongs to its separate socket lifecycle, not SDK history retirement.
  children.get(ids[3]).source = undefined; children.get(ids[3]).lifecycle = 'completed';
  admitSubagent({ ...base, id: randomUUID() }, { ...options, retained });
  assert.ok(children.has(selected.id)); assert.ok(children.has(pinned.id)); assert.ok(!children.has(disposable.id));
  const before = [...children.keys()];
  assert.throws(() => admitSubagent({ ...base, id: randomUUID() }, { ...options, retained }), /Observation capacity reached/);
  assert.deepEqual([...children.keys()], before);
  f.observer.close();
});

test('custodian refusal reaches a finite human diagnostic and detaches only the refused SDK reader', () => {
  const f = fixture(), native = Object.create(NativeObservation.prototype);
  Object.assign(native, { refused: message => f.observer.refused(message) });
  f.observer.started({ id: f.id }); const displayId = f.messages[0].id;
  native.receive({ type: 'subagent-refused', id: displayId, reason: 'observation-capacity-eight' });
  assert.equal(f.unsubscribed(), 1); assert.equal(f.observer.runs.size, 0);
  assert.match(f.observer.inspect().notice, /observation-capacity-eight/);
  assert.equal(f.record.status, 'running', 'refusal never cancels execution');
  // A fast terminal frame can finish sampling before the daemon replies: still report its refusal.
  native.receive({ type: 'subagent-refused', id: displayId, reason: 'observation-capacity-eight' });
  assert.match(f.observer.inspect().notice, /observation-capacity-eight/);
  f.observer.close();
});

test('native readiness queue overflow reports refusal without cancelling or displacing queued work', () => {
  const native = Object.create(NativeObservation.prototype), refused = [];
  Object.assign(native, { ready: false, refused: message => refused.push(message) });
  for (let i = 0; i < 8; i++) native.observeSubagent({ id: randomUUID() });
  const before = [...native.queuedSubagents.keys()], id = randomUUID(); native.observeSubagent({ id });
  assert.deepEqual([...native.queuedSubagents.keys()], before);
  assert.deepEqual(refused, [{ id, reason: 'observation-capacity-eight' }]);
  native.close();
});

test('eight concurrent SDK observations still refuse a ninth without evicting live work', () => {
  const parent = randomUUID(), records = new Map();
  const observer = new SubagentObserver(parent, () => {}, { registry: () => ({ getRecord: id => records.get(id) }) });
  try {
    for (let i = 0; i < 9; i++) {
      const id = randomUUID(); records.set(id, { rootSessionId: parent, status: 'running',
        session: { sessionManager: { getCwd: () => process.cwd() }, subscribe: () => () => {} } }); observer.started({ id });
    }
    assert.equal(observer.runs.size, 8); assert.ok([...observer.runs.values()].every(run => run.session));
    assert.match(observer.inspect().notice, /observation-capacity-eight/);
  } finally { observer.close(); }
});

test('native transport bounds outstanding messages per child without starving concurrent children', () => {
  const callbacks = [], sent = [];
  const native = Object.create(NativeObservation.prototype);
  Object.assign(native, { ready: true, parentFile: {}, child: { connected: true,
    send(message, done) { sent.push(message); callbacks.push(done); } } });
  const first = { id: randomUUID() }, second = { id: randomUUID() };
  native.observeSubagent(first); native.observeSubagent(first); native.observeSubagent(second);
  assert.equal(sent.length, 2); callbacks[0](); native.observeSubagent(first); assert.equal(sent.length, 3);
  native.closed = true; native.observeSubagent({ id: randomUUID() }); assert.equal(sent.length, 3);
});
