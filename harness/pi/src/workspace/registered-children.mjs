import { constants, closeSync, fstatSync, ftruncateSync, openSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { candidates, challenge, directory, privateDirectory, privateFile, sessionFileIdentity, limits } from '../observation/custody.mjs';
import { clean } from './presentation.mjs';

const activeStates = new Set(['starting', 'running', 'settled-awaiting-exit']);
const keys = ['task', 'role', 'provider', 'model', 'thinking', 'worktree', 'attempt'];
const sourceKey = item => `${item.generation}:${item.id}`;
function admitted(value, witness) {
  if (!value || value.parent !== witness.parent || value.generation !== witness.generation || !/^[a-f0-9-]{36}$/.test(value.id) ||
      !Number.isSafeInteger(value.started) || value.started > witness.at || !Number.isSafeInteger(value.order) || value.order < 1 ||
      !Number.isSafeInteger(value.sequence) || value.sequence < 0 || !Number.isSafeInteger(value.receivedAt) || value.receivedAt > witness.at ||
      !['connected', 'starting', 'disconnected'].includes(value.state) || ![...activeStates, 'completed', 'failed'].includes(value.lifecycle) ||
      !Number.isFinite(value.heartbeatSeconds) || value.heartbeatSeconds < 1 || value.heartbeatSeconds > 60 ||
      !keys.every(key => typeof value.identity?.[key] === 'string' && value.identity[key].length > 0 && value.identity[key].length <= 2200) ||
      !Array.isArray(value.history) || value.history.length > limits.records || value.history.some(text => typeof text !== 'string' || text.length > 1200) ||
      value.history.reduce((sum, text) => sum + text.length, 0) > limits.units ||
      !Number.isSafeInteger(value.omitted) || value.omitted < 0 ||
      (value.gaps !== undefined && (!Number.isSafeInteger(value.gaps) || value.gaps < 0)) ||
      (['completed', 'failed'].includes(value.lifecycle) ? !Number.isInteger(value.exitCode) || value.exitCode < 0 || value.exitCode > 255 || (value.lifecycle === 'completed') !== (value.exitCode === 0) : value.exitCode !== null)) throw new Error('Invalid admitted child');
  if (clean(value.identity.worktree) !== value.identity.worktree || realpathSync(value.identity.worktree) !== value.identity.worktree) throw new Error('Noncanonical admitted worktree');
  return { ...value, identity: Object.fromEntries(keys.map(key => [key, clean(value.identity[key]).replace(/\n/g, ' ')])), history: value.history.map(text => clean(text).replace(/\n/g, ' ')), verified: true };
}
export class RegisteredChildren {
  constructor(cwd, changed = () => {}, { discover = candidates, verify = challenge, now = Date.now, preferences = true } = {}) {
    this.cwd = realpathSync(cwd); this.changed = changed; this.discover = discover; this.verify = verify; this.now = now; this.preferences = preferences;
    this.sources = new Map(); this.selected = null; this.pin = null; this.session = null; this.file = null;
    this.epoch = 0; this.closed = false; this.open = true; this.pending = null; this.paused = false; this.frozen = null;
    this.notice = 'Waiting for actual Pi session identity';
  }
  start(session, file) {
    if (this.closed || this.session !== null || typeof session !== 'string' || !session) return;
    this.session = session; this.sessionFile = file;
    try { this.file = file ? sessionFileIdentity(file) : null; } catch { this.file = null; }
    this.loadPreference(); return this.refresh();
  }
  sessionChanged(session, file) {
    if (session === this.session && file === this.file?.path) return;
    this.invalidateRefresh(); this.sources.clear(); this.selected = null; this.pin = null; this.paused = false; this.frozen = null;
    this.session = null; this.file = null; this.notice = 'Session changed: old readers, pin and source context cleared'; this.changed();
    if (session) return this.start(session, file);
  }
  active(item) {
    return item?.verified && ['starting', 'connected'].includes(item.state) && activeStates.has(item.lifecycle) &&
      this.now() - item.receivedAt <= Math.max(5000, item.heartbeatSeconds * 3000);
  }
  recompute() {
    if (this.pin && this.sources.has(this.pin)) this.selected = this.pin;
    else if (!this.active(this.sources.get(this.selected))) {
      this.selected = [...this.sources.values()].filter(item => this.active(item)).sort((a, b) =>
        b.started - a.started || a.id.localeCompare(b.id)).map(sourceKey)[0] ?? null;
    }
  }
  invalidateRefresh() {
    this.epoch++; this.refreshController?.abort(); this.refreshController = null; this.pending = null;
  }
  async refresh() {
    if (this.closed || !this.open || !this.session || this.pending) return this.pending;
    const epoch = this.epoch, session = this.session, file = this.file;
    const controller = new AbortController(); this.refreshController = controller;
    const pending = (async () => {
      const next = new Map(); let refused = 0;
      try {
        for (const candidate of this.discover(this.cwd, session)) {
          if (controller.signal.aborted) return;
          try {
            const witness = await this.verify(this.cwd, session, candidate.path, candidate.descriptor, { signal: controller.signal });
            if (epoch !== this.epoch || this.closed || !this.open) return;
            if (JSON.stringify(witness.parentFile) !== JSON.stringify(file)) throw new Error('Actual Pi session file does not match launch custody');
            if (!Array.isArray(witness.children) || witness.children.length > limits.children) throw new Error('Custody child capacity exceeded');
            const admittedItems = [];
            for (const value of witness.children) {
              const item = admitted(value, witness), key = sourceKey(item), previous = this.sources.get(key);
              if (previous && (JSON.stringify(previous.identity) !== JSON.stringify(item.identity) || item.started !== previous.started || item.order !== previous.order || item.sequence < previous.sequence)) throw new Error('Attempt replaced or sequence regressed');
              admittedItems.push([key, item]);
            }
            for (const [key, item] of admittedItems) next.set(key, item);
          } catch { refused++; }
        }
        if (epoch !== this.epoch || this.closed || !this.open) return;
        // Keep a pinned lost source visibly disconnected, but never use invalidated custody for logs.
        const lost = this.sources.get(this.pin);
        if (lost && !next.has(this.pin)) next.set(this.pin, { ...lost, verified: false, state: 'disconnected' });
        const sorted = [...next.entries()].sort(([a, x], [b, y]) => (b === this.pin || b === this.selected ? 1 : 0) - (a === this.pin || a === this.selected ? 1 : 0) || y.started - x.started || a.localeCompare(b));
        this.sources = new Map(sorted.slice(0, limits.children)); this.recompute();
        this.notice = `${refused ? `${refused} stale/replaced/mismatched custody candidate(s) refused. ` : ''}${next.size > limits.children ? 'Capacity: newest eight plus retained selection/pin only. ' : ''}Live launch custody verified; pane is human-only`;
        this.restorePreference(); this.savePreference(); this.changed();
      } catch { if (epoch === this.epoch && !this.closed) { for (const item of this.sources.values()) item.verified = false; this.recompute(); this.notice = 'Registration discovery unavailable/capacity exceeded; no unverified source admitted'; this.changed(); } }
    })().finally(() => {
      // A late old-session completion must not release the current refresh's lock.
      if (this.refreshController === controller) { this.pending = null; this.refreshController = null; }
    });
    this.pending = pending; return pending;
  }
  tick() {
    if (!this.file && this.sessionFile) {
      try { this.file = sessionFileIdentity(this.sessionFile); this.invalidateRefresh(); } catch {}
    }
    this.recompute(); void this.refresh();
  }
  get verifiedWorktree() { const item = this.sources.get(this.selected); return item?.verified ? item.identity.worktree : null; }
  action(type) {
    if (this.closed || !this.session) return;
    if (type === 'toggle') {
      this.open = !this.open; this.invalidateRefresh();
      if (!this.open) { for (const item of this.sources.values()) { item.verified = false; item.state = 'disconnected'; } this.recompute(); this.notice = 'Readers closed; children unchanged'; }
      else void this.refresh();
    } else if (type === 'reconnect') { this.open = true; void this.refresh(); }
    else if (type === 'pin') { this.pin = this.pin === null ? this.selected : null; this.recompute(); }
    else if (type === 'next' || type === 'previous') {
      const eligible = [...this.sources.entries()].filter(([key, item]) => this.active(item) || key === this.pin).map(([key]) => key).sort();
      if (eligible.length) {
        const current = eligible.indexOf(this.selected), delta = type === 'next' ? 1 : -1;
        this.selected = eligible[(current + delta + eligible.length) % eligible.length];
        if (this.pin !== null) this.pin = this.selected;
      }
    } else if (type === 'pause') { this.paused = !this.paused; this.frozen = this.paused ? [...(this.sources.get(this.selected)?.history ?? [])] : null; }
    // A frozen display belongs to one selection, never a subsequently selected child.
    if (type !== 'pause') { this.paused = false; this.frozen = null; }
    this.savePreference(); this.changed();
  }
  loadPreference() {
    if (!this.preferences) return;
    try { const path = join(directory(this.cwd, this.session), 'preference.json'); privateFile(path); this.preference = JSON.parse(readFileSync(path, 'utf8')); } catch {}
  }
  restorePreference() {
    const choice = this.preference; if (!choice) return;
    // An editable preference cannot create membership. Keep it pending only until the first discovery.
    this.preference = null;
    if (this.sources.get(choice.pin)?.verified) { this.pin = choice.pin; this.selected = choice.pin; }
    else if (this.active(this.sources.get(choice.selected))) this.selected = choice.selected;
  }
  savePreference() {
    if (!this.preferences || !this.session || this.closed) return;
    let fd;
    try {
      const root = directory(this.cwd, this.session); privateDirectory(root);
      fd = openSync(join(root, 'preference.json'), constants.O_WRONLY | constants.O_CREAT | constants.O_NOFOLLOW | constants.O_NONBLOCK, 0o600);
      const info = fstatSync(fd);
      if (!info.isFile() || info.nlink !== 1 || info.uid !== process.getuid() || info.mode & 0o077) throw new Error();
      ftruncateSync(fd, 0); writeFileSync(fd, JSON.stringify({ selected: this.selected, pin: this.pin }) + '\n');
    } catch { this.notice += ' · Preference not saved'; } finally { if (fd !== undefined) closeSync(fd); }
  }
  view() {
    const item = this.sources.get(this.selected);
    return ['AUTOMATIC current-session children · observation only', `${this.pin === null ? 'UNPINNED' : 'PINNED'} · ${item ? `${item.identity.role} ${item.identity.task}` : 'IDLE — no eligible active child'}${this.open ? '' : ' · READERS CLOSED'}`,
      '[ / ] choose · f pin/unpin · p pause · c readers · r rediscover', `Primary session: ${clean(this.session ?? '(waiting)')}`,
      ...[...this.sources.entries()].map(([key, source]) => `${key === this.selected ? '>' : ' '} ${key === this.pin ? '[PIN] ' : ''}${source.identity.role} ${source.identity.task} · ${source.verified ? source.state : 'CUSTODY LOST'}/${source.lifecycle}\n  Worktree ${source.identity.worktree}\n  Attempt ${source.identity.attempt}\n  Generation ${source.generation} · child ${source.id}`),
      this.notice, item ? `Last known ${item.lifecycle}${item.exitCode === null ? '' : ` (exit ${item.exitCode}; not acceptance)`} · sequence ${item.sequence} · omitted ${item.omitted} · pipe gaps ${item.gaps ?? 'unknown (earlier custodian)'}${this.paused ? ' · DISPLAY PAUSED' : ''}` : 'Legacy/unregistered children unavailable; never relaunched',
      ...(this.paused ? this.frozen ?? [] : item?.history ?? []), 'Conversation cwd/grants unchanged; no pane output enters Pi/model context.'].join('\n');
  }
  close() { if (this.closed) return; this.savePreference(); this.closed = true; this.invalidateRefresh(); this.sources.clear(); this.selected = null; this.pin = null; this.paused = false; this.frozen = null; }
}
