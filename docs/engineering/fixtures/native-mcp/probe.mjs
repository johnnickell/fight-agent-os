// TASK-00555 owning-tool evaluation, not a product test or integration.
// Inputs: installed Pi package directory, disposable supplier package directory, NEW evidence directory.
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { createServer } from 'node:http';
import { mkdirSync, writeFileSync, readFileSync, existsSync, statSync } from 'node:fs';
import { resolve, join, dirname } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';
const [piRoot, supplierRoot, output] = process.argv.slice(2).map((p) => resolve(p));
assert(piRoot && supplierRoot && output);
assert(!existsSync(output), 'Use a new evidence directory');
mkdirSync(output, { recursive: true, mode: 0o700 });
const agentDir = join(output, 'agent');
const cwd = join(output, 'workspace');
mkdirSync(agentDir, { mode: 0o700 });
mkdirSync(cwd, { mode: 0o700 });
// This standalone process never mutates a running Pi parent's environment.
process.env.PI_CODING_AGENT_DIR = agentDir;
process.env.HOME = join(output, 'home');
mkdirSync(process.env.HOME, { mode: 0o700 });
const load = (root, path) => import(pathToFileURL(join(root, path)).href);
const pi = await load(piRoot, 'dist/index.js');
const { runToolCall } = await load(dirname(piRoot), 'pi-agent-core/dist/index.js');
const { emitSessionShutdownEvent } = await load(piRoot, 'dist/core/extensions/runner.js');
const { McpOAuthCredentialStore } = await load(piRoot, 'dist/extensions/mcp/oauth.js');
const { runAgent, setRememberAgents } = await load(supplierRoot, 'dist/agent-runner.js');
const { registerAgents } = await load(supplierRoot, 'dist/agent-types.js');
const rows = [], wire = [], hooks = [], sessions = [];
let browserOpens = 0;
const check = (name, value, detail = {}) => {
  rows.push({ name, pass: !!value, ...detail });
  writeFileSync(join(output, 'results.json'), JSON.stringify({ rows, wire, hooks }, null, 2));
  assert(value, name);
};
const wait = async (predicate, ms = 10000) => {
  const end = Date.now() + ms;
  while (!predicate()) {
    assert(Date.now() < end, 'Fixture deadline');
    await new Promise((r) => setTimeout(r, 20));
  }
};
const schema = { type: 'object', properties: {} };
const tools = ['echo', 'denied', 'hidden', 'slow', 'error', 'rpc_error'].map((name) => ({
  name, description: `TASK-00555 fixture ${name}`, inputSchema: schema,
  annotations: { readOnlyHint: true },
}));
const pending = new Map();
const server = createServer(async (req, res) => {
  if (req.method !== 'POST') { res.writeHead(405).end(); return; }
  let raw = '';
  for await (const chunk of req) raw += chunk;
  const message = JSON.parse(raw);
  const authorized = req.headers.authorization === 'Bearer task-00555-disposable';
  wire.push({ path: req.url, method: message.method, tool: message.params?.name,
    protocolVersion: message.method === 'initialize' ? message.params.protocolVersion : undefined, authorized });
  if (req.url === '/auth' && !authorized) {
    res.writeHead(401, { 'WWW-Authenticate': 'Bearer realm="fixture"' }).end(); return;
  }
  if (message.id === undefined) {
    if (message.method === 'notifications/cancelled') {
      const waiting = pending.get(message.params.requestId);
      if (waiting) { waiting.writeHead(204).end(); pending.delete(message.params.requestId); }
    }
    res.writeHead(202).end(); return;
  }
  let result = {};
  if (message.method === 'initialize') result = {
    protocolVersion: message.params.protocolVersion, capabilities: { tools: {} },
    serverInfo: { name: 'task-00555-http', version: '1' },
  };
  if (message.method === 'tools/list') result = { tools };
  if (message.method === 'tools/call') {
    if (message.params.name === 'slow') {
      pending.set(message.id, res);
      res.on('close', () => pending.delete(message.id));
      return;
    }
    if (message.params.name === 'rpc_error') {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ jsonrpc: '2.0', id: message.id, error: { code: -32603, message: 'fixture RPC failure' } }));
      return;
    }
    result = { content: [{ type: 'text', text: 'disposable HTTP fixture' }],
      structuredContent: { fixture: true }, ...(message.params.name === 'error' ? { isError: true } : {}) };
  }
  res.writeHead(200, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify({ jsonrpc: '2.0', id: message.id, result }));
});
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const url = `http://127.0.0.1:${server.address().port}`;
const stdioLog = join(output, 'stdio.jsonl');
const config = { mcpServers: {
  fixture: { url: `${url}/auth`, headers: { Authorization: 'Bearer task-00555-disposable' },
    exposure: 'direct', toolExposure: { hidden: 'hidden' }, timeout: 1 },
  proxy: { url: `${url}/auth`, headers: { Authorization: 'Bearer task-00555-disposable' },
    exposure: 'codemode', toolExposure: { hidden: 'hidden' }, timeout: 1 },
  auth: { url: `${url}/auth`, exposure: 'direct', timeout: 1 },
  unavailable: { url: 'http://127.0.0.1:1/mcp', exposure: 'direct', timeout: 1 },
  local: { command: process.execPath,
    args: [join(dirname(fileURLToPath(import.meta.url)), 'stdio.mjs'), stdioLog], exposure: 'direct' },
} };
writeFileSync(join(agentDir, 'mcp.json'), JSON.stringify(config), { mode: 0o600 });
writeFileSync(join(agentDir, 'settings.json'), '{}');
let parentCtx, parentApi, denyEcho = false;
const gate = (ext) => {
  parentApi = ext;
  ext.on('session_start', (_event, ctx) => { parentCtx = ctx; });
  ext.on('tool_call', (event) => {
    hooks.push({ name: event.toolName, parent: event.parentToolCallId ?? null });
    if (event.toolName.endsWith('__denied') || (denyEcho && event.toolName.endsWith('__echo')))
      return { block: true, reason: 'fixture policy denial' };
  });
};
const runtime = await pi.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null,
  modelsStorePath: join(agentDir, 'models-cache.json'), refreshOnCreate: false });
