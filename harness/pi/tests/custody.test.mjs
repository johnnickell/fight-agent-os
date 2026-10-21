import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomUUID, sign } from 'node:crypto';
import { chmodSync, lstatSync, mkdtempSync, readFileSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { join, relative } from 'node:path';
import { challenge, candidates, custodianScript, directory, endpointPath, privateDirectory, pruneCandidates, sessionFileIdentity } from '../src/observation/custody.mjs';
import { stageRequest, nativeExecutorAllowed, NativeLauncher } from '../src/observation/native-launch.mjs';
import { launchOptions, startCustodian, applyProgress } from '../src/observation/custodian.mjs';

async function fixture(t, respond) {
  const cwd = realpathSync(mkdtempSync('/tmp/fc-')), parent = randomUUID(), generation = randomUUID();
  const root = directory(cwd, parent); privateDirectory(root);
  const endpoint = endpointPath(cwd, parent, generation), path = join(root, `${generation}.json`);
  const { privateKey, publicKey } = generateKeyPairSync('ed25519'); const at = Date.now();
  let alter = value => value;
  const sockets = new Set();
  const server = createServer({ allowHalfOpen: true }, socket => {
    sockets.add(socket); socket.once('close', () => sockets.delete(socket));
    if (respond) { respond(socket); return; }
    let buffer = ''; socket.setEncoding('utf8'); socket.on('error', () => {});
    socket.on('data', chunk => {
      buffer += chunk;
      if (!buffer.includes('\n')) return;
      const request = JSON.parse(buffer.split('\n')[0]);
      const value = alter({ nonce: request.nonce, parent, parentFile: null, primary: cwd, generation, pid: process.pid, launcher: descriptor.launcher, at, children: [] });
      const body = JSON.stringify(value);
      socket.end(JSON.stringify({ body, signature: sign(null, Buffer.from(body), privateKey).toString('base64') }) + '\n');
    });
  });
  const socketPath = endpoint.length < 100 ? endpoint : relative(process.cwd(), endpoint);
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(socketPath, resolve); });
  chmodSync(endpoint, 0o600); const info = lstatSync(endpoint);
  const descriptor = { version: 1, parent, parentFile: null, primary: cwd, generation, pid: process.pid, created: at,
    launcher: { pid: 1234, entry: '/qualified/native/pi', process: 'native bootstrap tested separately' },
    process: `qualified-start node ${custodianScript} ${generation}`, publicKey: publicKey.export({ type: 'spki', format: 'pem' }), endpoint: { dev: info.dev, ino: info.ino } };
  writeFileSync(path, JSON.stringify(descriptor), { mode: 0o600 });
  t.after(async () => { for (const socket of sockets) socket.destroy(); await new Promise(resolve => server.close(resolve)); rmSync(cwd, { force: true, recursive: true }); });
  const options = { now: () => at, inspectProcess: () => descriptor.process };
  return { cwd, parent, generation, path, endpoint, descriptor, options, alter: fn => { alter = fn; }, read: () => challenge(cwd, parent, path, descriptor, options) };
}

test('a fresh signed challenge binds the actual parent, generation, endpoint inode and native-launch custodian', async t => {
  const f = await fixture(t); const value = await f.read(); assert.equal(value.parent, f.parent); assert.equal(value.generation, f.generation);
  assert.equal(candidates(f.cwd, f.parent).length, 1); assert.deepEqual(value.children, []);
});
test('a slow-trickle custody response has an absolute one-second deadline, not an activity timeout', async t => {
  let closed;
  const peerClosed = new Promise(resolve => { closed = resolve; });
  const f = await fixture(t, socket => {
    socket.on('error', () => {}); socket.resume();
    const timer = setInterval(() => socket.write(' '), 100);
    socket.once('close', () => { clearInterval(timer); closed(); });
  });
  const started = performance.now();
  const result = await Promise.race([
    f.read().then(() => 'accepted', error => error.message),
    new Promise(resolve => { const timer = setTimeout(() => resolve('watchdog exceeded'), 1600); timer.unref(); }),
  ]);
  assert.match(result, /Custody challenge timeout/);
  assert.ok(performance.now() - started < 1500, 'trickle must not reset the elapsed bound');
  await peerClosed;
});
test('challenge cancellation immediately closes a live transport and pre-aborted challenges never connect', async t => {
  let connected, closed;
  const connection = new Promise(resolve => { connected = resolve; });
  const disconnection = new Promise(resolve => { closed = resolve; });
  let connections = 0;
  const f = await fixture(t, socket => {
    connections++; connected(); socket.resume(); socket.on('error', () => {});
    const timer = setInterval(() => socket.write(' '), 20);
    socket.once('close', () => { clearInterval(timer); closed(); });
  });
  const controller = new AbortController();
  const pending = challenge(f.cwd, f.parent, f.path, f.descriptor, { ...f.options, signal: controller.signal });
  await connection; controller.abort();
  const result = await Promise.race([pending.catch(error => error.name), new Promise(resolve => { const timer = setTimeout(() => resolve('not cancelled'), 250); timer.unref(); })]);
  assert.equal(result, 'AbortError'); await disconnection;
  await assert.rejects(challenge(f.cwd, f.parent, f.path, f.descriptor, { ...f.options, signal: controller.signal }), { name: 'AbortError' });
  assert.equal(connections, 1);
});

