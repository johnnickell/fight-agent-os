// Owning native qualification; run with an installed Pi node_modules path and an ignored evidence directory.
// All credentials, model output and evaluator responses are controlled. No network/provider request is made.
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync, realpathSync, existsSync, readdirSync, symlinkSync, linkSync, lstatSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { homedir } from 'node:os';
import { dirname, join, relative, resolve } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]);
const restart = process.argv[4] === 'restart';
mkdirSync(evidence, { recursive: true });
const fixture = restart ? process.argv[5] : realpathSync.native(mkdtempSync(join(evidence, 'native-')));
const main = join(fixture, 'main'), checkout = join(fixture, 'worktrees/implementation'), consumer = join(fixture, 'consumer');
const agentDir = join(fixture, 'agent'), directory = join(fixture, 'operator'), sessionDir = join(fixture, 'sessions');
if (!restart) for (const path of [main, consumer, agentDir, sessionDir, dirname(checkout)]) mkdirSync(path, { recursive: true });
process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
delete process.env.FIGHT_SCREENING_PHASE; delete process.env.FIGHT_SCREENING_CHECKOUT; delete process.env.FIGHT_SCREENING_TASK;
const provider = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..');
const harness = process.env.FIGHT_QUALIFICATION_HARNESS ? resolve(process.env.FIGHT_QUALIFICATION_HARNESS) : join(provider, 'harness/pi');
const sdk = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/index.js')));
const { runToolCall } = await import(pathToFileURL(join(modules, '@earendil-works/pi-agent-core/dist/index.js')));
const { createAssistantMessageEventStream } = await import(pathToFileURL(join(modules, '@earendil-works/pi-ai/dist/utils/event-stream.js')));
const { formatSkillsForPrompt } = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/core/skills.js')));
const { ScreeningProfiles } = await import(pathToFileURL(join(harness, 'src/jev/screening-profile.mjs')));
const { connection } = await import(pathToFileURL(join(harness, 'src/jev/readiness.mjs')));
const { MonthlyLedger } = await import(pathToFileURL(join(harness, 'src/jev/monthly-budget.mjs')));
const { prepareScreening } = await import(pathToFileURL(join(harness, 'src/screening-bootstrap.mjs')));
const effects = process.env.FIGHT_QUALIFICATION_BASELINE ? null : (await import(pathToFileURL(join(harness, 'src/jev/workflow-access.mjs')))).skillEffects;
const profiles = new ScreeningProfiles(directory), extension = join(fixture, 'extension.ts'), requests = join(fixture, 'requests.jsonl');
const put = (path, content) => { mkdirSync(dirname(path), { recursive: true }); writeFileSync(path, content); };
const git = (cwd, args) => { const result = spawnSync('/usr/bin/git', args, { cwd, encoding: 'utf8' }); assert.equal(result.status, 0, result.stderr); return result.stdout; };
if (!restart) {
  for (const root of [main, consumer]) {
    git(root, ['init', '--initial-branch=develop']);
    for (const path of ['source.txt', 'AGENTS.md', 'README.md', 'planning/tasks/BOARD.md', 'planning/ROADMAP.md', 'planning/tasks/00489-TASK.md', '.env.example']) put(join(root, path), `Fixture ${path}\n`);
    put(join(root, '.gitignore'), '.runs/\n.env\nprivate/\n');
    git(root, ['add', '.']); git(root, ['-c', 'user.name=Qualification Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Controlled qualification']);
    put(join(root, 'private/denied.md'), 'Excluded fixture'); put(join(root, '.env.local'), 'CONTROLLED-SECRET');
  }
  const { schema, ...fixed } = connection;
  const config = { ...fixed, revision: randomUUID(), priceConfirmedAt: Date.now(), disclosure: 'authorized-supplied-state', requestMicroUsd: 2688, sessionMicroUsd: 100000 };
  for (const root of [main, consumer]) profiles.save(profiles.preview({ cwd: root, configuration: config, excludedPaths: ['private'], providerDisclosure: true, commandDisclosure: true }), []);
  writeFileSync(join(fixture, 'configuration.json'), JSON.stringify(config));
  const ledger = new MonthlyLedger(directory), liability = ledger.reserve('unresolved-fixture-session', 2688, 100000); ledger.dispatch(liability);
  writeFileSync(join(fixture, 'liability.txt'), liability);
  writeFileSync(extension, `import { appendFileSync } from 'node:fs';
import { installJev } from ${JSON.stringify(join(harness, 'src/jev-extension.ts'))};
import { ScreeningProfiles } from ${JSON.stringify(join(harness, 'src/jev/screening-profile.mjs'))};
export default function(pi) { installJev(pi, {settings: {load: () => (${JSON.stringify(config)})}, profiles: new ScreeningProfiles(${JSON.stringify(directory)}), credentialConfigured: () => true,
credentials: () => ({read: async () => Buffer.from('controlled-nonsecret-fixture')}),
transport: {submit: async (_endpoint, payload) => { appendFileSync(${JSON.stringify(requests)}, JSON.stringify(payload) + '\\n');
return {status:200, body:{model:${JSON.stringify(connection.evaluator)},provider:'TypeSafe',usage:{input_tokens:10,output_tokens:2,cost:0.000003},
answers: {relevance:{type:'noul',noul:0.95}}}};}} }); }
`);
}
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null, modelsStorePath: join(agentDir, 'models-cache.json'), refreshOnCreate: false });
// Only the auth preflight is controlled; the stream below never calls ModelRuntime/provider transport.
runtime.hasConfiguredAuth = () => true;
const model = runtime.getModel('openai-codex', 'gpt-6.1-sol'); assert.ok(model);
const assistant = () => ({ role: 'assistant', content: [], api: model.api, provider: model.provider, model: model.id,
  usage: { input: 0, output: 0, cacheRead: 0, cacheWrite: 0, totalTokens: 0, cost: { input: 0, output: 0, cacheRead: 0, cacheWrite: 0, total: 0 } }, stopReason: 'stop', timestamp: Date.now() });