const model = runtime.getModel('openai-codex', 'gpt-6.1-sol');
assert(model, 'Pinned configured model must exist; do not substitute');
let serial = 0;
const call = async (session, name, args = {}, signal) => {
  const toolCall = { type: 'toolCall', id: `fixture-${++serial}`, name, arguments: args };
  // A synthetic transcript entry supplies the issuing call for Pi's nested pipeline.
  // No model completion is substituted or claimed by this protocol fixture.
  const assistantMessage = { role: 'assistant', content: [toolCall], api: model.api, provider: model.provider,
    model: model.id, stopReason: 'toolUse', timestamp: Date.now(),
    usage: { input: 0, output: 0, cacheRead: 0, cacheWrite: 0,
      cost: { input: 0, output: 0, cacheRead: 0, cacheWrite: 0, total: 0 } } };
  session.sessionManager.appendMessage(assistantMessage);
  session.refreshContext();
  const outcome = await runToolCall(toolCall, {
    tools: session.agent.state.tools, context: session.agent.state, assistantMessage,
    beforeToolCall: session.agent.beforeToolCall, afterToolCall: session.agent.afterToolCall, signal,
  });
  session.sessionManager.appendMessage({ role: 'toolResult', toolCallId: toolCall.id, toolName: name,
    content: outcome.result.content, isError: outcome.isError, timestamp: Date.now() });
  session.refreshContext();
  return outcome;
};
const close = async (session) => {
  await emitSessionShutdownEvent(session.extensionRunner, { type: 'session_shutdown' });
  session.dispose();
};
try {
  const settingsManager = pi.SettingsManager.inMemory({ defaultTools: ['+codemode'] });
  const loader = new pi.DefaultResourceLoader({ cwd, agentDir, settingsManager,
    noExtensions: true, noSkills: true, noPromptTemplates: true, noThemes: true,
    extensionFactories: [pi.createCodemodeExtension(), pi.createToolSearchExtension(), pi.createMcpExtension({ openUrl: () => browserOpens++ }), gate],
    agentsFilesOverride: () => ({ agentsFiles: [] }) });
  await loader.reload();
  const { session: parent } = await pi.createAgentSession({ cwd, agentDir, resourceLoader: loader,
    settingsManager, modelRuntime: runtime, model, thinkingLevel: 'high', sessionManager: pi.SessionManager.inMemory(cwd) });
  sessions.push(parent);
  await parent.bindExtensions({});
  await wait(() => parent.getActiveToolNames().includes('mcp__fixture__echo'));
  check('parent HTTP discovery and invocation', !(await call(parent, 'mcp__fixture__echo')).isError);
  const indirect = await call(parent, 'codemode', { code: 'return (await tools.mcp__proxy__echo({})).structuredContent.fixture;' });
  check('codemode invokes MCP and preserves structured result', !indirect.isError
    && indirect.result.content.some((b) => b.text?.includes('true')));
  check('parent direct policy denial', (await call(parent, 'mcp__fixture__denied')).isError);
  const before = wire.filter((e) => e.tool === 'denied').length;
  const proxy = await call(parent, 'codemode', { code: 'const r = await tools.mcp__proxy__denied({}); console.log(r);' });
  check('codemode policy denial reaches nested hook', hooks.some((e) => e.name === 'mcp__proxy__denied' && e.parent)
    && wire.filter((e) => e.tool === 'denied').length === before, { outcome: proxy });
  check('hidden tool is unreachable', (await call(parent, 'mcp__fixture__hidden')).isError);
  check('MCP isError preserved', (await call(parent, 'mcp__fixture__error')).isError);
  check('JSON RPC failure preserved', (await call(parent, 'mcp__fixture__rpc_error')).isError);
  const abort = new AbortController();
  const slow = call(parent, 'mcp__fixture__slow', {}, abort.signal);
  await wait(() => pending.size > 0);
  abort.abort();
  check('call cancellation returns error', (await slow).isError);
  await wait(() => pending.size === 0);
  check('timeout returns error', (await call(parent, 'mcp__fixture__slow')).isError);
  await wait(() => pending.size === 0);
  check('server receives cancellation notifications', wire.filter((e) => e.method === 'notifications/cancelled').length === 2);
  check('failed tool calls are not replayed', ['error', 'rpc_error'].every((name) => wire.filter((e) => e.tool === name).length === 1)
    && wire.filter((e) => e.tool === 'slow').length === 2);
  check('auth unavailable and failed server expose no tools', !parent.getAllTools().some((t) => /^mcp__(auth|unavailable)__/.test(t.name)));
  // Headless commands execute without a model/provider request.
  await parent.prompt('/mcp');
  await parent.prompt('/mcp login auth');
  check('headless sign-in creates no OAuth material or browser', !parentCtx.hasUI && browserOpens === 0
    && (!existsSync(join(agentDir, 'mcp-auth.json'))
      || Object.keys(JSON.parse(readFileSync(join(agentDir, 'mcp-auth.json'), 'utf8'))).length === 0));
  const store = new McpOAuthCredentialStore();
  store.forServer('one', `${url}/auth`).save({ tokens: { fixture: 'not-a-real-token' } });
  check('OAuth store isolates different names and URLs',
    !!store.forServer('one', `${url}/auth`).load()
    && !store.forServer('two', `${url}/auth`).load()
    && !store.forServer('one', `${url}/other`).load()
    && (statSync(join(agentDir, 'mcp-auth.json')).mode & 0o777) === 0o600);
  check('same name and URL share OAuth state', !!new McpOAuthCredentialStore().forServer('one', `${url}/auth`).load());
  store.remove('one', `${url}/auth`);
  setRememberAgents(false);
  registerAgents(new Map([['fixture-child', {
    name: 'fixture-child', description: 'Disposable native MCP compatibility probe', builtinToolNames: [],
    extensions: ['builtin:mcp', 'builtin:codemode'],
    extSelectors: ['ext:builtin:mcp/mcp__fixture__echo', 'ext:builtin:codemode'],
    skills: false, systemPrompt: 'Disposable fixture; do not prompt a model.', promptMode: 'replace',
    persistSession: false, outputTranscript: false,
  }]]));
  const cancelled = new AbortController(); cancelled.abort();
  const childResult = await runAgent(parentCtx, 'fixture-child', '', {
    pi: parentApi, model, thinkingLevel: 'high', signal: cancelled.signal, cwd,
  });
  const child = childResult.session; sessions.push(child);
  writeFileSync(join(output, 'child-tools.json'), JSON.stringify(child.getAllTools().map((t) => t.name)));
  await wait(() => child.getAllTools().some((t) => t.name === 'mcp__fixture__echo'));
  // runAgent returns pre-prompt; allow background registration to settle, then its public scope re-narrows at turn_end.
  await new Promise((r) => setTimeout(r, 100));
  check('supplier child retains pinned model and thinking without inference',
    child.model.provider === 'openai-codex' && child.model.id === 'gpt-6.1-sol'
    && child.thinkingLevel === 'high' && child.messages.length === 0);
  check('supplier child selected MCP invocation', !(await call(child, 'mcp__fixture__echo')).isError);
  check('supplier child direct allowlist denies other MCP tool', (await call(child, 'mcp__fixture__denied')).isError);
  const calls = wire.filter((e) => e.tool === 'denied').length;
  const childProxy = await call(child, 'codemode', { code: 'const r = await tools.mcp__proxy__denied({}); console.log(r);' });
  check('supplier child codemode cannot bypass allowlist', childProxy.isError
    && childProxy.result.details.calls.some((c) => c.name === 'mcp__proxy__denied' && c.error.includes('not available'))
    && wire.filter((e) => e.tool === 'denied').length === calls, { outcome: childProxy });
  denyEcho = true;
  check('parent permission handler is not inherited by child',
    (await call(parent, 'mcp__fixture__echo')).isError && !(await call(child, 'mcp__fixture__echo')).isError);
  denyEcho = false;
  const starts = readFileSync(stdioLog, 'utf8').trim().split('\n').map(JSON.parse).filter((e) => e.event === 'start');
  check('enabled stdio starts per parent and child even if out of child scope', starts.length === 2);
  await close(child); sessions.splice(sessions.indexOf(child), 1);
  check('parent remains usable after child shutdown', !(await call(parent, 'mcp__fixture__echo')).isError);
  registerAgents(new Map([['profile-child', {
    name: 'profile-child', description: 'Current Fight extension-selector compatibility',
    builtinToolNames: ['read', 'grep', 'find', 'ls'], extensions: ['agent-os-pi'],
    extSelectors: ['ext:agent-os-pi'], skills: false,
    systemPrompt: 'Disposable selector probe, not a loaded Fight package or assignment.', promptMode: 'replace',
    persistSession: false, outputTranscript: false,
  }]]));
  const { session: profile } = await runAgent(parentCtx, 'profile-child', '', {
    pi: parentApi, model, thinkingLevel: 'high', signal: cancelled.signal, cwd,
  });
  sessions.push(profile);
  await new Promise((r) => setTimeout(r, 100));
  check('current Fight selector does not load native MCP',
    !profile.getAllTools().some((t) => t.name.startsWith('mcp__'))
    && readFileSync(stdioLog, 'utf8').trim().split('\n').map(JSON.parse).filter((e) => e.event === 'start').length === 2);
  await close(profile); sessions.splice(sessions.indexOf(profile), 1);
  await close(parent); sessions.splice(sessions.indexOf(parent), 1);
  await wait(() => starts.every(({ pid }) => { try { process.kill(pid, 0); return false; } catch { return true; } }));
  check('shutdown reaps both owned stdio processes', true);
  const strictSettings = pi.SettingsManager.inMemory({});
  const strictLoader = new pi.DefaultResourceLoader({ cwd, agentDir, settingsManager: strictSettings,
    noExtensions: true, noSkills: true, noPromptTemplates: true, noThemes: true,
    agentsFilesOverride: () => ({ agentsFiles: [] }),
    extensionFactories: [pi.createCodemodeExtension(), pi.createMcpExtension({ loadConfig: () => ({
      servers: ['fixture', 'proxy'].map((name) => ({ name, config: config.mcpServers[name], source: 'fixture' })), errors: [],
    }) })] });
  await strictLoader.reload();
  const { session: strict } = await pi.createAgentSession({ cwd, agentDir, resourceLoader: strictLoader,
    settingsManager: strictSettings, modelRuntime: runtime, model, thinkingLevel: 'high',
    tools: ['codemode', 'mcp__fixture__*'], excludeTools: ['mcp__fixture__denied'],
    sessionManager: pi.SessionManager.inMemory(cwd) });
  sessions.push(strict);
  await strict.bindExtensions({});
  await wait(() => strict.getAllTools().some((t) => t.name === 'mcp__fixture__echo'));
  check('native explicit MCP allowlist and exclusion filter registry',
    !strict.getAllTools().some((t) => t.name.startsWith('mcp__proxy__') || t.name === 'mcp__fixture__denied')
    && !(await call(strict, 'mcp__fixture__echo')).isError);
  const strictProxy = await call(strict, 'codemode', { code: 'return await tools.mcp__proxy__echo({});' });
  check('native filtered tool cannot be called indirectly', strictProxy.isError);
  await close(strict); sessions.splice(sessions.indexOf(strict), 1);
} finally {
  for (const session of sessions.reverse()) await close(session);
  server.closeAllConnections();
  await new Promise((r) => server.close(r));
  writeFileSync(join(output, 'results.json'), JSON.stringify({
    pi: JSON.parse(readFileSync(join(piRoot, 'package.json'))).version,
    supplier: JSON.parse(readFileSync(join(supplierRoot, 'package.json'))).version,
    model: 'openai-codex/gpt-6.1-sol/high',
    fixtureSha256: createHash('sha256').update(readFileSync(fileURLToPath(import.meta.url))).digest('hex'),
    stdioSha256: createHash('sha256').update(readFileSync(join(dirname(fileURLToPath(import.meta.url)), 'stdio.mjs'))).digest('hex'),
    rows, wire, hooks,
  }, null, 2));
}
console.log(JSON.stringify({ passed: rows.length, output }));
