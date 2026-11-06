import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { prepareEngineer, runEngineer } from '../src/build/engineer.mjs';
import { bindEngineerSession, closeWorkflow, engineerEffects, engineerIdentity, isSdkSession,
  publishWorkflow, sessionWorkflow, stageBinding, selectEngineerRoute, engineerRouteSelected, rememberSdkSession } from '../src/build/authority.mjs';
import { WorkflowAccess } from '../src/workflow/access.mjs';
import { SubagentObserver } from '../src/observation/subagents.mjs';

// Owning Harness contract checks. The engine/session transport here is controlled;
// tests/native/build-engineer.mjs separately qualifies real installed SDK sessions.
function fixture(t) {
  const root = realpathSync(mkdtempSync(join(tmpdir(), 'fight-engineer-')));
  t.after(() => rmSync(root, { recursive: true, force: true }));
  const git = (...args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  git('init', '--initial-branch=feature/engineer-fixture');
  mkdirSync(join(root, 'planning/tasks'), { recursive: true });
  writeFileSync(join(root, 'AGENTS.md'), 'Controlled governing instructions');
  writeFileSync(join(root, 'planning/tasks/00560-TASK.md'), '---\nid: TASK-00560\nstatus: in-progress\n---\n');
  git('add', '.'); git('-c', 'user.name=Harness fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Controlled fixture');
  const attempt = join(root, '.runs/build/TASK-00560/run/engineer');
  mkdirSync(attempt, { recursive: true }); writeFileSync(join(attempt, 'request.md'), 'Do the permitted remaining work.');
  const parent = { getSessionId: () => 'actual-parent' };
  const ctx = { cwd: root, sessionManager: parent, getSystemPrompt: () => '<project_context>\nControlled governing instructions\n</project_context>', modelRegistry: { find: (provider, id) => ({ provider, id, reasoning: true }) } };
  const model = { provider: 'qualified', id: 'exact-model', reasoning: true };
  const context = join(root, '.runs/build/TASK-00560/run/context.md');
  writeFileSync(context, `Controlled fixture context only, not target Work permission. Target ${root}; TASK-00560.`);
  const request = { task: 'TASK-00560', role: 'engineer', checkout: root, base: root, branch: git('branch', '--show-current'),
    context, record: join(root, 'planning/tasks/00560-TASK.md'), attempt, head: git('rev-parse', 'HEAD'),
    provider: model.provider, model: model.id, thinking: 'high', timeout: 10 };
  const profile = { worktreeIsolation: false, workflowsEnabled: false, schedulingEnabled: false,
    maxSubagentDepth: 1, disableDefaultAgents: true, fallbackSubagent: 'none', packageWorkflows: false,
    strictAgentFiles: true, packageAgents: ['@fight/agent-os-pi'] };
  const registry = { capabilities: { beforeSessionStart: true, boundRootSession: true }, hasRunning: () => false,
    spawn() { throw new Error('not-spawned'); }, getRecord() {} };
  const options = { selected: true, registry, profile };
  publishWorkflow(parent, new WorkflowAccess('build', { taskId: request.task, checkout: root }));
  const child = () => {
    const sessionDir = join(root, '.runs/tasks/TASK-00560/runs/sessions'); mkdirSync(sessionDir, { recursive: true });
    const id = randomUUID();
    return { messages: [], model, thinkingLevel: 'high', systemPrompt: 'Controlled governing instructions',
      isStreaming: false, subscribe() { return () => {}; }, extensionRunner: { hasHandlers: () => true },
      resourceLoader: { getExtensions: () => ({ errors: [], extensions: [] }) },
      sessionManager: { getSessionId: () => id, getCwd: () => root, getSessionDir: () => sessionDir,
        getSessionFile: () => join(sessionDir, id + '.jsonl'), getEntries: () => [] } };
  };
  const assignment = { parent: 'actual-parent', task: request.task, role: 'engineer', skill: 'work', effects: engineerEffects,
    checkout: root, attempt, provider: model.provider, model: model.id, thinking: 'high', requiredExtensions: [],
    instructions: ['Controlled governing instructions'], sessionRoots: [join(root, '.runs/tasks/TASK-00560/runs')] };
  return { root, ctx, request, options, child, assignment, registry, profile };
}

test('Build opt-in is live exact-session readiness, never workflow or sibling authority', () => {
  let id = 'original';
  const manager = { getSessionId: () => id }, sibling = { getSessionId: () => id };
  assert.equal(engineerRouteSelected(manager), false);
  selectEngineerRoute(manager, true);
  assert.equal(engineerRouteSelected(manager), true);
  assert.equal(sessionWorkflow(manager), undefined);
  assert.equal(engineerRouteSelected(sibling), false);
  selectEngineerRoute(sibling, true);
  closeWorkflow(manager);
  assert.equal(engineerRouteSelected(manager), false);
  assert.equal(engineerRouteSelected(sibling), true);
  selectEngineerRoute(manager, true);
  id = 'replacement';
  assert.equal(engineerRouteSelected(manager), false);
  selectEngineerRoute(manager, true);
  rememberSdkSession(manager);
  assert.equal(engineerRouteSelected(manager), false); // A child cannot borrow a host's flag.
  selectEngineerRoute(sibling, false);
  assert.equal(engineerRouteSelected(sibling), false);
});

test('only explicit SDK selection plus actual Build/TASK/checkout authority admits a launch', t => {
  const f = fixture(t);
  assert.throws(() => prepareEngineer(f.ctx, f.request, { ...f.options, selected: false }), /not-selected/);
  for (const role of ['work', 'review', 'qa', 'land']) {
    publishWorkflow(f.ctx.sessionManager, new WorkflowAccess(role, { taskId: f.request.task, checkout: f.root }));
    assert.throws(() => prepareEngineer(f.ctx, f.request, f.options), /build-authority-required/);
  }
  publishWorkflow(f.ctx.sessionManager, new WorkflowAccess('build', { taskId: 'TASK-99999', checkout: f.root }));
  assert.throws(() => prepareEngineer(f.ctx, f.request, f.options), /build-authority-required/);
  publishWorkflow(f.ctx.sessionManager, new WorkflowAccess('build', { taskId: f.request.task, checkout: f.root }));
  const ready = prepareEngineer(f.ctx, f.request, f.options);
  assert.equal(ready.model.id, f.request.model);
  assert.throws(() => prepareEngineer(f.ctx, f.request, f.options), /EEXIST/);
});

test('missing hooks, active work, unsafe profile, role, head and model refuse before dispatch', t => {
  const f = fixture(t);
  for (const capabilities of [{}, { beforeSessionStart: true }, { boundRootSession: true }]) {
    assert.throws(() => prepareEngineer(f.ctx, f.request, { ...f.options, registry: { ...f.registry, capabilities } }), /capability-unavailable/);
  }
  assert.throws(() => prepareEngineer(f.ctx, f.request, { ...f.options, registry: { ...f.registry, hasRunning: () => true } }), /already-running/);
  for (const patch of [{ worktreeIsolation: true }, { workflowsEnabled: true }, { schedulingEnabled: true }, { maxSubagentDepth: 2 }, { fallbackSubagent: 'general-purpose' }, { packageAgents: [] }]) {
    assert.throws(() => prepareEngineer(f.ctx, f.request, { ...f.options, profile: { ...f.profile, ...patch } }), /profile-unqualified/);
  }
  for (const [patch, reason] of [[{ role: 'review' }, /assignment-invalid/], [{ head: '0'.repeat(40) }, /candidate-changed/],
    [{ thinking: 'adaptive' }, /model-unavailable/], [{ timeout: 0 }, /timeout-invalid/], [{ attempt: f.root }, /attempt-outside-build/]]) {
    assert.throws(() => prepareEngineer(f.ctx, { ...f.request, ...patch }, f.options), reason);
  }
  f.ctx.modelRegistry.find = () => undefined;
  assert.throws(() => prepareEngineer(f.ctx, f.request, f.options), /model-unavailable/);
});

test('target context admits a different record layout, status vocabulary, branch and instruction discovery', t => {
  const f = fixture(t);
  const git = (...args) => execFileSync('git', ['-C', f.root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  git('branch', '-m', 'topic/consumer-change');
  rmSync(join(f.root, 'AGENTS.md'));
  rmSync(join(f.root, 'planning'), { recursive: true });
  mkdirSync(join(f.root, 'work-items'));
  f.request.record = join(f.root, 'work-items/current.md');
  writeFileSync(f.request.record, '# TASK-00560\nState: approved-to-code (controlled fixture, not actual Work).\n');
  writeFileSync(join(f.root, 'CLAUDE.md'), 'Consumer governing instructions');
  f.ctx.getSystemPrompt = () => '<project_context>\nConsumer governing instructions\n</project_context>\n\n<skills>Parent-only skills</skills>';
  f.request.branch = 'topic/consumer-change';
  const ready = prepareEngineer(f.ctx, { ...f.request, role: 'work' }, f.options);
  assert.deepEqual(ready.instructions, ['Consumer governing instructions']);
  assert.ok(ready.prompt.includes('State: approved-to-code'));
  assert.ok(ready.prompt.includes(f.request.context));
});

test('matching TASK text cannot substitute foreign target facts or missing governing context', t => {
  const f = fixture(t), foreign = fixture(t);
  for (const [patch, reason] of [
    [{ checkout: foreign.root }, /build-authority-required/],
    [{ base: foreign.root }, /base-mismatch/],
    [{ record: foreign.request.record }, /target-context-mismatch/],
    [{ context: foreign.request.context }, /target-context-mismatch/],
    [{ branch: 'other-branch' }, /candidate-changed/],
  ]) assert.throws(() => prepareEngineer(f.ctx, { ...f.request, ...patch }, f.options), reason);
  // Even a coordinator bound to the foreign label cannot cross the actual repository.
  publishWorkflow(f.ctx.sessionManager, new WorkflowAccess('build', { taskId: f.request.task, checkout: foreign.root }));
  assert.throws(() => prepareEngineer(f.ctx, { ...f.request, checkout: foreign.root }, f.options), /checkout-unregistered/);
  publishWorkflow(f.ctx.sessionManager, new WorkflowAccess('build', { taskId: f.request.task, checkout: f.root }));
  writeFileSync(f.request.record, '# TASK-99999\n');
  assert.throws(() => prepareEngineer(f.ctx, f.request, f.options), /task-identity-mismatch/);
  writeFileSync(f.request.record, '# TASK-00560\n');
  f.ctx.getSystemPrompt = () => '';
  assert.throws(() => prepareEngineer(f.ctx, f.request, f.options), /governing-context-missing/);
  f.ctx.getSystemPrompt = () => 'Native base prompt with no discovered instructions';
  const nested = join(f.root, 'nested-consumer'); mkdirSync(nested);
  execFileSync('git', ['-C', nested, 'init', '--initial-branch=trunk'], { stdio: 'ignore' });
  const nestedRecord = join(nested, 'record.md'); writeFileSync(nestedRecord, '# TASK-00560\n');
  assert.throws(() => prepareEngineer(f.ctx, { ...f.request, record: nestedRecord }, f.options), /target-context-mismatch/);
  // Missing optional instruction files are not a reason to invent AGENTS.md.
  assert.deepEqual(prepareEngineer(f.ctx, f.request, f.options).instructions, []);
});

test('a matching label or transcript cannot bind an inherited/wrong-model/wrong-checkout session', t => {
  const f = fixture(t);
  for (const patch of [{ model: { provider: 'other', id: f.request.model } }, { thinkingLevel: 'medium' }, { messages: [{ role: 'user' }] },
    { systemPrompt: 'Missing instructions' }, { resourceLoader: { getExtensions: () => ({ errors: ['missing'], extensions: [] }) } }]) {
    assert.throws(() => bindEngineerSession({ ...f.child(), ...patch }, f.assignment), /engineer-/);
  }
  const wrong = f.child(); wrong.sessionManager.getCwd = () => dirnameOf(f.root);
  assert.throws(() => bindEngineerSession(wrong, f.assignment), /checkout-mismatch/);
  const storage = f.child(); storage.sessionManager.getSessionFile = () => join(f.root, 'foreign.jsonl');
  assert.throws(() => bindEngineerSession(storage, f.assignment), /storage-unqualified/);
  assert.throws(() => bindEngineerSession(f.child(), { ...f.assignment, effects: ['delivery'] }), /assignment-invalid/);
  assert.throws(() => bindEngineerSession(f.child(), { ...f.assignment, parent: f.child().sessionManager.getSessionId(), role: 'land' }), /assignment-invalid/);
});
const dirnameOf = path => join(path, '..');

test('authority is exact-session-local, not shared environment or persisted-role replay', t => {
  const f = fixture(t), child = f.child(), sibling = f.child();
  const before = { ...process.env };
  const bound = bindEngineerSession(child, f.assignment);
  assert.equal(isSdkSession(child.sessionManager), true);
  assert.equal(engineerIdentity(child, 'actual-parent'), null); // not initialized yet
  publishWorkflow(child.sessionManager, new WorkflowAccess('work', { taskId: f.request.task, checkout: f.root }));
  assert.deepEqual(engineerIdentity(child, 'actual-parent'), { task: f.request.task, role: 'engineer', attempt: f.request.attempt });
  assert.equal(engineerIdentity(child, 'foreign-parent'), null);
  assert.equal(stageBinding(sibling.sessionManager), undefined);
  assert.equal(sessionWorkflow(f.ctx.sessionManager).skill, 'build');
  assert.throws(() => bindEngineerSession(child, f.assignment), /not-fresh/);
  closeWorkflow(child.sessionManager);
  assert.equal(bound.live, false); assert.equal(engineerIdentity(child, 'actual-parent'), null);
  assert.equal(isSdkSession(child.sessionManager), true); assert.deepEqual({ ...process.env }, before);
});

test('passive observation derives assigned identity only from actual initialized binding', t => {
  const f = fixture(t), session = f.child(), frames = [];
  bindEngineerSession(session, f.assignment);
  publishWorkflow(session.sessionManager, new WorkflowAccess('work', { taskId: f.request.task, checkout: f.root }));
  const record = { id: 'actual-record', rootSessionId: 'actual-parent', session, type: 'fight-engineer', status: 'running' };
  const observer = new SubagentObserver('actual-parent', frame => frames.push(frame), { registry: () => ({ getRecord: () => record }) });
  observer.started({ id: record.id });
  assert.equal(frames.at(-1).identity.task, f.request.task);
  assert.equal(frames.at(-1).identity.role, 'engineer'); assert.equal(frames.at(-1).identity.attempt, f.request.attempt);
  session.messages.push({ role: 'assistant', content: [{ type: 'text', text: 'Final bounded Work handoff' }] });
  record.status = 'completed'; observer.tick();
  assert.match(frames.at(-1).text, /Final bounded/); assert.equal(frames.at(-1).exitCode, null);
  assert.equal(stageBinding(session.sessionManager).live, true); observer.close();
});

function engine(f, behavior = 'complete') {
  let record, calls = 0, observedSignal;
  const registry = { ...f.registry, getRecord: () => record, spawn(_pi, _ctx, type, prompt, options) {
    calls++; assert.equal(type, 'fight-engineer'); assert.equal(options.inheritContext, false);
    assert.equal(options.cwd, f.root); assert.ok(!prompt.includes('Parent transcript'));
    const session = f.child(); observedSignal = options.signal;
    session.resourceLoader.getExtensions = () => ({ errors: [], extensions: ['workflow-extension.ts', 'build-engineer-extension.ts']
      .map(path => ({ path: fileURLToPath(new URL('../src/' + path, import.meta.url)) })) });
    let settle;
    record = { id: 'record', rootSessionId: 'actual-parent', status: 'running', promise: new Promise(resolve => { settle = resolve; }) };
    queueMicrotask(async () => {
      try {
        await options.beforeSessionStart(session, options.signal);
        publishWorkflow(session.sessionManager, new WorkflowAccess('work', { taskId: f.request.task, checkout: f.root }));
        stageBinding(session.sessionManager).admitted = true; record.session = session;
        if (behavior === 'complete' || behavior === 'provider-error') { record.status = behavior === 'complete' ? 'completed' : 'error'; settle(); }
        else if (behavior === 'record-loss') { record = undefined; }
        else options.signal.addEventListener('abort', () => { record.status = 'stopped'; settle(); }, { once: true });
      } catch { record.status = 'error'; settle(); }
    });
    return record.id;
  } };
  const pi = { events: { on: () => () => {}, emit() {} } };
  return { registry, pi, calls: () => calls, signal: () => observedSignal };
}

test('pre-abort is not-started; a synchronous possibly-started spawn error stays uncertain', async t => {
  const f = fixture(t);
  const stopped = await runEngineer({}, f.ctx, f.request, { ...f.options, signal: AbortSignal.abort() });
  assert.equal(stopped.state, 'not-started'); assert.equal(stopped.executionSettled, true);
  const attempt = join(f.root, '.runs/build/TASK-00560/run/throwing'); mkdirSync(attempt, { recursive: true });
  writeFileSync(join(attempt, 'request.md'), 'Controlled synchronous spawn error.');
  const uncertain = await runEngineer({}, f.ctx, { ...f.request, attempt }, f.options);
  assert.equal(uncertain.state, 'uncertain'); assert.equal(uncertain.executionSettled, false);
});

for (const behavior of ['complete', 'provider-error', 'timeout', 'abort', 'shutdown', 'record-loss']) {
  test(`SDK ${behavior} is reconciled without an executor fallback or acceptance`, async t => {
    const f = fixture(t), e = engine(f, behavior), caller = new AbortController();
    let shutdown;
    if (behavior === 'timeout') f.request.timeout = 1;
    if (behavior === 'abort') setTimeout(() => caller.abort(), 100);
    if (behavior === 'shutdown') setTimeout(() => shutdown?.(), 100);
    const outcome = await runEngineer(e.pi, f.ctx, f.request, { ...f.options, registry: e.registry, signal: caller.signal,
      onShutdown(cancel) { shutdown = cancel; return () => {}; } });
    assert.equal(e.calls(), 1); assert.equal(outcome.acceptance, false);
    assert.equal(outcome.admitted, true);
    if (behavior === 'complete') assert.equal(outcome.state, 'completed');
    else if (behavior === 'provider-error') assert.equal(outcome.state, 'error');
    else if (behavior === 'record-loss') { assert.equal(outcome.state, 'uncertain'); assert.equal(e.signal().aborted, true); }
    else { assert.equal(outcome.state, 'stopped'); assert.equal(outcome.executionSettled, true); assert.ok(outcome.reason); }
    assert.equal(JSON.parse(readFileSync(join(f.request.attempt, 'sdk-outcome.json'))).acceptance, false);
    await assert.rejects(runEngineer(e.pi, f.ctx, f.request, { ...f.options, registry: e.registry }), /EEXIST|attempt-not-fresh/);
  });
}
