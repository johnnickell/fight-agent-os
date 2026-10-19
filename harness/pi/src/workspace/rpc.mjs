import { spawn } from 'node:child_process';
import { EventEmitter } from 'node:events';

// No credential values, application secrets, role launcher flags or UI state enter the child environment.
// Pi resolves its own configured providers through HOME. This is not OS isolation.
export function childEnvironment(env) {
  return Object.fromEntries(['HOME', 'PATH', 'TMPDIR', 'LANG', 'LC_ALL', 'LC_CTYPE', 'TERM', 'COLORTERM']
    .filter(key => typeof env[key] === 'string').map(key => [key, env[key]]));
}
export class JsonLines {
  buffer = '';
  constructor(consume, maxBytes = 8 * 1024 * 1024) { this.consume = consume; this.maxBytes = maxBytes; }
  push(text) {
    this.buffer += text;
    let newline;
    while ((newline = this.buffer.indexOf('\n')) >= 0) {
      const line = this.buffer.slice(0, newline); this.buffer = this.buffer.slice(newline + 1);
      if (Buffer.byteLength(line) > this.maxBytes) throw new Error('Pi record exceeds display transport limit');
      if (line.trim()) this.consume(JSON.parse(line));
    }
    if (Buffer.byteLength(this.buffer) > this.maxBytes) throw new Error('Pi record exceeds display transport limit');
  }
}
export class PiRpc extends EventEmitter {
  pending = new Map(); sequence = 0; closing = false; queuedWrites = 0; writeQueue = Promise.resolve();
  constructor({ cwd, args = [], executable = 'pi', env = process.env, spawnProcess = spawn }) {
    super();
    this.child = spawnProcess(executable, ['--offline', '--mode', 'rpc', ...args], { cwd, env: childEnvironment(env), stdio: ['pipe', 'pipe', 'pipe'] });
    const records = new JsonLines(record => {
      if (record.type === 'response' && this.pending.has(record.id)) {
        const request = this.pending.get(record.id); this.pending.delete(record.id); clearTimeout(request.timer);
        if (record.success) request.resolve(record.data); else request.reject(new Error(record.error ?? 'Pi rejected command'));
      } else this.emit('record', record);
    });
    // stderr isn't JSONL: parse only complete lines, with bounded retention and no raw logging.
    let stderr = '';
    this.child.stdout.setEncoding('utf8'); this.child.stderr.setEncoding('utf8');
    this.child.stdout.on('data', text => { try { records.push(text); } catch (error) { this.emit('failure', error); this.close(); } });
    this.child.stderr.on('data', text => {
      stderr += text;
      let newline;
      while ((newline = stderr.indexOf('\n')) >= 0) {
        const line = stderr.slice(0, newline); stderr = stderr.slice(newline + 1);
        if (line.startsWith('Fight screening status: ')) {
          try { this.emit('policy', JSON.parse(line.slice('Fight screening status: '.length))); }
          catch { this.emit('failure', new Error('Malformed Harness status')); }
        } else if (/Failed to load extension|extension error/i.test(line)) this.emit('failure', new Error('Pi extension failed to load'));
      }
      if (stderr.length > 65536) { stderr = ''; this.emit('failure', new Error('Pi diagnostic line exceeded limit')); }
    });
    this.child.stdin.on('error', error => { if (!this.closing) this.emit('failure', error); });
    this.child.on('error', error => this.emit('failure', error));
    this.child.on('close', code => {
      this.exited = true;
      for (const request of this.pending.values()) { clearTimeout(request.timer); request.reject(new Error('Pi exited')); }
      this.pending.clear(); this.emit('exit', code);
    });
  }
  send(record) {
    const line = JSON.stringify(record) + '\n';
    if (Buffer.byteLength(line) > 131072) return Promise.reject(new Error('Input exceeds transport limit'));
    if (this.closing || this.child.stdin.destroyed) return Promise.reject(new Error('Pi is closing'));
    if (this.queuedWrites >= 64) return Promise.reject(new Error('Pi write queue is full'));
    this.queuedWrites++;
    // Bound outstanding requests and honor writable backpressure before sending another record.
    this.writeQueue = this.writeQueue.then(() => new Promise((resolve, reject) => {
      this.child.stdin.write(line, error => error ? reject(error) : resolve());
    })).finally(() => { this.queuedWrites--; });
    return this.writeQueue;
  }
  request(type, data = {}, timeout = 30000) {
    if (this.pending.size >= 32) return Promise.reject(new Error('Too many pending Pi requests'));
    const id = `workspace-${++this.sequence}`;
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => { this.pending.delete(id); reject(new Error(`Pi ${type} deadline exceeded`)); }, timeout);
      this.pending.set(id, { resolve, reject, timer });
      this.send({ ...data, id, type }).catch(error => { clearTimeout(timer); this.pending.delete(id); reject(error); });
    });
  }
  async close() {
    if (this.closePromise) return this.closePromise;
    this.closing = true;
    this.closePromise = new Promise(resolve => {
      if (this.exited) { resolve(); return; }
      const terminate = setTimeout(() => this.child.kill('SIGTERM'), 2000);
      const kill = setTimeout(() => this.child.kill('SIGKILL'), 4000);
      this.child.once('close', () => { clearTimeout(terminate); clearTimeout(kill); resolve(); });
      this.child.stdin.end();
    });
    return this.closePromise;
  }
}
export function messageText(message) {
  if (message.role === 'system' || message.display === false) return '';
  const content = typeof message.content === 'string' ? message.content : (message.content ?? []).map(block => {
    if (block.type === 'text') return block.text;
    if (block.type === 'thinking') return '[thinking]';
    if (block.type === 'toolCall') return `[tool: ${block.name}] ${JSON.stringify(block.arguments)}`;
    if (block.type === 'image') return '[image retained by Pi]';
    return '[unsupported content]';
  }).join('\n');
  const identity = message.role === 'assistant' && message.model ?
    ` · configured ${message.provider ?? '?'}/${message.model} · response model ${message.responseModel ?? 'not reported'}` : '';
  return `${message.role}${identity}${message.toolName ? `: ${message.toolName}` : ''}\n${content || message.summary || message.output || ''}${message.errorMessage ? `\nError: ${message.errorMessage}` : ''}`;
}
