import { realpathSync } from 'node:fs';
import { agentDetail, agentList } from '../workspace/agent-presentation.mjs';
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
    if (type === 'details' || type === 'enter') this.details = true;
    else if (type === 'back') this.details = false;
    else if (type === 'help') this.help = !this.help;
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
    const children = this.children;
    if (!this.valid) return 'UNAVAILABLE · agent activity';
    const counts = [...children.sources.values()].reduce((n, item) => { if (children.active(item)) n.live++; else if (children.completed(item)) n.done++; return n; }, { live: 0, done: 0 });
    return [this.details ? agentDetail(children)[0] : `${counts.live} live · ${counts.done} finished${children.removed ? ` · lost/evicted ${children.removed}` : ''}${children.open ? '' : ' · readers closed'}`,
      ...(this.help ? ['Recent agent messages and provider reasoning summaries.',
        'Selection stays in this pane. Use the picker below Pi’s prompt for main.',
        '↑↓ or [ ] choose · PgUp/PgDn scroll · c reader · r rediscover · q exit',
        `Primary ${clean(children.session)}`, children.notice] : []),
      ...(this.details ? agentDetail(children).slice(1) : children.sources.size ? agentList(children) : ['Waiting for agents'])].join('\n');
  }
  status() {
    if (!this.valid) return this.notice;
    const notice = this.children.notice;
    if (this.help || /^(Trusted-local child association verified|No eligible monitor association)/.test(notice)) return '';
    return notice;
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