test('caller parent IDs, renamed descriptors and expired lifetimes cannot borrow a live custodian', async t => {
  const f = await fixture(t);
  await assert.rejects(challenge(f.cwd, randomUUID(), f.path, f.descriptor, f.options), /cross-session/);
  await assert.rejects(challenge(f.cwd, f.parent, f.path + '.replacement', f.descriptor, f.options));
  await assert.rejects(challenge(f.cwd, f.parent, f.path, { ...f.descriptor, created: 1 }, f.options), /Stale/);
});
test('model-written registry key, endpoint generation, process custody and parent claims are candidates, never admission', async t => {
  const f = await fixture(t), keys = generateKeyPairSync('ed25519');
  await assert.rejects(challenge(f.cwd, f.parent, f.path, { ...f.descriptor, publicKey: keys.publicKey.export({ type: 'spki', format: 'pem' }) }, f.options), /signature/);
  await assert.rejects(challenge(f.cwd, f.parent, f.path, { ...f.descriptor, endpoint: { dev: 0, ino: 0 } }, f.options), /replaced/);
  await assert.rejects(challenge(f.cwd, f.parent, f.path, f.descriptor, { ...f.options, inspectProcess: () => 'unrelated same-user socket process' }), /custody lost/);
  f.alter(value => ({ ...value, parent: randomUUID() })); await assert.rejects(f.read(), /changed or expired/);
});
test('a signed replayed nonce, replaced native witness or stale event timestamp is rejected', async t => {
  const f = await fixture(t);
  for (const change of [value => ({ ...value, nonce: '0'.repeat(64) }), value => ({ ...value, launcher: {} }), value => ({ ...value, at: 1 }), value => ({ ...value, generation: randomUUID() })]) {
    f.alter(change); await assert.rejects(f.read(), /changed or expired/);
  }
});
test('candidate cleanup removes only dead exact private runtime identities; live candidates stay', async t => {
  const f = await fixture(t);
  // This fixture advertises a deliberately synthetic process identity, so OS inspection identifies it as stale.
  pruneCandidates(f.cwd, f.parent); assert.deepEqual(candidates(f.cwd, f.parent), []);
  assert.throws(() => readFileSync(f.path));
});
test('qualified builtin:bash metadata permits the native bridge without replacing another extension executor', () => {
  assert.equal(nativeExecutorAllowed({ path: 'builtin:bash', source: 'builtin' }, '/provider/native.ts'), true);
  assert.equal(nativeExecutorAllowed({ path: '/provider/native.ts', source: 'cli' }, '/provider/native.ts'), true);
  assert.equal(nativeExecutorAllowed({ path: '/other/restricted-executor.ts', source: 'cli' }, '/provider/native.ts'), false);
  assert.equal(nativeExecutorAllowed({ path: 'builtin:bash', source: 'cli' }, '/provider/native.ts'), false);
  assert.equal(nativeExecutorAllowed({ path: 'builtin:bash', source: 'builtin' }, '/provider/native.ts', false), false);
});

