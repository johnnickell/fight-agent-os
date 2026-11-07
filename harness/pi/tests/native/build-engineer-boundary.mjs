// Installed Pi's separate extension APIs and actual workflow/tool pipeline.
// Controlled private target + faux transport only, NOT operator selection or real Work.
// Usage: node build-engineer-boundary.mjs PI_NODE_MODULES TASK_EVIDENCE INSTALLED_HARNESS
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]), harness = realpathSync(resolve(process.argv[4]));
mkdirSync(evidence, { recursive: true });
const fixture = realpathSync(mkdtempSync(join(evidence, 'boundary-'))), root = join(fixture, 'target'), agentDir = join(fixture, 'agent');
for (const path of [root, agentDir, join(fixture, 'home')]) mkdirSync(path, { mode: 0o700 });
process.env.HOME = join(fixture, 'home'); process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
// Controlled launcher fixture, not a genuine Build invocation. Never emit interactive input.
process.env.FIGHT_WORKFLOW_PHASE = 'build'; process.env.FIGHT_WORKFLOW_TASK = 'TASK-00560'; process.env.FIGHT_WORKFLOW_CHECKOUT = root;
for (const key of Object.keys(process.env).filter(key => /^(HERDR_|FIGHT_WORKSPACE)/.test(key))) delete process.env[key];
const load = path => import(pathToFileURL(path));
const sdk = await load(join(modules, '@earendil-works/pi-coding-agent/dist/index.js'));
const ai = await load(join(modules, '@earendil-works/pi-ai/dist/index.js'));
const { runToolCall } = await load(join(modules, '@earendil-works/pi-agent-core/dist/index.js'));
const { publishWorkflow, sessionWorkflow, stageBinding } = await load(join(harness, 'src/build/authority.mjs'));
const { WorkflowAccess } = await load(join(harness, 'src/workflow/access.mjs'));
const git = (...args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
git('init', '--initial-branch=topic/boundary');
writeFileSync(join(root, 'CLAUDE.md'), 'Controlled boundary fixture. No external requests, real Work or acceptance.');
mkdirSync(join(root, 'work-items'));
writeFileSync(join(root, 'work-items/assigned.md'), '# TASK-00560\nState: approved-to-code (fixture only).\n');
git('add', '.'); git('-c', 'user.name=Harness fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Controlled fixture');
writeFileSync(join(agentDir, 'subagents.json'), readFileSync(join(harness, 'profiles/subagents.json')));
const settingsData = { packages: [harness], sessionDir: join(root, '.runs/tasks/TASK-00560/runs/sessions'),
  compaction: { enabled: false }, retry: { enabled: false } };
writeFileSync(join(agentDir, 'settings.json'), JSON.stringify(settingsData));
process.chdir(root);
const faux = ai.fauxProvider({ provider: 'faux', tokensPerSecond: 1000, models: [{ id: 'boundary', contextWindow: 200000, reasoning: true }] });
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null, refreshOnCreate: false });
runtime.registerNativeProvider(faux.provider);
const cases = [], errors = [];
let requests = 0, starts = 0;
const context = join(root, '.runs/build/TASK-00560/run/context.md');
mkdirSync(join(root, '.runs/build/TASK-00560/run'), { recursive: true });
writeFileSync(context, `Controlled target facts for ${root}, TASK-00560. No genuine operator selection.`);
const fresh = name => {
  const attempt = join(root, '.runs/build/TASK-00560/run', name); mkdirSync(attempt);
  writeFileSync(join(attempt, 'request.md'), 'Controlled transport qualification only. No real target Work.');
  return { task: 'TASK-00560', role: 'engineer', checkout: root, base: root, branch: 'topic/boundary', context,
    record: join(root, 'work-items/assigned.md'), head: git('rev-parse', 'HEAD'), attempt,
    provider: 'faux', model: 'boundary', thinking: 'high', timeout: 10 };
};
async function create(selected) {
  const settings = sdk.SettingsManager.create(root, agentDir, { projectTrusted: true }), events = sdk.createEventBus();
  const diagnostics = [];
  const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager: settings, eventBus: events,
    noSkills: true, noThemes: true, noPromptTemplates: true,
    extensionFactories: [pi => { // A separate REAL API instance: must not see Build's private flag.
      pi.on('session_start', () => diagnostics.push(pi.getFlag('fight-build-engineer')));
      pi.on('tool_call', event => { if (event.toolName === 'fight_build_engineer') cases.push({ pipeline: event.toolName }); });
    }] });
  await loader.reload(); assert.deepEqual(loader.getExtensions().errors, []);
  const { session } = await sdk.createAgentSession({ cwd: root, agentDir, modelRuntime: runtime, model: faux.getModel(),
    thinkingLevel: 'medium', settingsManager: settings, resourceLoader: loader,
    sessionManager: sdk.SessionManager.create(root, settingsData.sessionDir) });
  // Host qualification flag binding, NOT shared getFlag stubs or operator authority.
  if (selected) session.extensionRunner.setFlagValue('fight-build-engineer', 'sdk');
  events.on('subagents:started', () => { starts++; });
  await session.bindExtensions({ onError: error => errors.push(error.message) });
  assert.deepEqual(diagnostics, [undefined]);
  assert.equal(loader.getExtensions().extensions.filter(extension => extension.flags.has('fight-build-engineer')).length, 1);
  assert.equal(loader.getExtensions().extensions.filter(extension => extension.path.endsWith('/vendor/pi-subagents/src/index.ts')).length, 1);
  return session;
}
async function call(session, name, args) {
  // Normal agent tool preflight/result hooks, without an evaluator.
  session.setActiveToolsByName([...session.getActiveToolNames(), name]);
  const tools = session.agent.state.tools;
  return runToolCall({ type: 'toolCall', id: randomUUID(), name, arguments: args }, {
    tools, assistantMessage: ai.fauxAssistantMessage([]), context: { messages: session.messages, tools },
    beforeToolCall: ctx => session.agent.beforeToolCall(ctx), afterToolCall: ctx => session.agent.afterToolCall(ctx) });
}
const text = result => result.result.content.filter(block => block.type === 'text').map(block => block.text).join('\n');
async function refused(session, request, reason, label) {
  const before = starts;
  const result = await call(session, 'fight_build_engineer', request);
  assert.equal(result.isError, true, label); assert.match(text(result), reason, label);
  assert.equal(starts, before, label); assert.equal(existsSync(join(request.attempt, 'dispatch.lock')), false, label);
  cases.push({ refused: label, reason: text(result) });
}
let session = await create(true);
try {
  const status = JSON.parse(text(await call(session, 'fight_workflow_status', {})));
  assert.equal(status.state, 'active');
  assert.equal(status.workflow.skill, 'build'); assert.equal(status.workflow.mode, 'default');
  const request = fresh('explicit-opt-in');
  faux.setResponses([
    () => { requests++; return ai.fauxAssistantMessage([ai.fauxToolCall('fight_build_engineer', request)], { stopReason: 'toolUse' }); },
    () => { requests++; return ai.fauxAssistantMessage('Bounded scripted child completion, not real Work.'); },
    () => { requests++; return ai.fauxAssistantMessage('Controlled coordinator completion, not acceptance.'); },
  ]);
  // Full SDK prompt -> model tool call -> actual Build tool -> ONE engine child.
  await session.prompt('Run the controlled boundary qualification, not a real assignment.');
  const result = session.messages.find(message => message.role === 'toolResult' && message.toolName === 'fight_build_engineer');
  assert.ok(result); assert.equal(result.isError, false, JSON.stringify(result));
  const outcome = JSON.parse(result.content[0].text);
  assert.equal(outcome.state, 'completed'); assert.equal(outcome.admitted, true); assert.equal(outcome.acceptance, false);
  assert.equal(starts, 1); assert.equal(requests, 3);
  const registry = globalThis[Symbol.for('pi-subagents:manager')], child = registry.getRecord(outcome.id).session;
  assert.equal(stageBinding(child.sessionManager).task, 'TASK-00560');
  assert.equal(sessionWorkflow(child.sessionManager).skill, 'work');
  assert.equal(child.messages.filter(message => message.role === 'user').length, 1);
  assert.equal(child.thinkingLevel, 'high'); assert.equal(session.thinkingLevel, 'medium');
  cases.push({ admitted: 'explicit host fixture opt-in through normal SDK prompt', outcome });
  const before = starts;
  assert.equal((await call(session, 'fight_build_engineer', request)).isError, true);
  assert.equal(starts, before); cases.push({ refused: 'duplicate attempt' });
  const original = sessionWorkflow(session.sessionManager);
  for (const [label, workflow] of [
    ['wrong workflow', new WorkflowAccess('work', { taskId: request.task, checkout: root })],
    ['wrong mode', new WorkflowAccess('build', { taskId: request.task, checkout: root, mode: 'query' })],
    ['wrong TASK', new WorkflowAccess('build', { taskId: 'TASK-99999', checkout: root })],
    ['wrong checkout', new WorkflowAccess('build', { taskId: request.task, checkout: harness })],
  ]) {
    publishWorkflow(session.sessionManager, workflow); // Controlled trusted-state fixture, not a user input event.
    await refused(session, fresh(label.replaceAll(' ', '-')), /build-authority-required/, label);
  }
  publishWorkflow(session.sessionManager, original);
  faux.setResponses([() => { requests++; return ai.fauxAssistantMessage('Unbound subordinate.'); }]);
  const siblingId = registry.spawn(null, null, 'fight-explorer', 'Controlled unbound sibling.', { isBackground: true });
  await registry.waitForAll();
  const sibling = registry.getRecord(siblingId).session;
  assert.equal(sessionWorkflow(sibling.sessionManager).skill, 'review');
  await refused(sibling, fresh('unbound-sibling'), /not-selected|build-authority-required/, 'unbound sibling');
  // Actual supported reload closes old in-memory ownership then binds a new runtime.
  const oldManager = session.sessionManager;
  await session.reload();
  assert.equal(sessionWorkflow(oldManager)?.skill, 'build');
  // Reload keeps the explicit host flag; the new Build instance must requalify it.
  await refused(session, { ...fresh('reload-wrong-task'), task: 'TASK-99999' }, /build-authority-required/, 'reloaded wrong TASK');
  // A reload must retain the unresolved-attempt hold, not turn route selection into replay authority.
  session.sessionManager.appendCustomEntry('fight-build-sdk-attempt-v1', { attempt: 'controlled-unsettled', executionSettled: false });
  await session.reload();
  await refused(session, fresh('reload-unresolved'), /Reconcile the existing SDK Engineer attempt/, 'unresolved checkpoint after reload');
  // Shutdown revokes route/workflow even if the old tool is forcibly reactivated by this probe.
  await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'new' });
  assert.equal(sessionWorkflow(oldManager), undefined);
  await refused(session, fresh('closed-session'), /build-authority-required|not-selected|Reconcile the existing SDK Engineer attempt/, 'closed old session');
} finally { await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'quit' }); session.dispose(); }
// Replacement session has a different real manager, and must NOT borrow the former flag/readiness.
session = await create(false);
try {
  await refused(session, fresh('replacement-without-opt-in'), /not-selected/, 'replacement without opt-in');
  assert.equal(sessionWorkflow(session.sessionManager).skill, 'build');
  assert.deepEqual(errors, []);
  const summary = { pass: true, sdk: sdk.VERSION, harness, fixture, cases, engineerDispatches: 1, engineStarts: starts,
    scriptedRequests: requests, externalRequests: 0, realCredentialReads: 0, interactiveDialogs: 'unavailable in print-mode SDK qualification',
    realWork: false, genuineOperatorSelection: false, nativeHerdrEvidence: false };
  writeFileSync(join(evidence, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
  console.log(JSON.stringify(summary));
} finally { await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'quit' }); session.dispose(); }
