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
