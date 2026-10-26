import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomUUID, sign } from 'node:crypto';
import { chmodSync, lstatSync, mkdtempSync, readFileSync, realpathSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { challenge, candidates, custodianScript, directory, endpointPath, privateDirectory, pruneCandidates, sessionFileIdentity } from '../src/observation/custody.mjs';
import { NativeObservation } from '../src/observation/native-observation.mjs';
import { startCustodian, applyProgress, bindParentFile, monitorWitness } from '../src/observation/custodian.mjs';

async function fixture(t, respond) {
  const cwd = realpathSync(mkdtempSync('/tmp/fc-')), parent = randomUUID(), generation = randomUUID();
  const root = directory(cwd, parent); privateDirectory(root);
  const endpoint = endpointPath(cwd, parent, generation), path = join(root, `${generation}.json`);
  privateDirectory(dirname(endpoint));
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

test('a fresh signed challenge binds the actual parent, generation, endpoint inode and passive observation daemon', async t => {
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
test('caller payloads cannot initialize native observation or invent a provider monitor witness', () => {
  assert.throws(() => startCustodian({ native: { pid: process.pid } }), /native-parent-pid-mismatch/);
  for (const message of [{}, { type: 'launch' }, { type: 'cancel' }, { type: 'monitor', pid: process.pid, token: 'forged', worktree: process.cwd() }]) {
    assert.throws(() => monitorWitness(message, { pid: process.pid }), /Invalid monitor bootstrap/);
  }
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

test('closing observation releases only its IPC/read resource and never sends execution control', () => {
  const observation = new NativeObservation({ cwd: process.cwd(), sessionManager: { getSessionId: () => 'actual-context', getSessionFile: () => null } });
  const sent = []; let released = 0;
  observation.child = { connected: true, send(message) { sent.push(message); }, disconnect() { this.connected = false; released++; }, unref() {} };
  observation.close(); observation.close();
  assert.equal(released, 1); assert.deepEqual(sent, []);
});

test('pending observation publishes no fabricated file and binds only its actual native file lifetime', t => {
  const cwd = realpathSync(mkdtempSync('/tmp/fc-pending-')); t.after(() => rmSync(cwd, { recursive: true, force: true }));
  const parent = randomUUID(), path = join(cwd, 'actual-session.jsonl');
  const context = { cwd, sessionManager: { getSessionId: () => parent, getSessionFile: () => path } };
  const launcher = new NativeObservation(context); t.after(() => launcher.close());
  assert.equal(launcher.parentFile, null); assert.equal(launcher.pendingParentFile, path);
  const sent = [];
  launcher.child = { connected: true, send(value) { sent.push(value); }, disconnect() { this.connected = false; }, unref() {} };
  launcher.syncSessionFile(context); assert.deepEqual(sent, [], 'no fabricated or null witness is sent');
  writeFileSync(path, JSON.stringify({ type: 'session', id: parent }) + '\n');
  launcher.syncSessionFile(context);
  assert.deepEqual(sent, [{ type: 'bind-session', parentFile: sessionFileIdentity(path) }]);
  assert.equal(launcher.pendingParentFile, undefined);
  launcher.syncSessionFile(context); assert.equal(sent.length, 1, 'binding is one-way and idempotent');
  assert.throws(() => launcher.syncSessionFile({ ...context, sessionManager: { ...context.sessionManager, getSessionId: () => randomUUID() } }), /session context/);
  assert.throws(() => launcher.syncSessionFile({ ...context, sessionManager: { ...context.sessionManager, getSessionFile: () => join(cwd, 'other') } }), /session context/);
  renameSync(path, path + '.old'); writeFileSync(path, 'replaced');
  assert.throws(() => launcher.syncSessionFile(context), /session file replaced/);
  assert.equal(sent.length, 1, 'replacement never rebinds an existing witness');
});

test('custodian promotion requires the expected real native file and cannot downgrade, change path or reuse a replaced inode', t => {
  const cwd = realpathSync(mkdtempSync('/tmp/fc-bind-')); t.after(() => rmSync(cwd, { recursive: true, force: true }));
  const path = join(cwd, 'native-session');
  assert.throws(() => bindParentFile(path, null), /witness refused/);
  assert.throws(() => bindParentFile(path, { path }), /ENOENT/);
  writeFileSync(path, 'actual native fixture');
  const witness = sessionFileIdentity(path);
  assert.deepEqual(bindParentFile(path, witness), witness);
  assert.throws(() => bindParentFile(undefined, witness), /witness refused/);
  assert.throws(() => bindParentFile(join(cwd, 'other'), witness), /witness refused/);
  renameSync(path, path + '.old'); writeFileSync(path, 'replaced');
  assert.throws(() => bindParentFile(path, witness), /witness refused/);
});

test('terminal progress cannot resurrect a completed observed child', () => {
  const registration = { sequence: 0, history: [], units: 0, omitted: 0 };
  applyProgress(registration, { sequence: 1, text: 'done', lifecycle: 'completed', exitCode: 0 });
  assert.throws(() => applyProgress(registration, { sequence: 2, text: 'forged restart', lifecycle: 'running', exitCode: null }));
  assert.equal(registration.lifecycle, 'completed'); assert.equal(registration.sequence, 1);
});

test('monitor admission combines private bootstrap, exact live ancestry, fixed provider identity and registered worktree', t => {
  const cwd = realpathSync(mkdtempSync('/tmp/fc-monitor-')); t.after(() => rmSync(cwd, { recursive: true, force: true }));
  const runtime = join(cwd, 'var/terminal/monitors'); privateDirectory(runtime);
  const token = 'a'.repeat(64), pid = 9876, native = { pid: 8765, process: 'actual-native-lifetime', started: Number(process.hrtime.bigint() / 1000000n) - 1000, wallUs: Date.now() * 1000 - 1000000 };
  const monitor = fileURLToPath(new URL('../src/observation/run-stage.py', import.meta.url));
  const identity = { task: 'TASK-00502', role: 'work', provider: 'openai-codex', model: 'gpt-6.1-sol', thinking: 'high', worktree: cwd, attempt: '/display-only/not-read' };
  const witness = { pid, nativePid: native.pid, nativeProcess: native.process, token, worktree: cwd, identity, heartbeat: 1, started: Number(process.hrtime.bigint() / 1000000n), created: Date.now(), process: `monitor-start python3 -u ${monitor} --ordinary-args` };
  const path = join(runtime, `${pid}-${token}.json`), message = { type: 'monitor', pid, token, worktree: cwd };
  const options = { inspect: value => value === pid ? witness.process : native.process, births: [{ pid, parent: 9000, bornUs: native.wallUs + 50000 }, { pid: 9000, parent: native.pid, bornUs: native.wallUs + 30000 }, { pid: native.pid, parent: 1, bornUs: native.wallUs - 1000000 }], registrations: () => `worktree ${cwd}\0` };
  assert.throws(() => monitorWitness(message, native, options), /ENOENT/);
  writeFileSync(path, JSON.stringify(witness), { mode: 0o600 });
  assert.deepEqual(monitorWitness(message, native, options).identity, identity);
  assert.throws(() => monitorWitness(message, { ...native, pid: 1234 }, options), /refused/);
  assert.throws(() => monitorWitness(message, { ...native, started: witness.started + 1 }, options), /refused/);
  assert.throws(() => monitorWitness(message, { ...native, started: undefined }, options), /refused/);
  assert.throws(() => monitorWitness(message, native, { ...options, births: [{ pid, parent: 1111, bornUs: native.wallUs + 50000 }, { pid: 1111, parent: 1, bornUs: native.wallUs + 30000 }] }), /native context/);
  assert.throws(() => monitorWitness(message, native, { ...options, births: options.births.map(entry => entry.pid === 9000 ? { ...entry, bornUs: native.wallUs - 1000000 } : entry) }), /birth/);
  assert.throws(() => monitorWitness(message, native, { ...options, births: options.births.map(entry => entry.pid === 9000 ? { ...entry, bornUs: native.wallUs + 1000 } : entry) }), /birth/);
  assert.throws(() => monitorWitness(message, native, { ...options, registrations: () => 'worktree /unrelated\0' }), /Unregistered/);
  assert.throws(() => monitorWitness(message, native, { ...options, inspect: () => 'reused-pid-lifetime' }), /refused/);
  writeFileSync(path, JSON.stringify({ ...witness, token: 'b'.repeat(64) }));
  assert.throws(() => monitorWitness(message, native, options), /refused/);
  writeFileSync(path, JSON.stringify({ ...witness, created: 1 }));
  assert.throws(() => monitorWitness(message, native, options), /refused/);
});

test('installed package admits its own fixed monitor without a provider checkout sibling', t => {
  const cwd = realpathSync(mkdtempSync('/tmp/fc-installed-')); t.after(() => rmSync(cwd, { recursive: true, force: true }));
  const runtime = join(cwd, 'var/terminal/monitors'); privateDirectory(runtime);
  const token = 'c'.repeat(64), pid = 9876;
  const native = { pid: 8765, process: 'actual-native-lifetime', started: Number(process.hrtime.bigint() / 1000000n) - 1000, wallUs: Date.now() * 1000 - 1000000 };
  const monitor = fileURLToPath(new URL('../src/observation/run-stage.py', import.meta.url));
  const witness = { pid, nativePid: native.pid, nativeProcess: native.process, token, worktree: cwd,
    identity: { task: 'TASK-00538', role: 'work', provider: 'configured', model: 'configured', thinking: 'high', worktree: cwd, attempt: '/display-only' },
    heartbeat: 1, started: Number(process.hrtime.bigint() / 1000000n), created: Date.now(), process: `monitor-start python3 -u ${monitor} --ordinary-args` };
  writeFileSync(join(runtime, `${pid}-${token}.json`), JSON.stringify(witness), { mode: 0o600 });
  const options = { inspect: value => value === pid ? witness.process : native.process,
    births: [{ pid, parent: 9000, bornUs: native.wallUs + 50000 }, { pid: 9000, parent: native.pid, bornUs: native.wallUs + 30000 }, { pid: native.pid, parent: 1, bornUs: native.wallUs - 1000000 }], registrations: () => `worktree ${cwd}\0` };
  assert.deepEqual(monitorWitness({ type: 'monitor', pid, token, worktree: cwd }, native, options).identity, witness.identity);
});

test('session file identity includes native file lifetime, not just its caller-selectable ID', t => {
  const cwd = realpathSync(mkdtempSync('/tmp/fc-file-')); t.after(() => rmSync(cwd, { recursive: true, force: true }));
  const path = join(cwd, 'session'); writeFileSync(path, 'original'); const first = sessionFileIdentity(path);
  rmSync(path); writeFileSync(path, 'replacement'); assert.notDeepEqual(sessionFileIdentity(path), first);
});
