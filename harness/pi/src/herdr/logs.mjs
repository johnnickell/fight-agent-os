import { DiscoveredLogs } from './discovered-logs.mjs';
import { WorktreeLogs, testBindings } from '../workspace/worktree-logs.mjs';
import { RuntimeLogs, runtimeSelection, safeLog } from '../workspace/runtime-logs.mjs';
import { readSelection } from './activity.mjs';
import { queryLocal, validateContext } from './context.mjs';

/** Obtain this generation's discovery capability and live child endpoint, never log content */
export async function readLogConfiguration(binding) {
  const value = await queryLocal(binding, { token: binding.token, operation: 'logs' });
  validateContext(binding, value);
  if ((!Array.isArray(value.tests) && value.discovery !== true) || !Number.isInteger(value.companion?.port) || value.companion.port < 1 || value.companion.port > 65535 ||
      !/^[a-f0-9-]{36}$/.test(value.companion.token)) throw Error('Invalid log companion configuration');
  if (value.discovery === true) return { discovery: true, companion: { port: value.companion.port, token: value.companion.token } };
  return { tests: testBindings(value.tests), shared: runtimeSelection(value.shared), companion: { port: value.companion.port, token: value.companion.token } };
}

/** Follow verified selection without exporting logs to Pi, children or a model-facing API */
export class HerdrLogs {
  constructor(binding, configuration, changed = () => {}, { selection = readSelection, reader, sources } = {}) {
    this.binding = binding; this.companion = configuration.companion; this.changed = changed; this.selection = selection;
    this.discovery = configuration.discovery === true;
    this.logs = this.discovery ? new DiscoveredLogs(changed, { reader, sources }) : new WorktreeLogs(binding.primary.cwd, configuration.tests, configuration.shared, changed, { reader: reader ?? ((source, changed) => new RuntimeLogs(source, changed, { persist: false, pinGeneration: true })) });
    this.logs.hold(); this.notice = 'Waiting for verified child/idle-primary context'; this.details = false;
    this.startupDeadline = Date.now() + 10000;
  }
  hold(notice) {
    clearTimeout(this.expiry); this.key = null; this.metadata = null; this.notice = notice;
    this.logs.hold(); this.changed();
  }
  tick() {
    if (this.closed || this.invalidated || this.pending) return this.pending;
    this.pending = (async () => {
      try {
        const value = await this.selection(this.binding, this.companion);
        if (this.closed || this.invalidated) return;
        // readSelection validates both actual native lifetime and child metadata. Pin the first
        // persisted native file witness so a pending file can promote once, not change silently.
        this.verified = true;
        if (value.file) this.binding.file = value.file;
        if (!value.worktree || !['idle', 'selected'].includes(value.state)) {
          this.hold(`UNAVAILABLE — child context ${value.state}; no primary/shared fallback`); return;
        }
        clearTimeout(this.expiry);
        this.expiry = setTimeout(() => {
          this.invalidated = true;
          this.hold('UNAVAILABLE — selection expired; reopen from native Pi');
        }, Math.max(0, 1500 - (Date.now() - value.at)));
        const key = JSON.stringify([value.generation, value.worktree, value.child?.generation, value.child?.id]);
        this.metadata = value;
        this.notice = value.state === 'idle' ? (this.discovery ? 'IDLE — matching this checkout’s local runtime' : 'IDLE — following explicitly bound primary test source') : `${value.child.pinned ? 'PINNED' : 'FOLLOW'} — verified selected child worktree`;
        if (key !== this.key) {
          // Revoke old callbacks and clear live/frozen output before any asynchronous rebind.
          this.logs.hold(); this.key = key;
          void this.logs.rebind(value.worktree, { force: true }).catch(() => {
            if (!this.closed) this.hold('UNAVAILABLE — source rebind failed; no fallback');
          });
        } else if (this.discovery) {
          // Discovery is serialized separately; it must never delay selection expiry/revocation.
          void this.logs.refresh().catch(() => {
            if (!this.closed) this.hold('UNAVAILABLE — runtime discovery failed; no fallback');
          });
        }
        this.changed();
      } catch {
        if (!this.closed) {
          // Child custody discovery can still be settling when its owned process first
          // becomes healthy. Initial metadata polling grants no source and is finite.
          if (!this.verified && Date.now() < this.startupDeadline) {
            this.hold('UNAVAILABLE — waiting for initial child selection (10-second startup bound)'); return;
          }
          this.invalidated = true;
          this.hold('UNAVAILABLE — native/child lifetime lost or malformed; /fight-workspace open recovers a fresh generation');
        }
      }
    })().finally(() => { this.pending = null; });
    return this.pending;
  }
  async action(type, options) {
    if (this.closed) return;
    if (type === 'info') { this.details = !this.details; this.changed(); return; }
    if (!this.metadata || this.invalidated || !this.discovery && this.actionPending) return;
    if (!this.discovery) this.actionPending = true;
    try { await this.logs.action(type, options); }
    finally { if (!this.discovery) this.actionPending = false; this.changed(); }
  }
  view() {
    const value = this.metadata;
    if (this.discovery) {
      return ['RUNTIME LOGS — human only', this.notice,
        value ? `${value.state === 'idle' ? 'PRIMARY' : value.child.pinned ? 'PINNED CHILD' : 'SELECTED CHILD'} ${value.worktree}` : 'Waiting for verified checkout context',
        this.logs.current?.summary() ?? 'Logs unavailable — workspace stays open\ns choose source · r retry',
        'Exact checkout only · no shared fallback',
        ...(this.details ? [`Native session ${this.binding.primary.sessionId}`, this.logs.view()] : [this.logs.body()])
      ].join('\n').split('\n').map(safeLog).join('\n');
    }
    const header = ['RUNTIME LOGS — human only', this.notice,
      value ? `${value.state === 'idle' ? 'PRIMARY' : value.child.pinned ? 'PINNED CHILD' : 'SELECTED CHILD'} ${value.worktree}` : 'No verified source context',
      this.logs.current?.summary() ?? `Reader unavailable\n${this.logs.unavailable()}`,
      this.logs.mode === 'shared' ? 'EXPLICIT SHARED — not worktree-isolated' : 'FOLLOW WORKTREE TEST — no shared fallback'];
    const identity = [`Conversation checkout: ${this.binding.primary.cwd}`,
      `Native session: ${this.binding.primary.sessionId} · generation ${this.binding.generation}`,
      `Child: ${value?.child ? `${value.child.id} · generation ${value.child.generation} · ${value.child.lifecycle}` : 'none / unavailable'}`];
    const body = this.details ? [...identity, this.logs.view()] : [this.logs.body()];
    return [...header, ...body, 'p pause/drain · c close/reopen · r reconnect · i details · q exit'].join('\n').split('\n').map(safeLog).join('\n');
  }
  /** Expose bounded display-process ownership facts, never log/source content */
  readerPids() {
    return [...new Set([...this.logs.readers, ...(this.logs.shared ? [this.logs.shared] : [])]
      .flatMap(reader => [...reader.processes].map(child => child.pid))
      .filter(pid => Number.isSafeInteger(pid) && pid > 0))];
  }
  metadataPids() { return [...(this.logs.sources?.processes ?? [])].map(child => child.pid).filter(pid => Number.isSafeInteger(pid) && pid > 0); }
  async close() {
    this.closed = true; clearTimeout(this.expiry); this.logs.hold(); await this.logs.close();
  }
}
