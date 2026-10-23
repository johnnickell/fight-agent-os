import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, rm, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import net from 'node:net';
import { HerdrWorkspace } from '../src/herdr/workspace.mjs';

// Owned orchestration contracts, not assertions about Herdr's implementation or UI.
async function fixture(t, options = {}) {
  const cwd = await mkdtemp(join(tmpdir(), 'fight-herdr-'));
  const socket = net.createServer();
  const socketPath = join(cwd, 'herdr.sock');
  await new Promise((done) => socket.listen(socketPath, done));
  t.after(async () => { await new Promise((done) => socket.close(done)); await rm(cwd, { recursive: true, force: true }); });
  const primary = { pane_id: 'caller', terminal_id: 'native-terminal', tab_id: 'tab', workspace_id: 'workspace' };
  const panes = new Map([['caller', primary]]);
  const processes = new Map([['caller', { foreground_processes: [{ pid: process.pid }] }]]);
  const calls = [];
  let sequence = 0;
  const client = { async call(...args) {
    calls.push(args);
    const [group, action, id] = args;
    if (group !== 'pane') throw Error('Unsupported operation');
    if (options.fail?.(args, calls)) throw Error('transport failed');
    if (action === 'current') return { pane: primary };
    if (action === 'process-info') return { process_info: processes.get(id === '--current' ? 'caller' : args[3]) };
    if (action === 'layout') return { layout: { panes: [...panes.values()], zoomed: false } };
    if (action === 'get') { if (!panes.has(id)) throw Error('pane-not-found'); return { pane: panes.get(id) }; }
    if (action === 'split') {
      const pane = { ...primary, pane_id: `returned-${++sequence}`, terminal_id: `terminal-${sequence}` };
      panes.set(pane.pane_id, pane);
      processes.set(pane.pane_id, { shell_pid: sequence, foreground_processes: [{ pid: sequence, name: 'zsh' }] });
      return { pane };
    }
    if (action === 'run') { processes.set(id, { foreground_processes: [{ pid: 100 + sequence, name: 'node' }] }); return {}; }
    if (action === 'close') { panes.delete(id); return {}; }
    if (action === 'rename') return {};
    throw Error('Unsupported action');
  } };
  let workspace;
  const probe = async (file, token) => {
    const owned = workspace.state?.panes.find((p) => p.token === token);
    const info = owned && processes.get(owned.paneId);
    return info?.foreground_processes[0]?.name === 'node' ? { pid: info.foreground_processes[0].pid, paneId: owned.paneId } : undefined;
  };
  workspace = new HerdrWorkspace({ cwd, sessionId: 'actual-session', env: { HERDR_ENV: '1', HERDR_SOCKET_PATH: socketPath, HERDR_PANE_ID: 'caller', HERDR_TAB_ID: 'tab', HERDR_WORKSPACE_ID: 'workspace' }, client, probe, ...options.context });
  return { workspace, calls, panes, processes, primary };
}

test('Open keeps the native process and uses returned IDs, no-focus, same cwd and two truthful labels', async (t) => {
  const { workspace, calls, panes } = await fixture(t);
  await workspace.act('open');
  assert.equal(panes.size, 3);
  const splits = calls.filter((c) => c[1] === 'split');
  assert.equal(splits[0][2], 'caller');
  assert.equal(splits[1][2], 'returned-1');
  assert.ok(splits.every((c) => c.includes('--no-focus') && c.includes(workspace.context.cwd)));
  assert.deepEqual(calls.filter((c) => c[1] === 'rename').map((c) => c[3]), ['Child activity — placeholder', 'Runtime logs — placeholder']);
  assert.ok(calls.filter((c) => ['run', 'close', 'rename'].includes(c[1])).every((c) => c[2] !== 'caller'));
  assert.equal(JSON.parse(await readFile(workspace.file)).primary.pid, process.pid);
});

test('Repeated open reuses healthy companions without more splits or commands', async (t) => {
  const { workspace, calls } = await fixture(t);
  await workspace.act('open');
  const mutations = calls.filter((c) => ['split', 'run', 'rename'].includes(c[1])).length;
  await workspace.act('open');
  assert.equal(calls.filter((c) => ['split', 'run', 'rename'].includes(c[1])).length, mutations);
});

