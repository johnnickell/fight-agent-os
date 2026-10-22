import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { ChildObservation } from '../src/workspace/child-observation.mjs';
import { ConcurrentChildren } from '../src/workspace/concurrent-children.mjs';
import { Workspace, width } from '../src/workspace/presentation.mjs';

const identity = index => ({ task: 'TASK-00502', role: index ? 'review' : 'work', provider: 'configured', model: 'model', thinking: 'high', worktree: `/reported-${index}`, attempt: `/attempt-${index}` });
function fixture(count = 3) {
  let time = 1000; const sockets = [], attempts = new Map();
  const children = new ConcurrentChildren(Array.from({ length: count }, (_, i) => `/explicit/${i}.sock`), () => {}, {
    now: () => time,
    reader: (path, changed) => new ChildObservation([path], changed, {
      now: () => time, inspect: () => ({ isSocket: () => true, uid: process.getuid(), mode: 0o600 }),
      connect: () => {
        const socket = new EventEmitter(); socket.setEncoding = () => {}; socket.destroyed = 0; socket.destroy = () => socket.destroyed++;
        sockets.push({ path, socket }); return socket;
      },
    }),
  });
  const send = (index, lifecycle = 'running', options = {}) => {
    const connection = sockets.filter(value => value.path === `/explicit/${index}.sock`).at(-1);
    const sequence = options.sequence ?? (attempts.get(connection.socket) ?? -1) + 1;
    attempts.set(connection.socket, sequence);
    connection.socket.emit('data', JSON.stringify({ version: 'fight-child-observation-v1', kind: sequence ? 'progress' : 'snapshot', sequence,
      identity: identity(index), lifecycle, heartbeatSeconds: 1, exitCode: lifecycle === 'completed' ? 0 : lifecycle === 'failed' ? 1 : null,
      text: `child-${index}-${sequence}`, ...options }) + '\n');
  };
  return { children, sockets, send, advance: amount => { time += amount; children.tick(); } };
}
test('explicit endpoint admission waits for actual session identity; no directory discovery or child launch', () => {
  const f = fixture(); f.children.start(); assert.equal(f.sockets.length, 0);
  f.children.start('parent-A'); assert.equal(f.sockets.length, 3);
  f.children.start('parent-A'); assert.equal(f.sockets.length, 3);
  assert.match(f.children.view(), /parent membership unverified/);
  assert.match(f.children.view(), /launch order unverified/);
  assert.match(f.children.view(), /cannot redirect test logs/); f.children.close();
});
test('one active source is automatic; concurrent output and new siblings never steal active selection', () => {
  const f = fixture(); f.children.start('A'); f.send(0); assert.equal(f.children.selected, 0);
  f.send(1); f.send(2);
  for (let n = 0; n < 100; n++) { f.send(2); f.send(1); }
  assert.equal(f.children.selected, 0);
  f.children.action('next'); assert.equal(f.children.selected, 1);
  f.send(0); f.send(2); assert.equal(f.children.selected, 1); f.children.close();
});
test('completion picks newest host-admitted active source; no active sources yields honest idle', () => {
  const f = fixture(); f.children.start('A'); f.send(0); f.send(1); f.send(2);
  f.send(0, 'completed'); assert.equal(f.children.selected, 2);
  f.send(2, 'failed'); assert.equal(f.children.selected, 1);
  f.send(1, 'completed'); assert.equal(f.children.selected, null);
  assert.match(f.children.view(), /IDLE/); assert.match(f.children.view(), /No source selected/); f.children.close();
});
test('pin survives completion and disconnection; unpin recomputes without changing child execution', () => {
  const f = fixture(); f.children.start('A'); f.send(0); f.children.action('pin'); f.send(1); f.send(2);
  f.send(0, 'completed'); assert.equal(f.children.selected, 0); assert.equal(f.children.pin, 0);
  f.sockets[0].socket.emit('close'); assert.equal(f.children.selected, 0);
  assert.match(f.children.view(), /PINNED/); assert.match(f.children.view(), /completed/);
  f.children.action('pin'); assert.equal(f.children.pin, null); assert.equal(f.children.selected, 2);
  assert.equal(f.sockets[1].socket.destroyed, 0); f.children.close();
});
test('an explicit choice changes a pin, whereas sibling events cannot', () => {
  const f = fixture(); f.children.start('A'); f.send(0); f.send(1); f.children.action('pin');
  f.children.action('next'); assert.equal(f.children.selected, 1); assert.equal(f.children.pin, 1);
  f.send(2); assert.equal(f.children.selected, 1); f.children.action('previous'); assert.equal(f.children.pin, 0);
  f.children.close();
});
test('heartbeat expiry/disconnection is not completion; pinned source remains visible and unpin chooses live sibling', () => {
  const f = fixture(); f.children.start('A'); f.send(0); f.send(1); f.children.action('pin');
  f.advance(6000); assert.equal(f.children.selected, 0); assert.match(f.children.view(), /heartbeat overdue/);
  f.send(1); f.children.action('pin'); assert.equal(f.children.selected, 1);
  f.sockets[1].socket.emit('close'); assert.equal(f.children.selected, null); f.children.close();
});
test('two independent parents never inherit selection/pins/readers or changed-session endpoint admissions', () => {
  const a = fixture(2), b = fixture(2); a.children.start('parent-A'); b.children.start('parent-B');
  a.send(0); a.children.action('pin'); b.send(1);
  assert.equal(a.children.selected, 0); assert.equal(b.children.selected, 1); assert.equal(b.children.pin, null);
  a.children.sessionChanged('parent-C'); assert.equal(a.children.pin, null); assert.equal(a.children.selected, null);
  assert.equal(a.children.readers.length, 0); assert.ok(a.sockets.every(({ socket }) => socket.destroyed === 1));
  a.send(0); a.children.action('reconnect'); a.children.start('parent-C');
  assert.equal(a.sockets.length, 2); assert.equal(a.children.selected, null);
  assert.doesNotMatch(a.children.view(), /child-0|attempt-0/); assert.equal(b.children.selected, 1);
  a.children.close(); b.children.close();
});
test('same-session status is stable; lost identity clears pin and readers rather than trusting cwd', () => {
  const f = fixture(1); f.children.start('A'); f.send(0); f.children.action('pin'); f.children.sessionChanged('A');
  assert.equal(f.children.pin, 0); assert.equal(f.sockets[0].socket.destroyed, 0);
  f.children.sessionChanged(null); assert.equal(f.children.pin, null); assert.equal(f.sockets[0].socket.destroyed, 1);
  assert.match(f.children.view(), /Session changed\/unavailable/); f.children.close();
});
test('late replaced endpoint events cannot reselect or replace an attempt; reopen retains v1 identity pin', () => {
  const f = fixture(2); f.children.start('A'); f.send(0); f.send(1); const old = f.sockets[0].socket;
  f.children.action('reconnect');
  f.send(0, 'running', { identity: { ...identity(0), attempt: '/REPLACEMENT' } });
  assert.equal(f.children.readers[0].state, 'disconnected');
  old.emit('data', '{}\n'); old.emit('close'); old.emit('error', new Error('private'));
  f.send(1); assert.equal(f.children.selected, 1); assert.doesNotMatch(f.children.view(), /REPLACEMENT|private/); f.children.close();
});
test('pause is source-local and bounded; closing all readers cannot cancel any monitor and reopening is explicit', () => {
  const f = fixture(2); f.children.start('A'); f.send(0); f.send(1); f.children.action('pause');
  for (let n = 0; n < 200; n++) { f.send(0); f.send(1); }
  assert.equal(f.children.readers[0].history.length, 1); assert.equal(f.children.readers[0].omitted, 200);
  assert.equal(f.children.readers[1].history.length, 100);
  f.children.action('toggle'); assert.ok(f.sockets.every(({ socket }) => socket.destroyed === 1));
  assert.match(f.children.view(), /READERS CLOSED/); assert.equal(f.sockets.length, 2);
  f.children.action('toggle'); assert.equal(f.sockets.length, 4); f.children.close(); f.children.close();
  assert.ok(f.sockets.every(({ socket }) => socket.destroyed === 1));
});
test('wide/narrow presentation and keyboard pinning leave primary draft, directory, model transcript untouched', () => {
  const f = fixture(2); f.children.start('A'); f.send(0); f.send(1);
  const model = new Workspace('/primary'); model.childObservation = f.children; model.ready = true; model.insert('untouched'); model.focus = 1;
  assert.deepEqual(model.key('f'), { type: 'observation', action: 'pin' }); f.children.action('pin');
  for (const [columns, rows] of [[160, 40], [80, 24]]) {
    const lines = model.render(columns, rows); assert.ok(lines.every(line => width(line) === columns));
    assert.match(lines.join('\n'), /PINNED/); assert.match(lines.join('\n'), /parent membership unverified/);
  }
  for (const input of ['launch', '!', '/skill:work']) assert.equal(model.key(input), undefined);
  assert.equal(model.key('', { name: 'return' }), undefined); assert.equal(model.cwd, '/primary');
  assert.equal(model.draft, 'untouched'); assert.deepEqual(model.transcript, []); f.children.close();
});
