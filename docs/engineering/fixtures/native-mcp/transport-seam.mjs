// TASK-00555 R1: credential-free native transport API evaluation, not a signer/server or product test.
// Inputs: installed Pi package directory, NEW canonical TASK evidence directory. No network or inference.
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
const [piRoot, output] = process.argv.slice(2).map((p) => resolve(p));
assert(piRoot && output && !existsSync(output), 'Supply installed Pi and a new evidence directory');
mkdirSync(output, { recursive: true, mode: 0o700 });
const agentDir = join(output, 'agent'), cwd = join(output, 'workspace');
for (const path of [agentDir, cwd, join(output, 'home')]) mkdirSync(path, { mode: 0o700 });
process.env.PI_CODING_AGENT_DIR = agentDir;
process.env.HOME = join(output, 'home');
globalThis.fetch = async () => { throw new Error('No real network permitted in this probe'); };
const load = (root, path) => import(pathToFileURL(join(root, path)).href);
const pi = await load(piRoot, 'dist/index.js');
const { StreamableHttpTransport } = await load(dirname(piRoot), 'pi-mcp/dist/index.js');
const { createDefaultTransport } = await load(piRoot, 'dist/extensions/mcp/runtime.js');
const { emitSessionShutdownEvent } = await load(piRoot, 'dist/core/extensions/runner.js');
const { runToolCall } = await load(dirname(piRoot), 'pi-agent-core/dist/index.js');
const rows = [], wire = [];
const save = () => writeFileSync(join(output, 'results.json'), JSON.stringify({ rows, wire,
  pi: JSON.parse(readFileSync(join(piRoot, 'package.json'))).version,
  nativeMcp: JSON.parse(readFileSync(join(dirname(piRoot), 'pi-mcp/package.json'))).version,
  model: 'openai-codex/gpt-6.1-sol/high',
  fixtureSha256: createHash('sha256').update(readFileSync(new URL(import.meta.url))).digest('hex'),
  limits: 'Capture-only fetch; no socket, signer, nonce, credential, protected holder, Fight endpoint or inference',
}, null, 2));
const check = (name, value) => { rows.push({ name, pass: !!value }); assert(value, name); };
const url = 'https://fixture.invalid/mcp?z=2&a=1';
const entry = { name: 'seam', source: 'TASK-00555 fixture', scope: 'extension', config: {
  url, exposure: 'direct', headers: { Authorization: 'fixture-no-credential' }, timeout: 2,
} };
let session, factories = 0;
const runtime = await pi.ModelRuntime.create({ authPath: join(agentDir, 'auth.json'), modelsPath: null,
  modelsStorePath: join(agentDir, 'models-cache.json'), refreshOnCreate: false });
