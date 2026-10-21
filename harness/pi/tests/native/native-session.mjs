// Owning installed-SDK qualification: passive observation adds no execution route.
// No provider, credential, monitor or claimed native-origin operation.
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, mkdtempSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { NativeObservation } from '../../src/observation/native-observation.mjs';

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
  ['fresh-native-context', 'tui', false, undefined, false],
  ['rpc-no-native-bootstrap', 'rpc', false, undefined, false],
  ['disabled-bash', 'tui', true, undefined, false],
  ['shell-override', 'tui', false, '/bin/sh', false],
  ['custom-executor', 'tui', false, undefined, true],
]) {
  const settings = sdk.SettingsManager.inMemory({ shellPath, shellCommandPrefix: name === 'shell-override' ? 'printf SDK_PREFIX; ' : undefined,
    compaction: { enabled: false }, retry: { enabled: false } });
  let customCalls = 0;
  const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager: settings,
    noExtensions: true, noSkills: true, noPromptTemplates: true, noThemes: true, noContextFiles: true,
    additionalExtensionPaths: [extension], extensionFactories: customBash ? [pi => {
      pi.registerTool({ ...sdk.createBashToolDefinition(root), execute: async () => { customCalls++; throw new Error('SDK_CUSTOM_REFUSAL'); } });
    }] : [] });
  await loader.reload(); assert.deepEqual(loader.getExtensions().errors, []);
  const manager = sdk.SessionManager.create(root, join(fixture, name));
  manager.appendCustomEntry('setup-only', { version: 1 });
  const file = manager.getSessionFile(); assert.ok(file); assert.equal(existsSync(file), false);
  const observation = new NativeObservation({ cwd: root, sessionManager: manager });
  assert.equal(observation.parentFile, null); assert.equal(observation.pendingParentFile, file); observation.close();
  const { session } = await sdk.createAgentSession({ cwd: root, agentDir, modelRuntime: runtime, model,
    settingsManager: settings, resourceLoader: loader, sessionManager: manager, noTools });
  session.agent.streamFunction = async () => { providerRequests++; throw new Error('Unexpected provider request'); };
  const errors = [];
  try {
    await session.bindExtensions({ mode, uiContext: { notify() {} }, onError: error => errors.push(error) });
    assert.ok(!session.extensionRunner.getRegisteredCommands().some(command => command.name === 'fight-stage'));
    const bash = session.agent.state.tools.find(tool => tool.name === 'bash');
    if (noTools) assert.equal(bash, undefined);
    else if (customBash) { await assert.rejects(bash.execute(name, { command: 'printf SDK_UNEXPECTED' }), /SDK_CUSTOM_REFUSAL/); assert.equal(customCalls, 1); }
    else {
      const result = await bash.execute(name, { command: 'printf SDK_UNCHANGED' });
      const output = result.content.map(block => block.text ?? '').join('');
      assert.match(output, /SDK_UNCHANGED/);
      if (name === 'shell-override') assert.match(output, /SDK_PREFIX/);
    }
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(existsSync(file), false, 'observation cannot fabricate persisted conversation');
    assert.equal(existsSync(join(root, 'var/terminal/children')), false, 'SDK host is not a native bootstrap');
    assert.deepEqual(errors, []);
    scenarios.push({ name, mode, fileCreated: false, customCalls, bashEnabled: Boolean(bash) });
  } finally { await session.extensionRunner.emit({ type: 'session_shutdown' }); session.dispose(); }
}
assert.equal(providerRequests, 0); assert.equal(credentialReads, 0);
const summary = { result: 'passed', runtime: sdk.VERSION, scenarios, providerRequests, credentialReads,
  boundary: 'Actual SDK execution/settings/disabled tools; real native automatic launch/resume qualified separately' };
writeFileSync(join(evidence, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
console.log(JSON.stringify(summary, null, 2));