test('R1: rejected persisted ownership never mutates panes or becomes admitted on repeat', async (t) => {
  const mismatches = {
    sessionId: (saved) => { saved.primary.sessionId = 'prior-session'; },
    sessionFile: (saved) => { saved.primary.sessionFile = 'prior-session.jsonl'; },
    socket: (saved) => { saved.primary.inode += 1; },
    panes: (saved) => { saved.panes.push({ ...saved.panes[0] }); },
  };
  for (const [name, change] of Object.entries(mismatches)) {
    await t.test(name, async (t) => {
      const { workspace, calls, panes } = await fixture(t);
      await workspace.act('open');
      const saved = JSON.parse(await readFile(workspace.file, 'utf8'));
      change(saved);
      const rejected = JSON.stringify(saved);
      await writeFile(workspace.file, rejected);
      // Keep real returned IDs and live process/probe facts; only disk admission is stale.
      const subject = new HerdrWorkspace({ ...workspace.context, env: workspace.env, client: workspace.client, probe: workspace.probe });
      const observations = [];
      for (const action of ['open', 'open', 'close']) {
        const start = calls.length;
        let error;
        try { await subject.act(action); } catch (failure) { error = failure.message; }
        observations.push({
          error,
          mutations: calls.slice(start).filter((c) => ['split', 'run', 'rename', 'close'].includes(c[1])),
          record: await readFile(workspace.file, 'utf8'),
          admitted: subject.state !== undefined,
          panes: [...panes.keys()],
        });
      }
      for (const observed of observations) {
        assert.match(observed.error || '', /Ownership state mismatched; no layout changes/);
        assert.deepEqual(observed.mutations, []);
        assert.equal(observed.record, rejected);
        assert.equal(observed.admitted, false);
        assert.deepEqual(observed.panes, ['caller', 'returned-1', 'returned-2']);
      }
      const start = calls.length;
      await subject.shutdown();
      assert.deepEqual(calls.slice(start).filter((c) => ['split', 'run', 'rename', 'close'].includes(c[1])), []);
    });
  }
});

test('A manually closed companion rebuilds only owned topology', async (t) => {
  const { workspace, panes } = await fixture(t);
  await workspace.act('open');
  panes.delete('returned-1');
  await workspace.act('open');
  assert.deepEqual([...panes.keys()], ['caller', 'returned-3', 'returned-4']);
});

test('Close leaves primary and unrelated panes intact', async (t) => {
  const { workspace, panes } = await fixture(t);
  await workspace.act('open');
  panes.set('unrelated', { pane_id: 'unrelated' });
  await workspace.act('close');
  assert.deepEqual([...panes.keys()], ['caller', 'unrelated']);
  await workspace.act('close');
});

test('Stale returned IDs never authorize a replacement terminal', async (t) => {
  const { workspace, panes, calls } = await fixture(t);
  await workspace.act('open');
  panes.get('returned-2').terminal_id = 'replacement';
  const start = calls.length;
  await assert.rejects(workspace.act('open'), /no longer proven-owned/);
  assert.ok(!calls.slice(start).some((c) => ['run', 'rename', 'close'].includes(c[1]) && c[2] === 'returned-2'));
});

test('A different foreground process in a saved companion is not killed', async (t) => {
  const { workspace, processes, calls } = await fixture(t);
  await workspace.act('open');
  processes.set('returned-2', { foreground_processes: [{ pid: 999, name: 'editor' }] });
  const start = calls.length;
  await assert.rejects(workspace.act('close'), /ownership\/process changed/);
  assert.ok(!calls.slice(start).some((c) => c[1] === 'close'));
});

test('A lost split reply persists uncertainty and never blindly retries', async (t) => {
  const { workspace, calls } = await fixture(t, { fail: (args) => args[1] === 'split' });
  await assert.rejects(workspace.act('open'), /transport failed/);
  await assert.rejects(workspace.act('open'), /outcome uncertain/);
  assert.equal(calls.filter((c) => c[1] === 'split').length, 1);
  assert.equal(JSON.parse(await readFile(workspace.file)).uncertain, true);
});

test('A partial second split failure cleans no unknown resources', async (t) => {
  const { workspace, calls } = await fixture(t, { fail: (args, all) => args[1] === 'split' && all.filter((c) => c[1] === 'split').length === 2 });
  await assert.rejects(workspace.act('open'), /transport failed/);
  assert.equal(workspace.state.panes.length, 0);
  assert.deepEqual(calls.filter((c) => c[1] === 'close').map((c) => c[2]), ['returned-1']);
  assert.equal(workspace.state.uncertain, true);
});

test('No Herdr and non-foreground native identity refuse before any mutation', async (t) => {
  const { workspace, calls, processes } = await fixture(t);
  workspace.env.HERDR_ENV = '';
  await assert.rejects(workspace.act('open'), /inside Herdr/);
  assert.equal(calls.length, 0);
  workspace.env.HERDR_ENV = '1';
  processes.set('caller', { foreground_processes: [{ pid: -1 }] });
  await assert.rejects(workspace.act('open'), /not the foreground native Pi/);
  assert.ok(!calls.some((c) => c[1] === 'split'));
});

test('An unrelated initial pane refuses layout mutation', async (t) => {
  const { workspace, panes, calls } = await fixture(t);
  panes.set('unrelated', { pane_id: 'unrelated' });
  await assert.rejects(workspace.act('open'), /unrelated layout unchanged/);
  assert.ok(!calls.some((c) => c[1] === 'split'));
});

test('Concurrent opens are rejected rather than racing duplicate splits', async (t) => {
  const { workspace } = await fixture(t);
  const first = workspace.act('open');
  await assert.rejects(workspace.act('open'), /already in progress/);
  await first;
});

test('Shutdown closes proven companions and refuses subsequent open', async (t) => {
  const { workspace, panes } = await fixture(t);
  await workspace.act('open');
  await workspace.shutdown();
  assert.deepEqual([...panes.keys()], ['caller']);
  await workspace.shutdown();
  await assert.rejects(workspace.act('open'), /shutting down/);
});
