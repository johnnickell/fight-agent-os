import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { PassThrough } from 'node:stream';
import { mkdtempSync, mkdirSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';
import { HerdrLogs, readLogConfiguration } from '../src/herdr/logs.mjs';
import { installLogInput } from '../src/herdr/logs-input.mjs';
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

async function inputFixture(t, { menu = false } = {}) {
  let worktree = '/A', childGeneration = 'one', block = false, release, calls = 0, ambiguous = menu;
  const admissions = [], input = new PassThrough();
  const logs = new HerdrLogs({ primary: { cwd: '/A' } }, { discovery: true, companion: {} }, () => {}, {
    selection: async () => ({ generation: 'native', state: worktree ? 'selected' : 'unavailable', worktree,
      child: { id: 'child', generation: childGeneration, pinned: false }, at: Date.now() }),
    sources: { async discover(main) {
      calls++;
      if (block) { block = false; await new Promise(done => { release = done; }); }
      const source = service => ({ main, context: 'local', project: 'owned', service, endpoint: 'unix:///local',
        socket: {}, checkout: {}, container: service, witness: service, label: service });
      return { choices: ambiguous && main === '/A' ? [source('web'), source('worker')] : [source('web')], notice: 'verified' };
    }, async close() {} },
    reader: source => ({ source, paused: false, history: [], processes: new Set(),
      async subscribe() { admissions.push(source.main); this.state = 'following'; },
      async stop() {}, async close() {},
      async action(type) { if (type === 'pause') this.paused = !this.paused; },
    }),
  });
  let narrow = false, closing = false;
  const queue = installLogInput(input, logs, { closing: () => closing, close: async () => { closing = true; },
    canChoose: () => !narrow, scroll() {}, changed() {} });
  const settle = async () => { await queue.settled(); await logs.logs.transition; };
  const select = async (next, generation = childGeneration) => {
    worktree = next; childGeneration = generation; await logs.tick();
  };
  await select('/A'); await settle();
  t.after(async () => { release?.(); await settle(); await logs.close(); input.destroy(); });
  return { logs, queue, admissions, select, settle, calls: () => calls,
    key: value => input.write(value), narrow: value => { narrow = value; }, ambiguous: value => { ambiguous = value; },
    async block(value = 'r') {
      release = null; block = true; input.write(value);
      while (!release) await turn();
      return () => release();
    },
  };
}

test('terminal close accepted during reconnect belongs to the original checkout', async t => {
  const f = await inputFixture(t), release = await f.block();
  f.key('c'); await f.select('/B'); release(); await f.settle();
  assert.equal(f.logs.logs.entries.get('/A').closed, true, 'A close must bind before the terminal queue waits');
  assert.equal(f.logs.logs.entries.get('/B').closed, false, 'queued close must not transfer to B');
  assert.equal(f.logs.logs.current.source.main, '/B');
  await f.select('/A'); await f.settle();
  assert.equal(f.logs.logs.current, null, 'returning to A is not manual recovery');
  assert.deepEqual(f.admissions, ['/A', '/B']);
});

test('terminal buffered controls survive A to B to A and same-checkout child replacement in order', async t => {
  for (const route of ['return-before-settlement', 'new-child']) {
    for (const [keys, closed, paused] of [
      ['c', true, false], ['pc', true, true], ['cp', true, true], ['ppc', true, false],
      ['crc', true, false], ['ccp', false, true], ['rcp', true, true], ['crp', false, true],
    ]) await t.test(`${route}: ${keys}`, async t => {
      const f = await inputFixture(t), release = await f.block();
      f.key(keys);
      if (route === 'return-before-settlement') { await f.select('/B'); await f.select('/A'); }
      else await f.select('/A', 'new-child');
      release(); await f.settle();
      const entry = f.logs.logs.entries.get('/A');
      assert.equal(entry.closed, closed); assert.equal(entry.paused, paused);
      assert.equal(f.calls(), closed ? 2 : 3, 'stale controls cannot scan or admit readers');
      assert.deepEqual(f.admissions, closed ? ['/A'] : ['/A', '/A']);
      if (!closed) assert.equal(f.logs.logs.current.paused, paused);
      if (closed) {
        assert.equal(f.logs.logs.current, null);
        f.key('r'); await f.settle();
        assert.equal(f.logs.logs.current.paused, paused, 'explicit recovery preserves pause');
      }
    });
  }
});

test('terminal cancellation and recovery remain original-entry menu intent after revocation', async t => {
  for (const route of ['other-checkout', 'return-before-settlement', 'new-child']) {
    for (const keys of ['\x1b', 'p\x1b', '\x1bp', '\x1br', 's\x1b']) await t.test(`${route}: ${JSON.stringify(keys)}`, async t => {
      const f = await inputFixture(t, { menu: true }), release = await f.block('s');
      for (const key of keys) {
        f.key(key);
        // A standalone Esc is delivered only after readline rules out an ANSI sequence.
        if (key === '\x1b') await new Promise(done => setTimeout(done, 550));
      }
      if (route === 'new-child') await f.select('/A', 'new-child');
      else {
        await f.select('/B');
        if (route === 'return-before-settlement') await f.select('/A');
      }
      release(); await f.settle();
      assert.equal(f.logs.logs.entries.get('/A').cancelled, true);
      assert.equal(f.logs.logs.entries.get('/A').paused, false, 'menu keys cannot pause a reader');
      if (route === 'other-checkout') {
        assert.equal(f.logs.logs.entries.get('/B').cancelled, false);
        assert.equal(f.logs.logs.current.source.main, '/B');
      }
      assert.equal(f.calls(), route === 'other-checkout' ? 3 : 2, 'obsolete menu input cannot discover');
      assert.deepEqual(f.admissions, route === 'other-checkout' ? ['/B'] : []);
      await f.select('/A'); await f.settle(); assert.equal(f.logs.logs.current, null);
      f.key('s\x1b[B\r'); await f.settle();
      assert.equal(f.logs.logs.current.source.service, 'worker', 'coalesced explicit selection recovers cancellation');
    });
  }
});

test('terminal keys accepted during explicit selection keep menu semantics before and after revocation', async t => {
  for (const revoke of [false, true]) await t.test(`revoked: ${revoke}`, async t => {
    const f = await inputFixture(t), release = await f.block('s');
    f.key('pc'); f.key('\x1b'); await new Promise(done => setTimeout(done, 550));
    if (revoke) await f.select('/A', 'new-child');
    release(); await f.settle();
    const entry = f.logs.logs.entries.get('/A');
    assert.equal(entry.closed, false, 'toggle during explicit selection is not a reader close');
    assert.equal(entry.paused, false, 'pause during explicit selection is menu input');
    assert.equal(entry.cancelled, true, 'Escape cancels the original explicit selection');
    await f.select('/A', 'return-child'); await f.settle();
    assert.equal(f.logs.logs.current, null, 'cancelled selection cannot automatically recover');
  });
});

test('terminal split arrows, coalesced select/Enter/pause and narrow-pane choice retain menu semantics', async t => {
  const f = await inputFixture(t, { menu: true });
  f.narrow(true); f.key('\x1b['); f.key('B\r'); await f.settle();
  assert.equal(f.logs.logs.index, 1); assert.equal(f.logs.logs.current, null);
  assert.ok(f.logs.logs.menu, 'narrow pane cannot authorize a source');
  f.narrow(false); f.key('\r'); await f.settle();
  assert.equal(f.logs.logs.current.source.service, 'worker');
  f.key('s\x1b[B\rp'); await f.settle();
  assert.equal(f.logs.logs.current.source.service, 'worker');
  assert.equal(f.logs.logs.current.paused, true, 'pause after Enter is reader input');
  f.key('sc'); await f.settle();
  assert.equal(f.logs.logs.entries.get('/A').closed, false, 'reader toggle in an explicit menu is ignored');
});

test('terminal controls are not reinterpreted by automatic ambiguity and held input grants nothing', async t => {
  const f = await inputFixture(t), release = await f.block();
  f.key('c'); f.ambiguous(true); release(); await f.settle();
  assert.equal(f.logs.logs.entries.get('/A').closed, true); assert.equal(f.logs.logs.menu, false);
  await f.select(null); f.key('cprsc'); await f.settle();
  assert.equal(f.logs.logs.entries.get('/A').closed, true);
  assert.equal(f.logs.logs.entries.get('/A').paused, false);
  assert.equal(f.logs.logs.current, null);
  await f.select('/A'); await f.settle(); assert.equal(f.logs.logs.current, null);
});

test('terminal accepts at most sixteen pending actions without discarding accepted manual intent', async t => {
  const f = await inputFixture(t), release = await f.block();
  f.key('c'.repeat(16)); assert.equal(f.queue.pending(), 16, 'reconnect plus fifteen toggles; sixteenth toggle rejected');
  await f.select('/B'); release(); await f.settle();
  assert.equal(f.queue.pending(), 0);
  assert.equal(f.logs.logs.entries.get('/A').closed, true, 'all fifteen accepted toggles retain order');
  assert.equal(f.logs.logs.entries.get('/B').closed, false);
  assert.deepEqual(f.admissions, ['/A', '/B']);
});

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
