import { randomUUID } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { clean } from '../workspace/presentation.mjs';

const terminal = new Set(['completed', 'steered', 'aborted', 'stopped', 'error']);
const text = value => clean(typeof value === 'string' ? value.slice(0, 2400) : '').replace(/\n/g, ' ').slice(0, 600);

/** Subscribe to upstream sessions without owning execution or reading transcript files */
export class SubagentObserver {
  constructor(parent, send, { registry = () => globalThis[Symbol.for('pi-subagents:manager')], now = Date.now } = {}) {
    this.parent = parent; this.send = send; this.registry = registry; this.now = now;
    this.runs = new Map(); this.closed = false;
  }
  started(event) {
    if (this.closed || typeof event?.id !== 'string' || event.id.length > 200) return;
    this.remove(event.id);
    if (this.runs.size >= 8) return;
    this.runs.set(event.id, { id: randomUUID(), since: this.now(), sequence: 0, unsubscribe: null, session: null });
    this.tick();
  }
  remove(id) {
    const run = this.runs.get(id);
    try { run?.unsubscribe?.(); } catch { /* Observation cannot affect execution. */ }
    this.runs.delete(id);
  }
  emit(run, message, lifecycle = 'running') {
    if (!run.identity) return;
    this.send({ type: 'subagent-progress', id: run.id, parent: this.parent, identity: run.identity,
      sequence: ++run.sequence, lifecycle, exitCode: lifecycle === 'completed' ? 0 : lifecycle === 'failed' ? 1 : null,
      text: text(message) });
  }
  tick() {
    if (this.closed) return;
    for (const [id, run] of this.runs) {
      try {
        const record = this.registry()?.getRecord?.(id);
        if (!record || record.rootSessionId !== this.parent || record.parentAgentId !== undefined || record.workflowId !== undefined) {
          this.remove(id); continue;
        }
        const session = record.session;
        if (!session) {
          if (this.now() - run.since > 5000 || terminal.has(record.status)) this.remove(id);
          continue;
        }
        if (run.session && run.session !== session) { this.remove(id); continue; }
        if (!run.session) {
          if (typeof session.subscribe !== 'function' || typeof session.sessionManager?.getCwd !== 'function') { this.remove(id); continue; }
          run.session = session;
          run.identity = { task: 'Unassigned', role: text(record.type) || 'subagent', provider: text(session.model?.provider) || 'unknown',
            model: text(session.model?.id) || 'unknown', thinking: text(session.thinkingLevel) || 'unknown',
            worktree: realpathSync(session.sessionManager.getCwd()), attempt: id };
          this.emit(run, `Observing ${text(record.description)}`);
          run.unsubscribe = session.subscribe(event => {
            try {
              if (this.closed || this.runs.get(id) !== run) return;
              // Never forward reasoning, arguments, credentials or arbitrary tool result bodies.
              if (event.type === 'message_update' && event.assistantMessageEvent?.type === 'text_delta') {
                run.pending = ((run.pending ?? '') + text(event.assistantMessageEvent.delta)).slice(-600);
              } else if (event.type === 'tool_execution_start' || event.type === 'tool_execution_end') {
                run.pending = `${event.type === 'tool_execution_start' ? 'Tool' : 'Finished'}: ${text(event.toolName)}`;
              }
            } catch { this.remove(id); }
          });
        }
        if (terminal.has(record.status)) {
          this.emit(run, record.result ?? record.error ?? record.status, ['completed', 'steered'].includes(record.status) ? 'completed' : 'failed');
          this.remove(id);
        } else {
          this.emit(run, run.pending ?? 'Running'); run.pending = '';
        }
      } catch { this.remove(id); }
    }
  }
  close() {
    this.closed = true;
    for (const id of this.runs.keys()) this.remove(id);
  }
}
