// Owning installed-SDK regression: observation must preserve actual custom bash execution.
// Run explicitly with <installed node_modules> <task evidence directory>; no provider or monitor.
import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]);
mkdirSync(evidence, { recursive: true });
const fixture = realpathSync(mkdtempSync(join(evidence, 'executors-')));
const home = join(fixture, 'home'), root = join(fixture, 'project'), agentDir = join(home, '.pi/agent');
for (const path of [home, root, agentDir]) mkdirSync(path, { recursive: true, mode: 0o700 });
process.env.HOME = home; process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
const sdk = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/index.js')));
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null,
  modelsStorePath: join(agentDir, 'models.json'), refreshOnCreate: false });
let credentialReads = 0, providerRequests = 0;
runtime.getApiKeyForProvider = async () => { credentialReads++; throw new Error('Unexpected credential resolution'); };
const model = runtime.getModel('openai-codex', 'gpt-6.1-sol'); assert.ok(model);
const observer = resolve(dirname(fileURLToPath(import.meta.url)), '../../src/native-observation-extension.ts');
const refusal = 'NATIVE_EXECUTOR_CUSTOM_REFUSAL', scenarios = [];
for (const order of ['observer-first', 'custom-first', 'observer-absent']) {
  for (const timing of ['initial', 'session_start', 'command']) {
    const name = `${order}-${timing}`, callsFile = join(fixture, `${name}.calls.json`), stateFile = join(fixture, `${name}.state.json`);
    writeFileSync(callsFile, '[]');
    const custom = join(fixture, `${name}.ts`);
    writeFileSync(custom, `
import { createBashToolDefinition } from '@earendil-works/pi-coding-agent';
import { readFileSync, writeFileSync } from 'node:fs';
export default function (pi) {
  const register = () => pi.registerTool({ ...createBashToolDefinition(${JSON.stringify(root)}),
    execute: async (_id, args) => {
      const path = ${JSON.stringify(callsFile)};
      const calls = JSON.parse(readFileSync(path, 'utf8'));
      calls.push(args.command); writeFileSync(path, JSON.stringify(calls));
      throw new Error(${JSON.stringify(refusal)});
    } });
  ${timing === 'initial' ? 'register();' : timing === 'session_start' ? "pi.on('session_start', register);" : "pi.registerCommand('select-custom', { handler: async () => register() });"}
  pi.registerCommand('executor-state', { handler: async () => {
    writeFileSync(${JSON.stringify(stateFile)}, JSON.stringify({
      bash: pi.getAllTools().filter(tool => tool.name === 'bash').map(tool => tool.sourceInfo),
      api: Object.keys(pi).sort(), active: pi.getActiveTools(),
    }));
  } });
}
`);
    const settings = sdk.SettingsManager.inMemory({ compaction: { enabled: false }, retry: { enabled: false } });
    const paths = order === 'observer-first' ? [observer, custom] : order === 'custom-first' ? [custom, observer] : [custom];
    const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager: settings,
      noExtensions: true, noSkills: true, noPromptTemplates: true, noThemes: true, noContextFiles: true,
      additionalExtensionPaths: paths });
    await loader.reload(); assert.deepEqual(loader.getExtensions().errors, []);
    const { session } = await sdk.createAgentSession({ cwd: root, agentDir, modelRuntime: runtime, model,
      settingsManager: settings, resourceLoader: loader, sessionManager: sdk.SessionManager.inMemory(root) });
    session.agent.streamFunction = async () => { providerRequests++; throw new Error('Unexpected provider request'); };
    const errors = [], outcomes = [];
    try {
      await session.bindExtensions({ mode: 'tui', uiContext: { notify() {} }, onError: error => errors.push(error) });
      if (timing === 'command') await session.prompt('/select-custom');
      await session.prompt('/executor-state');
      const state = JSON.parse(readFileSync(stateFile, 'utf8'));
      for (const command of ['printf NATIVE_EXECUTOR_UNEXPECTED_LOCAL_EXECUTION', 'fight-stage {']) {
        // Keep the literal formerly intercepted prefix as the R3 regression: neither ordinary
        // commands nor the removed bridge's prefix may bypass the independently selected executor.
        const tool = session.agent.state.tools.find(tool => tool.name === 'bash'); assert.ok(tool);
        try {
          const result = await tool.execute(name, { command }, new AbortController().signal);
          outcomes.push({ command, result, preserved: false });
        } catch (error) {
          outcomes.push({ command, error: error.message, preserved: error.message === refusal });
        }
      }
      assert.deepEqual(errors, []);
      const calls = JSON.parse(readFileSync(callsFile, 'utf8'));
      scenarios.push({ order, timing, state, calls, outcomes,
        preserved: calls.length === 2 && outcomes.every(outcome => outcome.preserved) });
    } finally { await session.extensionRunner.emit({ type: 'session_shutdown' }); session.dispose(); }
  }
}
assert.equal(providerRequests, 0); assert.equal(credentialReads, 0);
const failed = scenarios.filter(scenario => !scenario.preserved);
const summary = { result: failed.length ? 'failed' : 'passed', sdk: sdk.VERSION, scenarios,
  providerRequests, credentialReads, failures: failed.map(({ order, timing }) => ({ order, timing })),
  boundary: 'Actual SDK selected-tool execution in both extension orders and absent controls; no provider, credentials, custodian, monitor or origin-attestation claim' };
writeFileSync(join(evidence, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
console.log(JSON.stringify(summary, null, 2));
assert.equal(failed.length, 0, 'Observation must preserve the custom executor at every supported registration timing and load order');
