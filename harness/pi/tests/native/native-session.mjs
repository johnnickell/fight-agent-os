// Owning installed-SDK qualification for fresh-session persistence and human refusals.
// No provider requests, credential reads, fixed monitor launches or origin-attestation claim.
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, mkdtempSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { NativeLauncher } from '../../src/observation/native-launch.mjs';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]);
mkdirSync(evidence, { recursive: true });
const fixture = realpathSync(mkdtempSync(join(evidence, 'session-')));
const home = join(fixture, 'home'), root = join(fixture, 'project'), agentDir = join(home, '.pi/agent');
for (const path of [home, root, agentDir]) mkdirSync(path, { recursive: true, mode: 0o700 });
process.env.HOME = home; process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
const sdk = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/index.js')));
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null,
  modelsStorePath: join(agentDir, 'models.json'), refreshOnCreate: false });
let credentialReads = 0, providerRequests = 0;
runtime.getApiKeyForProvider = async () => { credentialReads++; throw new Error('Unexpected credential resolution'); };
const model = runtime.getModel('openai-codex', 'gpt-6.1-sol'); assert.ok(model);
const extension = resolve(dirname(fileURLToPath(import.meta.url)), '../../src/native-observation-extension.ts');
const scenarios = [];
for (const [name, mode, noTools, shellPath, customBash] of [
  ['fresh-malformed', 'tui', false, undefined, false],
  ['rpc-refusal', 'rpc', false, undefined, false],
  ['disabled-bash', 'tui', true, undefined, false],
  ['shell-override', 'tui', false, '/bin/sh', false],
  ['custom-executor', 'tui', false, undefined, true],
]) {
  const settings = sdk.SettingsManager.inMemory({ shellPath, compaction: { enabled: false }, retry: { enabled: false } });
  const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager: settings,
    noExtensions: true, noSkills: true, noPromptTemplates: true, noThemes: true, noContextFiles: true,
    additionalExtensionPaths: [extension], extensionFactories: customBash ? [pi => {
      pi.registerTool({ ...sdk.createBashToolDefinition(root),
        execute: async () => { throw new Error('Custom executor must not be invoked'); } });
    }] : [] });
  await loader.reload(); assert.deepEqual(loader.getExtensions().errors, []);
  const manager = sdk.SessionManager.create(root, join(fixture, name));
  manager.appendCustomEntry('setup-only', { version: 1 });
  const file = manager.getSessionFile(); assert.ok(file); assert.equal(existsSync(file), false);
  const launcher = new NativeLauncher({ cwd: root, sessionManager: manager });
  assert.equal(launcher.parentFile, null); assert.equal(launcher.pendingParentFile, file); launcher.close();
  const { session } = await sdk.createAgentSession({ cwd: root, agentDir, modelRuntime: runtime, model,
    settingsManager: settings, resourceLoader: loader, sessionManager: manager, noTools });
  session.agent.streamFunction = async () => { providerRequests++; throw new Error('Unexpected provider request'); };
  const notices = [], errors = [];
  try {
    await session.bindExtensions({ mode, uiContext: { notify: message => notices.push(message) }, onError: error => errors.push(error) });
    await session.prompt(name === 'fresh-malformed' ? '/fight-stage {private-invalid-json}' : '/fight-stage {}');
    await new Promise(resolve => setTimeout(resolve, 20));
    const expected = name === 'fresh-malformed' ? /supply valid JSON/ : name === 'rpc-refusal' ? /Native-only launch/ :
      name === 'shell-override' ? /configured shell override/ : /existing authorized launch route/;
    assert.ok(notices.some(text => expected.test(text)), JSON.stringify({ name, notices }));
    assert.ok(notices.every(text => !text.includes('private-invalid-json')));
    assert.equal(existsSync(file), false, 'commands must not fabricate a persisted conversation');
    assert.equal(existsSync(join(root, 'var/terminal/children')), false, 'refusal must occur before custody/bootstrap');
    assert.deepEqual(errors, []);
    if (noTools) assert.equal(session.getActiveToolNames().includes('bash'), false);
    await session.extensionRunner.emit({ type: 'session_shutdown' });
    scenarios.push({ name, mode, notices, fileCreated: false, custodyStarted: false });
  } finally { await session.extensionRunner.emit({ type: 'session_shutdown' }); session.dispose(); }
}
assert.equal(providerRequests, 0); assert.equal(credentialReads, 0);
const summary = { result: 'passed', runtime: sdk.VERSION, scenarios, providerRequests, credentialReads,
  boundary: 'Actual SDK contexts/refusals; live native launches qualified separately; no independently attested origin' };
writeFileSync(join(evidence, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
console.log(JSON.stringify(summary, null, 2));
