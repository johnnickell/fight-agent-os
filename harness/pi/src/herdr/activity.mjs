import { realpathSync } from 'node:fs';
import { basename } from 'node:path';
import { RegisteredChildren } from '../workspace/registered-children.mjs';
import { queryLocal, validateContext, same } from './context.mjs';
import { clean } from '../workspace/presentation.mjs';

/** Observe children in a separate human-only process without touching native execution */
export class HerdrActivity {
  constructor(binding, changed = () => {}, { query = queryLocal, reader = (...args) => new RegisteredChildren(...args) } = {}) {
    this.binding = binding; this.changed = changed; this.query = query; this.reader = reader;
    this.notice = 'Waiting for actual native lifetime'; this.closed = false; this.pending = null;
    this.details = false; this.valid = false;
  }
  async context() {
    if (this.closed) throw Error('Companion closed');
    const value = validateContext(this.binding, await this.query(this.binding, { token: this.binding.token, operation: 'context' }));
    if (this.closed) throw Error('Companion closed');
    // A pending native file may become persistent once, never silently replace its inode.
    if (value.file) this.binding.file = value.file;
    return value;
  }
  invalidate() {
    this.valid = false; this.children?.close();
    this.notice = 'UNAVAILABLE — actual native session/file/lifetime lost; reopen from the intended native session';
    this.changed();
  }
  tick() {
    if (this.closed || this.pending || this.invalidated) return this.pending;
    this.pending = (async () => {
      try {
        const value = await this.context();
        if (!this.children) {
          this.children = this.reader(this.binding.primary.cwd, this.changed);
          void this.children.start(value.primary.sessionId, value.primary.sessionFile);
        }
        this.valid = true;
        this.children.tick(); await this.children.pending;
        // A response admitted while native lifetime was being replaced cannot publish authority.
        await this.context();
        this.updated = Date.now(); this.notice = this.children.notice; this.changed();
      } catch { if (!this.closed) { this.invalidated = true; this.invalidate(); } }
    })().finally(() => { this.pending = null; });
    return this.pending;
  }
  action(type) {
    if (type === 'details') this.details = !this.details;
    else if (this.valid && !this.closed) this.children?.action(type);
    this.changed();
  }
  /** Publish only verified identity/lifetime metadata; never use progress as log authority */
  async selection() {
    const context = await this.context();
    if (!this.valid || this.invalidated || !this.updated || Date.now() - this.updated > 1500) throw Error('Selection not freshly verified');
    const children = this.children, item = children.sources.get(children.selected);
    const worktree = children.verifiedWorktree;
    return { version: 1, generation: context.generation, primary: context.primary, file: context.file, at: Date.now(),
      state: !children.open ? 'closed' : item && !worktree ? 'unavailable' : item ? 'selected' : 'idle',
      worktree: !children.open ? null : item ? worktree : context.primary.cwd,
      child: children.open && item && worktree ? { generation: item.generation, id: item.id, worktree, lifecycle: item.lifecycle, state: children.active(item) ? item.state : 'unknown-or-inactive', pinned: children.pin !== null } : null };
  }
  view() {
    const children = this.children, item = children?.sources.get(children.selected);
    if (!this.valid) return ['CHILD ACTIVITY — observation only', this.notice, 'No execution controls. Ordinary Pi/children unchanged.'].join('\n');
    if (this.details) return children.view();
    const ordinal = [...children.sources.keys()].indexOf(children.selected) + 1;
    const status = item ? `${children.pin ? 'PINNED' : 'FOLLOW'} ${ordinal}/${children.sources.size} ${basename(item.identity.attempt)}` : 'IDLE — no eligible active child';
    const lifecycle = item ? `${item.lifecycle}${item.exitCode === null ? '' : ` exit ${item.exitCode} (not acceptance)`} · ${item.verified ? (children.active(item) ? item.state : 'unknown/inactive liveness') : 'CUSTODY LOST'}` : 'Unregistered/unsupported children unavailable';
    return [`CHILD ACTIVITY${children.paused ? ` · PAUSED +${Math.max(0, (item?.sequence ?? 0) - children.pausedSequence)}` : ' — human only'}`, `${children.open ? '' : 'READERS CLOSED · '}${status}`, lifecycle,
      item ? `${item.identity.role} ${item.identity.task}` : 'No execution controls',
      `[ ] choose · f pin · p pause · c readers · r rediscover · i details`,
      `Primary ${clean(children.session)}`,
      ...(item ? [`Worktree ${item.identity.worktree}`, `Attempt ${item.identity.attempt}`,
        `Child ${item.id} · generation ${item.generation}`,
        `Omitted ${item.omitted} · pipe gaps ${item.gaps ?? 'unknown'}${children.paused ? ` · PAUSED, ${Math.max(0, item.sequence - children.pausedSequence)} subsequent records drained` : ''}`] : []),
      children.notice, ...(children.paused ? children.frozen ?? [] : item?.history ?? [])].join('\n');
  }
  close() { this.closed = true; this.valid = false; this.children?.close(); }
}

/** Check the log consumer's expected parent and companion generation, not editable labels */
export async function readSelection(binding, companion) {
  validateContext(binding, await queryLocal(binding, { token: binding.token, operation: 'context' }));
  return validateSelection(binding, await queryLocal(companion, { token: companion.token, operation: 'selection', generation: binding.generation }));
}

export function validateSelection(binding, value, now = Date.now()) {
  validateContext(binding, value);
  if (!Number.isSafeInteger(now) || Math.abs(now - value.at) > 1500 || !['idle', 'selected', 'closed', 'unavailable'].includes(value.state)) throw Error('Invalid selection metadata');
  if (value.state === 'selected') {
    const item = value.child;
    if (!item || typeof item.worktree !== 'string' || realpathSync(item.worktree) !== item.worktree || !/^[a-f0-9-]{36}$/.test(item.id) || !/^[a-f0-9-]{36}$/.test(item.generation) || value.worktree !== item.worktree || typeof item.pinned !== 'boolean' ||
        !['starting', 'connected', 'unknown-or-inactive'].includes(item.state) ||
        !['starting', 'running', 'settled-awaiting-exit', 'completed', 'failed'].includes(item.lifecycle)) throw Error('Invalid selected child');
  } else if (value.child !== null || (value.state === 'idle' ? value.worktree !== binding.primary.cwd : value.worktree !== null)) throw Error('Unavailable metadata has no source authority');
  if (!same(value.primary, binding.primary)) throw Error('Cross-parent selection');
  return { version: 1, generation: value.generation, primary: { ...binding.primary }, file: value.file, at: value.at,
    state: value.state, worktree: value.worktree,
    child: value.child === null ? null : Object.fromEntries(['generation', 'id', 'worktree', 'lifecycle', 'state', 'pinned'].map(key => [key, value.child[key]])) };
}
