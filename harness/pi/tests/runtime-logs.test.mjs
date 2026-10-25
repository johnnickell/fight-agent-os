import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { PassThrough } from 'node:stream';
import { RuntimeLogs, runtimeSelection, safeLog, logLimits } from '../src/workspace/runtime-logs.mjs';
import { Workspace } from '../src/workspace/presentation.mjs';
import { mkdtempSync, rmSync, readFileSync, symlinkSync, realpathSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { tmpdir } from 'node:os';

const main = resolve('.');
const source = { main, context: 'local', project: 'fight-agent-os', service: 'web' };
const row = (service = 'web', extra = {}) => ({ id: (service === 'web' ? 'a' : 'b').repeat(64), service, directory: main, state: 'running', ...extra });
const settled = () => new Promise(resolve => setImmediate(resolve));
async function fixture({ rows = [row(), row('postgres')], endpoint = 'unix:///local/docker.sock', raw, fragmentMetadata = false, fail, persist = false, selection = source } = {}) {
  const calls = [], children = [];
  const logs = new RuntimeLogs(selection, () => {}, { persist, spawnProcess: (command, args, opts) => {
    calls.push({ command, args, opts });
    const child = new EventEmitter(); child.stdout = new PassThrough(); child.stderr = new PassThrough(); child.signals = [];
    child.kill = signal => { child.signals.push(signal); queueMicrotask(() => child.emit('close', null)); };
    children.push(child);
    queueMicrotask(() => {
      if (args.includes('logs') || fail === 'hang') return;
      if (fail === 'error') { child.emit('error', new Error('private diagnostic')); child.emit('close', 1); return; }
      if (fail === 'denied') { child.stderr.write('private daemon diagnostic'); child.emit('close', 1); return; }
      const result = args.includes('inspect') ? JSON.stringify(typeof endpoint === 'object' ? endpoint.value : endpoint) : raw ?? rows.map(row => JSON.stringify(row)).join('\n');
      if (fragmentMetadata) { for (const byte of Buffer.from(result)) child.stdout.write(Buffer.from([byte])); }
      else child.stdout.write(result);
      child.emit('close', 0);
    });
    return child;
  } });
  logs.start(); await settled();
  return { logs, calls, children, stream: children.at(-1) };
}

test('explicit complete selection validates every source argument', () => {
  assert.deepEqual(runtimeSelection(source), source);
  const invalid = [{ ...source, context: '-H' }, { ...source, project: 'bad;start' }, { ...source, service: '--all' }, { ...source, main: 'relative' }, { ...source, main: main + '\n' }, { main }];
  for (const selection of invalid) assert.throws(() => runtimeSelection(selection));
});
test('no runtime selection launches nothing, including after pane actions', async () => {
  const logs = new RuntimeLogs(null, () => {}, { spawnProcess: () => assert.fail('unselected launch') });
  logs.start(); for (const action of ['pause', 'toggle', 'next', 'reconnect']) await logs.action(action);
  assert.match(logs.view(), /No runtime selected/); await logs.close();
});
test('fixed host operations verify local context and Compose identity before bounded logs', async () => {
  const { logs, calls, stream } = await fixture();
  assert.equal(logs.state, 'following');
  assert.deepEqual(calls[0].args, ['--context', 'local', 'context', 'inspect', 'local', '--format', '{{json .Endpoints.docker.Host}}']);
  assert.deepEqual(calls[2].args, ['--context', 'local', 'container', 'logs', '--tail', '100', '--timestamps', '--follow', 'a'.repeat(64)]);
  for (const call of calls) {
    assert.equal(call.command, 'docker'); assert.equal(call.opts.shell, false); assert.equal(call.opts.cwd, '/');
    assert.equal(call.opts.env.DOCKER_HOST, undefined); assert.equal(call.opts.env.COMPOSE_FILE, undefined); assert.equal(call.opts.env.APP_ENV, undefined);
    assert.deepEqual(call.opts.stdio, ['ignore', 'pipe', 'pipe']);
  }
  stream.stdout.write('2026-10-20T12:00:00Z emitted text\n'); assert.match(logs.view(), /emitted text/);
  await logs.close(); assert.deepEqual(stream.signals, ['SIGTERM']);
});
test('directory mismatch, remote context, missing, stopped and replicated sources never launch logs', async () => {
  for (const setup of [{ rows: [row('web', { directory: '/other' })] }, { endpoint: 'tcp://remote:2375' }, { rows: [] }, { rows: [row('web', { state: 'exited' })] }, { rows: [row(), row()] }, { rows: [row('web', { id: '--option' })] }]) {
    const { logs, calls } = await fixture(setup);
    assert.equal(logs.state, 'unavailable'); assert.ok(calls.every(call => !call.args.includes('logs'))); await logs.close();
  }
});
test('missing Docker and denied access report honest generic states, not copied private errors', async () => {
  for (const fail of ['error', 'denied']) {
    const { logs } = await fixture({ fail }); assert.equal(logs.state, 'unavailable');
    assert.match(logs.view(), /unavailable|denied/); assert.doesNotMatch(logs.view(), /private/); await logs.close();
  }
});
test('fragmented UTF-8, blank lines, stdout/stderr and malformed bytes remain safe display data', async () => {
  const { logs, stream } = await fixture();
  const bytes = Buffer.from('timestamp 😀\n\n'); stream.stdout.write(bytes.subarray(0, 12)); stream.stdout.write(bytes.subarray(12));
  stream.stderr.write('stderr text\n'); stream.stdout.write(Buffer.from([0xff, 10]));
  assert.deepEqual(logs.history, ['timestamp 😀', '', 'stderr text', '\ufffd']); await logs.close();
});
test('terminal, clipboard and bidi controls are visibly escaped and cannot become terminal actions', async () => {
  const { logs, stream } = await fixture();
  stream.stdout.write('\x1b]52;c;payload\x07\r\u202e\x9b2J\ttext\n');
  assert.doesNotMatch(logs.view(), /[\x00-\x09\x0b-\x1f\x7f-\x9f\u202e]/);
  assert.match(logs.view(), /\\u001b\]52;c;payload\\u0007\\u000d\\u202e/);
  assert.equal(safeLog('\ud800'), '\ufffd'); await logs.close();
});
test('high-volume and oversized partial records stay bounded with disclosed clipping and eviction', async () => {
  const { logs, stream } = await fixture();
  for (let i = 0; i < 2000; i++) stream.stdout.write('x'.repeat(500) + '\n');
  stream.stdout.write('y'.repeat(2 * 1024 * 1024)); stream.stdout.write('\nlast\n');
  assert.ok(logs.history.length <= logLimits.lines); assert.ok(logs.units <= logLimits.units);
  assert.ok(logs.omitted > 0); assert.equal(logs.clipped, 1); assert.equal(logs.history.at(-1), 'last');
  assert.match(logs.view(), /record truncated/); await logs.close();
});
test('pause freezes bounded display while draining; resume shows bounded recent window and loss', async () => {
  const { logs, stream } = await fixture(); stream.stdout.write('before\n'); await logs.action('pause');
  for (let i = 0; i < 400; i++) stream.stdout.write(`after ${i}\n`);
  assert.match(logs.body(), /before/); assert.doesNotMatch(logs.body(), /after 399/); assert.ok(logs.omitted > 0);
  await logs.action('pause'); assert.match(logs.body(), /after 399/); assert.doesNotMatch(logs.body(), /before/); await logs.close();
});
test('reader close, explicit reconnect and service change reap only readers and disclose replay gaps', async () => {
  const { logs, stream, calls, children } = await fixture();
  await logs.action('toggle'); assert.equal(logs.open, false); assert.deepEqual(stream.signals, ['SIGTERM']);
  const count = calls.length; await settled(); assert.equal(calls.length, count);
  await logs.action('toggle'); assert.equal(logs.state, 'following'); assert.ok(logs.gaps > 0);
  const current = children.at(-1); stream.stdout.write('STALE\n'); stream.emit('close', 1); assert.equal(logs.state, 'following');
  assert.doesNotMatch(logs.view(), /STALE/); await logs.action('next'); assert.equal(logs.source.service, 'postgres');
  assert.deepEqual(current.signals, ['SIGTERM']); assert.ok(calls.every(call => !call.args.some(arg => ['up', 'start', 'restart', 'exec', 'run', 'stop'].includes(arg))));
  assert.match(logs.view(), /100 duplicate/); await logs.close();
});
test('interrupted or empty streams never infer history completeness or container death; no automatic retries', async t => {
  const { logs, stream, calls } = await fixture(); t.after(() => logs.close());
  assert.equal(logs.history.length, 0); assert.match(logs.body(), /No records yet/);
  stream.stdout.write('partial'); stream.stdout.end(); await settled(); stream.emit('close', 1);
  assert.equal(logs.state, 'disconnected'); assert.match(logs.view(), /History\/liveness unknown/); assert.match(logs.view(), /partial/);
  const count = calls.length; await settled(); assert.equal(calls.length, count); await logs.close();
});
test('closing during a reconnect cannot resurrect a reader', async () => {
  const { logs, calls } = await fixture(); const transition = logs.action('reconnect'); await logs.close(); await transition;
  const count = calls.length; await settled(); assert.equal(calls.length, count); assert.equal(logs.open, false); assert.equal(logs.processes.size, 0);
});
test('service selection stays explicit and independent of conversation/worktree/child context', async () => {
  const { logs, stream } = await fixture(); stream.stdout.write('private log never in conversation\n');
  const model = new Workspace('/worktree-A'); model.runtimeLogs = logs; model.ready = true; model.insert('draft'); model.focus = 2;
  model.cwd = '/worktree-B'; model.childObservation = { view: () => 'different child' };
  for (const [text, action] of [['p', 'pause'], ['c', 'toggle'], ['r', 'reconnect'], [']', 'next'], ['i', 'info']]) assert.deepEqual(model.key(text), { type: 'runtime', action });
  assert.equal(model.key('!'), undefined); assert.equal(model.key('', { name: 'return' }), undefined); assert.equal(model.draft, 'draft'); assert.deepEqual(model.transcript, []);
  assert.deepEqual(logs.source, source); assert.match(model.render(160, 40).join('\n'), /SHARED/); assert.match(model.render(160, 40).join('\n'), /private log never/);
  await logs.close();
});
test('runtime scrollback is keyboard accessible, keeps shared identity pinned and supports narrow views', async () => {
  const { logs, stream } = await fixture(); for (let i = 0; i < 60; i++) stream.stdout.write(`unique line ${i}\n`);
  const model = new Workspace(main); model.runtimeLogs = logs; model.focus = 2;
  assert.match(model.render(160, 40).join('\n'), /unique line 59/);
  model.key('', { name: 'pageup' }); assert.ok(model.offsets[2] > 0);
  assert.doesNotMatch(model.render(160, 40).join('\n'), /unique line 59/); assert.match(model.render(80, 24).join('\n'), /SHARED/);
  await logs.action('info'); assert.match(model.paneText(2), new RegExp(main)); await logs.close();
});
test('fragmented UTF-8 identity metadata preserves the exact non-ASCII main-checkout identity', async () => {
  const directory = realpathSync(mkdtempSync(join(tmpdir(), 'fight-🧪-')));
  let logs;
  try {
    ({ logs } = await fixture({ selection: { ...source, main: directory }, rows: [row('web', { directory })], fragmentMetadata: true }));
    assert.equal(logs.state, 'following'); assert.equal(logs.source.main, directory);
  } finally { await logs?.close(); rmSync(directory, { recursive: true, force: true }); }
});
test('malformed and oversized metadata and excessive container counts are refused without a reader', async () => {
  for (const setup of [{ raw: 'not-json' }, { raw: 'x'.repeat(70000) }, { rows: Array.from({ length: 33 }, () => row()) }]) {
    const { logs, calls } = await fixture(setup); assert.equal(logs.state, 'unavailable');
    assert.ok(calls.every(call => !call.args.includes('logs'))); await logs.close();
  }
});
test('context endpoint is pinned across explicit reconnect and never silently redirects', async () => {
  const endpoint = { value: 'unix:///first.sock' }, { logs, calls } = await fixture({ endpoint });
  endpoint.value = 'unix:///replacement.sock'; await logs.action('reconnect');
  assert.equal(logs.state, 'unavailable'); assert.match(logs.view(), /identity changed/);
  assert.equal(calls.filter(call => call.args.includes('logs')).length, 1); await logs.close();
});
test('identity lookup deadline and finite follow lifetime stop only owned readers without retry', async t => {
  t.mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const hung = await fixture({ fail: 'hang' }); t.mock.timers.tick(5001); await settled();
    assert.equal(hung.logs.state, 'unavailable'); assert.match(hung.logs.notice, /timed out/); await hung.logs.close();
    const { logs, calls, stream } = await fixture(); t.mock.timers.tick(900001); await settled();
    assert.equal(logs.state, 'disconnected'); assert.match(logs.notice, /lifetime/); assert.deepEqual(stream.signals, ['SIGTERM']);
    const count = calls.length; t.mock.timers.tick(900001); await settled(); assert.equal(calls.length, count); await logs.close();
  } finally { t.mock.timers.reset(); }
});
test('preference is owner-private under main var, never loaded to redirect explicit launch selection', async () => {
  const directory = realpathSync(mkdtempSync(join(tmpdir(), 'fight-runtime-preference-')));
  try {
    const selected = { ...source, main: directory };
    const { logs } = await fixture({ selection: selected, rows: [row('web', { directory })], persist: true });
    assert.equal(logs.state, 'following'); const file = join(directory, 'var/terminal/runtime-source.json');
    assert.deepEqual(JSON.parse(readFileSync(file, 'utf8')), selected); await logs.close();
    const refused = new RuntimeLogs(null); refused.start(); assert.equal(refused.source, null); await refused.close();
    rmSync(join(directory, 'var/terminal'), { recursive: true }); symlinkSync(directory, join(directory, 'var/terminal'));
    const result = await fixture({ selection: selected, rows: [row('web', { directory })], persist: true });
    assert.equal(result.logs.state, 'unavailable'); await result.logs.close();
  } finally { rmSync(directory, { recursive: true, force: true }); }
});
