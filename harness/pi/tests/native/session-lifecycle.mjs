// Qualify the installed public runtime's actual teardown/context contract.
// Native route continuity is checked by native-lifecycle.py, not inferred here.
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, mkdtempSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]);
mkdirSync(evidence, { recursive: true });
const fixture = realpathSync(mkdtempSync(join(evidence, 'lifecycle-'))), root = join(fixture, 'project'), agentDir = join(fixture, 'agent');
for (const path of [root, agentDir]) mkdirSync(path, { recursive: true });
process.env.HOME = fixture; process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
const sdk = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/index.js')));
const models = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null,
  modelsStorePath: join(agentDir, 'models.json'), refreshOnCreate: false });
let credentialReads = 0, providerRequests = 0, cancel = false;
models.getApiKeyForProvider = async () => { credentialReads++; throw new Error('Unexpected credential resolution'); };
const model = models.getModel('openai-codex', 'gpt-6.1-sol'); assert.ok(model);
const observer = resolve(dirname(fileURLToPath(import.meta.url)), '../../src/native-observation-extension.ts');
const events = [], contexts = [], errors = [], cases = [];
const settings = sdk.SettingsManager.inMemory({ compaction: { enabled: false }, retry: { enabled: false } });
const createRuntime = async ({ sessionManager, sessionStartEvent }) => {
  const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager: settings,
    noExtensions: true, noSkills: true, noPromptTemplates: true, noThemes: true, noContextFiles: true,
    additionalExtensionPaths: [observer], extensionFactories: [pi => {
      pi.on('session_before_switch', () => cancel ? { cancel: true } : undefined);
      pi.on('session_before_fork', () => cancel ? { cancel: true } : undefined);
      pi.on('session_start', (event, ctx) => { contexts.push(ctx); events.push(event); });
      pi.on('session_shutdown', event => { events.push(event); });
    }] });
  await loader.reload(); assert.deepEqual(loader.getExtensions().errors, []);
  const { session } = await sdk.createAgentSession({ cwd: root, agentDir, sessionManager, sessionStartEvent,
    settingsManager: settings, resourceLoader: loader, modelRuntime: models, model });
  session.agent.streamFunction = async () => { providerRequests++; throw new Error('Unexpected provider request'); };
  return { session, services: { cwd: root, agentDir }, diagnostics: [] };
};
const host = await sdk.createAgentSessionRuntime(createRuntime, { cwd: root, agentDir, sessionManager: sdk.SessionManager.inMemory(root) });
const bind = async session => session.bindExtensions({ mode: 'tui', uiContext: { notify() {} }, onError: error => errors.push(error) });
host.setRebindSession(bind);
await bind(host.session);
try {
  const original = host.session, ctx = contexts.at(-1), id = ctx.sessionManager.getSessionId();
  for (const [name, run] of [['cancel-new', () => host.newSession()], ['cancel-resume', () => host.switchSession('/unused-cancelled-target')], ['cancel-fork', () => host.fork('invalid-entry')]]) {
    cancel = true; const offset = events.length;
    assert.equal((await run()).cancelled, true); assert.equal(host.session, original);
    assert.equal(ctx.sessionManager.getSessionId(), id); assert.equal(events.length, offset);
    cases.push({ name, sameRuntime: true, teardownEvents: 0 });
  }
  cancel = false;
  const invalid = sdk.SessionManager.inMemory(join(fixture, 'absent-cwd'));
  const invalidFile = join(fixture, 'invalid-target.jsonl');
  writeFileSync(invalidFile, JSON.stringify(invalid.getHeader()) + '\n');
  for (const [name, run, expected] of [['failed-resume-validation', () => host.switchSession(invalidFile), /cwd/i], ['failed-fork-validation', () => host.fork('invalid-entry'), /Invalid entry/]]) {
    const offset = events.length;
    await assert.rejects(run(), expected); assert.equal(host.session, original);
    assert.equal(ctx.sessionManager.getSessionId(), id); assert.equal(events.length, offset);
    cases.push({ name, sameRuntime: true, teardownEvents: 0 });
  }
  const oldRunner = original.extensionRunner;
  const offset = events.length;
  assert.equal((await host.newSession()).cancelled, false);
  assert.notEqual(host.session, original);
  assert.throws(() => ctx.sessionManager.getSessionId(), /stale/);
  assert.deepEqual(events.slice(offset).map(event => [event.type, event.reason]), [['session_shutdown', 'new'], ['session_start', 'new']]);
  const current = contexts.at(-1), currentId = current.sessionManager.getSessionId();
  // Use the exported runner notification boundary, not a patched private method.
  // Notifications already queued by a disposed runtime cannot affect its successor.
  for (const event of [{ type: 'agent_end', messages: [] }, { type: 'tool_execution_end', toolCallId: 'late', toolName: 'bash', result: {}, isError: false }]) await oldRunner.emit(event);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(current.sessionManager.getSessionId(), currentId);
  assert.deepEqual(errors, []);
  assert.equal(existsSync(join(root, 'var/terminal/native')), false, 'SDK host cannot invent a native registration');
  cases.push({ name: 'replacement-and-late-old-notifications', staleContextRefused: true, currentRuntimeUnchanged: true });
} finally { await host.dispose(); }
await new Promise(resolve => setImmediate(resolve));
assert.throws(() => contexts.at(-1).sessionManager.getSessionId(), /stale/);
assert.equal(events.at(-1).type, 'session_shutdown'); assert.equal(events.at(-1).reason, 'quit');
assert.deepEqual(errors, []); assert.equal(providerRequests, 0); assert.equal(credentialReads, 0);
const summary = { result: 'passed', sdk: sdk.VERSION, cases, providerRequests, credentialReads,
  boundary: 'Actual public SDK runtime cancellation, pre-teardown validation failure, stale contexts and late notifications; genuine native routes checked separately' };
writeFileSync(join(evidence, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
console.log(JSON.stringify(summary, null, 2));
