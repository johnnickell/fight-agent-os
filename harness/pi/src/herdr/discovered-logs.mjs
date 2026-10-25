import { RuntimeLogs } from '../workspace/runtime-logs.mjs';
import { LocalLogSources } from './local-log-sources.mjs';

/** Keep discovery and optional selection entirely in the human-only companion */
export class DiscoveredLogs {
  constructor(changed = () => {}, { sources = new LocalLogSources(), reader } = {}) {
    this.changed = changed; this.sources = sources;
    this.reader = reader ?? (source => new RuntimeLogs(source, changed, { persist: false, pinGeneration: true,
      label: 'VERIFIED LOCAL', endpoint: source.endpoint, verifySource: (container, endpoint) => sources.verify(source, container, endpoint) }));
    this.entries = new Map(); this.readers = []; this.current = null; this.mode = 'follow';
    this.generation = 0; this.transition = Promise.resolve(); this.notice = 'Looking for your local runtime…';
  }
  hold() {
    this.held = true; this.generation++; this.menu = false;
    const previous = this.current; this.current = null;
    if (previous) {
      previous.open = false; previous.history = []; previous.units = 0; previous.frozen = null; previous.paused = false;
      const stopped = previous.stop(); this.transition = this.transition.then(() => stopped);
    }
    this.changed();
  }
  rebind(worktree) {
    const generation = ++this.generation;
    this.transition = this.transition.then(async () => {
      if (this.disposed || generation !== this.generation) return;
      this.worktree = worktree; this.held = false; this.menu = false;
      if (!this.entries.has(worktree)) {
        if (this.entries.size >= 9) throw Error('Worktree observation limit reached; reopen workspace for a fresh generation.');
        const entry = { choices: [], selected: null, notice: '' }; this.entries.set(worktree, entry);
        await this.discover(entry, generation, false);
      } else await this.open(this.entries.get(worktree), generation);
    }).catch(error => { if (!this.disposed && generation === this.generation) { this.notice = error.message; this.changed(); } });
    return this.transition;
  }
  async discover(entry, generation, explicit) {
    this.notice = 'Looking for matching existing local runtime…'; this.changed();
    try {
      const result = await this.sources.discover(this.worktree);
      if (this.disposed || this.held || generation !== this.generation) return;
      entry.choices = result.choices; entry.notice = result.notice;
      // A retry never replaces an earlier pinned identity. Only inspected selection does.
      if (!explicit && !entry.selected && entry.choices.length === 1) entry.selected = entry.choices[0];
      this.menu = entry.choices.length > 0 && (explicit || !entry.selected); this.index = 0;
      this.notice = entry.notice;
      if (!this.menu) await this.open(entry, generation);
    } catch (error) {
      if (this.disposed || generation !== this.generation) return;
      entry.notice = error.message; this.notice = error.message; this.menu = false;
    }
    this.changed();
  }
  async open(entry, generation) {
    if (this.disposed || this.held || generation !== this.generation) return;
    this.notice = entry.notice;
    // Every old process, including metadata, must settle before replacement log admission.
    if (this.readers.some(reader => reader.processes.size)) { this.notice = 'Previous reader has not exited; no replacement started. Press r after it settles.'; return; }
    if (!entry.selected) return;
    this.current = this.reader(entry.selected); this.readers = [this.current];
    this.current.open = true; await this.current.subscribe();
  }
  async action(type) {
    if (this.disposed || this.held) return;
    await this.transition;
    if (this.disposed || this.held) return;
    const entry = this.entries.get(this.worktree), generation = this.generation;
    if (!entry) return;
    if (type === 'select' || type === 'reconnect' && !entry.selected) { await this.discover(entry, generation, type === 'select'); return; }
    if (type === 'reconnect' && !this.current) { await this.open(entry, generation); this.changed(); return; }
    if (this.menu) {
      if (type === 'cancel') { this.menu = false; this.notice = 'Selection cancelled; workspace stays open. Press s to choose logs.'; }
      if (type === 'up') this.index = (this.index + entry.choices.length - 1) % entry.choices.length;
      if (type === 'down') this.index = (this.index + 1) % entry.choices.length;
      if (type === 'choose') {
        const selected = entry.choices[this.index];
        if (this.current) { this.current.open = false; await this.current.close(); this.current = null; }
        if (this.disposed || generation !== this.generation) return;
        entry.selected = selected; this.menu = false; await this.open(entry, generation);
      }
      this.changed(); return;
    }
    await this.current?.action(type); this.changed();
  }
  unavailable() { return `${this.notice}\nFocus this log pane: s choose source · r retry · Esc cancels selection. Pi stays usable.`; }
  body() {
    if (this.menu) {
      const entry = this.entries.get(this.worktree);
      const start = Math.max(0, this.index - 2);
      return ['Choose logs for this checkout (existing local sources only)', this.worktree,
        ...entry.choices.slice(start, start + 5).map((source, offset) => `${start + offset === this.index ? '>' : ' '} ${source.label}`),
        `${this.index + 1}/${entry.choices.length} · ↑/↓ move · Enter choose · Esc cancel (panes stay open)`].join('\n');
    }
    return this.current?.body() ?? this.unavailable();
  }
  view() { return [this.body(), this.current?.view() ?? 'No shared fallback. No containers changed.'].join('\n'); }
  async close() {
    this.disposed = true; this.hold(); await this.sources.close(); await this.transition;
    await Promise.all(this.readers.map(reader => reader.close()));
  }
}
