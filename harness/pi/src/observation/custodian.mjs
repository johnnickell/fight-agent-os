// Passive observation daemon. No agent/monitor launch, cancellation or execution protocol exists.
// Native and monitor bootstrap assertions trust local provider processes (accepted R1 limitation).
import { execFileSync } from 'node:child_process';
import { createHash, generateKeyPairSync, randomUUID, sign } from 'node:crypto';
import { chmodSync, lstatSync, readFileSync, realpathSync, unlinkSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { basename, dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { directory, limits, privateDirectory, privateFile, processIdentity, endpointPath, sessionFileIdentity, sameSessionFile, pruneCandidates } from './custody.mjs';
import { clean } from '../workspace/presentation.mjs';

const script = fileURLToPath(import.meta.url);
const monitor = fileURLToPath(new URL('./run-stage.py', import.meta.url));
const identityKeys = ['task', 'role', 'provider', 'model', 'thinking', 'worktree', 'attempt'];
const terminalStates = new Set(['completed', 'failed']);

export function applyProgress(registration, event) {
  if (terminalStates.has(registration.lifecycle) || !['starting', 'running', 'settled-awaiting-exit', 'completed', 'failed'].includes(event.lifecycle) ||
      typeof event.text !== 'string' || [...event.text].length > 600 || !Number.isSafeInteger(event.sequence) || event.sequence <= registration.sequence ||
      (terminalStates.has(event.lifecycle) ? !Number.isInteger(event.exitCode) || event.exitCode < 0 || event.exitCode > 255 || (event.lifecycle === 'completed') !== (event.exitCode === 0) : event.exitCode !== null)) throw new Error('Invalid monitor progress');
  registration.gaps = Math.min(Number.MAX_SAFE_INTEGER, (registration.gaps ?? 0) + event.sequence - registration.sequence - 1);
  registration.sequence = event.sequence; registration.lifecycle = event.lifecycle; registration.exitCode = event.exitCode;
  registration.receivedAt = Date.now(); registration.state = 'connected';
  const text = clean(event.text).replace(/\n/g, ' '); registration.history.push(text); registration.units += text.length;
  while (registration.history.length > limits.records || registration.units > limits.units) { registration.units -= registration.history.shift().length; registration.omitted++; }
}
export function bindParentFile(pendingPath, witness) {
  if (typeof pendingPath !== 'string' || witness?.path !== pendingPath ||
      !sameSessionFile(sessionFileIdentity(pendingPath), witness)) throw new Error('Native session file witness refused');
  return witness;
}
// Exact ancestry is launch evidence, not PID/cwd proximity. Bootstrap witnesses alone cannot
// confer membership: require the fixed live provider monitor below this exact live native process.
export function monitorWitness(message, native, { inspect = processIdentity, births,
  registrations = worktree => execFileSync('git', ['-C', worktree, 'worktree', 'list', '--porcelain', '-z'], { encoding: 'utf8', timeout: 1000, maxBuffer: 1024 * 1024 }) } = {}) {
  if (!message || message.type !== 'monitor' || !Number.isSafeInteger(message.pid) || message.pid < 1 ||
      !/^[a-f0-9]{64}$/.test(message.token) || typeof message.worktree !== 'string') throw new Error('Invalid monitor bootstrap');
  const worktree = realpathSync(message.worktree);
  const path = join(worktree, 'var/terminal/monitors', `${message.pid}-${message.token}.json`);
  privateFile(path);
  if (realpathSync(path) !== path) throw new Error('Monitor witness replaced');
  const witness = JSON.parse(readFileSync(path, 'utf8'));
  if (witness.pid !== message.pid || witness.nativePid !== native.pid || witness.nativeProcess !== native.process ||
      witness.token !== message.token || witness.worktree !== worktree || witness.process !== inspect(message.pid) ||
      !witness.process.includes(` ${monitor} `) || inspect(native.pid) !== native.process ||
      !identityKeys.every(key => typeof witness.identity?.[key] === 'string' && witness.identity[key].length > 0 && witness.identity[key].length <= 1100 && clean(witness.identity[key]) === witness.identity[key]) ||
      witness.identity.worktree !== worktree || !/^TASK-\d{5}$/.test(witness.identity.task) || !['work', 'review', 'qa', 'land'].includes(witness.identity.role) ||
      !Number.isFinite(witness.heartbeat) || witness.heartbeat < 1 || witness.heartbeat > 60 ||
      !Number.isSafeInteger(native.started) || !Number.isSafeInteger(witness.started) || witness.started < native.started || witness.started > Number(process.hrtime.bigint() / 1000000n) ||
      !Number.isSafeInteger(witness.created) || Math.abs(Date.now() - witness.created) > 5000) throw new Error('Monitor witness refused');
  const registered = registrations(worktree);
  if (!registered.split('\0').includes(`worktree ${worktree}`)) throw new Error('Unregistered monitor worktree');
  const lineage = births ?? JSON.parse(execFileSync('python3', [fileURLToPath(new URL('./process-birth.py', import.meta.url)), String(message.pid), String(native.pid)], { encoding: 'utf8', timeout: 500, maxBuffer: 8192 }));
  if (!Number.isSafeInteger(native.wallUs) || Math.abs((Date.now() - native.wallUs / 1000) - (Number(process.hrtime.bigint() / 1000000n) - native.started)) > 10 ||
      !Array.isArray(lineage) || lineage.length < 2 || lineage.length > 32 || lineage[0]?.pid !== message.pid || lineage.at(-1)?.pid !== native.pid ||
      lineage.some((entry, index) => !Number.isSafeInteger(entry.pid) || !Number.isSafeInteger(entry.bornUs) || entry.bornUs < 0 || entry.bornUs > Date.now() * 1000 + 1000 || index < lineage.length - 1 && entry.parent !== lineage[index + 1].pid) ||
      lineage.at(-1).bornUs > native.wallUs || lineage.at(-2).bornUs < native.wallUs + 20000) throw new Error('Executor birth does not establish this native context');
  // Recheck live identities after the kernel snapshot; no payload selects an execution ancestor.
  if (inspect(message.pid) !== witness.process || inspect(native.pid) !== native.process) throw new Error('Native descendant lifetime changed');
  return witness;
}
export function startCustodian({ primary, parent, parentFile, pendingParentFile, generation, native }) {
  if (!native || native.pid !== process.ppid) throw new Error('native-parent-pid-mismatch');
  if (processIdentity(process.ppid) !== native.process) throw new Error('native-parent-lifetime-mismatch');
  if (typeof native.entry !== 'string' || !/@earendil-works\/pi-coding-agent\/dist\/(?:bundle\/)?cli\.js$/.test(realpathSync(native.entry))) throw new Error('native-entry-unsupported');
  if (!Number.isSafeInteger(native.wallUs) || native.wallUs > Date.now() * 1000 || !Number.isSafeInteger(native.started) || native.started < 0 || native.started > Number(process.hrtime.bigint() / 1000000n)) throw new Error('native-context-start-unavailable');
  if (native.mode !== 'tui' || !process.send || typeof parent !== 'string' || !parent || !/^[a-f0-9-]{36}$/.test(generation)) throw new Error('native-context-unavailable');
  if (pendingParentFile !== undefined && (parentFile !== null || typeof pendingParentFile !== 'string' ||
      join(realpathSync(dirname(pendingParentFile)), basename(pendingParentFile)) !== pendingParentFile)) throw new Error('native-session-path-unavailable');
  if (pendingParentFile === undefined && (!parentFile || !sameSessionFile(sessionFileIdentity(parentFile.path), parentFile))) throw new Error('native-session-lifetime-unavailable');
  const launcher = { pid: native.pid, entry: realpathSync(native.entry), processId: createHash('sha256').update(native.process).digest('hex') };
  primary = realpathSync(primary);
  const root = directory(primary, parent); privateDirectory(root); pruneCandidates(primary, parent);
  const endpoint = endpointPath(primary, parent, generation), descriptorPath = join(root, `${generation}.json`);
  privateDirectory(dirname(endpoint));
  const routeRoot = join(primary, 'var/terminal/native'); privateDirectory(routeRoot);
  const routePath = join(routeRoot, `${native.pid}-${generation}.json`);
  const { privateKey, publicKey } = generateKeyPairSync('ed25519');
  const created = Date.now(), children = new Map(), tokens = new Set(), sockets = new Set();
  let sequence = 0, attached = true, lastLive = created, descriptor, descriptorFile, routeFile;
  const publish = () => {
    if (!descriptor || descriptorFile || pendingParentFile !== undefined) return;
    writeFileSync(descriptorPath, JSON.stringify(descriptor), { flag: 'wx', mode: 0o600 }); descriptorFile = lstatSync(descriptorPath);
  };
  const remove = (path, witness) => { try { const info = lstatSync(path); if (info.dev === witness?.dev && info.ino === witness?.ino) unlinkSync(path); } catch {} };
  const server = createServer({ allowHalfOpen: true }, socket => {
    if (sockets.size >= 16) { socket.destroy(); return; }
    sockets.add(socket); let buffer = '', registration, answered = false;
    // Absolute bootstrap/query deadline; monitor streams have a separate absolute lifetime.
    const deadline = setTimeout(() => socket.destroy(), 1000); let lifetime;
    socket.setEncoding('utf8'); socket.on('error', () => {});
    socket.on('close', () => { clearTimeout(deadline); clearTimeout(lifetime); sockets.delete(socket); if (registration) registration.state = 'disconnected'; });
    socket.on('data', chunk => {
      buffer += chunk;
      if (Buffer.byteLength(buffer) > (registration ? 65536 : 8192) || answered) { socket.destroy(); return; }
      let end;
      while ((end = buffer.indexOf('\n')) >= 0) {
        const line = buffer.slice(0, end); buffer = buffer.slice(end + 1);
        try {
          const request = JSON.parse(line);
          if (registration) { applyProgress(registration, request); continue; }
          if (Object.keys(request).length === 1 && /^[a-f0-9]{64}$/.test(request.nonce)) {
            answered = true;
            const body = JSON.stringify({ nonce: request.nonce, parent, parentFile, primary, generation, pid: process.pid, launcher, at: Date.now(), children: [...children.values()] });
            socket.end(JSON.stringify({ body, signature: sign(null, Buffer.from(body), privateKey).toString('base64') }) + '\n'); return;
          }
          for (const [id, child] of children) if (child.state === 'disconnected' && Date.now() - child.receivedAt > limits.retention) children.delete(id);
          if (!attached || tokens.has(request.token) || tokens.size >= 128 || children.size >= limits.children || Date.now() - created > limits.lifetime) throw new Error();
          const witness = monitorWitness(request, native);
          const id = randomUUID(); tokens.add(request.token);
          registration = { id, parent, generation, started: witness.started, order: ++sequence,
            identity: witness.identity, state: 'starting', lifecycle: 'starting', exitCode: null, heartbeatSeconds: witness.heartbeat,
            sequence: 0, receivedAt: Date.now(), history: [], units: 0, omitted: 0, gaps: 0 };
          children.set(id, registration); clearTimeout(deadline);
          lifetime = setTimeout(() => socket.destroy(), limits.lifetime);
          // Admission acknowledgement only; no command, cancellation or child-facing data.
          socket.write(JSON.stringify({ observed: true, pid: request.pid }) + '\n');
        } catch { socket.destroy(); return; }
      }
    });
  });
  server.on('error', () => cleanup(1));
  server.listen(relative(primary, endpoint), () => {
    chmodSync(endpoint, 0o600); const info = lstatSync(endpoint);
    descriptor = { version: 1, parent, parentFile, primary, generation, pid: process.pid, created, launcher,
      process: processIdentity(process.pid), publicKey: publicKey.export({ type: 'spki', format: 'pem' }), endpoint: { dev: info.dev, ino: info.ino } };
    publish();
    writeFileSync(routePath, JSON.stringify({ primary, parent, generation, native, pid: process.pid, process: descriptor.process, created, endpoint: descriptor.endpoint }), { flag: 'wx', mode: 0o600 });
    routeFile = lstatSync(routePath); if (process.connected) process.send({ type: 'ready' }, () => {});
  });
  process.on('disconnect', () => { attached = false; remove(routePath, routeFile); });
  process.on('message', message => {
    if (!attached || message.type !== 'bind-session') return;
    try { parentFile = bindParentFile(pendingParentFile, message.parentFile); pendingParentFile = undefined; if (descriptor) descriptor.parentFile = parentFile; publish(); }
    catch { cleanup(1); }
  });
  function cleanup(code = 0) {
    remove(routePath, routeFile); remove(endpoint, descriptor?.endpoint); remove(descriptorPath, descriptorFile);
    for (const socket of sockets) socket.destroy();
    // No monitor/agent process handles exist here, so shutdown cannot cancel one.
    process.exit(code);
  }
  const timer = setInterval(() => {
    if ([...children.values()].some(child => child.state !== 'disconnected')) lastLive = Date.now();
    if ((!attached && Date.now() - lastLive > limits.retention) || Date.now() - created > limits.lifetime) cleanup();
  }, 1000);
  process.on('SIGTERM', () => cleanup()); process.on('SIGINT', () => cleanup());
  return { children, server, timer };
}
if (process.argv[1] && resolve(process.argv[1]) === script) {
  const generation = process.argv[2], timer = setTimeout(() => process.exit(1), 3000);
  process.once('message', message => {
    clearTimeout(timer);
    if (message.type !== 'initialize' || message.generation !== generation) process.exit(1);
    try { startCustodian(message); } catch { process.exit(1); }
  });
}