try {
  // Inspect default construction without dispatch. Only a synthetic nonsecret environment value is used.
  process.env.TASK_00555_HEADER = 'first';
  const configured = { ...entry, config: { ...entry.config,
    headers: { 'X-Fixture': '${TASK_00555_HEADER}' } } };
  const fixed = createDefaultTransport(configured, cwd, undefined);
  process.env.TASK_00555_HEADER = 'second';
  check('default headers resolve at transport construction, not per request',
    fixed.options.headers['X-Fixture'] === 'first' &&
    createDefaultTransport(configured, cwd, undefined).options.headers['X-Fixture'] === 'second');
  delete process.env.TASK_00555_HEADER;
  const captureFetch = async (input, init) => {
    assert.equal(String(input), url);
    const headers = new Headers(init.headers);
    assert(!headers.has('authorization'), 'No bearer or signing material in seam probe');
    const message = init.body === undefined ? undefined : JSON.parse(init.body);
    wire.push({ method: init.method, url: String(input), rpc: message?.method,
      cursor: message?.params?.cursor, session: headers.has('mcp-session-id'),
      protocol: headers.has('mcp-protocol-version'), bodyType: typeof init.body,
      bodyBytes: Buffer.byteLength(init.body ?? ''),
      bodySha256: createHash('sha256').update(init.body ?? '').digest('hex') });
    if (init.method === 'GET') return new Response(null, { status: 405 });
    if (init.method === 'DELETE') return new Response(null, { status: 204 });
    assert.equal(init.method, 'POST');
    assert.equal(headers.get('content-type'), 'application/json');
    if (message.id === undefined) return new Response(null, { status: 202 });
    let result;
    if (message.method === 'initialize') result = { protocolVersion: message.params.protocolVersion,
      capabilities: { tools: {} }, serverInfo: { name: 'capture-only', version: '1' } };
    else if (message.method === 'tools/list') result = message.params?.cursor ? { tools: [] } : {
      tools: [{ name: 'echo', description: 'capture only', inputSchema: {
        type: 'object', properties: { text: { type: 'string' } }, required: ['text'],
      } }], nextCursor: 'fixture-page-2',
    };
    else if (message.method === 'tools/call') {
      assert.deepEqual(message.params.arguments, { text: 'fixture-é\\quoted"' });
      result = { content: [{ type: 'text', text: 'capture only' }] };
    } else throw new Error(`Unexpected RPC ${message.method}`);
    return new Response(JSON.stringify({ jsonrpc: '2.0', id: message.id, result }), {
      headers: { 'content-type': 'application/json', 'mcp-session-id': 'fixture-session' },
    });
  };
  const settingsManager = pi.SettingsManager.inMemory({});
  const loader = new pi.DefaultResourceLoader({ cwd, agentDir, settingsManager,
    noExtensions: true, noSkills: true, noPromptTemplates: true, noThemes: true,
    agentsFilesOverride: () => ({ agentsFiles: [] }),
    extensionFactories: [pi.createMcpExtension({ loadConfig: () => ({ servers: [entry], errors: [] }),
      createTransport: (actual, actualCwd) => {
        factories++;
        assert.equal(actual.name, entry.name);
        assert.equal(actualCwd, cwd);
        // Deliberately omit OAuth. This callback only captures inputs and synthesizes responses.
        return new StreamableHttpTransport({ url: actual.config.url, fetch: captureFetch });
      } })],
  });
  await loader.reload();
  const model = runtime.getModel('openai-codex', 'gpt-6.1-sol');
  assert(model, 'Do not substitute the configured model');
  ({ session } = await pi.createAgentSession({ cwd, agentDir, settingsManager, resourceLoader: loader,
    modelRuntime: runtime, model, thinkingLevel: 'high', sessionManager: pi.SessionManager.inMemory(cwd) }));
  await session.bindExtensions({});
  const deadline = Date.now() + 5000;
  while (!session.agent.state.tools.some((t) => t.name === 'mcp__seam__echo')) {
    assert(Date.now() < deadline, 'Native discovery deadline');
    await new Promise((r) => setTimeout(r, 10));
  }
  check('exported native extension invokes custom factory', factories === 1);
  const toolCall = { type: 'toolCall', id: 'seam-call', name: 'mcp__seam__echo',
    arguments: { text: 'fixture-é\\quoted"' } };
  const assistantMessage = { role: 'assistant', content: [toolCall], api: model.api,
    provider: model.provider, model: model.id, stopReason: 'toolUse', timestamp: Date.now(),
    usage: { input: 0, output: 0, cacheRead: 0, cacheWrite: 0, totalTokens: 0,
      cost: { input: 0, output: 0, cacheRead: 0, cacheWrite: 0, total: 0 } } };
  session.sessionManager.appendMessage(assistantMessage);
  session.refreshContext();
  const outcome = await runToolCall(toolCall, { tools: session.agent.state.tools,
    context: session.agent.state, assistantMessage,
    beforeToolCall: session.agent.beforeToolCall, afterToolCall: session.agent.afterToolCall });
  check('native tool dispatch reaches capture seam', !outcome.isError && wire.some((w) => w.rpc === 'tools/call'));
  check('each discovery page reaches fetch', wire.filter((w) => w.rpc === 'tools/list').length === 2 &&
    wire.some((w) => w.cursor === 'fixture-page-2'));
  check('POST seam sees serialized bytes and URI before dispatch',
    wire.filter((w) => w.method === 'POST').every((w) => w.bodyType === 'string' && w.bodyBytes > 0 && w.url === url));
  check('session protocol headers and GET reach fetch', wire.some((w) => w.method === 'GET' && w.session && w.protocol));
} finally {
  if (session) {
    await emitSessionShutdownEvent(session.extensionRunner, { type: 'session_shutdown' });
    session.dispose();
  }
  save();
}
check('session DELETE also reaches fetch', wire.some((w) => w.method === 'DELETE' && w.session));
save();
console.log(`${rows.length}/${rows.length} transport seam checks passed; no network/signing`);
