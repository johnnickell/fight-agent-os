import { execFile, fork } from 'node:child_process';
import { promisify } from 'node:util';
import { randomUUID } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { basename, dirname, join } from 'node:path';
import { custodianScript, sessionFileIdentity, sameSessionFile } from './custody.mjs';
import { childEnvironment } from '../workspace/rpc.mjs';

// Observation resources only. There is deliberately no execute, launch or cancel method.
export class NativeObservation {
  constructor(context, { started = Number(process.hrtime.bigint() / 1000000n), wallUs = Date.now() * 1000 } = {}) {
    this.started = started; this.wallUs = wallUs;
    this.primary = context.cwd; this.parent = context.sessionManager.getSessionId();
    this.sessionFile = context.sessionManager.getSessionFile(); this.closed = false;
    this.parentFile = null;
    try { this.parentFile = sessionFileIdentity(this.sessionFile); }
    catch (error) {
      if (error.code !== 'ENOENT') throw error;
      this.pendingParentFile = join(realpathSync(dirname(this.sessionFile)), basename(this.sessionFile));
    }
  }
  syncSessionFile(context) {
    if (this.closed || context.sessionManager.getSessionId() !== this.parent || context.sessionManager.getSessionFile() !== this.sessionFile) throw new Error('Native session context invalidated');
    if (!this.sessionFile) return;
    let witness;
    try { witness = sessionFileIdentity(this.sessionFile); }
    catch (error) { if (error.code === 'ENOENT' && this.pendingParentFile) return; throw error; }
    if (this.parentFile) {
      if (!sameSessionFile(witness, this.parentFile)) throw new Error('Native session file replaced');
      return;
    }
    if (witness.path !== this.pendingParentFile) throw new Error('Native session file replaced');
    this.parentFile = witness; this.pendingParentFile = undefined;
    if (this.child?.connected) this.child.send({ type: 'bind-session', parentFile: witness }, () => { this.flushSubagents(); });
  }
  async start() {
    if (this.closed || this.child || !this.sessionFile) return;
    const entry = realpathSync(process.argv[1]);
    if (!/@earendil-works\/pi-coding-agent\/dist\/(?:bundle\/)?cli\.js$/.test(entry)) return;
    const { stdout } = await promisify(execFile)('ps', ['-p', String(process.pid), '-o', 'lstart=', '-o', 'command='], { timeout: 1000, maxBuffer: 8192 });
    if (this.closed) return;
    const generation = randomUUID();
    const child = fork(custodianScript, [generation], { cwd: this.primary, detached: true,
      env: childEnvironment(process.env), stdio: ['ignore', 'ignore', 'ignore', 'ipc'] });
    this.child = child;
    child.on('message', message => { if (message?.type === 'ready') { this.ready = true; this.flushSubagents(); } });
    child.on('error', () => this.close());
    // The daemon is an observation reader, never an executor or child custodian.
    child.send({ type: 'initialize', primary: this.primary, parent: this.parent, parentFile: this.parentFile,
      pendingParentFile: this.pendingParentFile, generation,
      native: { pid: process.pid, entry, process: stdout.trim(), mode: 'tui', started: this.started, wallUs: this.wallUs } }, () => {});
  }
  observeSubagent(message) {
    if (this.closed) return;
    this.queuedSubagents ??= new Map();
    if (!this.queuedSubagents.has(message.id) && this.queuedSubagents.size >= 8) return;
    this.queuedSubagents.set(message.id, message);
    this.flushSubagents();
  }
  flushSubagents() {
    if (this.closed || !this.ready || !this.parentFile || !this.child?.connected) return;
    this.pendingSubagents ??= new Set();
    for (const [id, message] of this.queuedSubagents ?? []) {
      if (this.pendingSubagents.size >= 8) break;
      if (this.pendingSubagents.has(id)) continue;
      this.queuedSubagents.delete(id); this.pendingSubagents.add(id);
      try { this.child.send(message, () => { this.pendingSubagents.delete(id); this.flushSubagents(); }); }
      catch { this.pendingSubagents.delete(id); }
    }
  }
  close() {
    if (this.closed) return;
    this.closed = true;
    // At most eight coalesced frames. Preserve IPC ordering even if an earlier send's callback
    // has not fired; disconnect drains Node's queued messages, never waits on agent execution.
    if (this.ready && this.parentFile && this.child?.connected) {
      for (const message of this.queuedSubagents?.values() ?? []) {
        try { this.child.send(message, () => {}); } catch { /* Transport unavailable, not an SDK failure. */ }
      }
    }
    this.queuedSubagents?.clear();
    if (this.child?.connected) this.child.disconnect();
    this.child?.unref();
  }
}
