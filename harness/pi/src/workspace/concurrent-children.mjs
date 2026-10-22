// Presentation over human-admitted legacy endpoints, NOT authenticated session discovery.
import { ChildObservation, observationPaths } from './child-observation.mjs';
import { clean } from './presentation.mjs';

const activeStates = new Set(['starting', 'running', 'settled-awaiting-exit']);
export class ConcurrentChildren {
  constructor(paths, changed = () => {}, { reader = (path, changed) => new ChildObservation([path], changed), now = Date.now } = {}) {
    this.paths = observationPaths(paths); this.changed = changed; this.reader = reader; this.now = now;
    this.readers = []; this.admitted = new Map(); this.order = 0; this.selected = null; this.pin = null;
    this.session = null; this.closed = false; this.open = true; this.notice = 'Waiting for actual Pi session identity';
  }
  start(sessionId) {
    if (this.closed || this.session !== null || typeof sessionId !== 'string' || !sessionId) return;
    this.session = sessionId;
    this.notice = 'Explicit human endpoints only; parent membership and launch order unverified';
    // Subscribe once to each of at most eight explicitly authorized sources, not a filesystem scan.
    // Defer callbacks until the whole array exists, including synchronously failing connections.
    let starting = true;
    this.readers = this.paths.map(path => this.reader(path, () => { if (!starting) this.tick(); }));
    for (const source of this.readers) source.start();
    starting = false; this.tick();
  }
  sessionChanged(sessionId) {
    if (this.session === null) { this.start(sessionId); return; }
    if (sessionId === this.session || this.closed) return;
    // Human endpoint admission applies to the original workspace session, not every session in cwd.
    this.close(); this.session = sessionId;
    this.notice = 'Session changed/unavailable: old endpoints and pin cleared; reopen with explicit human choices';
    this.changed();
  }
  active(index) {
    const source = this.readers[index], item = source?.last;
    return source?.state === 'connected' && item && activeStates.has(item.lifecycle) && source.receivedAt !== null &&
      this.now() - source.receivedAt <= Math.max(5000, item.heartbeatSeconds * 3000);
  }
  tick() {
    if (this.closed) return;
    this.readers.forEach((source, index) => {
      if (source.last && !this.admitted.has(index)) this.admitted.set(index, ++this.order);
    });
    if (this.pin !== null) this.selected = this.pin;
    else if (this.selected === null || !this.active(this.selected)) {
      const candidates = this.readers.map((_, index) => index).filter(index => this.active(index));
      // Actual launch order is not supplied by v1. Use host snapshot-admission order, visibly legacy.
      candidates.sort((a, b) => this.admitted.get(b) - this.admitted.get(a) ||
        this.readers[a].last.identity.attempt.localeCompare(this.readers[b].last.identity.attempt));
      this.selected = candidates[0] ?? null;
    }
    this.changed();
  }
  action(type) {
    if (this.closed || this.session === null) return;
    if (type === 'pin') {
      this.pin = this.pin === null ? this.selected : null;
      this.tick(); return;
    }
    if (type === 'next' || type === 'previous') {
      const candidates = this.readers.map((_, index) => index).filter(index => this.active(index) || index === this.pin);
      if (!candidates.length) return;
      let position = candidates.indexOf(this.selected);
      position = position < 0 ? (type === 'next' ? 0 : candidates.length - 1) :
        (position + (type === 'next' ? 1 : candidates.length - 1)) % candidates.length;
      this.selected = candidates[position];
      // A pin prevents automatic switching, not explicit operator choice.
      if (this.pin !== null) this.pin = this.selected;
      this.changed(); return;
    }
    if (type === 'toggle') {
      this.open = !this.open;
      for (const source of this.readers) {
        if (this.open) source.action('reconnect'); else source.disconnect('All observation readers closed; execution unchanged');
      }
      this.tick(); return;
    }
    if (type === 'reconnect') {
      this.open = true;
      for (const source of this.readers) source.action('reconnect');
      this.tick(); return;
    }
    if (type === 'pause') this.readers[this.selected]?.action('pause');
  }
  view() {
    if (!this.paths.length) return 'Observation only — child agent\nUnavailable: no child selected\nAutomatic session registration unavailable; explicit legacy --observe-child remains available\nNo discovery, launch or child control.';
    const selected = this.readers[this.selected], item = selected?.last;
    return ['EXPLICIT LEGACY observation — parent membership unverified',
      `${this.pin === null ? 'UNPINNED' : 'PINNED'} · ${this.selected === null ? 'IDLE — no eligible active source' : `selection ${this.selected + 1}/${this.paths.length}`}${this.open ? '' : ' · READERS CLOSED'}`,
      '[ / ] choose · f pin/unpin · p pause · c all readers · r reconnect',
      `Primary session: ${clean(this.session ?? '(waiting)')}`,
      'Reported child worktree cannot redirect test logs; primary directory/grants unchanged',
      ...this.readers.map((source, index) => {
        const identity = source.last?.identity;
        return `${index === this.selected ? '>' : ' '} ${index === this.pin ? '[PIN] ' : ''}${identity ? `${identity.role} ${identity.task} · ${source.state}/${source.last.lifecycle}\n  Worktree ${identity.worktree}\n  Attempt ${identity.attempt}` : `Source ${index + 1}: ${source.state}`}`;
      }), this.notice,
      item ? selected.view() : 'No source selected; no completed state inferred',
      'Pane output is human-only; never sent to Pi/model context.'].join('\n');
  }
  close() {
    if (this.closed) return;
    this.closed = true; this.selected = null; this.pin = null;
    for (const source of this.readers) source.close();
    this.readers = []; this.admitted.clear();
  }
}
