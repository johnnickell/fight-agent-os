// Owning native qualification; run with an installed Pi node_modules path and an ignored evidence directory.
// All credentials, model output and evaluator responses are controlled. No network/provider request is made.
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync, realpathSync, existsSync, chmodSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';

const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]);
mkdirSync(evidence, { recursive: true });
const childMode = process.argv[4];
const fixture = childMode ? process.argv[5] : realpathSync.native(mkdtempSync(join(evidence, 'native-')));
const main = join(fixture, 'main'), checkout = join(fixture, 'worktrees/implementation'), consumer = join(fixture, 'consumer');
const agentDir = join(fixture, 'agent'), directory = join(fixture, 'operator'), sessionDir = join(fixture, 'sessions');
if (!childMode) for (const path of [main, consumer, agentDir, sessionDir, dirname(checkout)]) mkdirSync(path, { recursive: true });
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
const effects = (await import(pathToFileURL(join(harness, 'src/jev/workflow-access.mjs')))).skillEffects;
const profiles = new ScreeningProfiles(directory), extension = join(fixture, 'extension.ts'), requests = join(fixture, 'requests.jsonl');
const put = (path, content) => { mkdirSync(dirname(path), { recursive: true }); writeFileSync(path, content); };
const git = (cwd, args) => { const result = spawnSync('/usr/bin/git', args, { cwd, encoding: 'utf8' }); assert.equal(result.status, 0, result.stderr); return result.stdout; };
if (!childMode) {
  for (const root of [main, consumer]) {
    git(root, ['init', '--initial-branch=develop']);
    for (const path of ['source.txt', 'AGENTS.md', 'README.md', 'planning/tasks/BOARD.md', 'planning/ROADMAP.md', 'planning/tasks/00491-TASK.md', '.env.example']) put(join(root, path), `Fixture ${path}\n`);
    put(join(root, '.gitignore'), '.runs/\n.env\nprivate/\n');
    git(root, ['add', '.']); git(root, ['-c', 'user.name=Qualification Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Controlled qualification']);
    put(join(root, 'private/denied.md'), 'Excluded fixture'); put(join(root, '.env.local'), 'CONTROLLED-SECRET');
  }
  git(main, ['worktree', 'add', '-b', 'fixture-work', checkout]);
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
const command = JSON.stringify(payload).includes('command-risk');
return {status:200, body:{model:${JSON.stringify(connection.evaluator)},provider:'TypeSafe',usage:{input_tokens:10,output_tokens:2,cost:0.000003},
answers: command ? {'command-risk':{type:'choice',choice:JSON.stringify(payload).includes('child-held-marker')?'unknown':'ordinary',confidence:0.98,probabilities:JSON.stringify(payload).includes('child-held-marker')?{ordinary:0.01,risky:0.01,unknown:0.98}:{ordinary:0.98,risky:0.01,unknown:0.01}}} : {relevance:{type:'noul',noul:0.95}}}};}} }); }
`);
}
const runtime = await sdk.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null, modelsStorePath: join(agentDir, 'models-cache.json'), refreshOnCreate: false });
// Only the auth preflight is controlled; the stream below never calls ModelRuntime/provider transport.
runtime.hasConfiguredAuth = () => true;
const model = runtime.getModel('openai-codex', 'gpt-6.1-sol'); assert.ok(model);
const assistant = () => ({ role: 'assistant', content: [], api: model.api, provider: model.provider, model: model.id,
  usage: { input: 0, output: 0, cacheRead: 0, cacheWrite: 0, totalTokens: 0, cost: { input: 0, output: 0, cacheRead: 0, cacheWrite: 0, total: 0 } }, stopReason: 'stop', timestamp: Date.now() });
let outcomes = 0, confirmations = 0, modelTurns = 0;
let approve = true, onConfirm = async () => {}; const dialogs = [];
const sessions = [], nativeMetadata = [];
const calls = () => existsSync(requests) ? readFileSync(requests, 'utf8').trim().split('\n').filter(Boolean).length : 0;
async function start(cwd, phase, manager = sdk.SessionManager.create(cwd, sessionDir)) {
  if (phase) process.env.FIGHT_SCREENING_PHASE = phase; else delete process.env.FIGHT_SCREENING_PHASE;
  process.env.FIGHT_SCREENING_TASK = childMode === 'wrong-task' ? 'TASK-00490' : 'TASK-00491'; process.env.FIGHT_SCREENING_CHECKOUT = cwd;
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
  await session.bindExtensions({ mode: childMode ? 'json' : 'tui', uiContext: childMode ? undefined : { setStatus() {}, setFooter() {}, notify() {}, confirm: async (title, message) => { confirmations++; dialogs.push({title, message}); await onConfirm(); return approve; } }, onError: error => { throw error; } });
  sessions.push(session); return session;
}
async function call(session, name, args, allowed = true) {
  const message = { ...assistant(), stopReason: 'toolUse' }, tools = [...new Map([...session.agent.state.tools, ...session._getCallableTools()].map(tool => [tool.name, tool])).values()];
  const result = await runToolCall({ type: 'toolCall', id: randomUUID(), name, arguments: args }, { tools, assistantMessage: message,
    context: { messages: session.agent.state.messages, tools }, beforeToolCall: ctx => session.agent.beforeToolCall(ctx), afterToolCall: ctx => session.agent.afterToolCall(ctx) });
  assert.equal(result.isError, !allowed, `${name} ${JSON.stringify(args)}: ${JSON.stringify(result.result)}`); outcomes++; return result.result;
}
async function select(session, skill, suffix = '') { await session.prompt(`/skill:${skill} ${skill === 'release' ? '0.5.0' : 'TASK-00491'} ${suffix}`.trim()); }
const quote = value => "'" + value.replaceAll("'", "'\"'\"'") + "'";
try {
  if (childMode) {
    const session = await start(childMode === 'wrong-root' ? consumer : checkout, 'work');
    const marker = join(checkout, '.runs', randomUUID()); mkdirSync(dirname(marker), {recursive:true});
    const result = await call(session, 'bash', {command: 'printf child-held-marker > ' + quote(marker)}, childMode === 'allow');
    assert.equal(existsSync(marker), childMode === 'allow');
    if (childMode !== 'allow') assert.match(JSON.stringify(result), /command-operator-decision-required/);
    await call(session, 'bash', {command: 'test -z "${FIGHT_CHILD_COMMAND_APPROVAL+x}"'});
    if (childMode !== 'wrong-root') await call(session, 'write', {path:join(checkout, 'source.txt'),content:'work-still-allowed'});
    await call(session, 'bash', {command:'cat .env.local'}, false);
    console.log('child-qualification-passed');
  } else {
    const session = await start(main); await select(session, 'build');
    const run = mode => call(session, 'bash', {command: [process.execPath, fileURLToPath(import.meta.url), modules, evidence, mode, fixture].map(quote).join(' ')});
    // Regression: an enrolled JSON Work child must reach the parent's native dialog.
    await run('allow');
    assert.equal(confirmations, 1);
    assert.match(dialogs[0].message, /printf child-held-marker/);
    approve = false;
    await run('deny');
    assert.equal(confirmations, 2);
    approve = true;
    await run('wrong-task'); await run('wrong-root');
    assert.equal(confirmations, 2);
    onConfirm = () => select(session, 'review');
    await run('stale-parent');
    assert.equal(confirmations, 3);
    onConfirm = async () => {};
    await run('missing'); // A reviewer cannot export a Build approval channel.
    assert.equal(confirmations, 3);
    await select(session, 'build');
    // Exercise the unmodified production stage monitor and installed CLI, with a controlled model.
    const modelExtension = join(fixture, 'model.ts');
    put(modelExtension, `import { createAssistantMessageEventStream } from ${JSON.stringify(join(modules, '@earendil-works/pi-ai/dist/utils/event-stream.js'))};
