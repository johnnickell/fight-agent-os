// Native qualification: installed SDK, controlled model, isolated operator state, no live provider calls.
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync, realpathSync } from 'node:fs';
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
const { ScreeningProfiles } = await import(pathToFileURL(join(harness, 'src/jev/screening-profile.mjs')));
const { connection } = await import(pathToFileURL(join(harness, 'src/jev/readiness.mjs')));
let outcomes = 0;
for (const mode of ['unconfigured', 'enrolled-offline', 'enrolled-json', 'enrolled-online']) {
  const base = join(fixture, mode), root = join(base,'project'), agentDir = join(base,'agent'), directory = join(base,'operator');
  for (const path of [root,agentDir,directory]) mkdirSync(path,{recursive:true,mode:0o700});
  process.env.PI_CODING_AGENT_DIR = agentDir; process.env.PI_OFFLINE = '1';
  delete process.env.FIGHT_SCREENING_PHASE; delete process.env.FIGHT_SCREENING_TASK; delete process.env.FIGHT_SCREENING_CHECKOUT;
  if (mode === 'enrolled-json') {
    process.env.FIGHT_SCREENING_PHASE = 'work'; process.env.FIGHT_SCREENING_TASK = 'TASK-00492'; process.env.FIGHT_SCREENING_CHECKOUT = root;
  }
  assert.equal(spawnSync('/usr/bin/git',['init','--initial-branch=develop'],{cwd:root}).status,0);
  const {schema, ...fixed} = connection;
  const config = {...fixed,revision:randomUUID(),priceConfirmedAt:Date.now(),disclosure:'authorized-supplied-state',requestMicroUsd:2688,sessionMicroUsd:100000};
  const profiles = new ScreeningProfiles(directory), enrolled = mode !== 'unconfigured';
  if (enrolled) profiles.save(profiles.preview({cwd:root,configuration:config,providerDisclosure:true,commandDisclosure:mode === 'enrolled-online'}),[]);
  writeFileSync(join(root,'source.txt'),'export function example() { return 1; }\n');
  writeFileSync(join(root,'AGENTS.md'),'Controlled qualification instructions');
  const extension = join(base,'extension.ts'), requests = join(base,'requests.log');
  writeFileSync(requests,'');
  writeFileSync(extension, `import { appendFileSync } from 'node:fs';
import { installJev } from ${JSON.stringify(join(harness,'src/jev-extension.ts'))};
import { ScreeningProfiles } from ${JSON.stringify(join(harness,'src/jev/screening-profile.mjs'))};
export default function(pi) { installJev(pi, {settings:{load:()=>(${mode === 'enrolled-online' ? JSON.stringify(config) : 'null'})},profiles:new ScreeningProfiles(${JSON.stringify(directory)}),credentialConfigured:()=>${mode === 'enrolled-online'},
credentials:()=>({read:async()=>{appendFileSync(${JSON.stringify(requests)},'credential\\n');return Buffer.from('controlled-fixture');}}),
transport:{submit:async()=>{appendFileSync(${JSON.stringify(requests)},'request\\n');return {status:200,body:{model:${JSON.stringify(connection.evaluator)},provider:'TypeSafe',usage:{input_tokens:10,output_tokens:2,cost:0.000003},answers:{relevance:{type:'noul',noul:0.95}}}};}}});}`);
  const runtime = await sdk.ModelRuntime.create({authPath:join(agentDir,'auth.json'),modelsPath:null,modelsStorePath:join(agentDir,'models.json'),refreshOnCreate:false});
  runtime.hasConfiguredAuth = () => true;
  const model = runtime.getModel('openai-codex','gpt-6.1-sol'); assert.ok(model);
  const message = () => ({role:'assistant',content:[],api:model.api,provider:model.provider,model:model.id,usage:{input:0,output:0,cacheRead:0,cacheWrite:0,totalTokens:0,cost:{input:0,output:0,cacheRead:0,cacheWrite:0,total:0}},stopReason:'stop',timestamp:Date.now()});
  const settings = sdk.SettingsManager.inMemory({compaction:{enabled:false},retry:{enabled:false},shellCommandPrefix:'export QUALIFICATION_PREFIX=applied'});
  const loader = new sdk.DefaultResourceLoader({cwd:root,agentDir,settingsManager:settings,noExtensions:true,noSkills:true,noPromptTemplates:true,noContextFiles:true,additionalExtensionPaths:[extension]});
  await loader.reload(); assert.deepEqual(loader.getExtensions().errors,[]);
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
    // Both agent and user shell paths must execute with no Jev setup, credential or confirmation.
    await call('bash',{command:'test "$QUALIFICATION_PREFIX" = applied && printf completed > shell-result.txt'});
    assert.equal(readFileSync(join(root,'shell-result.txt'),'utf8'),'completed');
    for (const excludeFromContext of [false, true]) {
      assert.equal(await session.extensionRunner.emitUserBash({type:'user_bash', command:'printf user-shell', cwd:root, excludeFromContext}), undefined); outcomes++;
    }
    assert.equal((await session.executeBash('test "$QUALIFICATION_PREFIX" = applied && printf user-shell')).exitCode,0);outcomes++;
    await call('read',{path:'source.txt'});
    await call('write',{path:'created.txt',content:'before'});
    await call('edit',{path:'created.txt',edits:[{oldText:'before',newText:'after'}]});
    assert.equal(readFileSync(join(root,'created.txt'),'utf8'),'after');
    if (enrolled) {
      await call('file_locate',{path:'source.txt',need:'Locate example',symbol:'example'});
      await call('write',{path:join(directory,'forbidden.txt'),content:'denied'},false);
    }
    assert.equal(prompts,0); assert.equal(readFileSync(requests,'utf8'),'');
    if (mode === 'enrolled-online') {
      const result=await call('jev_read_file',{path:'source.txt',need:'Find example implementation',question:{type:'yes-no',instructions:'Is this the example implementation?'}});
      assert.equal(result.details.status,'complete');
      assert.equal(readFileSync(requests,'utf8'),'credential\nrequest\n');
    }
  } finally {await session.abort();session.dispose();}
}
console.log(JSON.stringify({fixture,outcomes,liveProviderRequests:0,realCredentialReads:0,result:'passed'}));