test('the fixed native request grammar does not evaluate a shell or accept a caller parent field as context', () => {
  assert.equal(stageRequest('printf ordinary-native-shell'), null);
  assert.throws(() => stageRequest('fight-stage $(anything)'));
  const value = { parent: 'caller-data', worktree: '/untrusted' };
  assert.deepEqual(stageRequest(`fight-stage ${JSON.stringify(value)}`), value);
  // Parsing is not admission. The fixed custodian rejects undeclared identity keys before any spawn.
  assert.throws(() => launchOptions(value), /Invalid fixed stage identity/);
  assert.throws(() => startCustodian({ native: { pid: process.pid } }), /native-parent-pid-mismatch/);
  assert.throws(() => stageRequest('fight-stage ' + 'a'.repeat(8193)));
});
test('monitor sequence gaps and history eviction are counted separately without accepting identity from progress', () => {
  const identity = { worktree: '/verified/source' }, registration = { identity, sequence: 0, gaps: 0, history: [], units: 0, omitted: 0 };
  applyProgress(registration, { sequence: 1, lifecycle: 'running', exitCode: null, text: 'first' });
  applyProgress(registration, { sequence: 5, lifecycle: 'running', exitCode: null, text: 'after drop', identity: { worktree: '/injected' } });
  assert.equal(registration.gaps, 3); assert.equal(registration.omitted, 0); assert.equal(registration.identity, identity);
  const before = JSON.stringify(registration);
  for (const event of [{ sequence: 5, lifecycle: 'completed', exitCode: 0, text: 'stale' }, { sequence: 6, lifecycle: 'completed', exitCode: 1, text: 'false success' }]) assert.throws(() => applyProgress(registration, event));
  assert.equal(JSON.stringify(registration), before);
  for (let sequence = 6; sequence <= 110; sequence++) applyProgress(registration, { sequence, lifecycle: 'running', exitCode: null, text: 'bounded' });
  assert.equal(registration.history.length, 100); assert.equal(registration.omitted, 7); assert.equal(registration.gaps, 3);
});

test('native cancellation is request-bound and does not cancel a concurrent sibling or turn monitor zero into success', async () => {
  const launcher = new NativeLauncher({ cwd: process.cwd(), sessionManager: { getSessionId: () => 'actual-context', getSessionFile: () => null } });
  const sent = []; launcher.ready = async () => {};
  launcher.child = { connected: true, send(message, callback) { sent.push(message); callback?.(); }, disconnect() { this.connected = false; }, unref() {} };
  const controller = new AbortController();
  const first = launcher.execute({ attempt: 'one' }, { onData() {}, signal: controller.signal });
  const second = launcher.execute({ attempt: 'two' }, { onData() {} }); await Promise.resolve();
  const launched = sent.filter(x => x.type === 'launch'); assert.equal(launched.length, 2);
  controller.abort(); assert.deepEqual(sent.filter(x => x.type === 'cancel'), [{ type: 'cancel', id: launched[0].id }]);
  for (const message of launched) { const request = launcher.requests.get(message.id); launcher.requests.delete(message.id); request.cleanup(); request.resolve({ exitCode: 0 }); }
  await assert.rejects(first, /aborted/); assert.deepEqual(await second, { exitCode: 0 }); launcher.close();
});
test('native operation deadlines request normal monitor cancellation; closing presentation never sends cancellation', async () => {
  const launcher = new NativeLauncher({ cwd: process.cwd(), sessionManager: { getSessionId: () => 'actual-context', getSessionFile: () => null } });
  const sent = []; launcher.ready = async () => {};
  launcher.child = { connected: true, send(message, callback) { sent.push(message); callback?.(); }, disconnect() { this.connected = false; }, unref() {} };
  const pending = launcher.execute({}, { onData() {}, timeout: 0.01 });
  await new Promise(resolve => setTimeout(resolve, 25));
  const launch = sent.find(x => x.type === 'launch'); assert.deepEqual(sent.filter(x => x.type === 'cancel'), [{ type: 'cancel', id: launch.id }]);
  const request = launcher.requests.get(launch.id); launcher.requests.delete(launch.id); request.cleanup(); request.resolve({ exitCode: 0 });
  await assert.rejects(pending, /timeout/);
  const waiting = launcher.execute({}, { onData() {} }); await Promise.resolve(); const count = sent.filter(x => x.type === 'cancel').length;
  launcher.close(); await assert.rejects(waiting, /context closed/); assert.equal(sent.filter(x => x.type === 'cancel').length, count);
});

test('session file identity includes native file lifetime, not just its caller-selectable ID', t => {
  const cwd = realpathSync(mkdtempSync('/tmp/fc-file-')); t.after(() => rmSync(cwd, { recursive: true, force: true }));
  const path = join(cwd, 'session'); writeFileSync(path, 'original'); const first = sessionFileIdentity(path);
  rmSync(path); writeFileSync(path, 'replacement'); assert.notDeepEqual(sessionFileIdentity(path), first);
});
