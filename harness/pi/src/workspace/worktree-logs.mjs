// Human-supplied existing-stack bindings, never repository/model discovery or execution authority.
import { realpathSync } from 'node:fs';
import { RuntimeLogs, runtimeSelection } from './runtime-logs.mjs';
import { clean } from './presentation.mjs';

export function testBindings(values) {
  if (values.length > 8) throw new Error('Use at most eight explicit test-stack bindings');
  const bindings = values.map(value => runtimeSelection({ ...value, purpose: 'test' }));
  const keys = bindings.map(value => JSON.stringify([value.main, value.context, value.project]));
  if (new Set(keys).size !== keys.length) throw new Error('Test-stack bindings must be distinct; select services in the pane');
  return bindings;
}

export class WorktreeLogs {
  constructor(primary, bindings, shared, changed = () => {}, { reader = (source, changed) => new RuntimeLogs(source, changed, { persist: false }) } = {}) {
    this.primary = realpathSync(primary); this.worktree = this.primary; this.changed = changed;
    this.bindings = testBindings(bindings); this.choices = new Map(); this.current = null;
    this.readers = this.bindings.map(binding => reader(binding, changed));
    this.shared = shared ? reader(runtimeSelection(shared), changed) : null;
    this.mode = 'follow'; this.info = false; this.generation = 0; this.disposed = false;
    this.transition = Promise.resolve();
  }
  get source() { return this.current?.source ?? null; }
  get state() { return this.current?.state ?? (this.candidates().length > 1 ? 'ambiguous' : 'unavailable'); }
  candidates() { return this.bindings.flatMap((binding, index) => binding.main === this.worktree ? [index] : []); }
  start() { void this.rebind(this.primary); }
  // The host passes only its primary directory or a freshly custody-admitted selected child.
  // Legacy monitor reports never reach this boundary.
  async rebind(worktree, { force = false } = {}) {
    const canonical = realpathSync(worktree), generation = ++this.generation;
    this.transition = this.transition.then(async () => {
      if (this.disposed || generation !== this.generation) return;
      const candidates = this.bindings.flatMap((binding, index) => binding.main === canonical ? [index] : []);
      const selected = this.choices.get(canonical);
      const index = candidates.includes(selected) ? selected : candidates.length === 1 ? candidates[0] : undefined;
      const next = this.mode === 'shared' ? this.shared : this.readers[index] ?? null;
      if (!force && this.worktree === canonical && this.current && this.current === next) return;
      await this.release();
      if (this.disposed || generation !== this.generation) return;
      this.worktree = canonical; this.held = false;
      // A reader which did not reap within its deadline blocks every replacement, not just itself.
      this.blocked = [...this.readers, ...(this.shared ? [this.shared] : [])].some(reader => reader.processes.size);
      if (!this.blocked) this.current = next;
      if (this.current) { this.current.open = true; await this.current.subscribe(); }
      this.changed();
    });
    return this.transition;
  }
  /** Revoke source authority immediately, even while old metadata/reaping is pending */
  hold() {
    this.held = true; this.generation++;
    const previous = this.current; this.current = null;
    if (previous) {
      previous.open = false;
      previous.history = []; previous.units = 0; previous.frozen = null; previous.paused = false;
      const stopped = previous.stop();
      this.transition = this.transition.then(() => stopped);
    }
    this.changed();
  }
  async release() {
    const previous = this.current; this.current = null;
    if (!previous) return;
    previous.open = false; await previous.stop();
    // Never attribute old or paused output to a new worktree/stack, including on returning.
    previous.history = []; previous.units = 0; previous.frozen = null; previous.paused = false;
  }
  async action(type) {
    if (this.disposed) return;
    await this.transition;
    if (this.disposed || this.held) return;
    if (type === 'info') { this.info = !this.info; this.changed(); return; }
    if (type === 'mode') {
      if (!this.shared) return;
      this.mode = this.mode === 'follow' ? 'shared' : 'follow';
      await this.rebind(this.worktree); return;
    }
    if (['stack-next', 'stack-previous'].includes(type) && this.mode === 'follow') {
      const candidates = this.candidates();
      if (!candidates.length) return;
      const previous = candidates.indexOf(this.choices.get(this.worktree));
      const position = previous < 0 ? (type === 'stack-next' ? 0 : candidates.length - 1) :
        (previous + (type === 'stack-next' ? 1 : candidates.length - 1)) % candidates.length;
      this.choices.set(this.worktree, candidates[position]); await this.rebind(this.worktree); return;
    }
    await this.current?.action(type); this.changed();
  }
  summary() {
    return `${this.mode === 'follow' ? 'FOLLOW WORKTREE TEST' : 'EXPLICIT SHARED'}\n${this.current?.summary() ?? this.unavailable()}\nLog source: ${clean(this.mode === 'shared' ? this.shared.source.main : this.worktree)}`;
  }
  unavailable() {
    if (this.blocked) return 'Previous reader has not exited; no replacement started';
    return this.candidates().length > 1 ? 'Ambiguous test stacks; { / } explicitly selects one' : 'No test-stack binding for this worktree; no shared fallback';
  }
  body() { return this.current?.body() ?? this.unavailable(); }
  view() {
    const candidates = this.candidates();
    return [this.summary(), `Primary: ${clean(this.primary)}`, `Follow context: ${clean(this.worktree)}`,
      'Automatic context: custody-verified selected/pinned child, or primary when idle',
      '{ / } stack · [ / ] service · m explicit shared/follow · i details',
      ...candidates.map(index => {
        const source = this.bindings[index];
        return `${this.current === this.readers[index] ? '>' : ' '} TEST ${source.context}/${source.project} · ${source.main}`;
      }), 'Bindings: explicit eligible test services; live metadata revalidated, container generations pinned; files cannot grant a source.',
      this.current?.view() ?? this.unavailable(), 'No log data is sent to Pi or model context.'].join('\n');
  }
  async close() {
    this.disposed = true; this.generation++; await this.transition; await this.release();
    await Promise.all([...this.readers, ...(this.shared ? [this.shared] : [])].map(reader => reader.close()));
  }
}
