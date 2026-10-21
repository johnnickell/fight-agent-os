import { fork } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { basename, dirname, join } from 'node:path';
import { custodianScript, candidates, privateDirectory, directory, sessionFileIdentity, limits, pruneCandidates, processIdentity } from './custody.mjs';
import { childEnvironment } from '../workspace/rpc.mjs';

// Session identity comes only from the supported ExtensionContext at the call boundary.
export class NativeLauncher {
  constructor(context, { harnessPackage } = {}) {
    this.harnessPackage = harnessPackage;
    this.primary = context.cwd; this.parent = context.sessionManager.getSessionId();
    this.sessionFile = context.sessionManager.getSessionFile();
    // Pi advertises a persistent path before the first real user message creates it.
    // Launch now, but publish no discoverable registration until native context binds its inode.
    this.parentFile = null; this.pendingParentFile = undefined;
    try { this.parentFile = sessionFileIdentity(this.sessionFile); }
    catch (error) {
      if (error.code !== 'ENOENT') throw error;
      this.pendingParentFile = join(realpathSync(dirname(this.sessionFile)), basename(this.sessionFile));
    }
    this.requests = new Map(); this.closed = false;
  }
  syncSessionFile(context) {
    if (this.closed || context.sessionManager.getSessionId() !== this.parent || context.sessionManager.getSessionFile() !== this.sessionFile) throw new Error('Native session context invalidated');
    if (!this.sessionFile) return;
    let witness;
    try { witness = sessionFileIdentity(this.sessionFile); }
    catch (error) { if (error.code === 'ENOENT' && this.pendingParentFile) return; throw new Error('Native session file unavailable'); }
    if (this.parentFile) {
      if (JSON.stringify(witness) !== JSON.stringify(this.parentFile)) throw new Error('Native session file replaced');
      return;
    }
    if (witness.path !== this.pendingParentFile) throw new Error('Native session file replaced');
    this.parentFile = witness; this.pendingParentFile = undefined;
    if (this.child?.connected) this.child.send({ type: 'bind-session', parentFile: witness });
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
      child.send({ type: 'initialize', primary: this.primary, parent: this.parent, parentFile: this.parentFile, pendingParentFile: this.pendingParentFile, generation, harnessPackage: this.harnessPackage,
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
// Never echo arbitrary exception messages, JSON input, paths or terminal controls to a notification.
export function nativeLaunchFailure(error) {
  if (error instanceof SyntaxError) return 'Native launch refused: supply valid JSON after /fight-stage';
  if (error?.code === 'ENOENT') return 'Native launch refused: a required session, worktree or attempt path is missing; verify the owned request';
  if (['EACCES', 'EPERM'].includes(error?.code)) return 'Native launch refused: session, runtime or attempt access denied; verify local permissions';
  const safe = new Set([
    'fight-stage requires native Pi and the existing authorized launch route',
    'Native observation requires supported effective-settings access',
    'Native fixed launch unavailable with a configured shell override; existing executor remains unchanged',
    'Native session context invalidated', 'Native session file unavailable', 'Native session file replaced',
    'Native custody bootstrap timed out', 'Custodian unavailable', 'Custodian exited during bootstrap',
    'Custody capacity reached; allow completed custodians to expire', 'Native launch capacity reached',
    'Native launch cancelled', 'Native launch channel lost', 'Custody lost; child status unknown',
    'Fixed stage request too large', 'Fixed monitor launch refused; verify fresh attempt, identity, worktree and capacity',
  ]);
  return safe.has(error?.message) ? `Native launch refused: ${error.message}` : 'Native launch failed: verify the native session, fixed request and owned attempt; no successful dispatch is implied';
}
export function stageRequest(command) {
  if (!command.startsWith('fight-stage ')) return null;
  if (command.length > 8192) throw new Error('Fixed stage request too large');
  return JSON.parse(command.slice('fight-stage '.length));
}
