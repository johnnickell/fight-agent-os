import { createServer, createConnection } from 'node:net';
import { randomBytes, randomUUID, createHash } from 'node:crypto';

export const childApprovalEnvironment = 'FIGHT_CHILD_COMMAND_APPROVAL';
const limit = 65536;
const deadlineMs = 120000;
const digest = value => createHash('sha256').update(JSON.stringify(value)).digest('hex');
const text = (value, max) => typeof value === 'string' && value.length > 0 && value.length <= max && !value.includes('\0');
function valid(request) {
  return request && /^[a-f0-9-]{36}$/.test(request.id) && text(request.command, 16384) &&
    text(request.cwd, 4096) && request.cwd.startsWith('/') && /^TASK-\d{5}$/.test(request.task) &&
    ['engineer', 'reviewer', 'qa', 'land'].includes(request.role) && text(request.session, 128) &&
    ['risky', 'uncertain', 'unavailable'].includes(request.outcome) && /^[a-z-]{1,80}$/.test(request.reason);
}

/** Relay one-command decisions to a live Build operator, never a reusable execution grant */
export async function openChildApprovals({ confirm, validate, signal, timeoutMs = deadlineMs }) {
  const token = randomBytes(32).toString('hex'), clients = new Set();
  let closed = false;
  const server = createServer(socket => {
    if (closed || clients.size >= 16) { socket.destroy(); return; }
    clients.add(socket);
    const controller = new AbortController();
    let buffer = Buffer.alloc(0), received = false;
    const timer = setTimeout(() => socket.destroy(), timeoutMs);
    socket.on('error', () => socket.destroy());
    socket.on('close', () => { clearTimeout(timer); controller.abort(); clients.delete(socket); });
    socket.on('data', async chunk => {
      if (received || buffer.length + chunk.length > limit) { socket.destroy(); return; }
      buffer = Buffer.concat([buffer, chunk]);
      if (!buffer.includes(10)) return;
      received = true;
      try {
        if (buffer.indexOf(10) !== buffer.length - 1) throw new Error('invalid-frame');
        const { token: supplied, request } = JSON.parse(buffer.toString('utf8'));
        if (supplied !== token || !valid(request)) throw new Error('invalid-request');
        const check = () => {
          if (closed || controller.signal.aborted || signal?.aborted) throw new Error('cancelled');
          validate(request);
        };
        check();
        const approved = await confirm(request, controller.signal, check);
        check();
        socket.end(JSON.stringify({ id: request.id, digest: digest(request), approved: approved === true }) + '\n');
      } catch { socket.destroy(); }
    });
  });
  const close = () => {
    closed = true;
    for (const socket of clients) socket.destroy();
    server.close();
    signal?.removeEventListener('abort', close);
  };
  try {
    if (signal?.aborted) throw new Error('cancelled');
    await new Promise((resolve, reject) => {
      server.once('error', reject);
      server.listen(0, '127.0.0.1', resolve);
    });
    server.on('error', close);
    signal?.addEventListener('abort', close, { once: true });
    if (signal?.aborted) { close(); throw new Error('cancelled'); }
    return { environment: JSON.stringify({ port: server.address().port, token }), close };
  } catch (error) { close(); throw error; }
}

/** Request a fresh decision; absent, malformed, disconnected or stale transport always refuses */
export async function requestChildApproval(environment, decision, { signal, timeoutMs = deadlineMs } = {}) {
  let endpoint;
  try { endpoint = JSON.parse(environment); } catch { return false; }
  if (!endpoint || !Number.isInteger(endpoint.port) || endpoint.port < 1 || endpoint.port > 65535 ||
      !/^[a-f0-9]{64}$/.test(endpoint.token) || signal?.aborted) return false;
  const request = { ...decision, id: randomUUID() };
  if (!valid(request)) return false;
  return new Promise(resolve => {
    const socket = createConnection({ host: '127.0.0.1', port: endpoint.port });
    let buffer = Buffer.alloc(0), settled = false;
    const finish = approved => {
      if (settled) return;
      settled = true; clearTimeout(timer); signal?.removeEventListener('abort', abort);
      socket.destroy(); resolve(approved === true);
    };
    const abort = () => finish(false);
    const timer = setTimeout(abort, timeoutMs);
    socket.on('error', abort); socket.on('close', abort);
    socket.on('connect', () => socket.write(JSON.stringify({ token: endpoint.token, request }) + '\n'));
    socket.on('data', chunk => {
      buffer = Buffer.concat([buffer, chunk]);
      if (buffer.length > limit) { finish(false); return; }
      if (!buffer.includes(10)) return;
      try {
        const response = JSON.parse(buffer.toString('utf8'));
        finish(response.id === request.id && response.digest === digest(request) && response.approved === true);
      } catch { finish(false); }
    });
    signal?.addEventListener('abort', abort, { once: true });
    if (signal?.aborted) abort();
  });
}
