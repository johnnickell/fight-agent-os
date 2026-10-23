import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, rm, readFile, writeFile, readdir, realpath, rename } from 'node:fs/promises';
import { randomUUID } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import net from 'node:net';
import { HerdrWorkspace } from '../src/herdr/workspace.mjs';
import { HerdrActivity, validateSelection } from '../src/herdr/activity.mjs';
import { RegisteredChildren } from '../src/workspace/registered-children.mjs';

// Owned orchestration contracts, not assertions about Herdr's implementation or UI.
async function fixture(t, options = {}) {
  const cwd = await realpath(await mkdtemp(join(tmpdir(), 'fight-herdr-')));
  const socket = net.createServer();
  const socketPath = join(cwd, 'herdr.sock');
  await new Promise((done) => socket.listen(socketPath, done));
  t.after(async () => { await new Promise((done) => socket.close(done)); await rm(cwd, { recursive: true, force: true }); });
  const primary = { pane_id: 'caller', terminal_id: 'native-terminal', tab_id: 'tab', workspace_id: 'workspace' };
  const panes = new Map([['caller', primary]]);
  const processes = new Map([['caller', { foreground_processes: [{ pid: process.pid }] }]]);
  const calls = [], activities = new Map();
  t.after(() => { for (const activity of activities.values()) activity.close(); });
  let sequence = 0;
  const client = { async call(...args) {
    calls.push(args);
    const [group, action, id] = args;
    if (group !== 'pane') throw Error('Unsupported operation');
    if (options.fail?.(args, calls)) throw Error('transport failed');
    if (action === 'current') return { pane: primary };
    if (action === 'process-info') {
      const paneId = id === '--current' ? 'caller' : args[3];
      const info = processes.get(paneId);
      return { process_info: options.processInfo?.(paneId, info, calls) ?? info };
    }
    if (action === 'layout') return { layout: { panes: [...panes.values()], zoomed: false } };
    if (action === 'get') { if (!panes.has(id)) throw Error('pane-not-found'); return { pane: panes.get(id) }; }
    if (action === 'split') {
      const pane = { ...primary, pane_id: `returned-${++sequence}`, terminal_id: `terminal-${sequence}` };
      panes.set(pane.pane_id, pane);
      processes.set(pane.pane_id, options.shellStartup?.(args, sequence) ?? { shell_pid: sequence, foreground_processes: [{ pid: sequence, name: 'zsh' }] });
      return { pane };
    }
    if (action === 'run') {
      processes.set(id, { foreground_processes: [{ pid: 100 + sequence, name: 'node' }] });
      const native = workspace.activityContext;
      if (workspace.state.panes[0].paneId === id) activities.set(id, new HerdrActivity(
        { port: native.server.port, token: native.token, ...native.snapshot() }, () => {},
        { reader: options.reader || ((root, changed) => new RegisteredChildren(root, changed, { preferences: false, discover: () => [] })) },
      ));
      return {};
    }
    if (action === 'close') { activities.get(id)?.close(); panes.delete(id); return {}; }
    if (action === 'rename') return {};
    throw Error('Unsupported action');
  } };
  let workspace;
  const probe = async (file, token) => {
    const owned = workspace.state?.panes.find((p) => p.token === token);
    const info = owned && processes.get(owned.paneId);
    const activity = activities.get(owned?.paneId);
    return info?.foreground_processes[0]?.name === 'node' ? {
      pid: info.foreground_processes[0].pid, paneId: owned.paneId,
      ...(activity ? { generation: activity.binding.generation, invalidated: activity.invalidated === true } : {}),
    } : undefined;
  };
  workspace = new HerdrWorkspace({ cwd, sessionId: 'actual-session', env: { HERDR_ENV: '1', HERDR_SOCKET_PATH: socketPath, HERDR_PANE_ID: 'caller', HERDR_TAB_ID: 'tab', HERDR_WORKSPACE_ID: 'workspace' }, client, probe, ...options.context });
  t.after(() => workspace.invalidate());
  return { workspace, calls, panes, processes, primary, activities };
}

test('Open keeps the native process and uses returned IDs, no-focus, same cwd and two truthful labels', async (t) => {
  const { workspace, calls, panes } = await fixture(t);
  await workspace.act('open');
  assert.equal(panes.size, 3);
  const splits = calls.filter((c) => c[1] === 'split');
  assert.equal(splits[0][2], 'caller');
  assert.equal(splits[1][2], 'returned-1');
  assert.ok(splits.every((c) => c.includes('--no-focus') && c.includes(workspace.context.cwd)));
  assert.deepEqual(calls.filter((c) => c[1] === 'rename').map((c) => c[3]), ['Child activity — observation only', 'Runtime logs — placeholder']);
  assert.ok(calls.filter((c) => ['run', 'close', 'rename'].includes(c[1])).every((c) => c[2] !== 'caller'));
  assert.equal(JSON.parse(await readFile(workspace.file)).primary.pid, process.pid);
});

