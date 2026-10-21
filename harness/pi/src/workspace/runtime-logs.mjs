// Human-facing host reader only. Nothing in this module is registered with Pi or sent over RPC.
import { spawn } from 'node:child_process';
import { realpathSync, mkdirSync, lstatSync, openSync, closeSync, writeFileSync, fstatSync, ftruncateSync, constants } from 'node:fs';
import { isAbsolute, join } from 'node:path';
import { StringDecoder } from 'node:string_decoder';

const name = /^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/;
const projectName = /^[a-z0-9][a-z0-9_-]{0,127}$/;
const cap = n => Math.min(Number.MAX_SAFE_INTEGER, n);
export const logLimits = Object.freeze({ tail: 100, lines: 300, units: 32768, record: 4096, metadata: 65536, lifetime: 900000 });
// Escape controls visibly, rather than interpreting ANSI, OSC, CR, bidi or clipboard actions.
export const safeLog = text => text.toWellFormed().replace(/[\x00-\x1f\x7f-\x9f\u061c\u200b\u200e\u200f\u202a-\u202e\u2060-\u206f\ufeff]/g,
  char => `\\u${char.charCodeAt(0).toString(16).padStart(4, '0')}`);
export function runtimeSelection(value) {
  if (!value) return null;
  if (!isAbsolute(value.main ?? '') || value.main.length > 1000 || /[\x00-\x1f\x7f]/.test(value.main) ||
      !name.test(value.context ?? '') || !projectName.test(value.project ?? '') || !name.test(value.service ?? '')) {
    throw new Error('Logs require explicit absolute --logs-main and validated --logs-context, --logs-project, --logs-service');
  }
  return Object.freeze({ main: realpathSync(value.main), context: value.context, project: value.project, service: value.service });
}
const listing = '{"id":{{json .ID}},"service":{{json (.Label "com.docker.compose.service")}},"directory":{{json (.Label "com.docker.compose.project.working_dir")}},"state":{{json .State}}}';
// Minimal host-only environment. Docker resolves the operator's context through HOME, not repository env/options.
function environment() {
  return Object.fromEntries(['HOME', 'PATH', 'TMPDIR', 'LANG', 'LC_ALL'].filter(key => process.env[key]).map(key => [key, process.env[key]]));
}
export class RuntimeLogs {
  constructor(selection, changed = () => {}, { spawnProcess = spawn, persist = true } = {}) {
    this.source = runtimeSelection(selection); this.changed = changed; this.spawn = spawnProcess; this.persist = persist;
    this.generation = 0; this.processes = new Set(); this.pending = new Set(); this.open = false; this.paused = false;
    this.history = []; this.units = 0; this.omitted = 0; this.clipped = 0; this.gaps = 0; this.state = 'unselected';
    this.notice = 'No runtime selected'; this.services = []; this.endpoint = null; this.timer = null; this.frozen = null;
  }
  start() { if (this.source) { this.open = true; void this.subscribe(); } }
  launch(args) {
    const child = this.spawn('docker', ['--context', this.source.context, ...args], {
      cwd: '/', env: environment(), stdio: ['ignore', 'pipe', 'pipe'], shell: false,
    });
    this.processes.add(child);
    child.once('close', () => this.processes.delete(child));
    return child;
  }
  async stop() {
    this.generation++; clearTimeout(this.timer);
    for (const cancel of [...this.pending]) cancel();
    await Promise.all([...this.processes].map(child => new Promise(resolve => {
      const timer = setTimeout(() => child.kill('SIGKILL'), 1000);
      const deadline = setTimeout(resolve, 2000);
      child.once('close', () => { clearTimeout(timer); clearTimeout(deadline); resolve(); });
      child.kill('SIGTERM');
    })));
  }
  query(args) {
    return new Promise((resolve, reject) => {
      const child = this.launch(args), decoder = new StringDecoder('utf8'); let bytes = 0, result = '', finished = false;
      const done = (error) => {
        if (finished) return; finished = true; clearTimeout(timer); this.pending.delete(cancel);
        if (error) { child.kill('SIGTERM'); const kill = setTimeout(() => child.kill('SIGKILL'), 1000); child.once('close', () => clearTimeout(kill)); reject(error); }
        else resolve(result + decoder.end());
      };
      const cancel = () => done(new Error('Reader cancelled'));
      this.pending.add(cancel);
      const timer = setTimeout(() => done(new Error('Docker identity lookup timed out')), 5000);
      child.stdout.on('data', chunk => { bytes += chunk.length; if (bytes > logLimits.metadata) done(new Error('Docker identity response exceeded limit')); else result += decoder.write(chunk); });
      // Never retain raw daemon diagnostics: they can include private host configuration.
      child.stderr.on('data', chunk => { bytes += chunk.length; if (bytes > logLimits.metadata) done(new Error('Docker diagnostic exceeded limit')); });
      child.once('error', () => done(new Error('Docker unavailable; check installation/context/access')));
      child.once('close', code => done(code === 0 ? null : new Error('Docker unavailable or access denied; check selected context')));
    });
  }
  async subscribe() {
    if (this.disposed || !this.source || !this.open || this.processes.size) return;
    const generation = ++this.generation, current = () => generation === this.generation && this.open;
    this.state = 'checking'; this.notice = 'Verifying explicitly selected shared source'; this.changed();
    try {
      const endpoint = JSON.parse(await this.query(['context', 'inspect', this.source.context, '--format', '{{json .Endpoints.docker.Host}}']));
      if (!current()) return;
      if (typeof endpoint !== 'string' || !endpoint.startsWith('unix:///') || endpoint.length > 1000 || this.endpoint && endpoint !== this.endpoint) throw new Error('Local Docker context identity changed or is not a Unix endpoint; relaunch with explicit selection');
      this.endpoint = endpoint;
      const raw = await this.query(['container', 'ls', '--all', '--no-trunc', '--filter', `label=com.docker.compose.project=${this.source.project}`, '--format', listing]);
      if (!current()) return;
      const rows = raw.trim() ? raw.trim().split('\n').map(line => JSON.parse(line)) : [];
      if (rows.length > 32) throw new Error('Too many Compose containers; source refused');
      const verified = rows.filter(row => /^[a-f0-9]{64}$/.test(row.id) && name.test(row.service) && row.directory === this.source.main);
      if (verified.length !== rows.length) throw new Error('Compose main-checkout identity mismatch; source refused');
      this.services = [...new Set(verified.map(row => row.service))].sort();
      const selected = verified.filter(row => row.service === this.source.service);
      if (selected.length !== 1) throw new Error('Selected service missing or replicated; select one existing single-container service');
      if (selected[0].state !== 'running') throw new Error('Selected source stopped; no container will be started');
      if (this.persist) this.savePreference();
      this.state = 'following'; this.notice = 'Finite tail; incomplete history. No task attribution inferred.';
      const child = this.launch(['container', 'logs', '--tail', String(logLimits.tail), '--timestamps', '--follow', selected[0].id]);
      const accept = stream => {
        const decoder = new StringDecoder('utf8'); let partial = '', overflow = false;
        const flush = (complete = false) => { if (complete || partial || overflow) { if (overflow) this.clipped = cap(this.clipped + 1); this.append(partial + (overflow ? ' [record truncated]' : '')); } partial = ''; overflow = false; };
        stream.on('data', chunk => {
          if (!current()) return;
          const text = decoder.write(chunk);
          for (const char of text) {
            if (char === '\n') flush(true);
            else if (partial.length + char.length <= logLimits.record) partial += char;
            else overflow = true;
          }
          this.changed();
        });
        stream.on('end', () => { if (current()) { partial += decoder.end(); flush(); this.changed(); } });
      };
      accept(child.stdout); accept(child.stderr);
      child.once('error', () => { if (current()) { this.state = 'unavailable'; this.notice = 'Docker reader unavailable; no automatic retry'; this.changed(); } });
      child.once('close', code => { if (current()) { clearTimeout(this.timer); this.state = 'disconnected'; this.notice = `Stream ended (${code ?? 'signal'}); source may be stopped or inaccessible. History/liveness unknown; r explicitly reconnects.`; this.changed(); } });
      this.timer = setTimeout(() => { if (current()) { void this.stop(); this.state = 'disconnected'; this.notice = '15-minute reader lifetime reached; r reconnects explicitly, with possible gaps/duplicates'; this.changed(); } }, logLimits.lifetime);
      this.changed();
    } catch (error) {
      if (current()) { this.state = 'unavailable'; this.notice = error.message; this.changed(); }
    }
  }
  savePreference() {
    // Write-only informational preference: never reload repository/model-editable state as authority.
    const root = join(this.source.main, 'var'), directory = join(root, 'terminal');
    for (const path of [root, directory]) {
      try { mkdirSync(path, { mode: 0o700 }); } catch (error) { if (error.code !== 'EEXIST') throw error; }
      const info = lstatSync(path);
      if (!info.isDirectory() || info.uid !== process.getuid() || (info.mode & 0o022)) throw new Error('Preference directory is not a trusted owned directory');
    }
    const file = join(directory, 'runtime-source.json');
    const fd = openSync(file, constants.O_WRONLY | constants.O_CREAT | constants.O_NOFOLLOW | constants.O_NONBLOCK, 0o600);
    try {
      const info = fstatSync(fd);
      if (!info.isFile() || info.nlink !== 1 || info.uid !== process.getuid() || (info.mode & 0o077)) throw new Error('Preference file must be private and owner-only');
      // No truncation until ownership/type checks have passed.
      ftruncateSync(fd, 0); writeFileSync(fd, JSON.stringify(this.source) + '\n');
    } finally { closeSync(fd); }
  }
  append(text) {
    text = safeLog(text); if (text.length > 8192) { text = text.slice(0, 8192) + ' [escaped record truncated]'; this.clipped = cap(this.clipped + 1); }
    this.history.push(text); this.units += text.length;
    while (this.history.length > logLimits.lines || this.units > logLimits.units) { this.units -= this.history.shift().length; this.omitted = cap(this.omitted + 1); }
  }
  async action(type) {
    if (!this.source) return;
    if (type === 'info') { this.info = !this.info; }
    else if (type === 'pause') { this.paused = !this.paused; this.frozen = this.paused ? [...this.history] : null; }
    else if (['next', 'previous', 'toggle', 'reconnect'].includes(type)) {
      await this.stop();
      if (this.disposed) return;
      if (type === 'toggle') this.open = !this.open; else this.open = true;
      if (type === 'next' || type === 'previous') {
        const index = this.services.indexOf(this.source.service), delta = type === 'next' ? 1 : -1;
        if (this.services.length) this.source = Object.freeze({ ...this.source, service: this.services[(index + delta + this.services.length) % this.services.length] });
        this.history = []; this.units = 0; this.frozen = null; this.paused = false;
      }
      this.gaps = cap(this.gaps + 1); this.state = 'closed'; this.notice = 'Reader closed; containers and agents unchanged';
      if (this.open) await this.subscribe();
    }
    this.changed();
  }
  summary() {
    if (!this.source) return 'SHARED runtime · unselected';
    return `SHARED ${this.source.context}/${this.source.project}/${this.source.service}\n${this.state}${this.paused ? ' · PAUSED' : ''} · omitted ${this.omitted} · clipped ${this.clipped} · gaps ${this.gaps}`;
  }
  body() {
    const display = (this.paused ? this.frozen : this.history) ?? [];
    return [this.notice, ...(display.length ? display : ['[No records yet; available history may be empty]']), this.paused ? '[PAUSED; reader draining; resume uses bounded recent window]' : '[finite tail 100; history incomplete; i source details]'].join('\n');
  }
  view() {
    if (!this.source) return 'Human-only shared runtime logs\nNo runtime selected; use explicit --logs-main/context/project/service options.\nNo log content enters Pi/model context.';
    return [`SHARED main-checkout logs · ${this.source.context}/${this.source.project}/${this.source.service}`,
      `Reader ${this.state}${this.paused ? ' · PAUSED (draining; bounded recent window on resume)' : ''}`,
      '[ / ] service · p pause · c reader close/open · r reconnect',
      `Main: ${this.source.main}`, `Endpoint: ${this.endpoint ?? 'not verified'}`,
      this.notice, `Omitted ${this.omitted} · clipped ${this.clipped} · reconnect/switch gaps ${this.gaps} (unknown loss; up to 100 duplicate tail lines)`,
      ...(this.paused ? this.frozen ?? [] : this.history).slice(-8),
      '--- bounded scrollback ---', ...(this.paused ? this.frozen ?? [] : this.history)].join('\n');
  }
  async close() { this.disposed = true; this.open = false; await this.stop(); }
}
