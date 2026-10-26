import { RuntimeLogs } from '../workspace/runtime-logs.mjs';
import { LocalLogSources } from './local-log-sources.mjs';

export const discoveryInterval = 3000;
// Replacement may change the container lifecycle, never checkout/daemon/service authority.
const sameSource = (a, b) => ['main', 'context', 'project', 'service', 'endpoint'].every(key => a[key] === b[key]) &&
  JSON.stringify(a.socket) === JSON.stringify(b.socket) && JSON.stringify(a.checkout) === JSON.stringify(b.checkout);
const sameGeneration = (a, b) => sameSource(a, b) && a.container === b.container && a.witness === b.witness;

/** Keep discovery and optional selection entirely in the human-only companion */
export class DiscoveredLogs {
  constructor(changed = () => {}, { sources = new LocalLogSources(), reader } = {}) {
    this.changed = changed; this.sources = sources;
    this.reader = reader ?? (source => new RuntimeLogs(source, changed, { persist: false, pinGeneration: true,
      label: 'VERIFIED LOCAL', endpoint: source.endpoint, verifySource: (container, endpoint) => sources.verify(source, container, endpoint) }));
    this.entries = new Map(); this.readers = []; this.current = null; this.mode = 'follow';
    this.generation = 0; this.transition = Promise.resolve(); this.notice = 'Looking for your local runtime…';
    this.refreshAt = 0;
  }
  clear(reader) {
    reader.open = false; reader.history = []; reader.units = 0; reader.frozen = null; reader.paused = false;
  }
  hold() {
    this.held = true; this.generation++; this.menu = false;
    const previous = this.current; this.current = null;
    if (previous) {
      const entry = this.entries.get(this.worktree); if (entry) entry.paused = previous.paused;
      this.clear(previous);
      const stopped = previous.stop(); this.transition = this.transition.then(() => stopped);
    }
    this.changed();
  }
  rebind(worktree) {
    this.hold();
    const generation = this.generation;
    this.transition = this.transition.then(async () => {
      if (this.disposed || generation !== this.generation) return;
      this.worktree = worktree; this.held = false;
      if (!this.entries.has(worktree)) {
        if (this.entries.size >= 9) throw Error('Worktree observation limit reached; reopen workspace for a fresh generation.');
        this.entries.set(worktree, { choices: [], selected: null, notice: '', paused: false, closed: false, cancelled: false });
      }
      const entry = this.entries.get(worktree);
      if (entry.closed || entry.cancelled) { this.notice = 'Reader closed or selection cancelled; c reopens · s chooses logs.'; return; }
      await this.discover(entry, generation, false);
    }).catch(error => { if (this.valid(generation)) { this.notice = error.message; this.changed(); } });
    return this.transition;
  }
  valid(generation) { return !this.disposed && !this.held && generation === this.generation; }
  /** Called only while HerdrLogs has freshly validated the current native/child selection */
  refresh({ force = false } = {}) {
    if (this.refreshPending) return this.refreshPending;
    const entry = this.entries.get(this.worktree), generation = this.generation;
    if (entry && this.current?.expired) entry.closed = true;
    if (!this.valid(generation) || this.menu || !entry || entry.closed || entry.cancelled || !force && Date.now() < this.refreshAt) return this.transition;
    this.transition = this.transition.then(async () => {
      // A rebind or explicit action may still be scanning when this refresh is queued.
      if (this.valid(generation) && !this.menu && !entry.closed && !entry.cancelled && (force || Date.now() >= this.refreshAt)) await this.discover(entry, generation, false);
    });
    const pending = this.transition.finally(() => { if (this.refreshPending === pending) this.refreshPending = null; });
    this.refreshPending = pending;
    return pending;
  }
  async stopCurrent() {
    const previous = this.current; this.current = null;
    if (!previous) return;
    this.clear(previous); await previous.stop();
  }
  async discover(entry, generation, explicit) {
    try {
      const result = await this.sources.discover(this.worktree);
      if (!this.valid(generation)) return;
      entry.choices = result.choices; entry.notice = result.notice; this.notice = entry.notice;
      if (explicit) {
        this.menu = entry.choices.length > 0; this.index = 0;
        return;
      }
      let selected;
      if (entry.selected) {
        selected = entry.choices.find(choice => sameSource(choice, entry.selected));
        if (!selected) this.notice = 'Selected source unavailable or its checkout/daemon authority changed; waiting for that source. Press s to choose another.';
      } else if (entry.choices.length === 1) selected = entry.choices[0];
      else if (entry.choices.length > 1) { this.menu = true; this.index = 0; }
      if (!selected) { await this.stopCurrent(); return; }
      const unchanged = entry.selected && sameGeneration(entry.selected, selected);
      entry.selected = selected;
      if (this.current?.expired) return; // Absolute reader lifetime still requires explicit human recovery.
      if (unchanged && this.current?.open && this.current.state === 'following') return;
      if (this.current) entry.paused = this.current.paused;
      await this.stopCurrent();
      if (this.valid(generation)) await this.open(entry, generation);
    } catch (error) {
      if (!this.valid(generation)) return;
      if (this.current) entry.paused = this.current.paused;
      await this.stopCurrent();
      if (!this.valid(generation)) return;
      entry.notice = error.message; this.notice = error.message; this.menu = false;
    } finally {
      this.refreshAt = Date.now() + discoveryInterval;
      if (this.valid(generation)) this.changed();
    }
  }
  async open(entry, generation) {
    if (!this.valid(generation) || entry.closed || entry.cancelled) return;
    // Every old process, including metadata, must settle before replacement admission.
    if (this.readers.some(reader => reader.processes.size)) { this.notice = 'Previous reader has not exited; no replacement started. Waiting for it to settle.'; return; }
    if (!entry.selected) return;
    this.current = this.reader(entry.selected); this.readers = [this.current];
    this.current.paused = entry.paused; this.current.frozen = entry.paused ? [] : null;
    this.current.open = true; await this.current.subscribe();
  }
  action(type) {
    const generation = this.generation, entry = this.entries.get(this.worktree), menu = this.menu;
    if (!this.valid(generation) || !entry) return this.transition;
    this.transition = this.transition.then(async () => {
      // Accepted controls belong to this entry, in queue order, even if its I/O authority is revoked.
      if (type === 'select' || !menu && (type === 'reconnect' || type === 'toggle')) {
        entry.closed = type === 'toggle' ? !entry.closed : false; entry.cancelled = false;
      } else if (type === 'cancel' && menu) entry.cancelled = true;
      else if (type === 'pause' && !menu) entry.paused = !entry.paused;
      if (!this.valid(generation)) return;
      if (type === 'select') {
        if (this.current) entry.paused = this.current.paused;
        await this.stopCurrent();
        if (this.valid(generation)) await this.discover(entry, generation, true);
      } else if (menu) {
        if (type === 'cancel') { this.menu = false; this.notice = 'Selection cancelled; workspace stays open. Press s to choose logs.'; }
        if (type === 'up') this.index = (this.index + entry.choices.length - 1) % entry.choices.length;
        if (type === 'down') this.index = (this.index + 1) % entry.choices.length;
        if (type === 'choose') {
          entry.selected = entry.choices[this.index]; entry.cancelled = false; entry.closed = false; this.menu = false;
          await this.open(entry, generation);
        }
      } else if (type === 'toggle' || type === 'reconnect') {
        this.menu = false;
        if (this.current) entry.paused = this.current.paused;
        if (entry.closed) {
          // Set display intent before awaiting reaping: hold() can revoke this reader meanwhile.
          const reader = this.current, notice = 'Reader closed; c reopens. Containers and agents unchanged.';
          if (reader) {
            this.clear(reader); reader.state = 'closed'; reader.notice = notice;
            reader.paused = entry.paused; reader.frozen = entry.paused ? [] : null;
            await reader.stop();
          }
          if (!this.valid(generation)) return;
          this.notice = notice;
        } else {
          await this.stopCurrent();
          if (this.valid(generation)) await this.discover(entry, generation, false);
        }
      } else {
        await this.current?.action(type);
        if (this.current) entry.paused = this.current.paused;
      }
      this.changed();
    });
    return this.transition;
  }
  unavailable() { return `${this.notice}\nAutomatic discovery waits for verified local sources · s choose · c close/open · r retry · Esc cancel`; }
  choice() {
    if (!this.menu) return null;
    const entry = this.entries.get(this.worktree);
    return { index: this.index, count: entry.choices.length, label: entry.choices[this.index].label, worktree: this.worktree };
  }
  body() {
    const choice = this.choice();
    if (choice) {
      return [`Choose logs ${choice.index + 1}/${choice.count} (existing local sources only)`,
        `> ${choice.label}`, `Checkout: ${choice.worktree}`,
        '↑/↓ source · Enter choose · Esc cancel (panes stay open)'].join('\n');
    }
    return this.current?.body() ?? this.unavailable();
  }
  view() { return [this.body(), this.current?.view() ?? 'No shared fallback. No containers changed.'].join('\n'); }
  async close() {
    this.disposed = true; this.hold(); await this.sources.close(); await this.transition;
    await Promise.all(this.readers.map(reader => reader.close()));
  }
}