test('QA-01: newly split shells settle before initial admission and the final input check', async (t) => {
  for (const transientAt of [2, 3, 'after-rename']) {
    await t.test(`startup process sample ${transientAt}`, async (t) => {
      const samples = new Map();
      const interrupted = new Set();
      const { workspace, calls, panes } = await fixture(t, {
        processInfo: (id, info, all) => {
          if (id === 'caller' || !info?.shell_pid) return info;
          const sample = (samples.get(id) || 0) + 1;
          samples.set(id, sample);
          // Real Herdr observation: prompt initialization shares the shell's process group.
          const transient = sample === transientAt || transientAt === 'after-rename' && !interrupted.has(id) && all.some((c) => c[1] === 'rename' && c[2] === id);
          if (transient) interrupted.add(id);
          return transient ? {
            ...info, foreground_process_group_id: info.shell_pid,
            foreground_processes: [...info.foreground_processes, { pid: 900, name: 'git' }],
          } : info;
        },
      });
      await workspace.act('open');
      assert.equal(panes.size, 3);
      assert.deepEqual(calls.filter((c) => c[1] === 'run').map((c) => c[2]), ['returned-1', 'returned-2']);
      await workspace.act('open');
      assert.equal(calls.filter((c) => c[1] === 'split').length, 2);
      await workspace.act('close');
      assert.deepEqual([...panes.keys()], ['caller']);
    });
  }
});

test('QA-01: readiness never submits to or cleans a changed terminal, shell or foreground job', async (t) => {
  for (const change of ['terminal', 'shell', 'foreground', 'startup-job', 'busy']) {
    await t.test(change, async (t) => {
      let subject;
      const { workspace, calls, panes } = await fixture(t, {
        processInfo: (id, info, all) => {
          if (id === 'caller' || !info?.shell_pid) return info;
          const sample = all.filter((c) => c[1] === 'process-info' && c[3] === id).length;
          if (sample < (change === 'terminal' ? 1 : 2)) return info;
          if (change === 'terminal') { subject.panes.get(id).terminal_id = 'replacement'; return info; }
          if (change === 'shell') return { ...info, shell_pid: 777, foreground_processes: [{ pid: 777, name: 'zsh' }] };
          if (change === 'foreground' || change === 'startup-job') return { ...info, foreground_process_group_id: 778, foreground_processes: [{ pid: 778, name: change === 'startup-job' ? 'printf' : 'editor' }] };
          return { ...info, foreground_process_group_id: info.shell_pid, foreground_processes: [...info.foreground_processes, { pid: 779, name: 'git' }] };
        },
      });
      subject = { panes };
      await assert.rejects(workspace.act('open'), /no input submitted/);
      assert.equal(calls.filter((c) => ['run', 'rename', 'close'].includes(c[1])).length, 0);
      assert.deepEqual([...panes.keys()], ['caller', 'returned-1']);
      const start = calls.length;
      await assert.rejects(workspace.act('open'));
      await assert.rejects(workspace.act('close'));
      assert.equal(calls.slice(start).filter((c) => ['split', 'run', 'rename', 'close'].includes(c[1])).length, 0);
    });
  }
});

test('QA-01: companion startup excludes personal zsh hooks across recovery and close/reopen', async (t) => {
  const { workspace, calls, panes } = await fixture(t, {
    shellStartup: (args, pid) => {
      // Actual failure: an inherited prompt hook starts printf in a separate foreground group.
      // Only a companion-local startup directory avoids that hook; never admit that foreground job.
      const isolated = args.some((arg, index) => args[index - 1] === '--env' && arg.startsWith('ZDOTDIR='));
      return isolated
        ? { shell_pid: pid, foreground_process_group_id: pid, foreground_processes: [{ pid, name: 'zsh' }] }
        : { shell_pid: pid, foreground_process_group_id: 900, foreground_processes: [{ pid: 900, name: 'printf' }] };
    },
  });
  for (let cycle = 0; cycle < 2; cycle++) {
    await workspace.act('open');
    await workspace.act('open');
    assert.equal(panes.size, 3);
    panes.delete(workspace.state.panes.at(-1).paneId);
    await workspace.act('open');
    assert.equal(panes.size, 3);
    await workspace.act('close');
    assert.deepEqual([...panes.keys()], ['caller']);
    await workspace.act('open');
    assert.equal(panes.size, 3);
    await workspace.act('close');
  }
  for (const split of calls.filter((c) => c[1] === 'split')) {
    const startup = split.find((arg) => arg.startsWith('ZDOTDIR='))?.slice('ZDOTDIR='.length);
    assert.equal(startup, join(workspace.directory, 'shell'));
    assert.deepEqual(await readdir(startup), []);
  }
  assert.equal(calls.filter((c) => c[1] === 'run').length, 12);
});

test('Repeated open reuses healthy companions even with unavailable custody or intentionally closed readers', async (t) => {
  const { workspace, calls, activities } = await fixture(t);
  await workspace.act('open');
  const activity = activities.get(workspace.state.panes[0].paneId); await activity.tick();
  const generation = workspace.activityContext.generation;
  for (const readersOpen of [true, false]) {
    if (!readersOpen) activity.action('toggle');
    const before = calls.length; await workspace.act('open');
    assert.equal(workspace.activityContext.generation, generation);
    assert.equal(calls.slice(before).filter(c => ['split', 'run', 'rename', 'close'].includes(c[1])).length, 0);
  }
});

