// Real private copied engine/SDK initialization with controlled faux responses.
// NOT genuine Build authority, independent acceptance, publication or Herdr evidence.
// Usage: node build-stages.mjs PI_NODE_MODULES TASK_EVIDENCE INSTALLED_HARNESS
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]), harness = realpathSync(resolve(process.argv[4]));
mkdirSync(evidence, { recursive: true });
const fixture = realpathSync(mkdtempSync(join(evidence, 'stages-'))), root = join(fixture, 'target'), agentDir = join(fixture, 'agent');
for (const path of [root, agentDir, join(fixture, 'home')]) mkdirSync(path, { mode: 0o700 });
process.env.HOME = join(fixture, 'home'); process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
delete process.env.PI_CODING_AGENT_SESSION_DIR;
// Controlled trusted-launch fixture only. Never synthesize interactive input.
process.env.FIGHT_WORKFLOW_PHASE = 'build'; process.env.FIGHT_WORKFLOW_TASK = 'TASK-00561'; process.env.FIGHT_WORKFLOW_CHECKOUT = root;
for (const key of Object.keys(process.env).filter(key => /^(HERDR_|FIGHT_WORKSPACE)/.test(key))) delete process.env[key];
const load = path => import(pathToFileURL(path));
const sdk = await load(join(modules, '@earendil-works/pi-coding-agent/dist/index.js'));
const ai = await load(join(modules, '@earendil-works/pi-ai/dist/index.js'));
const { runToolCall } = await load(join(modules, '@earendil-works/pi-agent-core/dist/index.js'));
const { stageBinding, sessionWorkflow, publishWorkflow } = await load(join(harness, 'src/build/authority.mjs'));
const { WorkflowAccess } = await load(join(harness, 'src/workflow/access.mjs'));
const { SubagentObserver } = await load(join(harness, 'src/observation/subagents.mjs'));
const git = (...args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
git('init', '--initial-branch=topic/controlled-consumer');
writeFileSync(join(root, 'CLAUDE.md'), 'Controlled fixture. No genuine Work, acceptance, provider or publication effects.');
mkdirSync(join(root, 'work-items')); writeFileSync(join(root, 'work-items/assigned.md'), '# TASK-00561\nState: approved-to-code (fixture only).\n');
git('add', '.'); git('-c', 'user.name=Harness fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Controlled fixture');
const settingsData = { packages: [harness], sessionDir: join(agentDir, 'sessions'),
  compaction: { enabled: false }, retry: { enabled: false } };
writeFileSync(join(agentDir, 'settings.json'), JSON.stringify(settingsData)); process.chdir(root);
const roles = ['engineer', 'reviewer', 'qa', 'land'];
const faux = ai.fauxProvider({ provider: 'faux', tokensPerSecond: 1000,
  models: ['coordinator', ...roles].map(id => ({ id, contextWindow: 200000, reasoning: true })) });
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null, refreshOnCreate: false });
runtime.registerNativeProvider(faux.provider);
const cases = [], errors = [], frames = [], outcomes = [], sessions = new Set();
let requests = 0, starts = 0, currentId;
const context = join(root, '.runs/build/TASK-00561/run/context.md'); mkdirSync(join(root, '.runs/build/TASK-00561/run'), { recursive: true });
writeFileSync(context, `Controlled target facts for ${root}, TASK-00561. No genuine operator selection.`);
const fresh = (name, role = 'engineer', repairsUsed = 0, repair = false, timeout = 20) => {
  const attempt = join(root, '.runs/build/TASK-00561/run', name); mkdirSync(attempt);
  writeFileSync(join(attempt, 'request.md'), 'Controlled transport only. Read the fixture context. No real target Work, independent verdict or publication.');
  return { task: 'TASK-00561', role, checkout: root, base: root, branch: 'topic/controlled-consumer', context,
    record: join(root, 'work-items/assigned.md'), head: git('rev-parse', 'HEAD'), attempt,
    provider: 'faux', model: role, thinking: 'high', timeout, repair, repairsUsed, repairLimit: 5 };
};
async function create() {
  const settings = sdk.SettingsManager.create(root, agentDir, { projectTrusted: true }), events = sdk.createEventBus();
  const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager: settings, eventBus: events,
    noSkills: true, noThemes: true, noPromptTemplates: true });
  await loader.reload(); assert.deepEqual(loader.getExtensions().errors, []);
  const { session } = await sdk.createAgentSession({ cwd: root, agentDir, modelRuntime: runtime,
    model: faux.getModel('coordinator'), thinkingLevel: 'medium', settingsManager: settings, resourceLoader: loader,
    sessionManager: sdk.SessionManager.create(root, settingsData.sessionDir) });
  events.on('subagents:started', event => { starts++; currentId = event.id; });
  await session.bindExtensions({ onError: error => errors.push(error.message) });
  assert.equal(loader.getExtensions().extensions.filter(extension => extension.flags.has('fight-build-stages')).length, 0);
  assert.equal(loader.getExtensions().extensions.filter(extension => extension.path.endsWith('/src/subagents-extension.ts')).length, 1);
  return { session, events };
}
async function call(session, name, args) {
  session.setActiveToolsByName([...session.getActiveToolNames(), name]);
  const tools = session.agent.state.tools;
  return runToolCall({ type: 'toolCall', id: randomUUID(), name, arguments: args }, {
    tools, assistantMessage: ai.fauxAssistantMessage([]), context: { messages: session.messages, tools },
    beforeToolCall: ctx => session.agent.beforeToolCall(ctx), afterToolCall: ctx => session.agent.afterToolCall(ctx) });
}
const text = result => result.result.content.filter(block => block.type === 'text').map(block => block.text).join('\n');
async function refused(session, request, reason, label) {
  const before = starts, result = await call(session, 'fight_build_stage', request);
  assert.equal(result.isError, true, label); assert.match(text(result), reason, label); assert.equal(starts, before, label);
  assert.equal(existsSync(join(request.attempt, 'dispatch.lock')), false, label); cases.push({ refused: label });
}
let { session, events } = await create();
try {
  const status = JSON.parse(text(await call(session, 'fight_workflow_status', {})));
  assert.equal(status.workflow.skill, 'build');
  const original = sessionWorkflow(session.sessionManager);
  for (const [label, workflow] of [
    ['wrong-workflow', new WorkflowAccess('work', { taskId: 'TASK-00561', checkout: root })],
    ['wrong-mode', new WorkflowAccess('build', { taskId: 'TASK-00561', checkout: root, mode: 'query' })],
    ['wrong-task', new WorkflowAccess('build', { taskId: 'TASK-99999', checkout: root })],
    ['wrong-checkout', new WorkflowAccess('build', { taskId: 'TASK-00561', checkout: harness })],
  ]) {
    publishWorkflow(session.sessionManager, workflow); await refused(session, fresh(label), /build-authority-required/, label);
  }
  publishWorkflow(session.sessionManager, original);
  for (const [label, patch, reason] of [
    ['wrong-head', { head: '0'.repeat(40) }, /candidate-changed/], ['wrong-role', { role: 'unknown' }, /role/],
    ['foreign-base', { base: harness }, /base-mismatch/], ['foreign-record', { record: join(harness, 'README.md') }, /target-context-mismatch/],
  ]) await refused(session, { ...fresh(label), ...patch }, reason, label);
  const registry = globalThis[Symbol.for('pi-subagents:manager')];
  const capabilities = registry.capabilities;
  try {
    registry.capabilities = {};
    await refused(session, fresh('missing-capability'), /capability-unavailable/, 'missing supported hooks, no fallback');
  } finally { registry.capabilities = capabilities; }
  const observer = new SubagentObserver(session.sessionManager.getSessionId(), frame => frames.push(frame));
  const unstarted = events.on('subagents:started', event => observer.started(event));
  const ticker = setInterval(() => observer.tick(), 10); ticker.unref();
  const skills = { engineer: 'work', reviewer: 'review', qa: 'qa', land: 'land' };
  // Every round is a fresh SDK session, including the bounded repair/assessors.
  // Reports deliberately remain negative/incomplete/wrong-subject fixtures.
  for (const [index, role] of ['engineer', 'reviewer', 'qa', 'engineer', 'reviewer', 'qa', 'land'].entries()) {
    const used = index >= 3 ? 1 : 0, request = fresh(`round-${index}-${role}`, role, used, index === 3);
    const report = role === 'reviewer' ? join(root, '.runs/reviews/TASK-00561/review.md') : role === 'qa' ? join(root, '.runs/qa/TASK-00561/qa.md') : null;
    const responses = [() => {
      requests++;
      const child = registry.getRecord(currentId).session, binding = stageBinding(child.sessionManager);
      assert.equal(dirname(child.sessionManager.getSessionFile()), join(request.attempt, 'sessions'));
      assert.equal(binding.role, role); assert.equal(binding.skill, skills[role]);
      assert.equal(sessionWorkflow(child.sessionManager).skill, skills[role]); assert.equal(binding.head, request.head);
      assert.equal(child.model.id, role); assert.equal(child.thinkingLevel, 'high');
      assert.ok(!child.getActiveToolNames().includes('fight_build_stage'), 'stage owners cannot coordinate nested Build');
      for (const tool of ['read', 'bash', 'write', 'edit', 'fight_workflow_status']) assert.ok(child.getActiveToolNames().includes(tool), `${role} needs ${tool}`);
      assert.ok(!sessions.has(child.sessionId)); sessions.add(child.sessionId);
      assert.equal(child.messages.filter(message => message.role === 'user').length, 1);
      assert.ok(child.systemPrompt.includes('Controlled fixture.'));
      return ai.fauxAssistantMessage([ai.fauxText(`Early controlled ${role}`), ai.fauxToolCall('fight_workflow_status', {})], { stopReason: 'toolUse' });
    }];
    if (report) responses.push(() => {
      requests++; return ai.fauxAssistantMessage([ai.fauxToolCall('write', { path: report,
        content: role === 'reviewer' ? `---\nreview_handoff_version: 3\ntask: TASK-00561\nhead_commit: ${'0'.repeat(40)}\nverdict: revise\n---\nControlled negative report ${index}; not acceptance.\n` :
          `# QA report: TASK-00561\n- QA disposition: INCOMPLETE\nControlled probe ${index}; no technical acceptance or real QA.\n` })], { stopReason: 'toolUse' });
    });
    responses.push(() => { requests++; return ai.fauxAssistantMessage(`Controlled ${role} terminal result; not acceptance.`); });
    let outcome;
    if (index === 0) {
      faux.setResponses([() => {
        requests++; assert.ok(session.getActiveToolNames().includes('fight_build_stage'), 'Build default is active without a flag');
        return ai.fauxAssistantMessage([ai.fauxToolCall('fight_build_stage', request)], { stopReason: 'toolUse' });
      },
        ...responses, () => { requests++; return ai.fauxAssistantMessage('Controlled coordinator return, not acceptance.'); }]);
      await session.prompt('Exercise the controlled pipeline, not a real assignment.');
      const result = session.messages.find(message => message.role === 'toolResult' && message.toolName === 'fight_build_stage');
      assert.ok(result); assert.equal(result.isError, false, JSON.stringify(result)); outcome = JSON.parse(result.content[0].text);
    } else {
      faux.setResponses(responses); const result = await call(session, 'fight_build_stage', request);
      assert.equal(result.isError, false, text(result)); outcome = JSON.parse(text(result));
    }
    assert.equal(outcome.acceptance, false); assert.equal(outcome.admitted, true); assert.equal(outcome.executionSettled, true);
    assert.equal(outcome.role, role); assert.equal(outcome.repairsUsed, used); assert.equal(session.thinkingLevel, 'medium');
    if (report) { assert.equal(outcome.reportObservation.changed, true); assert.equal(outcome.reportObservation.ownerSession, outcome.session); }
    observer.tick(); assert.ok(frames.some(frame => frame.identity.attempt === request.attempt && frame.identity.role === role));
    outcomes.push(outcome);
    const before = starts; assert.equal((await call(session, 'fight_build_stage', request)).isError, true); assert.equal(starts, before);
  }
  assert.equal(sessions.size, 7); assert.equal(starts, 7);
  await refused(session, fresh('reset-budget', 'engineer'), /accounting-changed/, 'repair budget reset');
  await refused(session, { ...fresh('pin-drift', 'engineer', 1), model: 'qa' }, /pin-changed/, 'model pin drift');
  // Unbound siblings cannot inherit the parent's launcher or choose independent roles.
  faux.setResponses([() => { requests++; return ai.fauxAssistantMessage('Unbound advice only.'); }]);
  const siblingId = registry.spawn(null, null, 'fight-explorer', 'Controlled unbound sibling.', { isBackground: true });
  await registry.waitForAll(); const sibling = registry.getRecord(siblingId).session;
  assert.equal(sessionWorkflow(sibling.sessionManager).skill, 'review');
  await refused(sibling, fresh('sibling', 'engineer', 1), /build-authority-required/, 'unbound sibling');
  const siblingWorkflow = sessionWorkflow(sibling.sessionManager);
  publishWorkflow(sibling.sessionManager, original);
  try { await refused(sibling, fresh('nested-build', 'engineer', 1), /build-authority-required/, 'SDK subordinate even with Build-shaped selection'); }
  finally { publishWorkflow(sibling.sessionManager, siblingWorkflow); }
  const failure = fresh('provider-error', 'engineer', 1);
  faux.setResponses([() => { requests++; return ai.fauxAssistantMessage([], { stopReason: 'error', errorMessage: 'Controlled provider failure' }); }]);
  const failed = await call(session, 'fight_build_stage', failure); assert.equal(failed.isError, true);
  const failureOutcome = JSON.parse(text(failed)); assert.equal(failureOutcome.state, 'error'); assert.equal(failureOutcome.acceptance, false);
  const pending = (_context, options) => {
    requests++; return new Promise(resolve => {
      const finish = () => resolve(ai.fauxAssistantMessage([], { stopReason: 'aborted' }));
      if (options.signal.aborted) finish(); else options.signal.addEventListener('abort', finish, { once: true });
    });
  };
  faux.setResponses([pending]); const timed = await call(session, 'fight_build_stage', fresh('timeout', 'engineer', 1, false, 1));
  assert.equal(timed.isError, true); const timeoutOutcome = JSON.parse(text(timed)); assert.equal(timeoutOutcome.reason, 'timeout');
  await session.reload(); // Supported actual runtime replacement, not a fake handler call.
  await refused(session, fresh('reload-reset', 'engineer'), /accounting-changed/, 'reload preserves repairs');
  let entered;
  const streaming = new Promise(resolve => { entered = resolve; });
  faux.setResponses([(context, options) => { entered(); return pending(context, options); }]);
  const duringShutdown = call(session, 'fight_build_stage', fresh('during-shutdown', 'engineer', 1));
  await streaming;
  await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'reload' });
  const stopped = await duringShutdown; assert.equal(stopped.isError, true);
  const shutdownOutcome = JSON.parse(text(stopped)); assert.equal(shutdownOutcome.acceptance, false);
  assert.ok(['parent-shutdown-or-reload', 'record-unavailable-or-replaced'].includes(shutdownOutcome.reason));
  // The actual outcome, not a fabricated checkpoint, governs replacement.
  await session.reload();
  if (!shutdownOutcome.executionSettled) await refused(session, fresh('reload-uncertain', 'engineer', 1), /Reconcile the existing SDK stage/, 'reload uncertainty');
  else cases.push({ reconciled: 'shutdown settled; still requires external-effect inspection' });
  clearInterval(ticker); unstarted(); observer.close();
  assert.deepEqual(errors, []);
  writeFileSync(join(evidence, 'summary.json'), JSON.stringify({ pass: true, sdk: sdk.VERSION, harness, fixture,
    controlledOnly: true, realBuildJourney: false, independentAcceptance: false, nativeHerdrEvidence: false,
    cases, outcomes, failureOutcome, timeoutOutcome, shutdownOutcome, distinctStageSessions: sessions.size, passiveFrames: frames.length,
    engineStarts: starts, scriptedRequests: requests, externalRequests: 0 }, null, 2) + '\n');
  console.log(`PASS: 7 fresh role/repair sessions; ${cases.length} boundary cases; ${frames.length} passive frames; controlled only`);
} finally { await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'quit' }); session.dispose(); }
for (const key of ['FIGHT_WORKFLOW_PHASE', 'FIGHT_WORKFLOW_TASK', 'FIGHT_WORKFLOW_CHECKOUT']) delete process.env[key];
({ session } = await create());
try { await refused(session, fresh('without-build'), /build-authority-required/, 'ordinary unassigned parent cannot dispatch'); }
finally { await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'quit' }); session.dispose(); }
