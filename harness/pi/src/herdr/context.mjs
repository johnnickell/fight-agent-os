import net from 'node:net';
import { randomUUID } from 'node:crypto';
import { constants, closeSync, fstatSync, lstatSync, openSync, readFileSync, realpathSync, unlinkSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { sessionFileIdentity } from '../observation/custody.mjs';

export const contextLimit = 16384;
export const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

/** Read only the explicit owner-private canonical native binding, never a discovery scan */
export function readBinding(file) {
  if (realpathSync(dirname(file)) !== resolve(dirname(file))) throw Error('Noncanonical native binding directory');
  const fd = openSync(file, constants.O_RDONLY | constants.O_NOFOLLOW | constants.O_NONBLOCK);
  try {
    const info = fstatSync(fd);
    if (!info.isFile() || info.nlink !== 1 || info.uid !== process.getuid() || info.mode & 0o077 || info.size > contextLimit) throw Error('Invalid native binding ownership');
    const value = JSON.parse(readFileSync(fd, 'utf8'));
    if (value.version !== 1 || !/^[a-f0-9-]{36}$/.test(value.generation) || !/^[a-f0-9-]{36}$/.test(value.token) ||
        !Number.isInteger(value.port) || value.port < 1 || value.port > 65535 ||
        typeof value.primary?.sessionId !== 'string' || !value.primary.sessionId || value.primary.sessionId.length > 256 ||
        !Number.isSafeInteger(value.primary.pid) || value.primary.pid < 1 ||
        typeof value.primary.cwd !== 'string' || realpathSync(value.primary.cwd) !== value.primary.cwd ||
        (value.primary.sessionFile !== null && typeof value.primary.sessionFile !== 'string')) throw Error('Invalid native binding identity');
    return value;
  } finally { closeSync(fd); }
}

/** Serve bounded read-only local identity queries, never progress or execution commands */
export async function serveLocal(answer) {
  const sockets = new Set();
  const server = net.createServer(socket => {
    sockets.add(socket); let input = '', answered = false;
    const timer = setTimeout(() => socket.destroy(), 400);
    socket.setEncoding('utf8');
    socket.on('error', () => {});
    socket.on('close', () => { clearTimeout(timer); sockets.delete(socket); });
    socket.on('data', chunk => {
      input += chunk;
      if (input.length > 512) return socket.destroy();
      if (answered || !input.includes('\n')) return;
      answered = true;
      void (async () => {
        try {
          const line = input.slice(0, input.indexOf('\n'));
          const query = line.startsWith('{') ? JSON.parse(line) : line;
          const value = await answer(query);
          const output = JSON.stringify(value) + '\n';
          if (Buffer.byteLength(output) > contextLimit) throw Error('Oversized context');
          if (!socket.destroyed) socket.end(output);
        } catch { socket.destroy(); }
      })();
    });
  });
  server.maxConnections = 4;
  await new Promise((done, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', done); });
  server.on('error', () => {});
  return { port: server.address().port, close() { for (const socket of sockets) socket.destroy(); server.close(); } };
}

/** Query one explicit loopback endpoint with an elapsed deadline and response cap */
export function queryLocal(endpoint, query) {
  if (!Number.isInteger(endpoint?.port) || endpoint.port < 1 || endpoint.port > 65535) return Promise.reject(Error('Invalid local endpoint'));
  return new Promise((accept, reject) => {
    const socket = net.createConnection({ host: '127.0.0.1', port: endpoint.port });
    let buffer = '', settled = false;
    const finish = (error, value) => {
      if (settled) return; settled = true; clearTimeout(timer); socket.destroy();
      error ? reject(error) : accept(value);
    };
    const timer = setTimeout(() => finish(Error('Local context deadline')), 350);
    socket.setEncoding('utf8');
    socket.on('connect', () => socket.write((typeof query === 'string' ? query : JSON.stringify(query)) + '\n'));
    socket.on('data', chunk => {
      buffer += chunk;
      if (Buffer.byteLength(buffer) > contextLimit) return finish(Error('Oversized local context'));
      if (buffer.includes('\n')) {
        try { finish(null, JSON.parse(buffer.slice(0, buffer.indexOf('\n')))); } catch (error) { finish(error); }
      }
    });
    socket.on('error', error => finish(error));
    socket.on('end', () => finish(Error('Local context unavailable')));
    socket.on('close', () => finish(Error('Local context closed')));
  });
}

/** Bind one companion generation to actual native session lifecycle, not transcript contents */
export class NativeWorkspaceContext {
  constructor(primary, current = () => primary) {
    this.primary = { ...primary, cwd: realpathSync(primary.cwd) };
    this.current = current; this.generation = randomUUID(); this.token = randomUUID(); this.closed = false;
    this.file = primary.sessionFile ? this.fileIdentity(true) : null;
  }
  fileIdentity(pending = false) {
    try { return sessionFileIdentity(this.primary.sessionFile); }
    catch (error) { if (pending && error.code === 'ENOENT') return null; throw error; }
  }
  snapshot() {
    if (this.closed) throw Error('Native workspace lifetime ended');
    const current = this.current();
    if (current.sessionId !== this.primary.sessionId || (current.sessionFile || null) !== this.primary.sessionFile || realpathSync(current.cwd) !== this.primary.cwd) {
      this.close(); throw Error('Actual native context changed');
    }
    const file = this.primary.sessionFile ? this.fileIdentity(!this.file) : null;
    if (this.file && !same(file, this.file)) { this.close(); throw Error('Actual native session file replaced'); }
    this.file = file;
    return { version: 1, generation: this.generation, primary: this.primary, file, at: Date.now() };
  }
  async start() {
    const server = await serveLocal(query => {
      if (query?.token !== this.token) throw Error('Invalid context query');
      const context = this.snapshot();
      if (query.operation === 'context') return context;
      // Explicit interactive declarations live only in this native open generation's memory.
      if (query.operation === 'logs' && this.logs) return { ...context, ...this.logs };
      throw Error('Invalid context query');
    });
    this.server = server;
    if (this.closed) { server.close(); throw Error('Native context closed during startup'); }
    return { port: server.port, token: this.token, ...this.snapshot() };
  }
  publish(file, binding) {
    const fd = openSync(file, constants.O_WRONLY | constants.O_CREAT | constants.O_EXCL | constants.O_NOFOLLOW, 0o600);
    try {
      writeFileSync(fd, JSON.stringify(binding) + '\n');
      const info = fstatSync(fd); this.bindingFile = { path: file, dev: info.dev, ino: info.ino };
    } finally { closeSync(fd); }
  }
  close() {
    this.closed = true; this.server?.close();
    // Only a binding created by this exact in-memory generation is cleanup authority.
    if (this.bindingFile) {
      try {
        const info = lstatSync(this.bindingFile.path);
        if (info.isFile() && info.dev === this.bindingFile.dev && info.ino === this.bindingFile.ino) unlinkSync(this.bindingFile.path);
      } catch { /* A replaced/missing binding is never removed by guessed state. */ }
      this.bindingFile = null;
    }
  }
}

/** Validate a fresh native lifetime response against the explicitly supplied generation */
export function validateContext(binding, value) {
  if (value?.version !== 1 || value.generation !== binding.generation || !same(value.primary, binding.primary) ||
      !Number.isSafeInteger(value.at) || Math.abs(Date.now() - value.at) > 1500 ||
      (binding.file && !same(binding.file, value.file)) ||
      (value.file && !same(sessionFileIdentity(binding.primary.sessionFile), value.file))) throw Error('Native context stale/replaced/mismatched');
  return value;
}