let outcomes = 0, confirmations = 0, modelTurns = 0;
const sessions = [], nativeMetadata = [];
const calls = () => existsSync(requests) ? readFileSync(requests, 'utf8').trim().split('\n').filter(Boolean).length : 0;
async function start(cwd, phase, manager = sdk.SessionManager.create(cwd, sessionDir)) {
  if (phase) process.env.FIGHT_SCREENING_PHASE = phase; else delete process.env.FIGHT_SCREENING_PHASE;
  process.env.FIGHT_SCREENING_TASK = 'TASK-00489'; process.env.FIGHT_SCREENING_CHECKOUT = cwd;
  const settings = sdk.SettingsManager.inMemory({ compaction: { enabled: false }, retry: { enabled: false } });
  const loader = new sdk.DefaultResourceLoader({ cwd, agentDir, settingsManager: settings, noExtensions: true, noSkills: true,
    additionalSkillPaths: [join(provider, '.pi/skills')], noPromptTemplates: true, noContextFiles: true,
    agentsFilesOverride: () => ({ agentsFiles: [{ path: join(cwd, 'AGENTS.md'), content: readFileSync(join(cwd, 'AGENTS.md'), 'utf8') }] }),
    additionalExtensionPaths: [extension] });
  await loader.reload(); assert.deepEqual(loader.getExtensions().errors, []);
  const skills = loader.getSkills().skills;
  if (effects) assert.deepEqual(skills.map(s => s.name).sort(), Object.keys(effects).sort());
  assert.equal(skills.find(s => s.name === 'build').disableModelInvocation, true);
  assert.ok(!formatSkillsForPrompt(skills).includes('<name>build</name>'));
  nativeMetadata.push(...skills.filter(s => s.disableModelInvocation).map(({ name, filePath, disableModelInvocation }) => ({ name, filePath, disableModelInvocation })));
  const { session } = await sdk.createAgentSession({ cwd, agentDir, modelRuntime: runtime, model, settingsManager: settings, resourceLoader: loader, sessionManager: manager });
  session.agent.streamFunction = async () => { modelTurns++; const stream = createAssistantMessageEventStream(); stream.push({ type: 'done', reason: 'stop', message: assistant() }); return stream; };
  await session.bindExtensions({ mode: 'tui', uiContext: { setStatus() {}, setFooter() {}, notify() {}, confirm: async () => { confirmations++; return false; } }, onError: error => { throw error; } });
  sessions.push(session); return session;
}
async function call(session, name, args, allowed = true) {
  const message = { ...assistant(), stopReason: 'toolUse' }, tools = [...new Map([...session.agent.state.tools, ...session._getCallableTools()].map(tool => [tool.name, tool])).values()];
  const result = await runToolCall({ type: 'toolCall', id: randomUUID(), name, arguments: args }, { tools, assistantMessage: message,
    context: { messages: session.agent.state.messages, tools }, beforeToolCall: ctx => session.agent.beforeToolCall(ctx), afterToolCall: ctx => session.agent.afterToolCall(ctx) });
  assert.equal(result.isError, !allowed, `${name} ${JSON.stringify(args)}: ${JSON.stringify(result.result)}`); outcomes++; return result.result;
}
async function select(session, skill, suffix = '') { await session.prompt(`/skill:${skill} ${skill === 'release' ? '0.5.0' : 'TASK-00489'} ${suffix}`.trim()); }
async function read(session, path, allowed = true) { return call(session, 'read', { path }, allowed); }
// Mutation qualification must never depend on a harness denial to protect real provider/runtime files.
async function write(session, path, content = 'Native controlled update') {
  assert.ok(path.startsWith(fixture + '/'), `Mutation target outside disposable fixture: ${path}`);
  const result = await call(session, 'write', { path, content });
  assert.equal(readFileSync(path, 'utf8'), content);
  return result;
}
async function core(session, cwd) {
  const before = calls();
  for (const path of ['AGENTS.md', 'README.md', 'planning/tasks/BOARD.md', 'planning/ROADMAP.md']) await read(session, join(cwd, path));
  await read(session, join(modules, '@earendil-works/pi-coding-agent/README.md'));
  await read(session, join(modules, '@earendil-works/pi-coding-agent/docs/sdk.md'));
  for (const name of ['timed-confirm.ts', 'modal-editor.ts', 'question.ts', 'commands.ts']) {
    await read(session, join(sdk.getExamplesPath(), 'extensions', name));
  }
  assert.equal(calls(), before);
}
try {
  if (process.env.FIGHT_QUALIFICATION_BASELINE) {
    const portable = await start(consumer); await select(portable, 'build');
    await read(portable, join(provider, '.pi/skills/build/SKILL.md'), false);
    await read(portable, '~/' + relative(homedir(), join(provider, 'docs/engineering/PROJECT_CONTEXT.md')), false);
    assert.equal(calls(), 0);
    console.log(JSON.stringify({ mode: 'baseline-native-provider-regression', outcomes, confirmations, modelTurns, fixture }));
  } else if (restart) {
    const session = await start(checkout, undefined, sdk.SessionManager.open(process.argv[6]));
    await read(session, join(main, '.runs/reviews/TASK-00489/review.md'));
    await write(session, join(checkout, 'source.txt'), 'Must fail');
    await write(session, join(main, '.runs/reviews/TASK-00489/restart.md'));
    console.log(JSON.stringify({ mode: 'separate-process-restart', outcomes, confirmations, modelTurns }));
  } else {
    const enrollment = readFileSync(profiles.path), ledger = new MonthlyLedger(directory), liability = readFileSync(join(fixture, 'liability.txt'), 'utf8');
    const work = await start(main);
    await select(work, 'work', `--checkout ${checkout}`);
    const beforeGit = calls();
    await call(work, 'bash', { command: `git worktree add --detach '${checkout}' HEAD` });
    assert.equal(calls(), beforeGit); assert.equal(confirmations, 0);
    assert.equal(prepareScreening(checkout, new ScreeningProfiles(directory)).enrolled, true);
    await core(work, checkout);
    await read(work, join(checkout, 'source.txt')); await write(work, join(checkout, 'source.txt'), 'First native update\n');
    await call(work, 'edit', { path: join(checkout, 'source.txt'), oldText: 'First native', newText: 'Second native' });
    assert.equal(readFileSync(join(checkout, 'source.txt'), 'utf8'), 'Second native update\n');
    await write(work, join(checkout, '.env.example')); await write(work, join(main, 'source.txt'), 'Forbidden other checkout');
    put(join(checkout, 'private/denied.md'), 'Excluded'); put(join(checkout, '.env.local'), 'CONTROLLED-SECRET');
    put(join(checkout, 'hardlink-original.txt'), 'Linked'); linkSync(join(checkout, 'hardlink-original.txt'), join(checkout, 'hardlink.txt'));
    symlinkSync(join(consumer, 'source.txt'), join(checkout, 'escape.txt'));
    for (const path of ['private/denied.md', '.env.local', '.git', 'escape.txt', 'hardlink.txt']) await read(work, join(checkout, path));
    for (const path of ['private/denied.md', '.env.local', 'synthetic/.git', 'escape.txt', 'hardlink.txt']) await write(work, join(checkout, path), 'Forbidden');
    const beforeScreen = calls();
    const screening = await call(work, 'jev_read_file', { path: join(checkout, 'source.txt'), need: 'Locate the controlled native update', question: { type: 'yes-no', instructions: 'Does this contain the controlled update?' } });
    assert.equal(JSON.parse(screening.content[0].text).status, 'complete'); assert.equal(calls(), beforeScreen + 1);
    assert.ok(ledger.inspect().pending.some(p => p.id === liability));
    let reviewFile;
    for (const [phase, skill, folder, report] of [['review', 'review', 'reviews', 'review.md'], ['qa', 'qa', 'qa', 'qa.md'], ['team-lead', 'build', 'build', 'ledger.md'], [undefined, 'build', 'build', 'interactive-ledger.md']]) {
      const session = await start(checkout, phase); await select(session, skill); await core(session, main);
      const status = JSON.parse((await call(session, 'file_screening_status', {})).content[0].text);
      assert.deepEqual(status.workflow, { skill, effect: effects[skill], taskId: 'TASK-00489', checkout,
        mode: 'default', launcherRole: phase === undefined ? null : phase === 'review' ? 'reviewer' : 'qa' });
      assert.equal(status.phase, skill === 'review' ? 'reviewer' : 'qa');
      assert.deepEqual(status.access, { state: 'available', reason: null });
      if (skill === 'qa') {
        const instruction = path => call(session, 'read', { path });
        await instruction(join(provider, '.pi/skills/qa/SKILL.md'));
        await instruction(join(provider, 'docs/engineering/QA.md'));
        await instruction(join(provider, 'docs/engineering/QA_REPORT_TEMPLATE.md'));
        await instruction(join(provider, 'docs/engineering/TERMINAL_CAPTURE.md'));
        await instruction(join(provider, 'docs/engineering/HANDOFFS.md'));
        await read(session, join(main, '.runs/reviews/TASK-00489/review.md'));
        put(join(main, '.runs/reviews/TASK-00489/runtime/probe.php'), '<?php // Controlled reviewed probe\n');
        await read(session, join(main, '.runs/reviews/TASK-00489/runtime/probe.php'));
        await read(session, join(provider, 'composer.json'));
      }
      if (skill === 'build' || skill === 'qa') {
        await write(session, join(main, '.runs', skill === 'build' ? 'qa' : 'build', 'TASK-00489/other-role.md'), 'Forbidden other workflow');
      }
      await read(session, join(checkout, 'source.txt'));
      const target = join(main, '.runs', folder, 'TASK-00489', report);
      await write(session, target); await call(session, 'edit', { path: target, oldText: 'Native controlled', newText: 'Updated controlled' });
      await write(session, join(main, '.runs', folder, 'TASK-00489/history/snapshot.md'));
      await read(session, target); await write(session, join(checkout, 'source.txt'), 'Forbidden source');
      await write(session, join(main, '.runs', folder, 'TASK-99999/other.md'), 'Forbidden task');
      // Atomic publication remains a native shell operation, never a denied file-tool workaround.
      await write(session, target + '.tmp', 'Atomic owned report');
      await call(session, 'bash', { command: `mv '${target}.tmp' '${target}'` });
      assert.equal(readFileSync(target, 'utf8'), 'Atomic owned report');
      if (skill === 'review') reviewFile = session.sessionManager.getSessionFile();
    }
    const land = await start(checkout, 'land'); await select(land, 'land'); await core(land, checkout);
    for (const path of ['.runs/reviews/TASK-00489/review.md', '.runs/qa/TASK-00489/qa.md']) await read(land, join(main, path));
    await write(land, join(checkout, 'planning/tasks/00489-TASK.md')); await write(land, join(main, 'planning/tasks/00489-TASK.md'));
    await write(land, join(main, 'source.txt'), 'Forbidden source');
    const child = spawnSync(process.execPath, [fileURLToPath(import.meta.url), modules, evidence, 'restart', fixture, reviewFile], { encoding: 'utf8' });
    assert.equal(child.status, 0, child.stdout + child.stderr); console.log(child.stdout.trim());
    const fork = sdk.SessionManager.forkFrom(reviewFile, checkout, sessionDir), forked = await start(checkout, undefined, fork);
    await write(forked, join(checkout, 'source.txt'), 'Forbidden inherited source');
    await select(forked, 'work'); await write(forked, join(checkout, 'source.txt'), 'Explicitly reselected Work');
    const oldLeaf = fork.getLeafId(); await select(forked, 'review');
    await forked.extensionRunner.emit({ type: 'session_before_tree', newLeafId: oldLeaf }); fork.branch(oldLeaf); await forked.reload();
    await write(forked, join(checkout, 'source.txt'), 'Forbidden historical Work');
    // Separate enrolled consumer uses the installed provider skill without enrolling or copying provider files.
    const portable = await start(consumer); const beforePortable = calls();
    await read(portable, join(provider, '.pi/skills/build/SKILL.md'));
    await select(portable, 'build');
    const expanded = portable.agent.state.messages.filter(m => m.role === 'user').at(-1);
    assert.ok(expanded.content.some(block => block.type === 'text' && block.text.includes('<skill name="build"')));
    await read(portable, join(provider, '.pi/skills/build/SKILL.md'));
    const context = join(provider, 'docs/engineering/PROJECT_CONTEXT.md');
    await read(portable, '~/' + relative(homedir(), context));
    await read(portable, join(provider, 'docs/engineering/EXECUTION.md'));
    await read(portable, join(provider, 'docs/engineering/TEAM.md'));
    await read(portable, join(provider, 'docs/engineering/LANDING.md'));
    await read(portable, join(provider, 'docs/engineering/REVIEW.md'));
    await read(portable, join(provider, 'docs/engineering/STANDARDS.md'));
    await read(portable, join(provider, 'harness/pi/src/jev/file-admission.mjs'));
    await read(portable, join(provider, 'composer.json'));
    await write(portable, join(fixture, 'provider-copy', '.pi/skills/build/SKILL.md'), 'Forbidden provider');
    await write(portable, join(fixture, 'runtime-copy', '@earendil-works/pi-coding-agent/docs/sdk.md'), 'Forbidden runtime');
    await write(portable, join(fixture, 'examples-copy', 'extensions/timed-confirm.ts'), 'Forbidden example write');
    await call(portable, 'edit', { path: join(fixture, 'examples-copy/extensions/timed-confirm.ts'),
      oldText: 'Forbidden example write', newText: 'Native example edit' });
    await read(portable, join(sdk.getPackageDir(), 'dist/config.js'));
    for (const skill of [...new Set(nativeMetadata.map(s => s.name))]) {
      await select(portable, skill); await read(portable, join(provider, '.pi/skills', skill, 'SKILL.md'));
      await write(portable, join(consumer, 'source.txt'), 'Forbidden hidden skill source');
    }
    assert.equal(calls(), beforePortable); assert.equal(confirmations, 0);
    // Invalid workflow input holds optional discovery; native file tools remain independent.
    for (const invocation of ['0.5.0', '0.4.0 status', 'v0.4.0 resume after signing']) {
      await portable.prompt('/skill:release 0.4');
      await read(portable, join(consumer, 'README.md'));
      await write(portable, join(consumer, '.runs/handoffs/release-0.4.0/rejected.md'), 'Malformed subject');
      const heldStatus = JSON.parse((await call(portable, 'file_screening_status', {})).content[0].text);
      assert.equal(heldStatus.reason, 'workflow-subject-required');
      assert.deepEqual(heldStatus.access, { state: 'held', reason: 'workflow-subject-required' });
      assert.equal(heldStatus.nextActionCode, 'correct-workflow-input');
      assert.match(heldStatus.nextAction, /\/skill:release/);
      await portable.extensionRunner.emitInput(`/skill:release ${invocation}`, undefined, 'extension');
      await portable.prompt('Continue with the current selection');
      await read(portable, join(consumer, 'README.md'));
      await portable.prompt(`/skill:release ${invocation}`);
      await read(portable, join(consumer, 'README.md'));
      const version = invocation.startsWith('0.5.0') ? '0.5.0' : '0.4.0';
      await write(portable, join(consumer, '.runs/handoffs', `release-${version}`, 'recovered.md'), 'Corrected subject');
      // Correcting input must not promote source, other-version, report, provider or runtime effects.
      for (const path of ['source.txt', '.env.local', 'private/denied.md', '.runs/handoffs/release-0.3.0/recovered.md',
        '.runs/handoffs/release-' + version + '-other/recovered.md', '.runs/reviews/TASK-00489/review.md']) {
        await write(portable, join(consumer, path), 'Forbidden recovered effect');
      }
      await write(portable, join(fixture, 'provider-copy', 'CHANGELOG.md'), 'Forbidden provider');
      await write(portable, join(fixture, 'runtime-copy', '@earendil-works/pi-coding-agent/docs/sdk.md'), 'Forbidden runtime');
    }
    // Correct selection cannot clear persistence or native-tool integrity failures.
    const persistenceHeld = await start(consumer);
    const append = persistenceHeld.sessionManager.appendCustomEntry;
    persistenceHeld.sessionManager.appendCustomEntry = function(type, data) {
      if (type === 'fight-workflow-role-v3') throw new Error('Controlled unavailable session storage');
      return append.call(this, type, data);
    };
    await persistenceHeld.prompt('/skill:release 0.4.0');
    persistenceHeld.sessionManager.appendCustomEntry = append;
    await persistenceHeld.prompt('/skill:release 0.4');
    await persistenceHeld.prompt('/skill:release 0.4.0 status');
    await read(persistenceHeld, join(consumer, 'README.md'));
    assert.equal((await call(persistenceHeld, 'file_screening_status', {})).details.access.state, 'held');
    await write(persistenceHeld, join(consumer, '.runs/handoffs/release-0.4.0/storage-held.md'), 'Forbidden');
    // Hiding the native reader does not poison mutation authority or require reenrollment.
    const integrityHeld = await start(consumer), activeTools = integrityHeld.getActiveToolNames();
    integrityHeld.setActiveToolsByName(activeTools.filter(name => name !== 'read'));
    await write(integrityHeld, join(consumer, 'native-read-independent.txt'), 'Allowed Work write');
    integrityHeld.setActiveToolsByName(activeTools);
    await read(integrityHeld, join(consumer, 'README.md'));
    assert.equal((await call(integrityHeld, 'file_screening_status', {})).details.access.state, 'available');
    // Real documented Release subjects replace the inventory's former artificial TASK argument.
    for (const invocation of ['0.5.0', '0.4.0 status', 'v0.4.0 resume after signing']) {
      await portable.prompt(`/skill:release ${invocation}`);
      const version = invocation.startsWith('0.5.0') ? '0.5.0' : '0.4.0';
      const target = join(consumer, '.runs/handoffs', `release-${version}`, 'ledger.md');
      await write(portable, target, 'Authorized release ledger');
      await call(portable, 'edit', { path: target, oldText: 'Authorized release', newText: 'Updated release' });
      await read(portable, target);
      await write(portable, join(consumer, '.runs/notes', `release-${version}`, 'state.md'));
      for (const path of ['.runs/handoffs/release-0.3.0/ledger.md', '.runs/handoffs/release-' + version + '-other/ledger.md',
        '.runs/reviews/TASK-00489/review.md', '.runs/qa/TASK-00489/qa.md', '.runs/tasks/TASK-00489/runs/release.md',
        'source.txt', '.env.local']) await write(portable, join(consumer, path), 'Forbidden Release effect');
      await write(portable, join(consumer, 'CHANGELOG.md'), 'Version preparation');
      await write(portable, join(fixture, 'provider-copy', 'CHANGELOG.md'), 'Forbidden provider release');
      await write(portable, join(fixture, 'runtime-copy', '@earendil-works/pi-coding-agent/docs/sdk.md'), 'Forbidden runtime');
    }
    await portable.prompt('/skill:release');
    await write(portable, join(consumer, 'CHANGELOG.md'), 'Missing release version');
    await select(portable, 'review');
    await write(portable, join(consumer, '.runs/handoffs/release-0.4.0/ledger.md'), 'Wrong role');
    assert.equal(calls(), beforePortable); assert.equal(confirmations, 0);
    const targets = { work: 'source.txt', land: 'source.txt', fix: 'planning/tasks/00489-TASK.md', grill: 'planning/epics/00001-EPIC.md',
      'to-tickets': 'planning/tickets/00001-TICKET.md', 'to-tasks': 'planning/tasks/00489-TASK.md', wayfinder: 'planning/wayfinder/map.md',
      research: 'planning/wayfinder/research/note.md', 'writing-for-agents': '.pi/skills/qualification/SKILL.md', graphify: 'graphify-out/graph.json', release: 'CHANGELOG.md' };
    for (const skill of Object.keys(effects)) {
      await select(work, skill);
      await read(work, join(provider, '.pi/skills', skill, 'SKILL.md'));
      await write(work, join(main, skill === 'release' ? '.runs/handoffs/release-0.5.0' : '.runs/tasks/TASK-00489/runs', skill, 'evidence.md'));
      await write(work, join(checkout, targets[skill] ?? 'source.txt'), 'Selected output');
      await select(work, skill, 'query');
      await write(work, join(checkout, targets[skill] ?? 'source.txt'), 'Query cannot write');
    }
    assert.deepEqual(readFileSync(profiles.path), enrollment);
    assert.ok(ledger.inspect().pending.some(p => p.id === liability));
    const moved = join(fixture, 'worktrees/moved'); git(main, ['worktree', 'move', checkout, moved]);
    await read(work, join(moved, 'source.txt')); await write(work, join(checkout, 'source.txt'), 'Stale');
    git(main, ['worktree', 'remove', '--force', moved]); git(main, ['worktree', 'prune']);
    await read(work, join(moved, 'source.txt'), false); await read(work, join(main, 'README.md'));
    profiles.revoke(main);
    await read(portable, join(consumer, 'README.md'));
    await portable.prompt('/skill:release 0.4');
    await portable.prompt('/skill:release 0.4.0 status');
    await read(portable, join(consumer, 'README.md'));
    await write(portable, join(consumer, '.runs/handoffs/release-0.4.0/revoked.md'), 'Forbidden');
    await read(work, join(main, 'README.md'));
    console.log(JSON.stringify({ mode: 'native-worktree-and-portable-provider', runtime: modules, fixture, outcomes, confirmations, modelTurns,
      controlledEvaluatorRequests: calls(), liveProviderRequests: 0, realCredentialReads: 0, hiddenSkills: [...new Set(nativeMetadata.map(s => s.name))],
      unchangedEnrollmentBeforeExplicitRevoke: true, unresolvedLiabilityPreserved: true }));
  }
} finally { for (const session of sessions) session.dispose(); }
