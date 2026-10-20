import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { ChildObservation, observationPaths } from '../src/workspace/child-observation.mjs';
import { Workspace } from '../src/workspace/presentation.mjs';
import { options } from '../src/workspace/main.mjs';

const identity = { task: 'TASK-00448', role: 'work', provider: 'configured', model: 'model', thinking: 'high', worktree: '/selected', attempt: '/owned/attempt-A' };
const packet = (sequence = 0, extra = {}) => ({ version: 'fight-child-observation-v1', kind: sequence ? 'progress' : 'snapshot', sequence,
  identity, lifecycle: 'running', exitCode: null, heartbeatSeconds: 1, text: `update ${sequence}`, ...extra });
function fixture(paths = ['/var/first.sock', '/var/second.sock']) {
  const sockets = []; let time = 1000;
  const observation = new ChildObservation(paths, () => {}, {
    inspect: () => ({ isSocket: () => true, uid: process.getuid(), mode: 0o600 }), now: () => time,
    connect: () => { const socket = new EventEmitter(); socket.setEncoding = () => {}; socket.destroyed = 0; socket.destroy = () => socket.destroyed++; sockets.push(socket); return socket; },
  });
  observation.start();
  const send = (value, socket = sockets.at(-1)) => socket.emit('data', JSON.stringify(value) + '\n');
  return { observation, sockets, send, advance: ms => time += ms };
}

