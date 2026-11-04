// Real installed engine/SDK/extension qualification with a scripted provider.
// Not a real authorized Work assignment, native Herdr capture or acceptance.
// Usage: node build-engineer.mjs PI_NODE_MODULES TASK_EVIDENCE INSTALLED_HARNESS
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]), harness = realpathSync(resolve(process.argv[4]));
mkdirSync(evidence, { recursive: true });
const root = realpathSync(mkdtempSync(join(evidence, 'installed-'))), agentDir = join(root, 'private-agent');
mkdirSync(agentDir);
process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
process.env.FIGHT_SCREENING_PHASE = 'build'; process.env.FIGHT_SCREENING_TASK = 'TASK-00560'; process.env.FIGHT_SCREENING_CHECKOUT = root;
// Inherit no operator setup, enrollment, credentials, settings or Herdr endpoints.
process.env.HOME = join(root, 'home'); mkdirSync(process.env.HOME);
for (const key of Object.keys(process.env).filter(key => /^(HERDR_|FIGHT_WORKSPACE)/.test(key))) delete process.env[key];
const sdk = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/index.js')));
const ai = await import(pathToFileURL(join(modules, '@earendil-works/pi-ai/dist/index.js')));
const { runToolCall } = await import(pathToFileURL(join(modules, '@earendil-works/pi-agent-core/dist/index.js')));
const { SubagentObserver } = await import(pathToFileURL(join(harness, 'src/observation/subagents.mjs')));
const git = (...args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8' }).trim();
git('init', '--initial-branch=feature/installed-engineer');
mkdirSync(join(root, 'planning/tasks'), { recursive: true });
writeFileSync(join(root, 'AGENTS.md'), 'Controlled installed SDK governing instructions. No external provider requests.');
writeFileSync(join(root, 'planning/tasks/00560-TASK.md'), '---\nid: TASK-00560\nstatus: in-progress\n---\n');
git('add', '.'); git('-c', 'user.name=Harness fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Controlled native fixture');
mkdirSync(join(root, '.pi'));
writeFileSync(join(root, '.pi/subagents.json'), readFileSync(join(harness, 'profiles/subagents.json')));
const settingsData = { packages: [harness], sessionDir: join(root, '.runs/tasks/TASK-00560/runs/sessions'),
  compaction: { enabled: false }, retry: { enabled: false } };
writeFileSync(join(agentDir, 'settings.json'), JSON.stringify(settingsData));
process.chdir(root);
const settings = sdk.SettingsManager.create(root, agentDir, { projectTrusted: true });
const events = sdk.createEventBus();
const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager: settings, eventBus: events, noSkills: true, noThemes: true, noPromptTemplates: true });
await loader.reload();
assert.deepEqual(loader.getExtensions().errors, []);
loader.getExtensions().runtime.flagValues.set('fight-build-engineer', 'sdk');
const faux = ai.fauxProvider({ provider: 'faux', tokensPerSecond: 200, models: [{ id: 'engineer-qualification', contextWindow: 200000, reasoning: true }] });
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null, refreshOnCreate: false });
runtime.registerNativeProvider(faux.provider);
const { session } = await sdk.createAgentSession({ cwd: root, agentDir, model: faux.getModel(), modelRuntime: runtime,
  thinkingLevel: 'medium', settingsManager: settings, resourceLoader: loader, sessionManager: sdk.SessionManager.create(root, settingsData.sessionDir) });
