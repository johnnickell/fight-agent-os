import { DiscoveredLogs } from './discovered-logs.mjs';
import { safeLog } from '../workspace/runtime-logs.mjs';
import { readSelection } from './activity.mjs';
import { queryLocal, validateContext } from './context.mjs';

/** Obtain this generation's discovery capability and live child endpoint, never log content */
export async function readLogConfiguration(binding) {
  const value = await queryLocal(binding, { token: binding.token, operation: 'logs' });
  validateContext(binding, value);
  if (value.discovery !== true || !Number.isInteger(value.companion?.port) || value.companion.port < 1 || value.companion.port > 65535 ||
      !/^[a-f0-9-]{36}$/.test(value.companion.token)) throw Error('Invalid log companion configuration');
  return { discovery: true, companion: { port: value.companion.port, token: value.companion.token } };
}

/** Follow verified activity or an explicit main pin without exporting logs to Pi or model APIs */
export class HerdrLogs {
  constructor(binding, configuration, changed = () => {}, { selection = readSelection, reader, sources } = {}) {
    this.binding = binding; this.companion = configuration.companion; this.changed = changed; this.selection = selection;
    this.logs = new DiscoveredLogs(changed, { reader, sources }); this.mode = 'follow';
    this.logs.hold(); this.notice = 'Waiting for verified activity context'; this.details = false;
    this.startupDeadline = Date.now() + 10000;
  }
  hold(notice) {
    clearTimeout(this.expiry); this.key = null; this.metadata = null; this.notice = notice;
    this.logs.hold(); this.changed();
  }
  bind(value) {
    const main = this.mode === 'main' || value.state === 'idle';
    const worktree = main ? this.binding.primary.cwd : value.worktree;
    const key = JSON.stringify([value.generation, main ? 'main' : worktree, main ? null : value.child?.generation, main ? null : value.child?.id]);
    if (key !== this.key) {
      this.logs.hold(); this.key = key;
      void this.logs.rebind(worktree, { main }).catch(() => {
        if (!this.closed) this.hold('UNAVAILABLE — source rebind failed; no fallback');
      });
    } else {
      void this.logs.refresh().catch(() => { if (!this.closed) this.hold('UNAVAILABLE — discovery failed; no fallback'); });
    }
    this.notice = this.mode === 'main' ? 'MAIN PIN — active work cannot change this display' : value.state === 'idle' ? 'FOLLOW ACTIVE — idle; verified main stack' : 'FOLLOW ACTIVE — verified selected agent stack';
  }
  tick() {
    if (this.closed || this.invalidated || this.pending) return this.pending;
    this.pending = (async () => {
      try {
        const value = await this.selection(this.binding, this.companion);
        if (this.closed || this.invalidated) return;
        this.verified = true;
        if (value.file) this.binding.file = value.file;
        if (!value.worktree || !['idle', 'selected'].includes(value.state)) {
          this.hold(`UNAVAILABLE — activity context ${value.state}; no hidden main fallback`); return;
        }
        clearTimeout(this.expiry);
        this.expiry = setTimeout(() => {
          this.invalidated = true; this.hold('UNAVAILABLE — selection expired; reopen from native Pi');
        }, Math.max(0, 1500 - (Date.now() - value.at)));
        this.metadata = value; this.bind(value); this.changed();
      } catch {
        if (!this.closed) {
          if (!this.verified && Date.now() < this.startupDeadline) {
            this.hold('UNAVAILABLE — waiting for initial activity selection (10-second bound)'); return;
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
    if (!this.metadata || this.invalidated) return;
    if (type === 'main' || type === 'follow') {
      this.mode = type; this.bind(this.metadata); this.changed(); return;
    }
    try { await this.logs.action(type, options); } finally { this.changed(); }
  }
  view() {
    const value = this.metadata;
    return ['LOGS', this.notice,
      value ? `${this.mode === 'main' || value.state === 'idle' ? 'MAIN' : value.child?.pinned ? 'PINNED AGENT' : 'ACTIVE AGENT'} · Services: ${this.logs.filterLabel()}` : 'Waiting for verified context',
      this.logs.current?.summary() ?? 'Stack unavailable\nAutomatic bounded discovery; r retry',
      'f Follow active · m Main · s Services',
      ...(this.details ? [`Native session: ${this.binding.primary.sessionId}`, `Activity checkout: ${value?.worktree ?? 'unavailable'}`, this.logs.view(),
        'Observation only: no provisioning, shell, container controls or model ingestion.'] : [this.logs.body()])
    ].join('\n').split('\n').map(safeLog).join('\n');
  }
  /** Expose bounded display-process ownership facts only, never sources or output */
  readerPids() {
    return [...new Set(this.logs.readers.flatMap(reader => [...reader.processes].map(child => child.pid))
      .filter(pid => Number.isSafeInteger(pid) && pid > 0))];
  }
  metadataPids() { return [...(this.logs.sources.processes ?? [])].map(child => child.pid).filter(pid => Number.isSafeInteger(pid) && pid > 0); }
  async close() { this.closed = true; clearTimeout(this.expiry); this.logs.hold(); await this.logs.close(); }
}