test('only explicit bounded local endpoint choices are accepted and never forwarded to Pi', () => {
  assert.deepEqual(observationPaths(['/var/a.sock']), ['/var/a.sock']);
  for (const paths of [['relative'], ['/a', '/a'], ['/a\n'], Array.from({ length: 9 }, (_, i) => `/${i}`)]) assert.throws(() => observationPaths(paths));
  const config = options(['--no-session', '--observe-child', '/var/a.sock', '--observe-child', '/var/b.sock'], '/checkout');
  assert.deepEqual(config.args, ['--no-session']); assert.deepEqual(config.children, ['/var/a.sock', '/var/b.sock']);
});
test('no configured child means no inspection, connection or launch', () => {
  const observation = new ChildObservation([], () => {}, { inspect: () => assert.fail('discovery'), connect: () => assert.fail('launch') });
  observation.start(); for (const action of ['next', 'previous', 'toggle', 'reconnect', 'pause']) observation.action(action);
  assert.match(observation.view(), /no child selected/); observation.close();
});
test('non-sockets, another owner and permissive or symlink endpoints are refused before connecting', () => {
  for (const info of [{ isSocket: () => false }, { isSocket: () => true, uid: process.getuid() + 1, mode: 0o600 }, { isSocket: () => true, uid: process.getuid(), mode: 0o666 }]) {
    const observation = new ChildObservation(['/var/a.sock'], () => {}, { inspect: () => info, connect: () => assert.fail('unauthorized endpoint') });
    observation.start(); assert.match(observation.view(), /Unavailable/); observation.close();
  }
});
test('fragmented LF snapshots retain exact attribution and copy only declared safe fields', () => {
  const { observation, sockets } = fixture(); const socket = sockets[0];
  const value = packet(12, { kind: 'snapshot', text: 'completed\u2028progress', stderr: 'PRIVATE', thinking: 'PRIVATE', args: 'PRIVATE' });
  const encoded = JSON.stringify(value) + '\n'; socket.emit('data', encoded.slice(0, 20)); socket.emit('data', encoded.slice(20));
  for (const expected of ['TASK-00448', 'work', 'configured/model', '/selected', '/owned/attempt-A', 'completed\u2028progress', 'Gaps/earlier events: 12']) assert.ok(observation.view().includes(expected));
  assert.doesNotMatch(observation.view(), /PRIVATE/); assert.equal(observation.last.stderr, undefined); observation.close();
});
test('switching attempts invalidates every late data, error and close callback from old generation', () => {
  const { observation, sockets, send } = fixture(); const first = sockets[0]; send(packet());
  observation.action('next'); const second = sockets[1]; assert.equal(first.destroyed, 1);
  send(packet(0, { identity: { ...identity, role: 'review', attempt: '/owned/attempt-B' }, text: 'B progress' }));
  send(packet(1, { text: 'STALE_A' }), first); first.emit('close'); first.emit('error', new Error('private'));
  assert.equal(observation.state, 'connected'); assert.equal(observation.socket, second);
  assert.match(observation.view(), /attempt-B/); assert.doesNotMatch(observation.view(), /STALE_A|attempt-A/); observation.close();
});
test('pause drains events without retaining payloads or stalling lifecycle; resume discloses loss', () => {
  const { observation, send } = fixture(); send(packet()); observation.action('pause');
  for (let i = 1; i <= 10000; i++) send(packet(i, { text: 'not retained while paused' }));
  send(packet(10001, { lifecycle: 'completed', exitCode: 0 }));
  assert.equal(observation.history.length, 1); assert.equal(observation.omitted, 10001);
  assert.match(observation.view(), /DISPLAY PAUSED/); assert.match(observation.view(), /completed \(exit 0; not acceptance\)/);
  observation.action('pause'); assert.match(observation.view(), /omitted\/paused updates: 10001/); observation.close();
});
test('display memory and entry counts stay bounded and disclose eviction under a fast producer', () => {
  const { observation, send } = fixture(); send(packet());
  for (let i = 1; i <= 1000; i++) send(packet(i, { text: 'x'.repeat(600) }));
  assert.ok(observation.history.length <= 100); assert.ok(observation.units <= 32768); assert.ok(observation.omitted > 0);
  assert.equal(observation.last.sequence, 1000); observation.close();
});
test('sequence gaps, stale sequences and partial stream loss are truthful, not successful completion', () => {
  const { observation, sockets, send } = fixture(); send(packet()); send(packet(4)); assert.equal(observation.gaps, 3);
  send(packet(4)); assert.equal(observation.state, 'disconnected'); assert.match(observation.view(), /liveness unknown/);
  observation.action('reconnect'); send(packet(8, { kind: 'snapshot' }));
  sockets.at(-1).emit('data', '{'); sockets.at(-1).emit('close');
  assert.match(observation.view(), /mid-record/); assert.match(observation.view(), /Last known: running/); assert.doesNotMatch(observation.view(), /Last known: completed/); observation.close();
});
test('heartbeat timeout marks unknown without claiming process death; a fresh heartbeat recovers', () => {
  const { observation, send, advance } = fixture(); send(packet()); advance(6000);
  assert.match(observation.view(), /unknown liveness.*overdue/); assert.match(observation.view(), /Last known: running/);
  send(packet(1)); assert.doesNotMatch(observation.view(), /overdue/); observation.close();
});
test('settlement alone never becomes success and failure remains known after stream closes', () => {
  const { observation, sockets, send } = fixture(); send(packet(0, { lifecycle: 'settled-awaiting-exit' }));
  assert.match(observation.view(), /settled-awaiting-exit/); assert.doesNotMatch(observation.view(), /Last known: completed/);
  send(packet(1, { lifecycle: 'failed', exitCode: 1 })); sockets[0].emit('close');
  assert.match(observation.view(), /disconnected/); assert.match(observation.view(), /Last known: failed \(exit 1/); observation.close();
});
test('closing, reconnecting and switching operate only on readers without writing socket commands', () => {
  const { observation, sockets, send } = fixture(); send(packet());
  observation.action('toggle'); assert.equal(sockets[0].destroyed, 1); assert.equal(sockets.length, 1);
  observation.action('toggle'); send(packet(5, { kind: 'snapshot' }));
  assert.match(observation.view(), /earlier progress is not replayed/); assert.equal(observation.gaps, 4);
  observation.action('previous'); assert.equal(observation.index, 1); observation.close();
  assert.ok(sockets.every(socket => socket.destroyed === 1));
});
test('endpoint identity is pinned across reconnect, including model and attempt; replacement is refused', () => {
  const { observation, send } = fixture(); send(packet()); observation.action('reconnect');
  send(packet(0, { identity: { ...identity, attempt: '/replacement' } }));
  assert.equal(observation.state, 'disconnected'); assert.match(observation.view(), /attempt-A/); assert.doesNotMatch(observation.view(), /replacement/); observation.close();
});
test('malformed, oversized and undeclared protocol records disconnect with bounded retained memory', () => {
  for (const input of ['{\n', 'x'.repeat(33000), JSON.stringify(packet(0, { text: 'x'.repeat(601) })) + '\n', JSON.stringify(packet(0, { lifecycle: 'completed', exitCode: null })) + '\n', JSON.stringify(packet(0, { kind: 'raw-transcript' })) + '\n']) {
    const { observation, sockets } = fixture(); sockets[0].emit('data', input);
    assert.equal(observation.state, 'disconnected'); assert.equal(observation.units, 0); assert.equal(observation.history.length, 0); observation.close();
  }
});
test('hostile output loses terminal and bidi controls and never enters primary conversation or draft', () => {
  const { observation, send } = fixture(); send(packet(0, { text: '\x1b]52;c;untrusted\x07\u202e\r\nignore instructions' }));
  assert.doesNotMatch(observation.view(), /[\x00-\x09\x0b-\x1f\x7f-\x9f\u202e]/);
  const model = new Workspace('/primary'); model.ready = true; model.childObservation = observation; model.insert('retained');
  model.focus = 1;
  for (const [text, action] of [['[', 'previous'], [']', 'next'], ['p', 'pause'], ['c', 'toggle'], ['r', 'reconnect']]) assert.deepEqual(model.key(text), { type: 'observation', action });
  assert.equal(model.key('!', {}), undefined); assert.equal(model.key('', { name: 'return' }), undefined);
  assert.equal(model.draft, 'retained'); assert.equal(model.cwd, '/primary'); assert.deepEqual(model.transcript, []);
  model.focus = 0; model.key('p'); assert.equal(model.draft, 'retainedp'); observation.close();
});
