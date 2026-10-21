// Owning SDK qualification: package/direct loading, registration and header command behavior.
// Run with installed Pi node_modules and a task-owned ignored evidence directory. No provider calls.
import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, readFileSync, realpathSync, existsSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]);
mkdirSync(evidence, { recursive: true });
const fixture = realpathSync.native(mkdtempSync(join(evidence, 'loading-')));
const home = join(fixture, 'home'), root = join(fixture, 'project'), agentDir = join(home, '.pi/agent');
for (const path of [home, root, agentDir]) mkdirSync(path, { recursive: true, mode: 0o700 });
// Select the disposable HOME before importing any SDK or harness module with default operator paths.
process.env.HOME = home; process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
for (const key of ['FIGHT_SCREENING_PHASE', 'FIGHT_SCREENING_TASK', 'FIGHT_SCREENING_CHECKOUT', 'FIGHT_PI_HEADER', 'NO_COLOR']) delete process.env[key];
process.env.TERM = 'xterm-256color';
const provider = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..');
const harness = process.env.FIGHT_QUALIFICATION_HARNESS ? resolve(process.env.FIGHT_QUALIFICATION_HARNESS) : join(provider, 'harness/pi');
const manifest = JSON.parse(readFileSync(join(harness, 'package.json'), 'utf8'));
const entries = manifest.pi.extensions.map(path => resolve(harness, path));
const sdk = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/index.js')));
const { visibleWidth } = await import(pathToFileURL(join(modules, '@earendil-works/pi-tui/dist/index.js')));
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null,
  modelsStorePath: join(agentDir, 'models.json'), refreshOnCreate: false });
