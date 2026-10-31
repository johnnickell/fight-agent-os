// Trusted-local provider-process custody, not independent native-origin attestation or a same-user sandbox.
import { createHash, randomBytes, verify } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { constants, closeSync, fstatSync, lstatSync, mkdirSync, openSync, opendirSync, readFileSync, realpathSync, unlinkSync } from 'node:fs';
import { createConnection } from 'node:net';
import { join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const custodianScript = fileURLToPath(new URL('./custodian.mjs', import.meta.url));
export const limits = Object.freeze({ brokers: 8, children: 8, records: 100, units: 32768, frame: 2 * 1024 * 1024, lifetime: 24 * 60 * 60 * 1000, retention: 10 * 60 * 1000 });
export const sessionKey = session => createHash('sha256').update(session).digest('hex').slice(0, 24);
export const directory = (cwd, session) => join(realpathSync(cwd), 'var/terminal/children', sessionKey(session));
// Keep relative cross-worktree paths below AF_UNIX limits without changing any agent cwd.
export const endpointPath = (cwd, _session, generation) => join(realpathSync(cwd), 'var/terminal/s', `${generation.replaceAll('-', '').slice(0, 24)}.sock`);
export function privateDirectory(path) {
  mkdirSync(path, { recursive: true, mode: 0o700 });
  const info = lstatSync(path);
  if (!info.isDirectory() || info.uid !== process.getuid() || info.mode & 0o077 || realpathSync(path) !== resolve(path)) throw new Error('Private canonical custody directory required');
}
export function privateFile(path, socket = false) {
  const info = lstatSync(path);
  if (!(socket ? info.isSocket() : info.isFile()) || info.uid !== process.getuid() || info.mode & 0o077 || (!socket && info.size > 8192)) throw new Error('Invalid custody candidate');
  return info;
}
const birthScript = fileURLToPath(new URL('./session-file-birth.py', import.meta.url));
const decimal = value => typeof value === 'string' && /^(0|[1-9][0-9]{0,29})$/.test(value);
export function validSessionFile(value) {
  return value !== null && typeof value === 'object' && Object.keys(value).length === 5 && value.version === 2 &&
    typeof value.path === 'string' && value.path.startsWith('/') && value.path.length <= 8192 && !value.path.includes('\0') &&
    decimal(value.dev) && decimal(value.ino) && value.ino !== '0' && decimal(value.birthtimeNs) &&
    BigInt(value.birthtimeNs) > 0n && BigInt(value.birthtimeNs) % 1000000000n !== 0n;
}
export function sameSessionFile(a, b) {
  return validSessionFile(a) && validSessionFile(b) && ['path', 'dev', 'ino', 'birthtimeNs'].every(key => a[key] === b[key]);
}
export function sessionFileIdentity(path) {
  if (!path) return null;
  if (!['darwin', 'linux'].includes(process.platform)) throw new Error('Native session file lifetime unsupported');
  const canonical = realpathSync(path);
  // Pin this observation to one open inode while inspecting the path; no retained handles or registry.
  const fd = openSync(canonical, constants.O_RDONLY | constants.O_NOFOLLOW | constants.O_NONBLOCK);
  try {
    const info = fstatSync(fd, { bigint: true });
    if (!info.isFile()) throw new Error('Persistent parent session unavailable');
    // Linux's stat fallback may substitute mutable ctime. Require explicit native STATX_BTIME support instead.
    const birthtimeNs = process.platform === 'linux'
      ? JSON.parse(execFileSync('python3', ['-I', '-B', birthScript], { stdio: ['ignore', 'pipe', 'ignore', fd], encoding: 'utf8', timeout: 500, maxBuffer: 4096 })).birthtimeNs
      : info.birthtimeNs?.toString();
    const witness = { version: 2, path: canonical, dev: info.dev.toString(), ino: info.ino.toString(), birthtimeNs };
    if (!validSessionFile(witness)) throw new Error('Native session file lifetime unavailable/ambiguous');
    const current = lstatSync(canonical, { bigint: true });
    if (!current.isFile() || current.dev !== info.dev || current.ino !== info.ino || realpathSync(path) !== canonical) throw new Error('Native session file replaced during observation');
    return witness;
  } finally { closeSync(fd); }
}
export function processIdentity(pid) {
  if (!Number.isSafeInteger(pid) || pid < 1) throw new Error('Invalid custodian PID');
  return execFileSync('ps', ['-p', String(pid), '-o', 'lstart=', '-o', 'command='], { encoding: 'utf8', timeout: 1000, maxBuffer: 8192, stdio: ['ignore', 'pipe', 'ignore'] }).trim();
}
export function candidates(cwd, session) {
  const root = directory(cwd, session), result = [];
  let handle;
  try {
    const info = lstatSync(root);
    if (!info.isDirectory() || info.uid !== process.getuid() || info.mode & 0o077 || realpathSync(root) !== root) throw new Error('Invalid custody directory');
    handle = opendirSync(root);
    // Two files per custodian, plus one preference file; never an unbounded glob/watch.
    for (let count = 0, entry; (entry = handle.readSync()); count++) {
      if (count >= limits.brokers * 2 + 1) throw new Error('Custody discovery capacity exceeded; inspect expired registrations');
      if (/^[a-f0-9-]{36}\.json$/.test(entry.name)) {
        const path = join(root, entry.name); privateFile(path);
        const descriptor = JSON.parse(readFileSync(path, 'utf8'));
        result.push({ path, descriptor });
      }
    }
    if (result.length > limits.brokers) throw new Error('Custody discovery capacity exceeded');
    return result;
  } catch (error) { if (error.code === 'ENOENT') return []; throw error; }
  finally { handle?.closeSync(); }
}
export function pruneCandidates(cwd, session) {
  for (const { path, descriptor } of candidates(cwd, session)) {
    if (path !== join(directory(cwd, session), `${descriptor.generation}.json`) || descriptor.parent !== session || !/^[a-f0-9-]{36}$/.test(descriptor.generation)) continue;
    let live = false;
    try { live = processIdentity(descriptor.pid) === descriptor.process; } catch {}
    if (live) continue;
    const endpoint = endpointPath(cwd, session, descriptor.generation);
    try { const info = privateFile(endpoint, true); if (info.dev === descriptor.endpoint?.dev && info.ino === descriptor.endpoint?.ino) unlinkSync(endpoint); } catch {}
    // Remove only the exact bounded candidate inspected here; never follow a registry path.
    privateFile(path); unlinkSync(path);
  }
}
export async function challenge(cwd, session, file, descriptor, { now = Date.now, inspectProcess = processIdentity, signal } = {}) {
  const started = performance.now();
  signal?.throwIfAborted();
  const root = directory(cwd, session), expected = join(root, `${descriptor.generation}.json`);
  if (file !== expected || descriptor.version !== 1 || descriptor.parent !== session || descriptor.primary !== realpathSync(cwd) ||
      !validSessionFile(descriptor.parentFile) || !/^[a-f0-9-]{36}$/.test(descriptor.generation) || typeof descriptor.publicKey !== 'string' || descriptor.publicKey.length > 256 ||
      !Number.isSafeInteger(descriptor.created) || now() < descriptor.created || now() - descriptor.created > limits.lifetime) throw new Error('Stale or cross-session custody candidate');
  const endpoint = endpointPath(cwd, session, descriptor.generation), info = privateFile(endpoint, true);
  if (info.dev !== descriptor.endpoint.dev || info.ino !== descriptor.endpoint.ino) throw new Error('Custody endpoint replaced');
  const identity = inspectProcess(descriptor.pid);
  if (identity !== descriptor.process || !identity.endsWith(` ${custodianScript} ${descriptor.generation}`)) throw new Error('Custodian process custody lost');
  const nonce = randomBytes(32).toString('hex');
  // Relative AF_UNIX paths keep deeply nested worktrees within the host's path limit.
  const local = relative(process.cwd(), endpoint);
  const socketPath = Buffer.byteLength(local) < Buffer.byteLength(endpoint) ? local : endpoint;
  if (Buffer.byteLength(socketPath) > 100) throw new Error('Custody socket path too long from this workspace');
  if (performance.now() - started >= 1000) throw new Error('Custody challenge timeout');
  const response = await new Promise((accept, reject) => {
    const socket = createConnection({ path: socketPath }); let buffer = '', settled = false;
    const finish = (error, value) => {
      if (settled) return;
      settled = true; clearTimeout(timer); signal?.removeEventListener('abort', abort); socket.destroy();
      error ? reject(error) : accept(value);
    };
    const abort = () => finish(signal.reason ?? new DOMException('Custody challenge aborted', 'AbortError'));
    // An elapsed watchdog, not socket inactivity: bytes cannot extend this deadline.
    const timer = setTimeout(() => finish(new Error('Custody challenge timeout')), Math.max(0, 1000 - (performance.now() - started)));
    signal?.addEventListener('abort', abort, { once: true });
    socket.setEncoding('utf8');
    socket.on('connect', () => socket.end(JSON.stringify({ nonce }) + '\n'));
    socket.on('data', chunk => {
      buffer += chunk;
      if (Buffer.byteLength(buffer) > limits.frame) { finish(new Error('Oversized custody response')); return; }
      const end = buffer.indexOf('\n');
      if (end >= 0) { try { finish(null, JSON.parse(buffer.slice(0, end))); } catch (error) { finish(error); } }
    });
    socket.on('error', error => finish(error));
    socket.on('end', () => finish(new Error('Incomplete custody response')));
    socket.on('close', () => finish(new Error('Custody transport closed')));
    if (signal?.aborted) abort();
  });
  const { body, signature } = response;
  if (typeof body !== 'string' || typeof signature !== 'string' || !verify(null, Buffer.from(body), descriptor.publicKey, Buffer.from(signature, 'base64'))) throw new Error('Invalid custody signature');
  const value = JSON.parse(body);
  const current = privateFile(endpoint, true);
  if (current.dev !== info.dev || current.ino !== info.ino || value.nonce !== nonce || value.parent !== session || value.generation !== descriptor.generation ||
      value.primary !== descriptor.primary || value.pid !== descriptor.pid || !descriptor.launcher ||
      JSON.stringify(value.launcher) !== JSON.stringify(descriptor.launcher) || !sameSessionFile(value.parentFile, descriptor.parentFile) ||
      !Number.isSafeInteger(value.at) || Math.abs(now() - value.at) > 5000 || !Array.isArray(value.children) || value.children.length > limits.children) throw new Error('Custody witness changed or expired');
  if (!sameSessionFile(sessionFileIdentity(descriptor.parentFile.path), descriptor.parentFile)) throw new Error('Parent session file replaced/unavailable or legacy witness');
  if (performance.now() - started >= 1000) throw new Error('Custody challenge timeout');
  return value;
}
