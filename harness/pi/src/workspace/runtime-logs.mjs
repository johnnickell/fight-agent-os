// Human-facing bounded Docker reader. Never registered with Pi or exported over RPC.
import { spawn } from 'node:child_process';
import { StringDecoder } from 'node:string_decoder';

const cap = n => Math.min(Number.MAX_SAFE_INTEGER, n);
export const logLimits = Object.freeze({ tail: 100, sources: 16, lines: 300, units: 32768, record: 4096, metadata: 65536 });
export const safeLog = text => text.toWellFormed().replace(/[\x00-\x1f\x7f-\x9f\u061c\u200b\u200e\u200f\u202a-\u202e\u2060-\u206f\ufeff]/g,
  char => `\\u${char.charCodeAt(0).toString(16).padStart(4, '0')}`);
const environment = () => Object.fromEntries(['HOME', 'PATH', 'TMPDIR', 'LANG', 'LC_ALL'].filter(key => process.env[key]).map(key => [key, process.env[key]]));

/** Drain one verified stack into a single bounded prefixed history */
export class RuntimeLogs {
  constructor(source, changed = () => {}, { spawnProcess = spawn, verifySource } = {}) {
    this.source = source; this.changed = changed; this.spawn = spawnProcess; this.verifySource = verifySource;
    this.processes = new Set(); this.generation = 0; this.open = false; this.paused = false;
    this.history = []; this.units = 0; this.omitted = 0; this.clipped = 0; this.gaps = 0; this.frozen = null;
    this.state = 'unselected'; this.notice = 'No stack selected';
  }
  clear() { this.history = []; this.units = 0; this.frozen = this.paused ? [] : null; }
  async stop() {
    this.generation++;
    await Promise.all([...this.processes].map(child => new Promise(done => {
      const kill = setTimeout(() => child.kill('SIGKILL'), 1000), deadline = setTimeout(done, 2000);
      child.once('close', () => { clearTimeout(kill); clearTimeout(deadline); done(); }); child.kill('SIGTERM');
    })));
  }
  async subscribe() {
    if (this.disposed || !this.open || this.processes.size) return;
    const generation = ++this.generation, current = () => !this.disposed && this.open && generation === this.generation;
    this.state = 'checking'; this.notice = 'Verifying complete stack generation'; this.changed();
    try {
      const members = this.source.members;
      if (!members?.length || members.length > logLimits.sources || !this.verifySource ||
          !/^unix:\/\/\/[^\x00-\x1f\x7f]{1,990}$/.test(this.source.endpoint) ||
          members.some(member => !/^[a-f0-9]{64}$/.test(member.container) || !/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/.test(member.service)) ||
          new Set(members.map(member => member.container)).size !== members.length) throw Error('Verified bounded stack capability required');
      await this.verifySource(this.source);
      if (!current()) return;
      this.state = 'following'; this.notice = 'Following verified stack; finite tail, incomplete history.';
      for (const member of members) {
        const prefix = `${member.service}/${member.container.slice(0, 12)} | `;
        const child = this.spawn('docker', ['--host', this.source.endpoint, 'container', 'logs', '--tail', String(logLimits.tail), '--timestamps', '--follow', member.container], {
          cwd: '/', env: environment(), stdio: ['ignore', 'pipe', 'pipe'], shell: false,
        });
        this.processes.add(child); child.once('close', () => this.processes.delete(child));
        const accept = stream => {
          const decoder = new StringDecoder('utf8'); let partial = '', overflow = false;
          const flush = (complete = false) => {
            if (complete || partial || overflow) {
              if (overflow) this.clipped = cap(this.clipped + 1);
              this.append(prefix + partial + (overflow ? ' [record truncated]' : ''));
            }
            partial = ''; overflow = false;
          };
          stream.on('data', chunk => {
            if (!current()) return;
            for (const char of decoder.write(chunk)) {
              if (char === '\n') flush(true);
              else if (partial.length + char.length <= logLimits.record) partial += char;
              else overflow = true;
            }
            this.changed();
          });
          stream.on('end', () => { if (current()) { partial += decoder.end(); flush(); this.changed(); } });
        };
        accept(child.stdout); accept(child.stderr);
        const lost = () => {
          if (!current()) return;
          this.clear(); this.state = 'disconnected'; this.gaps = cap(this.gaps + 1);
          this.notice = 'A stack stream ended or was denied; all readers revoked. Fresh discovery will reverify; unknown gaps/duplicate tails.';
          void this.stop(); this.changed();
        };
        child.once('error', lost); child.once('close', lost);
      }
      this.changed();
    } catch (error) {
      if (current()) {
        this.clear(); this.state = 'unavailable'; this.notice = error.message;
        void this.stop(); this.changed();
      }
    }
  }
  append(text) {
    text = safeLog(text);
    if (text.length > 8192) { text = text.slice(0, 8192) + ' [escaped record truncated]'; this.clipped = cap(this.clipped + 1); }
    this.history.push(text); this.units += text.length;
    while (this.history.length > logLimits.lines || this.units > logLimits.units) { this.units -= this.history.shift().length; this.omitted = cap(this.omitted + 1); }
  }
  async action(type) {
    if (type === 'pause') { this.paused = !this.paused; this.frozen = this.paused ? [...this.history] : null; this.changed(); }
  }
  summary() {
    return `${this.source.project} · ${this.source.members.length} containers\n${this.state}${this.paused ? ' · PAUSED' : ''} · omitted ${this.omitted} · clipped ${this.clipped} · gaps ${this.gaps}`;
  }
  body() {
    const display = (this.paused ? this.frozen : this.history) ?? [];
    return [this.notice,
      ...(this.gaps ? ['[Readmission gaps unknown; up to 100 duplicate tail lines per container]'] : []),
      ...(this.source.stopped?.length ? [`Stopped services: ${this.source.stopped.join(', ')} — no live output from these services`] : []),
      ...(display.length ? display : [this.state === 'following' ? '[QUIET — verified readers following; no records in this bounded window]' : '[No currently verified output]']),
      this.paused ? '[PAUSED; draining into bounded recent window]' : '[tail 100 per container; mixed stream order is arrival order, not a total ordering]'].join('\n');
  }
  view() {
    return [`Checkout: ${this.source.main}`, `Endpoint: ${this.source.endpoint}`, `Project: ${this.source.project}`,
      ...this.source.members.map(member => `${member.service}: ${member.container}`),
      'Reconnect/filter/source changes can lose records and replay up to 100 tail lines per container.', this.body()].join('\n');
  }
  async close() { this.disposed = true; this.open = false; this.clear(); await this.stop(); }
}
