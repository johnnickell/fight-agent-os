// Native qualification: installed SDK, controlled model, isolated operator state, no live provider calls.
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync, realpathSync, symlinkSync, linkSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
const modules = resolve(process.argv[2]), evidence = resolve(process.argv[3]);
mkdirSync(evidence, {recursive:true});
const fixture = realpathSync.native(mkdtempSync(join(evidence, 'native-')));
const provider = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..');
const harness = join(provider, 'harness/pi');
const sdk = await import(pathToFileURL(join(modules, '@earendil-works/pi-coding-agent/dist/index.js')));
const { runToolCall } = await import(pathToFileURL(join(modules, '@earendil-works/pi-agent-core/dist/index.js')));
const { createAssistantMessageEventStream } = await import(pathToFileURL(join(modules, '@earendil-works/pi-ai/dist/utils/event-stream.js')));
let outcomes = 0;
for (const mode of ['policy-active', 'policy-missing']) {
  const base = join(fixture, mode), root = join(base,'project'), agentDir = join(base,'agent'), directory = join(base,'operator');
  for (const path of [root,agentDir,directory]) mkdirSync(path,{recursive:true,mode:0o700});
  process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
  delete process.env.FIGHT_SCREENING_PHASE; delete process.env.FIGHT_SCREENING_TASK; delete process.env.FIGHT_SCREENING_CHECKOUT;
  assert.equal(spawnSync('/usr/bin/git',['init','--initial-branch=develop'],{cwd:root}).status,0);
  const policyPath = join(directory, 'policy.json');
  if (mode === 'policy-active') writeFileSync(policyPath, JSON.stringify({version:1, profile:'native-reads',
    revision:1, roots:[root], protectedPaths:[join(root,'private')], tools:['bash'],
    effects:['read','write'], scripts:[]}), {mode:0o600});
  writeFileSync(join(root,'source.txt'),'export function example() { return 1; }\n');
  writeFileSync(join(root,'AGENTS.md'),'Controlled qualification instructions');
  // Ordinary native reads remain available independently of optional screening policy.
  const terminalLog = '\x1b[1G\x1b[2K\x1b[31mFAIL\x1b[0m\n';
  writeFileSync(join(root, 'build.log'), terminalLog);
  writeFileSync(join(root, '.env.local'), 'SYNTHETIC=fixture-only');
  writeFileSync(join(base, 'provider.md'), 'Cross-project provider instructions');
  symlinkSync(join(base, 'provider.md'), join(root, 'linked.md'));
  writeFileSync(join(root, 'hardlink-original.txt'), 'Hardlinked evidence');
  linkSync(join(root, 'hardlink-original.txt'), join(root, 'hardlink.txt'));
  writeFileSync(join(root, 'large.log'), 'Native bounded output\n'.repeat(60000));
  writeFileSync(join(base, 'evidence.png'), Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAIAAAB7QOjdAAAAD0lEQVR4nGP4z8DA8J8BAAf/Af8Bf4mnAAAAAElFTkSuQmCC', 'base64'));
  const extension = join(harness, 'src/tool-policy-extension.ts');
  const runtime = await sdk.ModelRuntime.create({authPath:join(agentDir,'auth.json'),modelsPath:null,modelsStorePath:join(agentDir,'models.json'),refreshOnCreate:false});
  runtime.hasConfiguredAuth = () => true;
  const model = runtime.getModel('openai-codex','gpt-6.1-sol'); assert.ok(model);
  const message = () => ({role:'assistant',content:[],api:model.api,provider:model.provider,model:model.id,usage:{input:0,output:0,cacheRead:0,cacheWrite:0,totalTokens:0,cost:{input:0,output:0,cacheRead:0,cacheWrite:0,total:0}},stopReason:'stop',timestamp:Date.now()});
  const settings = sdk.SettingsManager.inMemory({compaction:{enabled:false},retry:{enabled:false},shellCommandPrefix:'export QUALIFICATION_PREFIX=applied'});
  const loader = new sdk.DefaultResourceLoader({cwd:root,agentDir,settingsManager:settings,noExtensions:true,noSkills:true,noPromptTemplates:true,noContextFiles:true,additionalExtensionPaths:[extension]});
  await loader.reload(); assert.deepEqual(loader.getExtensions().errors,[]);
  loader.getExtensions().runtime.flagValues.set('fight-tool-policy', policyPath);
  const {session} = await sdk.createAgentSession({cwd:root,agentDir,modelRuntime:runtime,model,settingsManager:settings,resourceLoader:loader,sessionManager:sdk.SessionManager.create(root,join(base,'sessions'))});
  session.agent.streamFunction = async()=>{const stream=createAssistantMessageEventStream();stream.push({type:'done',reason:'stop',message:message()});return stream;};
  let prompts=0;
  await session.bindExtensions({mode:mode === 'enrolled-json'?'json':'tui',uiContext:mode === 'enrolled-json'?undefined:{setStatus(){},setFooter(){},notify(){},confirm:async()=>{prompts++;return false;},custom:async()=>{prompts++;return false;}},onError:error=>{throw error;}});
  async function call(name,args,allowed=true) {
    const tools=[...new Map([...session.agent.state.tools,...session._getCallableTools()].map(tool=>[tool.name,tool])).values()];
    const result=await runToolCall({type:'toolCall',id:randomUUID(),name,arguments:args},{tools,assistantMessage:message(),context:{messages:session.agent.state.messages,tools},beforeToolCall:ctx=>session.agent.beforeToolCall(ctx),afterToolCall:ctx=>session.agent.afterToolCall(ctx)});
    assert.equal(result.isError,!allowed,`${mode}/${name}: ${JSON.stringify(result.result)}`);outcomes++;return result.result;
  }
  try {
    for (const name of ['read','write','edit']) assert.ok(session.getActiveToolNames().includes(name));
    for (const path of ['build.log', '.env.local', 'linked.md', 'hardlink.txt', 'large.log', join(base, 'provider.md'), join(provider, '.pi/skills/review/SKILL.md'), join(provider, 'docs/engineering/REVIEW.md')]) {
      await call('read', {path});
    }
    const log = await call('read', {path: 'build.log'});
    assert.match(log.content.filter(block => block.type === 'text').map(block => block.text).join('\n'), /FAIL/);
    assert.equal(readFileSync(join(root, 'build.log'), 'utf8'), terminalLog);
    const picture = await call('read', {path: join(base, 'evidence.png')});
    assert.ok(picture.content.some(block => block.type === 'image'));
    await call('read', {path: 'missing-file.txt'}, false); // Native filesystem failures remain honest.

    await call('write', {path: '.env.local', content: 'synthetic updated'});
    await call('write', {path: join(base, 'planning/tickets/00083-TICKET.md'), content: 'status: in-progress'});
    await call('edit', {path: join(base, 'planning/tickets/00083-TICKET.md'), edits: [{oldText:'in-progress',newText:'done'}]});
    assert.equal(readFileSync(join(base, 'planning/tickets/00083-TICKET.md'), 'utf8'), 'status: done');
    if (mode === 'policy-active') {
      await call('write', {path: 'created.txt', content: 'before'});
      await call('edit', {path: 'created.txt', edits:[{oldText:'before',newText:'after'}]});
      assert.equal(readFileSync(join(root,'created.txt'),'utf8'), 'after');
      writeFileSync(policyPath, '{}'); // Authority drift holds shell policy, never native file tools.
      await call('write', {path:'created.txt',content:'still native'});
    }
    await call('read', {path:'build.log'});
    await call('read', {path:join(base, 'provider.md')});
    await call('bash', {command:'printf forbidden > shell-marker'}, false);
    assert.equal(existsSync(join(root, 'shell-marker')), false);
    const nested = await session.extensionRunner.emitToolCall({type:'tool_call',toolName:'read',
      toolCallId:randomUUID(),parentToolCallId:randomUUID(),input:{path:'build.log'}});
    assert.notEqual(nested?.block, true); outcomes++;
    assert.equal(prompts, 0);
  } finally {await session.abort();session.dispose();}
}
console.log(JSON.stringify({fixture,outcomes,liveProviderRequests:0,realCredentialReads:0,result:'passed'}));