export default function(pi) {
  let turn = 0;
  pi.registerProvider('qualification', {baseUrl:'https://fixture.invalid', apiKey:'controlled-fixture', api:'fixture-stream',
    models:[{id:'controlled',name:'Controlled',reasoning:false,input:['text'],contextWindow:128000,maxTokens:4096,cost:{input:0,output:0,cacheRead:0,cacheWrite:0}}],
    streamSimple(model) {
      const first = turn++ === 0;
      const block = first ? {type:'toolCall',id:'fixture-command',name:'bash',arguments:{command:'printf child-held-marker > ' + process.env.FIGHT_QUALIFICATION_MARKER}} : {type:'text',text:'Controlled stage finished'};
      const message = {role:'assistant',content:[block],api:model.api,provider:model.provider,model:model.id,
        usage:{input:0,output:0,cacheRead:0,cacheWrite:0,totalTokens:0,cost:{input:0,output:0,cacheRead:0,cacheWrite:0,total:0}},stopReason:first?'toolUse':'stop',timestamp:Date.now()};
      const stream = createAssistantMessageEventStream();
      stream.push({type:'start',partial:message});
      stream.push(first?{type:'toolcall_start',contentIndex:0,partial:message}:{type:'text_start',contentIndex:0,partial:message});
      stream.push(first?{type:'toolcall_end',contentIndex:0,toolCall:block,partial:message}:{type:'text_end',contentIndex:0,content:block.text,partial:message});
      stream.push({type:'done',reason:message.stopReason,message}); return stream;
    }});
}`);
    put(join(agentDir, 'settings.json'), JSON.stringify({packages:[], extensions:[], skills:[join(provider,'.pi/skills')], compaction:{enabled:false}, retry:{enabled:false}}));
    put(join(fixture,'bin/pi'), '#!/bin/sh\nexec ' + quote(process.execPath) + ' ' + quote(join(modules,'@earendil-works/pi-coding-agent/dist/cli.js')) + ' --no-extensions --extension ' + quote(modelExtension) + ' "$@"\n');
    chmodSync(join(fixture,'bin/pi'), 0o700);
    // Inject the isolated operator store at the preflight boundary, retaining the real preflight function.
    const bootstrap = join(fixture, 'bootstrap.mjs');
    put(bootstrap, `import { prepareScreening } from ${JSON.stringify(join(harness, 'src/screening-bootstrap.mjs'))};
