import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { PassThrough } from 'node:stream';
import { RuntimeLogs, safeLog, logLimits } from '../src/workspace/runtime-logs.mjs';

const turn = () => new Promise(done => setImmediate(done));
const source = { main: '/checkout', endpoint: 'unix:///controlled.sock', project: 'owned', stopped: [],
  members: [{ service: 'web', container: 'a'.repeat(64) }, { service: 'database', container: 'b'.repeat(64) }] };
async function fixture(t, { verify = async () => {}, stubborn = false, selected = source } = {}) {
  const calls = [], streams = [];
  const logs = new RuntimeLogs(selected, () => {}, { verifySource: verify, spawnProcess(command, args, options) {
    calls.push({ command, args, options });
    const child = new EventEmitter(); child.pid = 1000 + calls.length; child.stdout = new PassThrough(); child.stderr = new PassThrough(); child.signals = [];
    child.kill = signal => { child.signals.push(signal); if (!stubborn) queueMicrotask(() => child.emit('close', null)); };
    streams.push(child); return child;
  } });
  t.after(async () => { streams.forEach(child => child.emit('close', null)); await logs.close(); });
  logs.open = true; await logs.subscribe(); return { logs, calls, streams };
}

test('one fixed reader per verified container prefixes mixed output with service/container identity', async t => {
  let verified = false;
  const f = await fixture(t, { verify: async () => { verified = true; } });
  assert.equal(verified, true); assert.equal(f.streams.length, 2); assert.equal(f.logs.processes.size, 2);
  f.streams[0].stdout.write('web record\n'); f.streams[1].stderr.write('database record\n');
  assert.deepEqual(f.logs.history, ['web/aaaaaaaaaaaa | web record', 'database/bbbbbbbbbbbb | database record']);
  for (const [index, call] of f.calls.entries()) {
    assert.deepEqual(call.args, ['--host', source.endpoint, 'container', 'logs', '--tail', '100', '--timestamps', '--follow', source.members[index].container]);
    assert.equal(call.command, 'docker'); assert.equal(call.options.shell, false); assert.equal(call.options.cwd, '/');
    assert.ok(Object.keys(call.options.env).every(key => ['HOME', 'PATH', 'TMPDIR', 'LANG', 'LC_ALL'].includes(key)));
  }
});

test('quiet verified following differs from no source, denied verification and stopped services', async t => {
  const f = await fixture(t, { selected: { ...source, stopped: ['worker'] } });
  assert.match(f.logs.body(), /QUIET.*verified readers following/); assert.match(f.logs.body(), /Stopped services: worker/);
  const denied = await fixture(t, { verify: async () => { throw Error('Docker denied'); } });
  assert.equal(denied.calls.length, 0); assert.equal(denied.logs.state, 'unavailable'); assert.match(denied.logs.body(), /Docker denied/);
  assert.doesNotMatch(denied.logs.body(), /QUIET/);
});

test('complete source verification settles before any log process and revocation fences late verification', async t => {
  let release; const logs = new RuntimeLogs(source, () => {}, { verifySource: () => new Promise(done => { release = done; }), spawnProcess: () => assert.fail('revoked source') });
  logs.open = true; const checking = logs.subscribe();
  logs.open = false; await logs.stop(); release(); await checking;
  assert.equal(logs.processes.size, 0); await logs.close();
});

test('bounded UTF-8 framing escapes hostile controls and discloses clipping/eviction across every stream', async t => {
  const f = await fixture(t);
  const bytes = Buffer.from('start 😀\x1b]52;clipboard\x07\r\u202e end\n');
  for (const byte of bytes) f.streams[0].stdout.write(Buffer.from([byte]));
  assert.match(f.logs.history[0], /😀\\u001b.*\\u0007\\u000d\\u202e/);
  assert.doesNotMatch(f.logs.history[0], /[\x00-\x1f\x7f-\x9f\u202e]/);
  f.streams[1].stdout.write('x'.repeat(10000) + '\n'); assert.equal(f.logs.clipped, 1);
  assert.match(f.logs.history.at(-1), /record truncated/);
  for (let i = 0; i < 500; i++) f.streams[i % 2].stdout.write('x'.repeat(300) + '\n');
  assert.ok(f.logs.history.length <= logLimits.lines); assert.ok(f.logs.units <= logLimits.units); assert.ok(f.logs.omitted > 0);
  assert.equal(safeLog('\x1b\u2066'), '\\u001b\\u2066');
});

test('pause drains one bounded history and source revocation clears both live and frozen records', async t => {
  const f = await fixture(t); f.streams[0].stdout.write('before\n'); await f.logs.action('pause');
  f.streams[1].stdout.write('after\n'); assert.match(f.logs.body(), /before/); assert.doesNotMatch(f.logs.body(), /after/);
  await f.logs.action('pause'); assert.match(f.logs.body(), /after/);
  await f.logs.action('pause'); f.logs.open = false; f.logs.clear(); await f.logs.stop();
  f.streams[0].stdout.write('late\n'); assert.deepEqual(f.logs.history, []); assert.deepEqual(f.logs.frozen, []);
  assert.equal(f.logs.processes.size, 0); assert.ok(f.streams.every(child => child.signals.includes('SIGTERM')));
});

test('a single lost/denied stream revokes the whole generation and every stale callback', async t => {
  const f = await fixture(t); f.streams[0].stdout.write('obsolete\n');
  f.streams[1].emit('error', Error('private daemon diagnostic'));
  f.streams[0].stdout.write('late\n'); await turn();
  assert.equal(f.logs.state, 'disconnected'); assert.deepEqual(f.logs.history, []); assert.equal(f.logs.processes.size, 0);
  assert.doesNotMatch(f.logs.body(), /private daemon/); assert.match(f.logs.body(), /unknown gaps/);
});

test('finite reaping escalates TERM/KILL but retains an unreaped process and refuses replacement', async t => {
  t.mock.timers.enable({ apis: ['setTimeout'] });
  const f = await fixture(t, { stubborn: true });
  const stopped = f.logs.stop(); t.mock.timers.tick(1000);
  assert.ok(f.streams.every(child => child.signals.includes('SIGKILL')));
  t.mock.timers.tick(1000); await stopped; await f.logs.subscribe();
  assert.equal(f.logs.processes.size, 2); assert.equal(f.calls.length, 2);
  f.streams.forEach(child => child.emit('close', null)); t.mock.timers.reset();
});

test('invalid, replicated IDs and over-bound sources cannot become arbitrary Docker commands', async t => {
  for (const members of [[], Array(17).fill(source.members[0]), [source.members[0], source.members[0]], [{ service: 'web', container: '--help' }]]) {
    const f = await fixture(t, { selected: { ...source, members } });
    assert.equal(f.logs.state, 'unavailable'); assert.equal(f.calls.length, 0);
  }
});