for (const failure of ['transient-link', 'same-path-file-replacement', 'closed-native-context']) {
  test(`Review R1: explicit open replaces ${failure} with a fresh observer, never old authority`, async t => {
    const childId = randomUUID(), custodyGeneration = randomUUID(), started = Date.now() - 100;
    const { workspace, calls, panes, activities } = await fixture(t, {
      reader: (root, changed) => new RegisteredChildren(root, changed, {
        preferences: false, discover: () => [{ path: '/controlled-custody', descriptor: {} }],
        verify: async () => { const now = Date.now(); return { parent: workspace.context.sessionId, generation: custodyGeneration,
          parentFile: workspace.activityContext.snapshot().file, at: now, children: [{
            id: childId, parent: workspace.context.sessionId, generation: custodyGeneration,
            started, order: 1, sequence: 1, receivedAt: now,
            state: 'connected', lifecycle: 'running', exitCode: null, heartbeatSeconds: 2,
            identity: { task: 'TASK-00511', role: 'work', provider: 'openai-codex', model: 'configured-only', thinking: 'high', worktree: root, attempt: '/existing-attempt' },
            history: ['safe existing progress'], omitted: 0, gaps: 0,
          }] }; },
      }),
    });
    const file = join(workspace.context.cwd, 'native.jsonl');
    await writeFile(file, 'controlled native identity'); workspace.context.sessionFile = file;
    await workspace.act('open');
    const oldNative = workspace.activityContext, old = activities.get(workspace.state.panes[0].paneId);
    await old.tick(); old.action('pin');
    const oldMetadata = await old.selection(), oldGeneration = oldNative.generation;
    assert.equal(oldMetadata.child.id, childId);
    if (failure === 'transient-link') {
      const query = old.query; old.query = async () => { throw Error('One lost native query'); };
      await old.tick(); old.query = query;
      // The native transport is restored; only this observer remains permanently invalidated.
      assert.equal(oldNative.closed, false);
    } else {
      if (failure === 'same-path-file-replacement') {
        await writeFile(file + '.replacement', 'replacement'); await rename(file + '.replacement', file);
      } else oldNative.close();
      await old.tick(); assert.equal(oldNative.closed, true);
    }
    assert.equal(old.invalidated, true); assert.equal(old.children.sources.size, 0);
    assert.equal(old.children.pin, null); await assert.rejects(old.selection());
    const before = calls.length;
    await workspace.act('open');
    assert.notEqual(workspace.activityContext.generation, oldGeneration, 'Explicit recovery must issue a fresh generation');
    const next = activities.get(workspace.state.panes[0].paneId); await next.tick();
    const nextMetadata = await next.selection();
    assert.equal(nextMetadata.child.id, childId, 'Freshly rediscover existing work, not a new execution');
    assert.equal(nextMetadata.child.pinned, false);
    assert.throws(() => validateSelection(next.binding, oldMetadata));
    await assert.rejects(old.selection()); assert.equal(old.invalidated, true); assert.equal(oldNative.closed, true);
    assert.equal(panes.size, 3); assert.ok(panes.has('caller'));
    assert.ok(calls.slice(before).filter(c => ['close', 'run', 'rename'].includes(c[1])).every(c => c[2] !== 'caller'));
    const stable = calls.length; await workspace.act('open');
    assert.equal(calls.slice(stable).filter(c => ['split', 'run', 'rename', 'close'].includes(c[1])).length, 0);
  });
}

test('Review R1: invalidation does not bypass uncertain outcomes or unrelated topology', async t => {
  for (const hold of ['uncertain', 'unrelated', 'changed-process']) await t.test(hold, async t => {
    const { workspace, calls, panes, processes, activities } = await fixture(t);
    await workspace.act('open');
    const old = activities.get(workspace.state.panes[0].paneId);
    old.query = async () => { throw Error('Lost native query'); }; await old.tick();
    if (hold === 'uncertain') workspace.state.uncertain = true;
    if (hold === 'unrelated') panes.set('unrelated', { pane_id: 'unrelated' });
    if (hold === 'changed-process') processes.set(workspace.state.panes.at(-1).paneId, { foreground_processes: [{ pid: 999, name: 'editor' }] });
    const before = calls.length; await assert.rejects(workspace.act('open'));
    assert.equal(calls.slice(before).filter(c => ['split', 'run', 'rename'].includes(c[1])).length, 0);
    assert.ok(panes.has('caller'));
    if (hold === 'unrelated') assert.ok(panes.has('unrelated'));
    if (hold === 'uncertain') assert.equal(workspace.state.uncertain, true);
    if (hold === 'changed-process') assert.equal(calls.slice(before).filter(c => c[1] === 'close').length, 0);
  });
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
