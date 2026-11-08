// Direct owning qualification: actual native CLI + normal provider process-stage entry.
// All operator/session/provider state is synthetic and isolated. No genuine Build authority.
// Usage: node retirement.mjs PI_NODE_MODULES TASK_EVIDENCE COPIED_HARNESS
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { createHash, randomUUID } from 'node:crypto';
import { chmodSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]), harness = realpathSync(process.argv[4]);
mkdirSync(evidence, { recursive: true });
const fixture = realpathSync(mkdtempSync(join(evidence, 'retirement-')));
const provider = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..');
const hash = value => createHash('sha256').update(value).digest('hex');
const snapshot = root => existsSync(root) ? readdirSync(root, { recursive: true, withFileTypes: true })
  .filter(entry => entry.isFile()).map(entry => {
    const path = join(entry.parentPath, entry.name);
    return [path, hash(readFileSync(path))];
  }).sort(([a], [b]) => a.localeCompare(b)) : [];
const put = (path, data) => { mkdirSync(dirname(path), { recursive: true, mode: 0o700 }); writeFileSync(path, typeof data === 'string' ? data : JSON.stringify(data), { mode: 0o600 }); };
const scenarios = [];
for (const mode of ['fresh', 'enrolled', 'corrupt-held']) {
  const base = join(fixture, mode), root = join(base, 'project'), home = join(base, 'home'), agentDir = join(home, '.pi/agent');
  for (const path of [root, home, agentDir]) mkdirSync(path, { recursive: true, mode: 0o700 });
  const env = { HOME: home, PATH: process.env.PATH, TERM: 'dumb', NO_COLOR: '1', PI_OFFLINE: '1',
    PI_CODING_AGENT_DIR: agentDir, PYTHONDONTWRITEBYTECODE: '1' };
  // No inherited evaluator/provider keys, stage variables, account settings or trust.
  assert.equal(spawnSync('git', ['init', '--initial-branch=develop'], { cwd: root, env }).status, 0);
  const legacy = join(home, 'Library/Application Support/Fight Agent OS/jev');
  if (mode !== 'fresh') {
    const revision = randomUUID();
    const profile = { version: 'screening-profile-v2', revision, configuration: hash('historical fixture connection'),
      projects: [{ root, excludedPaths: [join(root, '.pi/settings.json')], instructions: [], targets: [] }],
      excludedNames: ['.env', 'auth.json'], providerDisclosure: true, expiresAt: null, commandDisclosure: false,
      revoked: false, requestMicroUsd: 2688, sessionMicroUsd: 100000 };
    put(join(legacy, 'screening-profile.json'), mode === 'corrupt-held' ? '{unreadable historical enrollment' : profile);
    put(join(legacy, `enforcement-${revision}.json`), { version: 1, profile: 'file-screening', revision: 1,
      roots: [root], protectedPaths: [legacy, join(root, '.pi/settings.json')], tools: ['read', 'write', 'edit'], effects: ['read', 'write'], scripts: [] });
    put(join(legacy, 'connection.json'), '{historical unavailable connection');
    put(join(legacy, 'monthly.json'), { settledMicroUsd: 13, pending: [{ id: randomUUID(), reservedMicroUsd: 2688, outcome: 'unknown' }] });
    put(join(legacy, 'legacy.json'), { unresolvedExposureMicroUsd: 5376, evidence: 'synthetic retained liability' });
    put(join(legacy, 'session.json'), { spentMicroUsd: 13, finiteTestGrant: 1 });
    put(join(legacy, 'receipt.json'), { historical: true, chargeMicroUsd: 13 });
    put(join(legacy, 'cache/receipt.json'), { historical: true });
    put(join(legacy, 'writer.lock'), 'Historical unresolved lock: never clear automatically');
  }
  put(join(agentDir, 'auth.json'), { openrouter: { type: 'api_key', key: 'synthetic-unused-fixture-not-a-credential' } });
  put(join(root, 'AGENTS.md'), 'Synthetic qualification only. Do not call any external service.');
  const probe = join(base, 'probe.ts');
  put(probe, `import { fauxProvider, fauxAssistantMessage, fauxText, fauxToolCall } from '@earendil-works/pi-ai';
import { appendFileSync, writeFileSync } from 'node:fs';
export default function(pi) {
  globalThis.fetch = async () => { appendFileSync(${JSON.stringify(join(base, 'unexpected-network'))}, 'fetch\\n'); throw Error('external-network-forbidden'); };
  const faux = fauxProvider({provider:'retirement-fixture', tokensPerSecond:10000, models:[{id:'local', contextWindow:200000, reasoning:true}]});
  const call = (name, args) => () => fauxAssistantMessage([fauxToolCall(name, args)], {stopReason:'toolUse'});
  faux.setResponses([
    call('write', {path:'created.txt', content:'before'}),
    call('edit', {path:'created.txt', edits:[{oldText:'before',newText:'after'}]}),
    call('read', {path:'created.txt'}),
    call('bash', {command:'printf native-shell > shell-marker'}),
    call('fight_workflow_status', {}),
    () => fauxAssistantMessage([fauxText('Local native qualification complete; not acceptance')]),
  ]);
  pi.registerProvider(faux.provider);
  pi.on('session_start', (_event, ctx) => {
    writeFileSync(${JSON.stringify(join(base, 'registration.json'))}, JSON.stringify({
      tools:pi.getAllTools().map(t=>t.name), commands:pi.getCommands().map(c=>c.name),
      session:ctx.sessionManager.getSessionId(), file:ctx.sessionManager.getSessionFile(), cwd:ctx.cwd
    }));
  });
}
`);
  const settings = { packages: [harness], extensions: [probe], defaultProvider: 'retirement-fixture', defaultModel: 'local',
    compaction: { enabled: true }, retry: { enabled: false }, openrouter: { retainedFixturePreference: true } };
  put(join(agentDir, 'settings.json'), settings);
  const before = snapshot(legacy), auth = readFileSync(join(agentDir, 'auth.json')), settingsBytes = readFileSync(join(agentDir, 'settings.json'));
  const parent = spawnSync('pi', ['--offline', '--mode', 'json', '--session-dir', join(base, 'parent-sessions'), '--provider', 'retirement-fixture', '--model', 'local', '--thinking', 'high', 'Perform the synthetic local probe only.'],
    { cwd: root, env, encoding: 'utf8', timeout: 60000, maxBuffer: 8 * 1024 * 1024 });
  put(join(base, 'parent.stdout'), parent.stdout); put(join(base, 'parent.stderr'), parent.stderr);
  assert.equal(parent.status, 0, parent.stderr);
  const parentRegistration = JSON.parse(readFileSync(join(base, 'registration.json')));
  const attempt = join(base, 'attempt'); mkdirSync(attempt);
  put(join(attempt, 'request.md'), 'Synthetic native process-stage probe only. No real target Work or acceptance.');
  const child = spawnSync('python3', ['-B', join(provider, '.pi/skills/build/scripts/run-stage.py'), '--harness-package', harness,
    '--attempt', attempt, '--task', 'TASK-00586', '--role', 'engineer', '--provider', 'retirement-fixture', '--model', 'local', '--thinking', 'high', '--timeout', '60', '--heartbeat', '5'],
    { cwd: root, env, encoding: 'utf8', timeout: 90000, maxBuffer: 8 * 1024 * 1024 });
  put(join(base, 'child.stdout'), child.stdout); put(join(base, 'child.stderr'), child.stderr);
  assert.equal(child.status, 0, child.stdout + child.stderr);
  const childRegistration = JSON.parse(readFileSync(join(base, 'registration.json')));
  assert.notEqual(parentRegistration.session, childRegistration.session);
  for (const registration of [parentRegistration, childRegistration]) {
    assert.equal(registration.cwd, root);
    for (const name of ['read', 'write', 'edit', 'bash', 'fight_workflow_status']) assert.ok(registration.tools.includes(name));
    for (const name of ['jev_read_file', 'file_locate', 'file_screening_status']) assert.ok(!registration.tools.includes(name));
    assert.ok(!registration.commands.some(name => ['jev', 'file-screening'].includes(name)));
  }
  const messages = output => output.trim().split('\n').map(line => JSON.parse(line)).filter(event => event.type === 'message_end').map(event => event.message);
  const parentMessages = messages(parent.stdout), childMessages = messages(readFileSync(join(attempt, 'events.jsonl'), 'utf8'));
  for (const list of [parentMessages, childMessages]) {
    const results = list.filter(message => message.role === 'toolResult');
    assert.equal(results.length, 5); assert.ok(results.every(result => !result.isError));
  }
  const parentWorkflow = JSON.parse(parentMessages.find(message => message.toolName === 'fight_workflow_status').content[0].text).workflow;
  const childWorkflow = JSON.parse(childMessages.find(message => message.toolName === 'fight_workflow_status').content[0].text).workflow;
  assert.equal(parentWorkflow.checkout, root); assert.equal(parentWorkflow.launcherRole, null);
  assert.equal(childWorkflow.skill, 'work'); assert.equal(childWorkflow.taskId, 'TASK-00586'); assert.equal(childWorkflow.checkout, root);
  assert.equal(readFileSync(join(root, 'created.txt'), 'utf8'), 'after');
  assert.equal(readFileSync(join(root, 'shell-marker'), 'utf8'), 'native-shell');
  assert.deepEqual(snapshot(legacy), before);
  assert.deepEqual(readFileSync(join(agentDir, 'auth.json')), auth);
  assert.deepEqual(readFileSync(join(agentDir, 'settings.json')), settingsBytes);
  assert.equal(existsSync(join(base, 'unexpected-network')), false);
  scenarios.push({ mode, root, legacy, retainedFiles: before.length, parent: parentRegistration, child: childRegistration,
    parentWorkflow, childWorkflow, nativeToolEffects: 10, providerRequests: 0, evaluatorCredentials: 0,
    scriptedModelResponses: 12, retainedStateUnchanged: true, processExit: child.status, independentAcceptance: false });
}
// Session restore/provenance through the actual SDK; this is host qualification, not operator authority.
process.env.HOME = join(fixture, 'sdk-home'); mkdirSync(process.env.HOME);
process.env.PI_CODING_AGENT_DIR = join(process.env.HOME, '.pi/agent');
process.env.PI_OFFLINE = '1';
for (const name of Object.keys(process.env).filter(name => /^(FIGHT_WORKFLOW_|FIGHT_SCREENING_|HERDR_)/.test(name))) delete process.env[name];
const sdk = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/index.js')));
const root = scenarios[1].root, agentDir = process.env.PI_CODING_AGENT_DIR;
mkdirSync(agentDir, { recursive: true });
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null, refreshOnCreate: false });
const manager = sdk.SessionManager.create(root, join(fixture, 'restore-session'));
manager.appendMessage({ role: 'user', content: 'Preserved historical transcript', timestamp: Date.now() });
manager.appendCustomEntry('fight-workflow-role-v3', { sessionId: manager.getSessionId(), launcher: null,
  role: 'land', skill: 'land', taskId: 'TASK-00586', checkout: root, mode: 'default' });
