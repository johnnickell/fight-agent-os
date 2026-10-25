import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { PassThrough } from 'node:stream';
import { mkdtempSync, mkdirSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { RuntimeLogs } from '../src/workspace/runtime-logs.mjs';
import { WorktreeLogs, testBindings } from '../src/workspace/worktree-logs.mjs';
import { Workspace } from '../src/workspace/presentation.mjs';

const tick = () => new Promise(resolve => setImmediate(resolve));
async function fixture(t, multiple = false) {
  const directory = realpathSync(mkdtempSync(join(tmpdir(), 'worktree-logs-')));
  const primary = join(directory, 'A'), second = join(directory, 'B'), missing = join(directory, 'missing');
  for (const path of [primary, second, missing]) mkdirSync(path);
  const binding = (main, project) => ({ main, context: 'local', project, service: 'web', allowedServices: ['web', 'db'] });
  const bindings = [binding(primary, 'test-a'), binding(second, 'test-b')];
  if (multiple) bindings.push(binding(primary, 'test-a-alternate'));
  const rows = new Map([...bindings, binding(primary, 'shared')].map((source, index) => [source.project, [
    { id: String(index + 1).repeat(64), service: 'web', directory: source.main, state: 'running' },
    { id: String(index + 5).repeat(64), service: 'db', directory: source.main, state: 'running' },
  ]]));
  const calls = [], streams = [];
  const logs = new WorktreeLogs(primary, bindings, binding(primary, 'shared'), () => {}, {
    reader: (source, changed) => new RuntimeLogs(source, changed, { persist: false, spawnProcess: (command, args, opts) => {
      calls.push({ command, args, opts });
      const child = new EventEmitter(); child.stdout = new PassThrough(); child.stderr = new PassThrough(); child.signals = [];
      child.kill = signal => { child.signals.push(signal); queueMicrotask(() => child.emit('close', null)); };
      queueMicrotask(() => {
        if (args.includes('logs')) { streams.push(child); return; }
        if (args.includes('inspect')) child.stdout.write(JSON.stringify('unix:///operator.sock'));
        else {
          const project = args.find(arg => arg.startsWith('label='))?.split('=').at(-1);
          child.stdout.write((rows.get(project) ?? []).map(row => JSON.stringify(row)).join('\n'));
        }
        child.emit('close', 0);
      });
      return child;
    } }),
  });
  t.after(async () => { await logs.close(); rmSync(directory, { recursive: true, force: true }); });
  logs.start(); await logs.transition; await tick();
  return { logs, primary, second, missing, rows, calls, streams };
}

test('explicit human test bindings are bounded, canonical and narrow eligible services', () => {
  const main = realpathSync('.');
  const bindings = testBindings([{ main, context: 'local', project: 'test-a', service: 'web' }]);
  assert.deepEqual(bindings, [{ main, context: 'local', project: 'test-a', service: 'web', purpose: 'test', allowedServices: ['web'] }]);
  assert.throws(() => testBindings(Array(9).fill(bindings[0])));
  assert.throws(() => testBindings([bindings[0], bindings[0]]));
  assert.throws(() => testBindings([{ ...bindings[0], context: '-H' }]));
});
test('only the primary matching test source starts, without a container lifecycle operation', async t => {
  const { logs, primary, calls, streams } = await fixture(t);
  assert.equal(logs.source.main, primary); assert.equal(logs.source.project, 'test-a');
  assert.equal(logs.source.purpose, 'test'); assert.equal(streams.length, 1);
  assert.match(logs.summary(), /FOLLOW WORKTREE TEST/);
  assert.match(logs.view(), /custody-verified selected\/pinned child/);
  assert.ok(logs.view().includes(`Worktree: ${primary}`));
  for (const call of calls) {
    assert.equal(call.command, 'docker'); assert.equal(call.opts.shell, false);
    assert.ok(!call.args.some(arg => ['up', 'start', 'stop', 'run', 'exec', 'restart', 'compose'].includes(arg)));
    assert.equal(call.opts.env.DOCKER_HOST, undefined);
  }
});
test('worktree changes drain/reap old readers, clear paused buffers and reject late old-source events', async t => {
  const { logs, primary, second, streams } = await fixture(t), first = streams[0];
  first.stdout.write('A-only\n'); await logs.action('pause');
  first.stdout.write('A-paused\n');
  await logs.rebind(second); await tick();
  assert.equal(logs.source.main, second); assert.deepEqual(first.signals, ['SIGTERM']);
  streams.at(-1).stdout.write('B-only\n'); first.stdout.write('LATE-A\n'); first.emit('close', 1);
  assert.equal(logs.state, 'following'); assert.match(logs.body(), /B-only/);
  assert.doesNotMatch(logs.view(), /A-only|A-paused|LATE-A/);
  await logs.rebind(primary); await tick();
  assert.doesNotMatch(logs.view(), /A-only|A-paused|B-only|LATE-A/);
});
test('reselecting the same worktree retains the current valid log reader', async t => {
  const { logs, primary, calls, streams } = await fixture(t);
  streams[0].stdout.write('still current\n'); const count = calls.length;
  await logs.rebind(primary);
  assert.equal(calls.length, count); assert.deepEqual(streams[0].signals, []);
  assert.match(logs.body(), /still current/);
});
test('multiple bindings require a labelled stack choice; service selection survives worktree return', async t => {
  const { logs, primary, second, calls } = await fixture(t, true);
  assert.equal(logs.state, 'ambiguous'); assert.equal(calls.length, 0);
  assert.match(logs.view(), /test-a-alternate/); assert.match(logs.body(), /Ambiguous/);
  await logs.action('stack-next'); assert.equal(logs.source.project, 'test-a');
  await logs.action('next'); assert.equal(logs.source.service, 'db');
  await logs.rebind(second); await logs.rebind(primary);
  assert.equal(logs.source.project, 'test-a'); assert.equal(logs.source.service, 'db');
  await logs.action('stack-next'); assert.equal(logs.source.project, 'test-a-alternate');
  await logs.action('stack-previous'); assert.equal(logs.source.project, 'test-a');
});
test('missing context never falls back to shared; shared mode is a distinct explicit action', async t => {
  const { logs, missing, calls } = await fixture(t);
  await logs.rebind(missing); const count = calls.length;
  assert.equal(logs.source, null); assert.match(logs.body(), /no shared fallback/);
  for (const action of ['reconnect', 'next', 'toggle']) await logs.action(action);
  assert.equal(calls.length, count);
  await logs.action('mode'); assert.equal(logs.source.project, 'shared'); assert.match(logs.summary(), /EXPLICIT SHARED/);
  assert.ok(logs.summary().includes(`Log source: ${logs.source.main}`));
  assert.ok(logs.view().includes(`Follow context: ${missing}`));
  await logs.action('mode'); assert.equal(logs.source, null);
});
test('changed checkout labels, stopped, removed and recreated test generations are refused on revalidation', async t => {
  for (const failure of ['mismatch', 'stopped', 'removed', 'recreated']) {
    const { logs, primary, second, rows, calls, streams } = await fixture(t);
    await logs.rebind(second);
    const containers = rows.get('test-a');
    if (failure === 'mismatch') containers[0].directory = second;
    if (failure === 'stopped') containers[0].state = 'exited';
    if (failure === 'removed') rows.set('test-a', []);
    if (failure === 'recreated') containers[0].id = 'f'.repeat(64);
    const count = calls.filter(call => call.args.includes('logs')).length;
    await logs.rebind(primary);
    assert.equal(logs.state, 'unavailable', failure);
    assert.equal(calls.filter(call => call.args.includes('logs')).length, count, failure);
    assert.doesNotMatch(logs.body(), /B-only/);
    assert.ok(streams.every(stream => stream.signals.includes('SIGTERM')));
  }
});
test('closing during pending rebinding cannot start another source or resurrect a pane', async t => {
  const { logs, second, calls } = await fixture(t);
  const switching = logs.rebind(second); await logs.close(); await switching;
  const count = calls.length; await tick(); assert.equal(calls.length, count); assert.equal(logs.current, null);
  await logs.action('mode'); assert.equal(calls.length, count);
});
test('an unreaped old reader blocks replacements across bindings', async t => {
  const { logs, second, calls } = await fixture(t);
  // Exercise the reader deadline result without waiting or leaving an actual subprocess behind.
  const old = logs.current; const process = [...old.processes][0];
  const stop = old.stop.bind(old); old.stop = async () => { old.generation++; };
  const count = calls.length; await logs.rebind(second);
  assert.equal(calls.length, count); assert.equal(logs.current, null);
  assert.match(logs.body(), /has not exited/);
  process.emit('close', null); old.stop = stop;
});
test('source controls and output stay human-only with unchanged conversation, primary directory and draft', async t => {
  const { logs, primary, streams } = await fixture(t);
  streams[0].stdout.write('pane-only-marker\n');
  const model = new Workspace(primary); model.ready = true; model.focus = 2; model.runtimeLogs = logs; model.insert('retained draft');
  for (const [text, action] of [['{', 'stack-previous'], ['}', 'stack-next'], ['m', 'mode']]) {
    assert.deepEqual(model.key(text), { type: 'runtime', action });
  }
  assert.equal(model.key('!', {}), undefined); assert.equal(model.key('', { name: 'return' }), undefined);
  assert.deepEqual(model.transcript, []); assert.equal(model.cwd, primary); assert.equal(model.draft, 'retained draft');
  for (const [columns, rows] of [[160, 42], [80, 24]]) assert.match(model.render(columns, rows).join('\n'), /FOLLOW WORKTREE TEST/);
  await logs.action('info'); assert.match(model.paneText(2), /pane-only-marker/);
});
