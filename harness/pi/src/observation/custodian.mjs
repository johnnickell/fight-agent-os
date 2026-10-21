// Only inherited Node IPC can initialize custody or launch/cancel a fixed provider monitor.
// Trust local bootstrap callers; IPC does not independently attest native Pi/session origin (R1).
// The public socket answers bounded read-only nonce challenges; it never registers callers.
import { spawn, execFileSync } from 'node:child_process';
import { createHash, generateKeyPairSync, randomUUID, sign } from 'node:crypto';
import { chmodSync, lstatSync, realpathSync, unlinkSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { directory, limits, privateDirectory, processIdentity, endpointPath } from './custody.mjs';
import { clean } from '../workspace/presentation.mjs';

const script = fileURLToPath(import.meta.url);
const monitor = fileURLToPath(new URL('../../../../.pi/skills/build/scripts/run-stage.py', import.meta.url));
const identityKeys = ['task', 'role', 'provider', 'model', 'thinking', 'worktree', 'attempt'];
const terminalStates = new Set(['completed', 'failed']);

export function launchOptions(value) {
  const keys = ['attempt', 'task', 'role', 'provider', 'model', 'thinking', 'worktree', 'timeout', 'heartbeat', 'screeningDispatch'];
  if (!value || Object.keys(value).some(key => !keys.includes(key)) ||
      !keys.slice(0, 7).every(key => typeof value[key] === 'string' && value[key].length > 0 && value[key].length <= 1100 && !/[\x00-\x1f\x7f]/.test(value[key])) ||
      !/^TASK-\d{5}$/.test(value.task) || !['work', 'review', 'qa', 'land'].includes(value.role)) throw new Error('Invalid fixed stage identity');
  const worktree = realpathSync(value.worktree), attempt = realpathSync(value.attempt);
  // Canonical Git registration validates the worktree claim, not parent membership or task authority.
  const registered = execFileSync('git', ['-C', worktree, 'worktree', 'list', '--porcelain', '-z'], { encoding: 'utf8', timeout: 2000, maxBuffer: 1024 * 1024 });
  if (!registered.split('\0').includes(`worktree ${worktree}`) || !attempt.includes('/.runs/') || !lstatSync(join(attempt, 'request.md')).isFile()) throw new Error('Registered worktree and fresh task attempt required');
  if (value.screeningDispatch !== undefined && typeof value.screeningDispatch !== 'boolean') throw new Error('Invalid private screening mode');
  const timeout = value.timeout ?? 1800, heartbeat = value.heartbeat ?? 30;
  if (!Number.isFinite(timeout) || timeout <= 0 || timeout > 7200 || !Number.isFinite(heartbeat) || heartbeat < 1 || heartbeat > 60) throw new Error('Stage timing outside bounds');
  return { ...value, worktree, attempt, timeout, heartbeat };
}

export function applyProgress(registration, event) {
  if (!['starting', 'running', 'settled-awaiting-exit', 'completed', 'failed'].includes(event.lifecycle) ||
      typeof event.text !== 'string' || [...event.text].length > 600 || !Number.isSafeInteger(event.sequence) || event.sequence <= registration.sequence ||
      (terminalStates.has(event.lifecycle) ? !Number.isInteger(event.exitCode) || event.exitCode < 0 || event.exitCode > 255 || (event.lifecycle === 'completed') !== (event.exitCode === 0) : event.exitCode !== null)) throw new Error('Invalid monitor progress');
  registration.gaps = Math.min(Number.MAX_SAFE_INTEGER, (registration.gaps ?? 0) + event.sequence - registration.sequence - 1);
  registration.sequence = event.sequence; registration.lifecycle = event.lifecycle; registration.exitCode = event.exitCode;
  registration.receivedAt = Date.now(); registration.state = 'connected';
  const text = clean(event.text).replace(/\n/g, ' '); registration.history.push(text); registration.units += text.length;
  while (registration.history.length > limits.records || registration.units > limits.units) { registration.units -= registration.history.shift().length; registration.omitted++; }
}

export function startCustodian({ primary, parent, parentFile, generation, harnessPackage, native }) {
  if (!native || native.pid !== process.ppid) throw new Error('native-parent-pid-mismatch');
  if (processIdentity(process.ppid) !== native.process) throw new Error('native-parent-lifetime-mismatch');
  if (typeof native.entry !== 'string' || !/@earendil-works\/pi-coding-agent\/dist\/(?:bundle\/)?cli\.js$/.test(realpathSync(native.entry))) throw new Error('native-entry-unsupported');
  // Pi changes its OS process title, so ps is a lifetime check, not a CLI/mode parser.
  // Normal mode/session come from extension IPC, never launch JSON; a malicious local caller can impersonate it.
  if (native.mode !== 'tui') throw new Error('native-mode-unavailable');
  if (!process.send || typeof parent !== 'string' || !parent || !/^[a-f0-9-]{36}$/.test(generation)) throw new Error('Inherited native bootstrap required');
  const launcher = { pid: native.pid, entry: realpathSync(native.entry), processId: createHash('sha256').update(native.process).digest('hex') };
  primary = realpathSync(primary);
  const root = directory(primary, parent); privateDirectory(root);
  const endpoint = endpointPath(primary, parent, generation), descriptorPath = join(root, `${generation}.json`);
  const { privateKey, publicKey } = generateKeyPairSync('ed25519');
  const created = Date.now(), children = new Map(); let sequence = 0, attached = true, lastLive = created, clients = 0;
  let descriptor, descriptorFile;
  let pendingMessages = 0;
  const send = value => {
    if (!process.connected || pendingMessages >= 64 && value.type === 'output') return;
    pendingMessages++;
    process.send(value, () => { pendingMessages--; });
  };
  const server = createServer({ allowHalfOpen: true }, socket => {
    if (++clients > 8) { clients--; socket.destroy(); return; }
    let buffer = '', answered = false;
    // Bound the entire request/response lifetime, including trickling or blocked peers.
    const deadline = setTimeout(() => socket.destroy(), 1000);
    socket.setEncoding('utf8');
    socket.on('error', () => {}); socket.on('close', () => { clearTimeout(deadline); clients--; });
    socket.on('data', chunk => {
      if (answered) { socket.destroy(); return; }
      buffer += chunk;
      if (buffer.length > 128) { socket.destroy(); return; }
      const end = buffer.indexOf('\n'); if (end < 0) return;
      try {
        const request = JSON.parse(buffer.slice(0, end));
        if (Object.keys(request).length !== 1 || !/^[a-f0-9]{64}$/.test(request.nonce)) throw new Error();
        answered = true;
        const body = JSON.stringify({ nonce: request.nonce, parent, parentFile, primary, generation, pid: process.pid, launcher, at: Date.now(), children: [...children.values()].map(child => child.registration) });
        socket.end(JSON.stringify({ body, signature: sign(null, Buffer.from(body), privateKey).toString('base64') }) + '\n');
      } catch { socket.destroy(); }
    });
  });
  server.on('error', () => process.exit(1));
  server.listen(relative(primary, endpoint), () => {
    chmodSync(endpoint, 0o600); const info = lstatSync(endpoint);
    descriptor = { version: 1, parent, parentFile, primary, generation, pid: process.pid, created, launcher,
      process: processIdentity(process.pid), publicKey: publicKey.export({ type: 'spki', format: 'pem' }), endpoint: { dev: info.dev, ino: info.ino } };
    writeFileSync(descriptorPath, JSON.stringify(descriptor), { flag: 'wx', mode: 0o600 });
    descriptorFile = lstatSync(descriptorPath);
    send({ type: 'ready', descriptor });
  });
  process.on('disconnect', () => { attached = false; });
  process.on('message', message => {
    if (!attached) return;
    if (message.type === 'cancel') {
      const child = [...children.values()].find(child => child.request === message.id);
      if (child?.process) child.process.kill('SIGTERM');
      return;
    }
    if (message.type !== 'launch') return;
    try {
      for (const [id, child] of children) if (!child.process && Date.now() - child.registration.receivedAt > limits.retention) children.delete(id);
      if (children.size >= limits.children) throw new Error('Custodian child capacity reached');
      const config = launchOptions(message.config), id = randomUUID();
      const identity = Object.fromEntries(identityKeys.map(key => [key, config[key]]));
      const registration = { id, parent, generation, started: Number(process.hrtime.bigint() / 1000000n), order: ++sequence, identity, state: 'starting', lifecycle: 'starting', exitCode: null,
        heartbeatSeconds: config.heartbeat, sequence: 0, receivedAt: Date.now(), history: [], units: 0, omitted: 0, gaps: 0 };
      const args = ['-u', monitor, '--attempt', config.attempt, '--task', config.task, '--role', config.role, '--provider', config.provider,
        '--model', config.model, '--thinking', config.thinking, '--timeout', String(config.timeout), '--heartbeat', String(config.heartbeat), '--custody-fd', '3'];
      if (harnessPackage) args.push('--harness-package', harnessPackage);
      if (config.screeningDispatch) args.push('--screening-dispatch');
      const child = spawn('python3', args, { cwd: config.worktree, env: process.env, stdio: ['ignore', 'pipe', 'pipe', 'pipe'] });
      const item = { registration, process: child, request: message.id }; children.set(id, item);
      // This pipe is created by the fixed launcher, never discovered from an agent payload.
      child.stdio[3].write(JSON.stringify({ parent, generation, id, identity }) + '\n');
      child.stdio[3].setEncoding('utf8'); let buffer = '';
      child.stdio[3].on('data', chunk => {
        buffer += chunk;
        if (Buffer.byteLength(buffer) > 65536) { child.stdio[3].destroy(); registration.state = 'disconnected'; return; }
        let end;
        while ((end = buffer.indexOf('\n')) >= 0) {
          const line = buffer.slice(0, end); buffer = buffer.slice(end + 1);
          try {
            applyProgress(registration, JSON.parse(line));
          } catch { registration.state = 'disconnected'; child.stdio[3].destroy(); return; }
        }
      });
      child.stdio[3].on('error', () => { registration.state = 'disconnected'; });
      child.stdio[3].on('close', () => { registration.state = 'disconnected'; });
      // Only the attached original native operation receives monitor stdout. Observers cannot request it.
      child.stdout.on('data', data => send({ type: 'output', id: message.id, text: data.toString('utf8').slice(0, 8192) }));
      child.stderr.resume();
      child.on('error', () => { registration.state = 'disconnected'; });
      child.on('close', code => { item.process = null; registration.state = 'disconnected'; lastLive = Date.now(); send({ type: 'exit', id: message.id, code: code ?? 1 }); });
      send({ type: 'launched', id: message.id, attempt: id });
    } catch { send({ type: 'exit', id: message.id, code: 1, error: 'Fixed monitor launch refused; verify fresh attempt, identity, worktree and capacity' }); }
  });
  const cleanup = () => {
    try { const info = lstatSync(endpoint); if (info.dev === descriptor?.endpoint.dev && info.ino === descriptor?.endpoint.ino) unlinkSync(endpoint); } catch {}
    try { const info = lstatSync(descriptorPath); if (info.dev === descriptorFile?.dev && info.ino === descriptorFile?.ino) unlinkSync(descriptorPath); } catch {}
    process.exit(0);
  };
  const timer = setInterval(() => {
    if ([...children.values()].some(child => child.process)) lastLive = Date.now();
    if ((!attached && Date.now() - lastLive > limits.retention) || Date.now() - created > limits.lifetime) cleanup();
  }, 1000);
  process.on('SIGTERM', cleanup); process.on('SIGINT', cleanup);
  return { children, server, timer };
}
if (process.argv[1] && resolve(process.argv[1]) === script) {
  const generation = process.argv[2];
  const timer = setTimeout(() => process.exit(1), 3000);
  process.once('message', message => {
    clearTimeout(timer);
    if (message.type !== 'initialize' || message.generation !== generation) process.exit(1);
    try { startCustodian(message); } catch (error) {
      const reason = /^native-[a-z-]+$/.test(error.message) ? error.message : 'native-custody-bootstrap-unavailable';
      process.send({ type: 'bootstrap-error', reason }, () => process.exit(1));
    }
  });
}