const sessionFile = manager.getSessionFile(), original = readFileSync(sessionFile, 'utf8');
const settingsManager = sdk.SettingsManager.inMemory();
const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager, noExtensions: true, noSkills: true,
  noThemes: true, noPromptTemplates: true, noContextFiles: true, additionalExtensionPaths: [join(harness, 'src/workflow-extension.ts')] });
await loader.reload(); assert.deepEqual(loader.getExtensions().errors, []);
const { session } = await sdk.createAgentSession({ cwd: root, agentDir, modelRuntime: runtime,
  settingsManager, resourceLoader: loader, sessionManager: sdk.SessionManager.open(sessionFile) });
const errors = [];
await session.bindExtensions({ mode: 'json', onError: error => errors.push(error.message) });
const status = async () => (await session.agent.state.tools.find(tool => tool.name === 'fight_workflow_status').execute('status', {}, undefined, undefined, {})).details;
assert.equal((await status()).workflow.skill, 'land');
for (const source of ['rpc', 'extension']) await session.extensionRunner.emitInput('/skill:work TASK-00586', undefined, source);
assert.equal((await status()).workflow.skill, 'land');
await session.extensionRunner.emitInput(`/skill:review TASK-00586 --checkout ${root}`, undefined, 'interactive');
assert.equal((await status()).workflow.skill, 'review');
await session.reload();
assert.equal((await status()).workflow.skill, 'review');
assert.ok(readFileSync(sessionFile, 'utf8').startsWith(original));
// A real file persistence refusal cannot leave Build authority published or heal without reload.
chmodSync(sessionFile, 0o400);
await session.extensionRunner.emitInput(`/skill:build TASK-00586 --checkout ${root}`, undefined, 'interactive');
assert.equal((await status()).state, 'held');
assert.equal((await status()).reason, 'workflow-role-persistence-failed');
await session.extensionRunner.emitInput('/skill:land TASK-00586', undefined, 'interactive');
assert.equal((await status()).state, 'held');
chmodSync(sessionFile, 0o600);
await session.reload();
assert.equal((await status()).state, 'active');
assert.equal((await status()).workflow.skill, 'review');
assert.deepEqual(errors, []);
await session.extensionRunner.emit({ type: 'session_shutdown', reason: 'quit' }); session.dispose();
const summary = { result: 'passed', sdk: sdk.VERSION, fixture, scenarios, sessionRestore: { sessionFile, originalPrefixPreserved: true,
  interactiveSelectionPersists: true, rpcAndExtensionPromotion: false, persistenceFailureHoldsUntilReload: true }, externalProviderRequests: 0,
  genuineBuildAuthority: false, visualEvidence: false };
put(join(evidence, 'summary.json'), summary);
console.log(JSON.stringify(summary));