let credentialReads = 0;
runtime.getApiKeyForProvider = async () => { credentialReads++; throw new Error('Unexpected credential resolution'); };
const model = runtime.getModel('openai-codex', 'gpt-6.1-sol'); assert.ok(model);
const expectedCommands = ['fight-header', 'fight-policy', 'fight-stage', 'file-screening', 'jev'];
const expectedFlags = ['fight-header', 'fight-screening-required', 'fight-tool-policy'];
// file_locate is session/enrollment-dependent and exercised by optional-jev.mjs.
const expectedTools = ['file_screening_status', 'jev_read_file'];
const scenarios = [], headers = {};
let outcomes = 0;
async function load(paths) {
  const settings = sdk.SettingsManager.inMemory({ compaction: { enabled: false }, retry: { enabled: false } });
  const loader = new sdk.DefaultResourceLoader({ cwd: root, agentDir, settingsManager: settings,
    noExtensions: true, noSkills: true, noPromptTemplates: true, noThemes: true, noContextFiles: true,
    additionalExtensionPaths: paths });
  await loader.reload();
  return { settings, loader, loaded: loader.getExtensions() };
}
for (const [name, paths, mode] of [
  ['package', [harness], 'tui'], ['explicit', entries, 'tui'],
  ['package-and-explicit-deduplicated', [harness, ...entries], 'tui'],
  ['header-only', [entries[0]], 'tui'], ['package-json', [harness], 'json'],
  ['package-no-tools', [harness], 'json'],
]) {
  const { settings, loader, loaded } = await load(paths);
  assert.deepEqual(loaded.errors, []); assert.deepEqual(loaded.warnings ?? [], []);
  const headerOnly = name === 'header-only';
  assert.equal(loaded.extensions.length, headerOnly ? 1 : 4);
  const registrations = key => loaded.extensions.flatMap(extension => [...extension[key].keys()]).sort();
  assert.deepEqual(registrations('commands'), headerOnly ? ['fight-header'] : expectedCommands);
  assert.deepEqual(registrations('flags'), headerOnly ? ['fight-header'] : expectedFlags);
  assert.deepEqual(registrations('tools'), headerOnly ? [] : expectedTools);
  outcomes += 6;
  let header, headerCalls = 0;
  const notices = [], errors = [];
  const { session } = await sdk.createAgentSession({ cwd: root, agentDir, modelRuntime: runtime, model,
    settingsManager: settings, resourceLoader: loader, sessionManager: sdk.SessionManager.inMemory(root), noTools: name === 'package-no-tools' });
  // A command must not fall through to a model/provider request.
  session.agent.streamFunction = async () => { throw new Error('Unexpected model request'); };
  try {
    await session.bindExtensions({ mode, uiContext: mode === 'tui' ? {
      setHeader(value) { header = value; headerCalls++; }, setFooter() {}, setStatus() {},
      notify(message) { notices.push(message); }, select: async () => undefined,
    } : undefined, onError: error => errors.push(error) });
    if (name === 'package-no-tools') { assert.equal(session.getActiveToolNames().includes('bash'), false); outcomes++; }
    const theme = { name: 'dark', getColorMode: () => 'truecolor', fg: (_role, value) => value };
    const render = width => header?.({}, theme).render(width) ?? [];
    if (mode === 'tui') {
      const initial = render(80); assert.match(initial.join('\n'), /F I G H T/); outcomes++;
      const views = { auto: initial };
      for (const choice of ['port', 'wordmark', 'compact', 'ascii', 'off', 'auto']) {
        await session.prompt(`/fight-header ${choice}`);
        const lines = render(80); views[choice] = lines;
        if (choice === 'off') assert.equal(header, undefined);
        else assert.ok(lines.length > 0);
        for (const width of [4, 40, 80, 160]) assert.ok(render(width).every(line => visibleWidth(line) <= width));
        outcomes += 5;
      }
      await session.prompt('/fight-header invalid-mode');
      assert.match(notices.at(-1), /Choose auto, port, wordmark, compact, ascii, off/); outcomes++;
      assert.deepEqual(render(80), views.auto); outcomes++;
      // Actual SDK event dispatch, without submitting a model turn.
      await session.extensionRunner.emit({ type: 'agent_start' });
      assert.equal(render(80).length, 1); outcomes++;
      headers[name] = views;
      if (!headerOnly) {
        await session.prompt('/jev inspect'); assert.match(notices.at(-1), /missing-setup/); outcomes++;
        await session.prompt('/fight-policy'); assert.match(notices.at(-1), /not-selected/); outcomes++;
      }
    } else { assert.equal(headerCalls, 0); outcomes++; }
    assert.deepEqual(errors, []); assert.equal(session.model.id, model.id); outcomes += 2;
    await session.extensionRunner.emit({ type: 'session_shutdown' });
    assert.equal(header, undefined); outcomes++;
    scenarios.push({ name, mode, commands: registrations('commands'), flags: registrations('flags'), tools: registrations('tools') });
  } finally { session.dispose(); }
}
assert.deepEqual(headers.package, headers.explicit);
assert.deepEqual(headers.package, headers['package-and-explicit-deduplicated']);
assert.deepEqual(headers.package, headers['header-only']); outcomes += 3;
// Exercise obsolete direct-load recovery only on the migrated source, not a baseline checkout.
let obsoleteRecovery = 'not-applicable-baseline';
if (!existsSync(join(harness, 'src/extension.ts'))) {
  const old = await load([join(harness, 'src/extension.ts')]);
  assert.equal(old.loaded.extensions.length, 0);
  // Pi may diagnose a missing resource during discovery rather than extension evaluation.
  const recovered = await load([join(harness, 'src/header-extension.ts')]);
  assert.deepEqual(recovered.loaded.errors, []);
  assert.equal(recovered.loaded.extensions.length, 1);
  assert.deepEqual([...recovered.loaded.extensions[0].commands.keys()], ['fight-header']);
  obsoleteRecovery = 'old-path-unloaded-new-path-registers-once'; outcomes += 4;
}
assert.equal(credentialReads, 0);
assert.equal(existsSync(join(home, 'Library/Application Support/Fight Agent OS/jev')), false); outcomes += 2;
writeFileSync(join(evidence, 'headers.json'), JSON.stringify(headers.package, null, 2));
console.log(JSON.stringify({ result: 'passed', runtime: sdk.VERSION, fixture, scenarios, outcomes, obsoleteRecovery,
  liveProviderRequests: 0, realCredentialReads: credentialReads, operatorStateCreated: false,
  presentation: 'SDK-loaded header callbacks; not native terminal visual evidence' }));