import { ScreeningProfiles } from ${JSON.stringify(join(harness, 'src/jev/screening-profile.mjs'))};
const result = prepareScreening(process.argv[3], new ScreeningProfiles(${JSON.stringify(directory)}));
if (result.enrolled) result.extension = ${JSON.stringify(extension)};
console.log(JSON.stringify(result));`);
    put(join(fixture, 'bin/node'), '#!/bin/sh\nif [ "$1" = ' + quote(join(provider, 'harness/pi/src/screening-bootstrap.mjs')) +
      ' ]; then shift; exec ' + quote(process.execPath) + ' ' + quote(bootstrap) + ' "$@"; fi\nexec ' + quote(process.execPath) + ' "$@"\n');
    chmodSync(join(fixture, 'bin/node'), 0o700);
    for (const accepted of [true, false]) {
      approve = accepted;
      const attempt = join(main,'.runs',accepted?'monitor-allow':'monitor-deny');
      put(join(attempt,'request.md'), 'Exercise one controlled command then stop.');
      const marker = accepted ? '.runs/monitor-allowed' : '.runs/monitor-denied';
      await call(session, 'bash', {command: 'cd ' + quote(checkout) + ' && PATH=' + quote(join(fixture,'bin')) + ':"$PATH" FIGHT_QUALIFICATION_MARKER=' + quote(marker) +
        ' python3 ' + quote(join(provider,'.pi/skills/build/scripts/run-stage.py')) + ' --attempt ' + quote(attempt) + ' --task TASK-00491 --role work --provider qualification --model controlled --thinking off --timeout 60'});
      assert.equal(existsSync(join(checkout,marker)), accepted);
      assert.equal(readFileSync(join(attempt,'exit-code'),'utf8').trim(), '0');
      assert.match(readFileSync(join(attempt,'stderr.log'),'utf8'), accepted ? /explicit-one-command-decision/ : /command-operator-decision-required/);
      assert.doesNotMatch(readFileSync(join(attempt,'progress.log'),'utf8'), /printf child-held-marker|FIGHT_CHILD_COMMAND_APPROVAL/);
    }
    assert.equal(confirmations, 5);
    console.log(JSON.stringify({outcomes, confirmations, fixture, controlledEvaluatorRequests:calls(), liveProviderRequests:0, realCredentialReads:0, result:'passed'}));
  }
} finally {
  for (const session of sessions) { await session.abort(); session.dispose(); }
}