const errors = [], frames = [];
let requests = 0;
faux.setResponses([
  () => { requests++; return ai.fauxAssistantMessage([ai.fauxText('Early scripted Engineer activity'),
    ai.fauxToolCall('read', { path: join(root, 'AGENTS.md') })], { stopReason: 'toolUse' }); },
  async () => { requests++; await new Promise(resolve => setTimeout(resolve, 100));
    return ai.fauxAssistantMessage([ai.fauxText('Qualified scripted Engineer completion')]); },
  () => { requests++; return ai.fauxAssistantMessage('Ordinary subordinate, no stage authority'); },
]);
async function call(name, args) {
  const tools = [...new Map([...session.agent.state.tools, ...session._getCallableTools()].map(tool => [tool.name, tool])).values()];
  const message = ai.fauxAssistantMessage([]);
  return runToolCall({ type: 'toolCall', id: crypto.randomUUID(), name, arguments: args }, {
    tools, assistantMessage: message, context: { messages: session.agent.state.messages, tools },
    beforeToolCall: ctx => session.agent.beforeToolCall(ctx), afterToolCall: ctx => session.agent.afterToolCall(ctx) });
}
try {
  await session.bindExtensions({ onError: error => errors.push(error.message) });
  session.setActiveToolsByName([...session.getActiveToolNames(), 'fight_build_engineer']);
  const initial = await call('file_screening_status', {});
  assert.equal(JSON.parse(initial.result.content[0].text).workflow.skill, 'build');
  // Controlled input dispatch checks, not operator authority or live acceptance:
  // RPC cannot select a role; an actual root interactive selection needs no Jev.
  await session.extensionRunner.emitInput('/skill:work TASK-00560', undefined, 'rpc');
  assert.equal(JSON.parse((await call('file_screening_status', {})).result.content[0].text).workflow.skill, 'build');
  await session.extensionRunner.emitInput('/skill:work TASK-00560', undefined, 'interactive');
  assert.equal(JSON.parse((await call('file_screening_status', {})).result.content[0].text).workflow.skill, 'work');
  await session.extensionRunner.emitInput('/skill:build TASK-00560', undefined, 'interactive');
  const registry = globalThis[Symbol.for('pi-subagents:manager')];
  const observer = new SubagentObserver(session.sessionManager.getSessionId(), frame => frames.push(frame));
  const unstarted = events.on('subagents:started', event => observer.started(event));
  const ticker = setInterval(() => observer.tick(), 10);
  ticker.unref();  const attempt = join(root, '.runs/build/TASK-00560/run/engineer'); mkdirSync(attempt, { recursive: true });
  writeFileSync(join(attempt, 'request.md'), 'Scripted transport qualification only. No real Work or acceptance claim.');
  const request = { task: 'TASK-00560', role: 'engineer', checkout: root, attempt, head: git('rev-parse', 'HEAD'),
    provider: 'faux', model: 'engineer-qualification', thinking: 'high', timeout: 30 };
  const result = await call('fight_build_engineer', request);
  assert.equal(result.isError, false, JSON.stringify(result.result));
  const outcome = JSON.parse(result.result.content[0].text);
  assert.equal(outcome.state, 'completed'); assert.equal(outcome.admitted, true); assert.equal(outcome.executionSettled, true);
  assert.equal(outcome.thinking, 'high'); assert.equal(session.thinkingLevel, 'medium');
  assert.equal(outcome.acceptance, false); assert.equal(requests, 2);
  const record = registry.getRecord(outcome.id);
  assert.equal(record.rootSessionId, session.sessionManager.getSessionId());
  assert.equal(record.session.messages.filter(message => message.role === 'user').length, 1);
  observer.tick();
  assert.ok(frames.some(frame => frame.text.includes('Early scripted')));
  assert.ok(frames.some(frame => /Tool: read|Finished: read/.test(frame.text)));
  assert.equal(frames.at(-1).identity.task, 'TASK-00560'); assert.equal(frames.at(-1).identity.role, 'engineer');
  assert.equal(frames.at(-1).identity.attempt, attempt); assert.match(frames.at(-1).text, /Qualified scripted/);
  // Native SDK child retained exact session identity and actual Fight Work admission.
  const childStatus = record.session.agent.state.tools.find(tool => tool.name === 'file_screening_status');
  const status = await childStatus.execute('status', {}, undefined, undefined, { sessionManager: record.session.sessionManager });
  const child = JSON.parse(status.content[0].text);
  assert.equal(child.workflow.skill, 'work'); assert.equal(child.workflow.taskId, 'TASK-00560');
  assert.equal(child.workflow.checkout, root); assert.equal(child.stage.attempt, attempt);
  assert.equal(child.stage.parent, session.sessionManager.getSessionId());
  assert.deepEqual(child.stage.effects, ['source', 'checks', 'commit', 'task-handoff']);
  assert.equal((await call('fight_build_engineer', request)).isError, true); assert.equal(requests, 2);
  // An ordinary subordinate in the same Build process cannot inherit its launcher.
  const subordinate = registry.spawn(null, null, 'fight-explorer', 'Controlled subordinate probe.', { description: 'No stage authority', isBackground: true });
  await registry.waitForAll();
  const sub = registry.getRecord(subordinate).session;
  const subStatus = await sub.agent.state.tools.find(tool => tool.name === 'file_screening_status').execute('status', {}, undefined, undefined, {});
  assert.equal(JSON.parse(subStatus.content[0].text).workflow.skill, 'review');
  await sub.extensionRunner.emitInput('/skill:work TASK-00560', undefined, 'interactive');
  const stillSubordinate = await sub.agent.state.tools.find(tool => tool.name === 'file_screening_status').execute('status', {}, undefined, undefined, {});
  assert.equal(JSON.parse(stillSubordinate.content[0].text).workflow.skill, 'review');
  assert.equal(process.env.FIGHT_SCREENING_PHASE, 'build');
  const failures = [];
  const fresh = (name, timeout = 10) => {
    const attempt = join(root, '.runs/build/TASK-00560/run', name); mkdirSync(attempt, { recursive: true });
    writeFileSync(join(attempt, 'request.md'), 'Controlled installed failure qualification.');
    return { ...request, attempt, timeout };
  };
  faux.setResponses([() => { requests++; return ai.fauxAssistantMessage([], { stopReason: 'error', errorMessage: 'Controlled provider error' }); }]);
  const providerFailure = await call('fight_build_engineer', fresh('provider-error'));
  const providerOutcome = JSON.parse(providerFailure.result.content[0].text);
  assert.equal(providerFailure.isError, true); assert.equal(providerOutcome.state, 'error');
  assert.equal(providerOutcome.executionSettled, true); failures.push(providerOutcome);
  const pendingResponse = (_context, options) => {
    requests++;
    return new Promise(resolve => {
      const finish = () => resolve(ai.fauxAssistantMessage([], { stopReason: 'aborted' }));
      if (options.signal.aborted) finish(); else options.signal.addEventListener('abort', finish, { once: true });
    });
  };
  faux.setResponses([pendingResponse]);
  const timed = await call('fight_build_engineer', fresh('timeout', 1));
  const timedOutcome = JSON.parse(timed.result.content[0].text);
  assert.equal(timed.isError, true); assert.equal(timedOutcome.reason, 'timeout');
  assert.equal(timedOutcome.executionSettled, true); failures.push(timedOutcome);
  let entered;
  const streaming = new Promise(resolve => { entered = resolve; });
  faux.setResponses([(context, options) => { entered(); return pendingResponse(context, options); }]);
  const duringReload = call('fight_build_engineer', fresh('reload'));
  await streaming;
  await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'reload' });
  const reloaded = await duringReload;
  assert.equal(reloaded.isError, true);
  const reloadOutcome = JSON.parse(reloaded.result.content[0].text);
  assert.ok(['parent-shutdown-or-reload', 'record-unavailable-or-replaced'].includes(reloadOutcome.reason));
  failures.push(reloadOutcome);
  clearInterval(ticker); unstarted(); observer.close();
  assert.deepEqual(errors, []);
  const summary = { sdk: sdk.VERSION, harness, fixture: root, pass: true, installedCopy: true,
    engines: loader.getExtensions().extensions.filter(extension => extension.path.endsWith('/vendor/pi-subagents/src/index.ts')).length,
    outcome, failures, childWorkflow: child.workflow, childStage: child.stage, passiveFrames: frames.length, scriptedRequests: requests,
    externalProviderRequests: 0, realEngineerAssignment: false, nativeHerdrEvidence: false };
  writeFileSync(join(evidence, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
  console.log(JSON.stringify(summary));
} finally {
  await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'quit' }); session.dispose();
}
