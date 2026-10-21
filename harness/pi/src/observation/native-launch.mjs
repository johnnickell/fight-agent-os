import { fork } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { custodianScript, candidates, privateDirectory, directory, sessionFileIdentity, limits, pruneCandidates, processIdentity } from './custody.mjs';
import { childEnvironment } from '../workspace/rpc.mjs';

// Session identity comes only from the supported ExtensionContext at the call boundary.
export class NativeLauncher {
  constructor(context, { harnessPackage } = {}) {
    this.harnessPackage = harnessPackage;
    this.primary = context.cwd; this.parent = context.sessionManager.getSessionId();
    this.parentFile = sessionFileIdentity(context.sessionManager.getSessionFile());
    this.requests = new Map(); this.closed = false;
  }
  async ready() {
    if (this.readyPromise) return this.readyPromise;
    const root = directory(this.primary, this.parent); privateDirectory(root); pruneCandidates(this.primary, this.parent);
    if (candidates(this.primary, this.parent).length >= limits.brokers) throw new Error('Custody capacity reached; allow completed custodians to expire');
    const generation = randomUUID();
    const child = fork(custodianScript, [generation], { cwd: this.primary, detached: true, env: childEnvironment(process.env), stdio: ['ignore', 'ignore', 'ignore', 'ipc'] });
    this.child = child;
    this.readyPromise = new Promise((resolve, reject) => {
      const timer = setTimeout(() => { if (child.connected) child.disconnect(); reject(new Error('Native custody bootstrap timed out')); }, 3000);
      child.on('message', message => {
        if (message.type === 'bootstrap-error') { clearTimeout(timer); reject(new Error(message.reason)); return; }
        if (message.type === 'ready') { clearTimeout(timer); resolve(message.descriptor); return; }
        const request = this.requests.get(message.id);
        if (message.type === 'output') request?.onData(Buffer.from(message.text));
        else if (message.type === 'exit' && request) {
          this.requests.delete(message.id); request.cleanup();
          message.error ? request.reject(new Error(message.error)) : request.resolve({ exitCode: message.code });
        }
      });
      child.on('error', () => { clearTimeout(timer); reject(new Error('Custodian unavailable')); });
      child.on('exit', () => {
        clearTimeout(timer); reject(new Error('Custodian exited during bootstrap'));
        for (const request of this.requests.values()) { request.cleanup(); request.reject(new Error('Custody lost; child status unknown')); }
        this.requests.clear();
      });
      child.send({ type: 'initialize', primary: this.primary, parent: this.parent, parentFile: this.parentFile, generation, harnessPackage: this.harnessPackage,
        native: { pid: process.pid, entry: process.argv[1], process: processIdentity(process.pid), mode: 'tui' } });
    });
    return this.readyPromise;
  }
  async execute(config, { onData, signal, timeout }) {
    if (this.closed) throw new Error('Native session context invalidated');
    await this.ready();
    if (this.closed || signal?.aborted) throw new Error('Native launch cancelled');
    if (this.requests.size >= limits.children) throw new Error('Native launch capacity reached');
    const id = randomUUID();
    return new Promise((resolve, reject) => {
      let reason;
      const cancel = value => { reason = value; if (this.child.connected) this.child.send({ type: 'cancel', id }); };
      const abort = () => cancel('aborted');
      const timer = timeout ? setTimeout(() => cancel(`timeout:${timeout}`), timeout * 1000) : undefined;
      const cleanup = () => { clearTimeout(timer); signal?.removeEventListener('abort', abort); };
      this.requests.set(id, { resolve: value => reason ? reject(new Error(reason)) : resolve(value), reject, onData, cleanup });
      signal?.addEventListener('abort', abort, { once: true });
      this.child.send({ type: 'launch', id, config }, error => {
        if (error) { this.requests.delete(id); cleanup(); reject(new Error('Native launch channel lost')); }
      });
    });
  }
  close() {
    if (this.closed) return; this.closed = true;
    // Dropping the native presentation connection is not child cancellation.
    if (this.child?.connected) this.child.disconnect();
    this.child?.unref();
    for (const request of this.requests.values()) { request.cleanup(); request.reject(new Error('Native context closed; monitor continues independently')); }
    this.requests.clear();
  }
}
export function nativeExecutorAllowed(source, owner, active = true) {
  return active && (source?.path === owner || source?.source === 'builtin' && source.path === 'builtin:bash');
}
export function stageRequest(command) {
  if (!command.startsWith('fight-stage ')) return null;
  if (command.length > 8192) throw new Error('Fixed stage request too large');
  return JSON.parse(command.slice('fight-stage '.length));
}
