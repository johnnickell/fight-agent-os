import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { PassThrough } from 'node:stream';
import { mkdtempSync, mkdirSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';
import { HerdrLogs, readLogConfiguration } from '../src/herdr/logs.mjs';
import { NativeWorkspaceContext, serveLocal } from '../src/herdr/context.mjs';
import { RuntimeLogs } from '../src/workspace/runtime-logs.mjs';

const turn = () => new Promise(done => setImmediate(done));
async function fixture(t, { multiple = false, shared = false } = {}) {
  const root = realpathSync(mkdtempSync(join(tmpdir(), 'herdr-logs-')));
  const a = join(root, 'a'), b = join(root, 'b'), missing = join(root, 'missing');
  for (const directory of [a, b, missing]) mkdirSync(directory);
  const primary = { cwd: a, sessionId: randomUUID(), sessionFile: null, pid: process.pid };
  const native = new NativeWorkspaceContext(primary), binding = await native.start();
  let value = { ...native.snapshot(), state: 'idle', worktree: a, child: null }, denied = false;
  const token = randomUUID();
  const endpoint = await serveLocal(query => {
    if (denied || query?.token !== token || query.operation !== 'selection' || query.generation !== binding.generation) throw Error('Unavailable');
    return { ...value, at: Date.now() };
  });
  const source = (main, project) => ({ main, context: 'local', project, service: 'web' });
  native.logs = { tests: [source(a, 'test-a'), source(b, 'test-b'), ...(multiple ? [source(a, 'alternate')] : [])], shared: shared ? source(a, 'shared') : null,
    companion: { port: endpoint.port, token } };
  const config = await readLogConfiguration(binding), calls = [], streams = [], rows = new Map();
  config.tests.forEach((item, index) => rows.set(item.project, { id: String(index + 1).repeat(64), directory: item.main, service: 'web', state: 'running' }));
  rows.set('shared', { id: 'e'.repeat(64), directory: a, service: 'web', state: 'running' });
  const logs = new HerdrLogs(binding, config, () => {}, { reader: (source, changed) => new RuntimeLogs(source, changed, { persist: false, pinGeneration: true, spawnProcess: (command, args, options) => {
    calls.push({ command, args, options });
    const child = new EventEmitter(); child.pid = 1000 + calls.length; child.stdout = new PassThrough(); child.stderr = new PassThrough(); child.signals = [];
    child.kill = signal => { child.signals.push(signal); queueMicrotask(() => child.emit('close', null)); };
    queueMicrotask(() => {
      if (args.includes('logs')) { streams.push(child); return; }
      if (args.includes('inspect')) child.stdout.write(JSON.stringify('unix:///controlled-endpoint.sock'));
      else {
        const project = args.find(arg => arg.startsWith('label=com.docker.compose.project='))?.split('=').at(-1);
        child.stdout.write(JSON.stringify(rows.get(project)));
      }
      child.emit('close', 0);
    });
    return child;
  } }) });
  const refresh = async () => { await logs.tick(); await logs.logs.transition; await turn(); };
  const select = (worktree, child = {}) => { value = { ...native.snapshot(), state: 'selected', worktree, child: { generation: randomUUID(), id: randomUUID(), worktree, lifecycle: 'running', state: 'connected', pinned: false, ...child } }; };
  t.after(async () => { await logs.close(); native.close(); endpoint.close(); rmSync(root, { recursive: true, force: true }); });
  return { logs, native, binding, config, a, b, missing, calls, streams, rows, refresh, select, deny: (value = true) => { denied = value; }, set: next => { value = next; } };
}

test('open-generation configuration is bounded, explicit and served only with actual native lifetime', async t => {
  const f = await fixture(t);
  assert.equal(f.calls.length, 0, 'Configuration alone starts no Docker process');
  assert.equal(f.config.tests.length, 2);
  assert.equal(f.config.tests[0].purpose, 'test');
  assert.deepEqual(f.config.tests[0].allowedServices, ['web']);
  f.native.logs.tests = Array(9).fill(f.native.logs.tests[0]);
  await assert.rejects(readLogConfiguration(f.binding));
  f.native.close(); await assert.rejects(readLogConfiguration(f.binding));
});

test('actual bounded loopback selection drives primary, child, pin and idle without per-switch configuration', async t => {
  const f = await fixture(t); await f.refresh();
  assert.equal(f.logs.logs.source.main, f.a);
  f.streams[0].stdout.write('A-private-display-only\n'); await f.logs.action('pause');
  f.select(f.b); await f.refresh();
  assert.equal(f.logs.logs.source.main, f.b); assert.deepEqual(f.streams[0].signals, ['SIGTERM']);
  f.streams[0].stdout.write('late-A\n'); f.streams.at(-1).stdout.write('B-only\n');
  assert.doesNotMatch(f.logs.view(), /A-private|late-A/); assert.match(f.logs.view(), /B-only/);
  f.select(f.b, { pinned: true, lifecycle: 'completed' }); await f.refresh();
  assert.match(f.logs.view(), /PINNED/);
  f.set({ ...f.native.snapshot(), state: 'idle', worktree: f.a, child: null }); await f.refresh();
  assert.equal(f.logs.logs.source.main, f.a); assert.doesNotMatch(f.logs.view(), /B-only|A-private/);
  assert.ok(f.calls.every(c => c.command === 'docker' && c.options.shell === false));
  assert.ok(f.calls.every(c => !c.args.some(arg => ['up', 'restart', 'run', 'exec', 'compose'].includes(arg))));
});

test('ownership health reports only tracked reader PIDs and drops them after reaping', async t => {
  const f = await fixture(t);
  assert.deepEqual(f.logs.readerPids(), []);
  await f.refresh();
  f.streams[0].stdout.write('private-display-record\n');
  assert.deepEqual(f.logs.readerPids(), [f.streams[0].pid]);
  await f.logs.action('toggle');
  assert.deepEqual(f.logs.readerPids(), []);
});

test('a new child generation in the same worktree fences callbacks and frozen records', async t => {
  const f = await fixture(t); f.select(f.a); await f.refresh();
  const old = f.streams[0]; old.stdout.write('old-generation\n'); await f.logs.action('pause');
  f.select(f.a); await f.refresh(); old.stdout.write('late-generation\n');
  assert.equal(f.streams.length, 2); assert.deepEqual(old.signals, ['SIGTERM']);
  assert.equal(f.logs.logs.current.paused, false); assert.doesNotMatch(f.logs.view(), /old-generation|late-generation/);
});

test('closed/unavailable child custody clears output and never falls back to primary or shared', async t => {
  const f = await fixture(t, { shared: true }); f.select(f.b); await f.refresh();
  f.streams[0].stdout.write('private-old\n');
  f.set({ ...f.native.snapshot(), state: 'unavailable', worktree: null, child: null }); await f.refresh();
  assert.equal(f.logs.logs.current, null); assert.equal(f.streams.length, 1);
  assert.doesNotMatch(f.logs.view(), /private-old/); assert.match(f.logs.view(), /no primary\/shared fallback/);
  for (const action of ['mode', 'toggle', 'reconnect']) await f.logs.action(action);
  assert.equal(f.streams.length, 1);
  f.select(f.b); await f.refresh(); assert.equal(f.logs.logs.source.main, f.b);
});

test('missing/ambiguous bindings require labelled human choices; shared remains visibly explicit', async t => {
  const f = await fixture(t, { multiple: true, shared: true }); await f.refresh();
  assert.equal(f.calls.length, 0); await f.logs.action('info'); assert.match(f.logs.view(), /alternate/);
  await f.logs.action('stack-next'); assert.equal(f.logs.logs.source.project, 'test-a');
  f.select(f.missing); await f.refresh(); assert.equal(f.logs.logs.source, null);
  await f.logs.action('mode'); assert.equal(f.logs.logs.source.project, 'shared'); assert.match(f.logs.view(), /EXPLICIT SHARED/);
  f.rows.get('shared').id = 'f'.repeat(64); const count = f.streams.length;
  await f.logs.action('reconnect'); assert.equal(f.logs.logs.state, 'unavailable'); assert.equal(f.streams.length, count);
  assert.match(f.logs.view(), /fresh human binding required/);
  await f.logs.action('mode'); assert.equal(f.logs.logs.source, null);
});

test('pause drains bounded hostile records, close/reopen has honest gaps and container replacement refuses', async t => {
  const f = await fixture(t); await f.refresh(); const reader = f.logs.logs.current;
  f.streams[0].stdout.write('before-pause\n'); await f.logs.action('pause');
  f.streams[0].stdout.write('\x1b]52;private\x07' + 'x'.repeat(10000) + '\n' + 'line\n'.repeat(400));
  assert.ok(reader.history.length <= 300 && reader.units <= 32768); assert.ok(reader.omitted > 0 && reader.clipped > 0);
  assert.deepEqual(reader.frozen, ['before-pause']);
  await f.logs.action('pause'); assert.doesNotMatch(f.logs.view(), /\x1b|\x07/);
  await f.logs.action('toggle'); assert.equal(reader.state, 'closed');
  await f.logs.action('toggle'); assert.equal(reader.state, 'following'); assert.ok(reader.gaps >= 2);
  f.rows.get('test-a').id = 'f'.repeat(64); const count = f.streams.length;
  await f.logs.action('reconnect'); assert.equal(reader.state, 'unavailable'); assert.equal(f.streams.length, count);
  assert.match(f.logs.view(), /generation changed/);
});

test('initial child discovery may settle within a finite bound without granting a source', async t => {
  const f = await fixture(t); f.deny(); await f.refresh();
  assert.notEqual(f.logs.invalidated, true); assert.equal(f.calls.length, 0);
  f.deny(false); await f.refresh(); assert.equal(f.logs.logs.source.main, f.a);
  const g = await fixture(t); g.deny(); g.logs.startupDeadline = Date.now() - 1; await g.refresh();
  assert.equal(g.logs.invalidated, true); assert.equal(g.calls.length, 0);
});

test('a revoked source cannot be reopened by an in-flight reader action', async t => {
  const f = await fixture(t); await f.refresh();
  const old = f.logs.logs.current, stop = old.stop.bind(old); let finish;
  old.stop = async () => { await stop(); await new Promise(done => { finish = done; }); };
  const action = f.logs.action('reconnect');
  while (!finish) await turn();
  f.set({ ...f.native.snapshot(), state: 'closed', worktree: null, child: null });
  // hold() invokes stop independently; both calls must settle, and neither may
  // start a replacement from the action's old source generation.
  old.stop = stop; await f.logs.tick(); const count = f.calls.length;
  finish(); await action; await f.logs.logs.transition;
  assert.equal(f.calls.length, count); assert.equal(f.logs.logs.current, null);
});

test('selection freshness expires and clears paused output even without another completed query', async t => {
  const f = await fixture(t); await f.refresh();
  f.streams[0].stdout.write('expired-source\n'); await f.logs.action('pause');
  await new Promise(done => setTimeout(done, 1550));
  assert.equal(f.logs.invalidated, true); assert.equal(f.logs.logs.current, null);
  assert.doesNotMatch(f.logs.view(), /expired-source/);
});

test('malformed/lost native or selection lifetime permanently invalidates this display generation', async t => {
  const f = await fixture(t); await f.refresh();
  f.deny(); await f.refresh(); assert.equal(f.logs.invalidated, true); assert.equal(f.logs.logs.current, null);
  const count = f.calls.length; await f.refresh(); await f.logs.action('reconnect'); assert.equal(f.calls.length, count);
  assert.match(f.logs.view(), /reopen|recovers/);
});

test('unreaped readers block every replacement and close cannot be resurrected by late metadata', async t => {
  const f = await fixture(t); await f.refresh();
  const old = f.logs.logs.current, stream = f.streams[0], stop = old.stop.bind(old);
  old.stop = async () => { old.generation++; };
  f.select(f.b); await f.refresh(); assert.equal(f.logs.logs.current, null); assert.match(f.logs.view(), /has not exited/);
  stream.emit('close', null); old.stop = stop;
  let finish; f.logs.selection = () => new Promise(done => { finish = done; });
  const pending = f.logs.tick(); await f.logs.close();
  finish({ ...f.native.snapshot(), state: 'idle', worktree: f.a, child: null }); await pending;
  assert.equal(f.logs.logs.current, null);
});
