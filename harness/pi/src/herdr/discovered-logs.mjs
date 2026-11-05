import { RuntimeLogs, safeLog } from '../workspace/runtime-logs.mjs';
import { LocalLogSources } from './local-log-sources.mjs';

export const discoveryInterval = 3000;
// Replacement may change membership/lifecycle, never checkout/daemon/project authority.
const sameSource = (a, b) => ['main', 'context', 'project', 'endpoint'].every(key => a[key] === b[key]) &&
  JSON.stringify(a.socket) === JSON.stringify(b.socket) && JSON.stringify(a.checkout) === JSON.stringify(b.checkout);
const sameGeneration = (a, b) => sameSource(a, b) && a.witness === b.witness;

/** Keep discovery and optional selection entirely in the human-only companion */
export class DiscoveredLogs {
  constructor(changed = () => {}, { sources = new LocalLogSources(), reader } = {}) {
    this.changed = changed; this.sources = sources;
    this.reader = reader ?? (source => new RuntimeLogs(source, changed, { verifySource: () => sources.verify(source) }));
    this.entries = new Map(); this.readers = []; this.current = null;
    this.generation = 0; this.transition = Promise.resolve(); this.notice = 'Looking for your local runtime…';
    this.refreshAt = 0; this.inputMode = { revision: 0, menu: false, selecting: false };
  }
  clear(reader) {
    reader.open = false; reader.history = []; reader.units = 0; reader.frozen = null; reader.paused = false;
  }
  hold() {
    this.held = true; this.generation++; this.menu = false;
    // New input needs fresh authority; already accepted actions keep their own ordered mode.
    this.inputMode = { revision: 0, menu: false, selecting: false };
    const previous = this.current; this.current = null;
    if (previous) {
      const entry = this.entries.get(this.entryKey); if (entry) entry.paused = previous.paused;
      this.clear(previous);
      const stopped = previous.stop(); this.transition = this.transition.then(() => stopped);
    }
    this.changed();
  }
  rebind(worktree, { main = false } = {}) {
    this.hold();
    const generation = this.generation;
    this.transition = this.transition.then(async () => {
      if (this.disposed || generation !== this.generation) return;
      this.worktree = worktree; this.main = main; this.entryKey = main ? 'main' : worktree; this.held = false;
      if (!this.entries.has(this.entryKey)) {
        if (this.entries.size >= 9) throw Error('Worktree observation limit reached; reopen workspace for a fresh generation.');
        this.entries.set(this.entryKey, { choices: [], selected: null, notice: '', paused: false, closed: false, cancelled: false });
      }
      const entry = this.entries.get(this.entryKey);
      entry.services = null; // Every stack/context switch resets Services to All
      if (entry.closed || entry.cancelled) { this.notice = 'Reader closed or selection cancelled; c reopens · s Services.'; return; }
      await this.discover(entry, generation, false);
    }).catch(error => { if (this.valid(generation)) { this.notice = error.message; this.changed(); } });
    return this.transition;
  }
  valid(generation) { return !this.disposed && !this.held && generation === this.generation; }
  /** Called only while HerdrLogs has freshly validated the current native/child selection */
  refresh({ force = false } = {}) {
    if (this.refreshPending) return this.refreshPending;
    const entry = this.entries.get(this.entryKey), generation = this.generation;
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
      const result = await this.sources.discover(this.worktree, { main: this.main });
      if (!this.valid(generation)) return;
      entry.choices = result.choices; entry.notice = result.notice; this.notice = entry.notice;
      if (explicit) {
        const fresh = entry.selected && entry.choices.find(choice => sameSource(choice, entry.selected));
        if (fresh && !sameGeneration(fresh, entry.selected)) entry.services = null;
        entry.selected = fresh ?? null;
        this.menu = entry.selected ? 'services' : entry.choices.length > 0 ? 'projects' : false;
        this.draft = new Set(entry.services ?? entry.selected?.services ?? []); this.index = 0;
        return;
      }
      let selected;
      if (entry.selected) {
        selected = entry.choices.find(choice => sameSource(choice, entry.selected));
        if (!selected) this.notice = 'Selected stack unavailable or checkout/daemon authority changed; waiting for that stack. Retry discovery to inspect its identity.';
      } else if (entry.choices.length === 1) selected = entry.choices[0];
      else if (entry.choices.length > 1) { this.menu = 'projects'; this.index = 0; }
      if (!selected) { await this.stopCurrent(); return; }
      const unchanged = entry.selected && sameGeneration(entry.selected, selected);
      if (!unchanged) entry.services = null;
      entry.selected = selected;
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
    const members = entry.selected.members.filter(member => !entry.services || entry.services.includes(member.service));
    this.current = this.reader({ ...entry.selected, members }); this.readers = [this.current];
    entry.gaps = Math.min(Number.MAX_SAFE_INTEGER, (entry.gaps ?? -1) + 1);
    this.current.gaps = entry.gaps;
    this.current.paused = entry.paused; this.current.frozen = entry.paused ? [] : null;
    this.current.open = true; await this.current.subscribe();
  }
  action(type, { canChoose = () => true } = {}) {
    const generation = this.generation, entry = this.entries.get(this.entryKey);
    const inputMode = this.inputMode, revision = inputMode.revision;
    const acceptedMenu = this.menu || inputMode.selecting;
    if (!this.valid(generation) || !entry) return this.transition;
    this.transition = this.transition.then(async () => {
      // Explicit transitions order accepted controls even after hold() revokes I/O.
      // Background ambiguity is not an explicit transition and cannot reinterpret input.
      const menu = revision !== inputMode.revision ? inputMode.menu : acceptedMenu;
      if (type === 'choose' && menu && (!canChoose() || this.valid(generation) && !this.menu)) return;
      if (type === 'choose' && this.valid(generation) && this.menu === 'services' && !this.draft.size) {
        this.notice = 'Choose at least one service, or All.'; this.changed(); return;
      }
      if (type === 'select' || menu && (type === 'cancel' || type === 'choose')) {
        inputMode.revision++; inputMode.menu = type === 'select' ? 'services' : false;
      }
      // Accepted controls belong to this entry, in queue order, even if its I/O authority is revoked.
      if (type === 'select' || !menu && (type === 'reconnect' || type === 'toggle')) {
        entry.closed = type === 'toggle' ? !entry.closed : false; entry.cancelled = false;
      } else if (type === 'cancel' && menu) entry.cancelled = true;
      else if (type === 'choose' && menu) { entry.closed = false; entry.cancelled = false; }
      else if (type === 'pause' && !menu) entry.paused = !entry.paused;
      if (!this.valid(generation)) return;
      if (type === 'select') {
        inputMode.selecting = true;
        try {
          if (this.current) entry.paused = this.current.paused;
          await this.stopCurrent();
          if (this.valid(generation)) await this.discover(entry, generation, true);
        } finally { inputMode.selecting = false; }
      } else if (menu) {
        if (type === 'cancel') { this.menu = false; this.notice = 'Selection cancelled; workspace stays open. s Services · r retry stack discovery.'; }
        const services = this.menu === 'services';
        const count = services ? entry.selected.services.length + 1 : entry.choices.length;
        if (type === 'up') this.index = (this.index + count - 1) % count;
        if (type === 'down') this.index = (this.index + 1) % count;
        if (type === 'mark' && services) {
          if (this.index === 0) this.draft = new Set(entry.selected.services);
          else {
            const service = entry.selected.services[this.index - 1];
            if (this.draft.has(service)) this.draft.delete(service); else this.draft.add(service);
          }
        }
        if (type === 'choose') {
          if (services) {
            entry.services = this.draft.size === entry.selected.services.length ? null : [...this.draft].sort();
          } else { entry.selected = entry.choices[this.index]; entry.services = null; }
          this.menu = false;
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
  unavailable() { return `${this.notice}\nAutomatic discovery waits for a verified stack · s Services · c close/open · r retry · Esc cancel`; }
  filterLabel() { const entry = this.entries.get(this.entryKey); return entry?.services?.join(', ') ?? 'All'; }
  choice() {
    if (!this.menu) return null;
    const entry = this.entries.get(this.entryKey);
    if (this.menu === 'services') {
      const services = entry.selected.services;
      return { kind: 'Services', index: this.index, count: services.length + 1,
        label: this.index === 0 ? 'All — Space selects all services' : `${this.draft.has(services[this.index - 1]) ? '[x]' : '[ ]'} ${services[this.index - 1]}`,
        selection: services.map(service => `${this.draft.has(service) ? '[x]' : '[ ]'} ${service}`).join('\n'),
        notice: safeLog(this.notice), worktree: safeLog(entry.selected.main) };
    }
    return { kind: 'Stack', index: this.index, count: entry.choices.length, label: safeLog(entry.choices[this.index].label), worktree: safeLog(this.worktree) };
  }
  body() {
    const choice = this.choice();
    if (choice) {
      return [`${choice.kind} ${choice.index + 1}/${choice.count} (verified local stack only)`,
        `> ${choice.label}`, `Checkout: ${choice.worktree}`,
        '↑/↓ move · Space mark services · Enter apply · Esc cancel (panes stay open)'].join('\n');
    }
    return this.current?.body() ?? this.unavailable();
  }
  view() { return [this.body(), this.current?.view() ?? 'No shared fallback. No containers changed.'].join('\n'); }
  async close() {
    this.disposed = true; this.hold(); await this.sources.close(); await this.transition;
    await Promise.all(this.readers.map(reader => reader.close()));
  }
}
