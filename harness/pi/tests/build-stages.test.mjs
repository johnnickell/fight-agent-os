import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { prepareStage, runStage } from '../src/build/stages.mjs';
import { bindStageSession, closeWorkflow, stageDefinitions, stageIdentity, isSdkSession,
  publishWorkflow, sessionWorkflow, stageBinding, rememberSdkSession } from '../src/build/authority.mjs';
import { stageHistory } from '../src/build/history.mjs';
import { WorkflowAccess } from '../src/workflow/access.mjs';
import { SubagentObserver } from '../src/observation/subagents.mjs';

// Controlled owning Harness contracts, not real independent stage acceptance.
function fixture(t, role = 'engineer') {
  const root = realpathSync(mkdtempSync(join(tmpdir(), 'fight-stages-')));
  t.after(() => rmSync(root, { recursive: true, force: true }));
  const git = (...args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  git('init', '--initial-branch=topic/consumer');
  mkdirSync(join(root, 'work-items')); writeFileSync(join(root, 'CLAUDE.md'), 'Controlled governing instructions');
  writeFileSync(join(root, 'work-items/task.md'), '# TASK-00561\nState: approved-to-code (fixture only).\n');
  git('add', '.'); git('-c', 'user.name=Harness fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Controlled fixture');
  const attempt = join(root, '.runs/build/TASK-00561/run', role);
  mkdirSync(attempt, { recursive: true }); writeFileSync(join(attempt, 'request.md'), 'Fresh bounded request, no inherited dialogue.');
  const entries = [], parent = { getSessionId: () => 'actual-parent', getEntries: () => entries };
  const ctx = { cwd: root, sessionManager: parent,
    getSystemPrompt: () => '<project_context>\nControlled governing instructions\n</project_context>',
    modelRegistry: { find: (provider, id) => ({ provider, id, reasoning: true }) } };
  const model = { provider: 'qualified', id: 'exact-model', reasoning: true };
  const context = join(root, '.runs/build/TASK-00561/run/context.md'); writeFileSync(context, 'Resolved fixture context for TASK-00561.');
  const request = { task: 'TASK-00561', role, checkout: root, base: root, branch: 'topic/consumer', context,
    record: join(root, 'work-items/task.md'), attempt, head: git('rev-parse', 'HEAD'),
    provider: model.provider, model: model.id, thinking: 'high', timeout: 10, repair: false, repairsUsed: 0, repairLimit: 5 };
  const profile = JSON.parse(readFileSync(new URL('../profiles/subagents.json', import.meta.url)));
  const registry = { capabilities: { beforeSessionStart: true, boundRootSession: true }, hasRunning: () => false,
    spawn() { throw new Error('possibly-started'); }, getRecord() {} };
  const options = { registry, profile };
  publishWorkflow(parent, new WorkflowAccess('build', { taskId: request.task, checkout: root }));
  const child = () => {
    const sessionDir = join(root, '.runs/tasks/TASK-00561/runs/sessions'); mkdirSync(sessionDir, { recursive: true });
    const id = randomUUID();
    return { messages: [], model, thinkingLevel: 'high', systemPrompt: 'Controlled governing instructions',
      isStreaming: false, subscribe() { return () => {}; }, extensionRunner: { hasHandlers: () => true },
      resourceLoader: { getExtensions: () => ({ errors: [], extensions: [] }) },
      sessionManager: { getSessionId: () => id, getCwd: () => root, getSessionDir: () => sessionDir,
        getSessionFile: () => join(sessionDir, id + '.jsonl'), getEntries: () => [] } };
  };
  const assignment = { parent: 'actual-parent', task: request.task, role, skill: stageDefinitions[role].skill,
    effects: stageDefinitions[role].effects, checkout: root, attempt, provider: model.provider, model: model.id,
    thinking: 'high', requiredExtensions: [], instructions: ['Controlled governing instructions'], sessionRoots: [join(root, '.runs/tasks/TASK-00561/runs')] };
  return { root, ctx, request, options, child, assignment, registry, profile, entries };
}

test('default route requires actual session-local Build authority and excludes SDK subordinates', t => {
  const f = fixture(t), sibling = { getSessionId: f.ctx.sessionManager.getSessionId, getEntries: () => [] };
  assert.equal(sessionWorkflow(sibling), undefined);
  assert.throws(() => prepareStage({ ...f.ctx, sessionManager: sibling }, f.request, f.options), /build-authority-required/);
  // Even a Build-shaped workflow cannot turn an SDK subordinate into a coordinator.
  publishWorkflow(sibling, sessionWorkflow(f.ctx.sessionManager)); rememberSdkSession(sibling);
  assert.throws(() => prepareStage({ ...f.ctx, sessionManager: sibling }, f.request, f.options), /build-authority-required/);
  closeWorkflow(f.ctx.sessionManager);
  assert.throws(() => prepareStage(f.ctx, f.request, f.options), /build-authority-required/);
  publishWorkflow(f.ctx.sessionManager, new WorkflowAccess('build', { taskId: f.request.task, checkout: f.root }));
  assert.equal(prepareStage(f.ctx, f.request, f.options).role, 'engineer');
});

for (const role of Object.keys(stageDefinitions)) {
  test(`${role} requires real Build authority, exact target and a fresh attempt`, t => {
    const f = fixture(t, role);
    for (const skill of ['work', 'review', 'qa', 'land', 'sync']) {
      publishWorkflow(f.ctx.sessionManager, new WorkflowAccess(skill, { taskId: f.request.task, checkout: f.root }));
      assert.throws(() => prepareStage(f.ctx, f.request, f.options), /build-authority-required/);
    }
    publishWorkflow(f.ctx.sessionManager, new WorkflowAccess('build', { taskId: f.request.task, checkout: f.root }));
    for (const patch of [{ task: 'TASK-99999' }, { checkout: join(f.root, 'foreign') }])
      assert.throws(() => prepareStage(f.ctx, { ...f.request, ...patch }, f.options), /build-authority-required/);
    for (const [patch, reason] of [[{ head: '0'.repeat(40) }, /candidate-changed/], [{ branch: 'other' }, /candidate-changed/],
      [{ base: join(f.root, 'work-items') }, /base-mismatch/], [{ context: f.request.record }, /target-context-mismatch/],
      [{ thinking: 'adaptive' }, /model-unavailable/],
      [{ timeout: 0 }, /timeout-invalid/], [{ role: 'build' }, /assignment-invalid/]])
      assert.throws(() => prepareStage(f.ctx, { ...f.request, ...patch }, f.options), reason);
    const ready = prepareStage(f.ctx, f.request, f.options);
    assert.equal(ready.role, role); assert.equal(ready.definition.skill, stageDefinitions[role].skill);
    assert.ok(ready.prompt.includes('approved-to-code')); assert.ok(!ready.prompt.includes('Parent transcript'));
    assert.throws(() => prepareStage(f.ctx, f.request, f.options), /EEXIST/);
  });

  test(`${role} binding and observation derive only from the initialized actual session`, t => {
    const f = fixture(t, role), child = f.child(), sibling = f.child(), before = { ...process.env };
    for (const patch of [{ messages: [{ role: 'user' }] }, { thinkingLevel: 'medium' }, { model: { provider: 'other', id: 'exact-model' } },
      { systemPrompt: 'Missing governing instructions' }]) assert.throws(() => bindStageSession({ ...f.child(), ...patch }, f.assignment), /stage-/);
    assert.throws(() => bindStageSession(f.child(), { ...f.assignment, effects: ['all'] }), /assignment-invalid/);
    const binding = bindStageSession(child, f.assignment);
    assert.equal(stageIdentity(child, 'actual-parent'), null);
    publishWorkflow(child.sessionManager, new WorkflowAccess(f.assignment.skill, { taskId: f.request.task, checkout: f.root }));
    assert.deepEqual(stageIdentity(child, 'actual-parent'), { task: f.request.task, role, attempt: f.request.attempt });
    assert.equal(stageIdentity(child, 'foreign-parent'), null); assert.equal(stageBinding(sibling.sessionManager), undefined);
    assert.equal(isSdkSession(child.sessionManager), true); assert.equal(sessionWorkflow(f.ctx.sessionManager).skill, 'build');
    const frames = [], record = { id: 'record', rootSessionId: 'actual-parent', status: 'running', session: child };
    const observer = new SubagentObserver('actual-parent', frame => frames.push(frame), { registry: () => ({ getRecord: () => record }) });
    observer.started({ id: record.id }); assert.equal(frames.at(-1).identity.role, role); observer.close();
    assert.throws(() => bindStageSession(child, f.assignment), /not-fresh/);
    closeWorkflow(child.sessionManager); assert.equal(binding.live, false); assert.equal(stageIdentity(child, 'actual-parent'), null);
    assert.deepEqual({ ...process.env }, before);
  });
}

test('unsupported hooks, unsafe profile, concurrent work and unavailable models refuse before dispatch', t => {
  const f = fixture(t);
  for (const capabilities of [{}, { beforeSessionStart: true }, { boundRootSession: true }])
    assert.throws(() => prepareStage(f.ctx, f.request, { ...f.options, registry: { ...f.registry, capabilities } }), /capability-unavailable/);
  assert.throws(() => prepareStage(f.ctx, f.request, { ...f.options, registry: { ...f.registry, hasRunning: () => true } }), /already-running/);
  for (const patch of [{ worktreeIsolation: true }, { workflowsEnabled: true }, { schedulingEnabled: true }, { maxSubagentDepth: 2 }, { packageAgents: [] }])
    assert.throws(() => prepareStage(f.ctx, f.request, { ...f.options, profile: { ...f.profile, ...patch } }), /profile-unqualified/);
  f.ctx.modelRegistry.find = () => undefined; assert.throws(() => prepareStage(f.ctx, f.request, f.options), /model-unavailable/);
});

test('simultaneous role contexts cannot overwrite each other or inherit the native launcher', t => {
  const f = fixture(t), bound = [], environment = { ...process.env };
  for (const [role, definition] of Object.entries(stageDefinitions)) {
    const session = f.child();
    bindStageSession(session, { ...f.assignment, role, skill: definition.skill, effects: definition.effects });
    publishWorkflow(session.sessionManager, new WorkflowAccess(definition.skill, { taskId: f.request.task, checkout: f.root }));
    bound.push([role, session]);
  }
  for (const [role, session] of bound) assert.equal(stageIdentity(session, 'actual-parent').role, role);
  closeWorkflow(bound[0][1].sessionManager);
  for (const [role, session] of bound.slice(1)) assert.equal(stageIdentity(session, 'actual-parent').role, role);
  assert.equal(sessionWorkflow(f.ctx.sessionManager).skill, 'build'); assert.deepEqual({ ...process.env }, environment);
});

test('actual repository/context and task-private session storage remain required without inventing an AGENTS file', t => {
  const f = fixture(t), foreign = fixture(t);
  assert.throws(() => prepareStage(f.ctx, { ...f.request, base: foreign.root }, f.options), /base-mismatch/);
  assert.throws(() => prepareStage(f.ctx, { ...f.request, record: foreign.request.record }, f.options), /target-context-mismatch/);
  f.ctx.getSystemPrompt = () => '';
  assert.throws(() => prepareStage(f.ctx, f.request, f.options), /governing-context-missing/);
  f.ctx.getSystemPrompt = () => 'Native base prompt with no discovered project context';
  assert.deepEqual(prepareStage(f.ctx, { ...f.request, role: 'work' }, f.options).instructions, []);
  const child = f.child(); child.sessionManager.getSessionFile = () => join(f.root, 'foreign.jsonl');
  assert.throws(() => bindStageSession(child, f.assignment), /storage-unqualified/);
  const inherited = f.child(); inherited.sessionManager.getEntries = () => [{ type: 'message' }];
  assert.throws(() => bindStageSession(inherited, f.assignment), /not-fresh/);
});

test('shared repairs and exact role pins survive assessor changes and reload entries without reset', () => {
  const request = { task: 'TASK-00561', role: 'engineer', checkout: '/target', base: '/base', provider: 'p', model: 'm', thinking: 'high',
    repair: false, repairsUsed: 2, repairLimit: 5 };
  const entry = data => ({ type: 'custom', customType: 'fight-build-sdk-attempt-v1', data });
  const entries = [entry({ ...request, executionSettled: true })];
  stageHistory(entries, { ...request, role: 'reviewer' });
  assert.throws(() => stageHistory(entries, { ...request, repairsUsed: 0 }), /accounting-changed/);
  assert.throws(() => stageHistory(entries, { ...request, repairLimit: 10 }), /accounting-changed/);
  assert.throws(() => stageHistory(entries, { ...request, repair: true }), /accounting-changed/);
  stageHistory(entries, { ...request, repair: true, repairsUsed: 3 });
  for (const key of ['provider', 'model', 'thinking']) assert.throws(() => stageHistory(entries, { ...request, [key]: 'changed' }), /pin-changed/);
  entries.push(entry({ ...request, role: 'qa', executionSettled: true }));
  stageHistory(entries, { ...request, repair: true, repairsUsed: 3 });
  assert.throws(() => stageHistory(entries, { ...request, role: 'qa', repair: true, repairsUsed: 3 }), /accounting-invalid/);
  assert.throws(() => stageHistory([entry({ ...request, executionSettled: false })], request), /unreconciled/);
  assert.throws(() => stageHistory(entries, { ...request, checkout: '/other' }), /target-changed/);
});

function engine(f, behavior = 'completed', deferPromise = false) {
  let record, calls = 0, signal;
  const registry = { ...f.registry, getRecord: () => record, spawn(_pi, _ctx, type, prompt, options) {
    calls++; assert.equal(type, stageDefinitions[f.request.role].agent); assert.equal(options.inheritContext, false);
    assert.equal(options.cwd, f.root); assert.ok(!prompt.includes('Parent transcript'));
    const session = f.child(); signal = options.signal;
    session.resourceLoader.getExtensions = () => ({ errors: [], extensions: ['workflow-extension.ts', 'build-stages-extension.ts']
      .map(path => ({ path: fileURLToPath(new URL('../src/' + path, import.meta.url)) })) });
    let settle, reject;
    const promise = new Promise((resolve, fail) => { settle = resolve; reject = fail; });
    record = { id: 'record', rootSessionId: 'actual-parent', startedAt: 1, status: 'running',
      abortController: new AbortController(), promise: deferPromise ? undefined : promise };
    if (deferPromise) record.status = 'queued';
    queueMicrotask(async () => {
      try {
        if (deferPromise) { record.status = 'running'; record.startedAt++; }
        await options.beforeSessionStart(session, options.signal);
        publishWorkflow(session.sessionManager, new WorkflowAccess(f.assignment.skill, { taskId: f.request.task, checkout: f.root }));
        stageBinding(session.sessionManager).admitted = true; record.session = session;
        if (typeof behavior === 'function') behavior({ record, session, settle, reject, signal, promise });
        else if (['completed', 'error', 'steered', 'aborted'].includes(behavior)) { record.status = behavior; settle(); }
        else if (behavior === 'record-loss') record = undefined;
        else options.signal.addEventListener('abort', () => { record.status = 'stopped'; settle(); }, { once: true });
      } catch { record.status = 'error'; settle(); }
    });
    return record.id;
  } };
  return { registry, pi: { events: { on: () => () => {}, emit() {} } }, calls: () => calls, signal: () => signal };
}

for (const behavior of ['completed', 'error', 'steered', 'aborted', 'timeout', 'abort', 'shutdown', 'record-loss'])
  test(`SDK ${behavior} is execution evidence only, never acceptance or an executor retry`, async t => {
    const f = fixture(t, 'reviewer'), e = engine(f, behavior), caller = new AbortController(); let shutdown;
    if (behavior === 'timeout') f.request.timeout = 1;
    if (behavior === 'abort') setTimeout(() => caller.abort(), 100);
    if (behavior === 'shutdown') setTimeout(() => shutdown?.(), 100);
    const outcome = await runStage(e.pi, f.ctx, f.request, { ...f.options, registry: e.registry, signal: caller.signal,
      onShutdown(cancel) { shutdown = cancel; return () => {}; } });
    assert.equal(e.calls(), 1); assert.equal(outcome.acceptance, false); assert.equal(outcome.admitted, true);
    assert.equal(outcome.reportObservation.digest, null); assert.equal(outcome.reportObservation.changed, false);
    if (behavior === 'record-loss') { assert.equal(outcome.state, 'uncertain'); assert.equal(outcome.executionSettled, false); assert.equal(e.signal().aborted, true); }
    else if (['timeout', 'abort', 'shutdown'].includes(behavior)) { assert.equal(outcome.state, 'stopped'); assert.ok(outcome.reason); }
    else assert.equal(outcome.state, behavior);
    assert.equal(JSON.parse(readFileSync(join(f.request.attempt, 'sdk-outcome.json'))).acceptance, false);
    await assert.rejects(runStage(e.pi, f.ctx, f.request, { ...f.options, registry: e.registry }), /EEXIST|attempt-not-fresh/);
  });

for (const transition of ['settled-before-replacement', 'late-resolution', 'late-rejection', 'promise-only',
  'foreground-resume', 'same-clock-resume', 'queued-resume', 'replacement-already-settled'])
  test(`same-record ${transition} holds the admitted stage instead of proving quiescence`, async t => {
    const f = fixture(t, 'reviewer'), timers = []; let releaseReplacement;
    t.after(() => { timers.forEach(clearTimeout); releaseReplacement?.(); });
    const e = engine(f, ({ record, session, settle, reject }) => {
      t.after(settle);
      record.status = 'completed';
      if (transition === 'settled-before-replacement') { session.isStreaming = true; settle(); }
      // Allow a terminal poll to subscribe before the record is reused.
      timers.push(setTimeout(() => {
        if (!['late-resolution', 'late-rejection'].includes(transition)) settle();
        if (!['foreground-resume', 'same-clock-resume', 'queued-resume'].includes(transition)) {
          record.promise = new Promise(resolve => { releaseReplacement = resolve; });
          if (transition === 'replacement-already-settled') releaseReplacement();
        }
        if (transition !== 'promise-only' && transition !== 'same-clock-resume' && transition !== 'queued-resume') record.startedAt++;
        if (transition === 'same-clock-resume') record.abortController = new AbortController();
        record.status = transition === 'queued-resume' ? 'queued' : 'stopped';
        session.isStreaming = false;
        if (transition === 'late-resolution') timers.push(setTimeout(settle, 0));
        if (transition === 'late-rejection') timers.push(setTimeout(() => reject(new Error('prior-run-failed')), 0));
        // Keep the old implementation finite even for a queued transition.
        if (transition === 'queued-resume') timers.push(setTimeout(() => { record.status = 'stopped'; }, 300));
      }, 150));
    });
    const outcome = await runStage(e.pi, f.ctx, f.request, { ...f.options, registry: e.registry });
    assert.equal(outcome.executionSettled, false);
    assert.equal(outcome.state, 'uncertain'); assert.equal(outcome.reason, 'stage-run-changed');
    assert.equal(e.signal().aborted, true); assert.equal(e.calls(), 1); assert.equal(outcome.acceptance, false);
    const entry = { type: 'custom', customType: 'fight-build-sdk-attempt-v1', data: outcome };
    assert.throws(() => stageHistory([entry], { ...f.request, role: 'qa' }), /unreconciled/);
  });

test('initial promise installation and current streaming must both finish before settlement', async t => {
  const f = fixture(t), timers = []; let streamingFinished = false;
  t.after(() => timers.forEach(clearTimeout));
  const e = engine(f, ({ record, session, settle, promise }) => {
    session.isStreaming = true;
    timers.push(setTimeout(() => { record.promise = promise; record.status = 'completed'; settle(); }, 150));
    timers.push(setTimeout(() => { session.isStreaming = false; streamingFinished = true; }, 350));
  }, true);
  const outcome = await runStage(e.pi, f.ctx, f.request, { ...f.options, registry: e.registry });
  assert.equal(streamingFinished, true); assert.equal(outcome.executionSettled, true);
  assert.equal(outcome.state, 'completed'); assert.equal(outcome.reason, null); assert.equal(e.calls(), 1);
});

test('pre-abort is not-started; possibly-started synchronous exceptions stay uncertain', async t => {
  const f = fixture(t);
  const stopped = await runStage({}, f.ctx, f.request, { ...f.options, signal: AbortSignal.abort() });
  assert.equal(stopped.state, 'not-started'); assert.equal(stopped.executionSettled, true);
  const attempt = join(f.root, '.runs/build/TASK-00561/run/throwing'); mkdirSync(attempt);
  writeFileSync(join(attempt, 'request.md'), 'Controlled exception.');
  const uncertain = await runStage({}, f.ctx, { ...f.request, attempt }, f.options);
  assert.equal(uncertain.state, 'uncertain'); assert.equal(uncertain.executionSettled, false);
});
