import { randomUUID } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { clean } from '../workspace/presentation.mjs';

export const upstreamTerminal = new Set(['completed', 'steered', 'aborted', 'stopped', 'error']);
const text = value => clean(typeof value === 'string' ? value.slice(-2400) : '').replace(/\n/g, ' ').slice(-600);
// Only finalized assistant prose from the live SDK. Never reasoning, tool bodies or transcript files.
const assistantText = message => Array.isArray(message?.content)
  ? text(message.content.slice(-16).filter(part => part.type === 'text').map(part => text(part.text)).join(' ')) : '';
function lastText(session) {
  const messages = session.messages;
  if (!Array.isArray(messages)) return '';
  for (let i = messages.length - 1; i >= Math.max(0, messages.length - 16); i--) {
    const message = messages[i];
    if (message?.role === 'assistant' && Array.isArray(message.content)) {
      return assistantText(message);
    }
  }
  return '';
}

/** Subscribe to upstream sessions without owning execution or reading transcript files */
export class SubagentObserver {
  constructor(parent, send, { registry = () => globalThis[Symbol.for('pi-subagents:manager')], now = Date.now, changed = () => {} } = {}) {
    this.parent = parent; this.send = send; this.registry = registry; this.now = now; this.changed = changed;
    this.runs = new Map(); this.closed = false; this.notice = 'Waiting for supported Agent-tool activity';
  }
  inspect() {
    return { capability: typeof this.registry()?.getRecord === 'function' ? 'live-session-observation' : 'manager-unavailable',
      active: this.runs.size, closed: this.closed, notice: this.notice };
  }
  diagnose(reason) { this.notice = reason; this.changed(); }
  refused(message) {
    if (this.closed || !/^[a-f0-9-]{36}$/.test(message?.id) ||
        !['observation-capacity-eight', 'subagent-admission-refused'].includes(message.reason)) return;
    for (const [id, run] of this.runs) if (run.id === message.id) this.remove(id);
    this.diagnose(`${message.reason}: display unavailable; SDK agent execution unchanged`);
  }
  resuming(id) {
    if (this.closed || typeof id !== 'string' || this.runs.has(id)) return;
    // Foreground resume emits no upstream started event. Tool input is ONLY a lookup candidate:
    // tick still requires exact root ownership, stable record/session and an actual new run.
    const record = this.registry()?.getRecord?.(id);
    if (!record || record.status === 'running' || record.status === 'queued') return;
    this.started({ id }, { previousStarted: record.startedAt });
  }
  started(event, resume) {
    if (this.closed || typeof event?.id !== 'string' || !event.id || event.id.length > 200) return;
    this.remove(event.id);
    if (this.runs.size >= 8) { this.diagnose('observation-capacity-eight'); return; }
    this.runs.set(event.id, { id: randomUUID(), since: this.now(), sequence: 0, unsubscribe: null, session: null, resume });
    this.tick();
  }
  remove(id, reason) {
    const run = this.runs.get(id);
    try { run?.unsubscribe?.(); } catch { /* Observation cannot affect execution. */ }
    this.runs.delete(id);
    if (reason) this.diagnose(reason);
  }
  emit(run, message, status = 'running') {
    if (!run.identity) return;
    const lifecycle = upstreamTerminal.has(status) ? (['completed', 'steered'].includes(status) ? 'completed' : 'failed') : 'running';
    this.send({ type: 'subagent-progress', id: run.id, parent: this.parent, identity: run.identity,
      sequence: ++run.sequence, lifecycle, exitCode: null, upstreamStatus: status, text: text(message) });
  }
  tick() {
    if (this.closed) return;
    for (const [id, run] of this.runs) {
      try {
        const record = this.registry()?.getRecord?.(id);
        if (!record) { this.remove(id, 'record-unavailable'); continue; }
        if (!record.rootSessionId) { this.remove(id, 'root-association-unavailable: use the supported Agent tool, not RPC/direct spawn'); continue; }
        if (record.rootSessionId !== this.parent || record.parentAgentId !== undefined || record.workflowId !== undefined) {
          this.remove(id, 'foreign-nested-or-workflow-record-refused'); continue;
        }
        if (run.record && run.record !== record) { this.remove(id, 'record-replaced'); continue; }
        run.record = record;
        if (run.resume) {
          if (record.status !== 'running' && (!Number.isFinite(record.startedAt) || record.startedAt === run.resume.previousStarted)) {
            if (this.now() - run.since > 5000) this.remove(id, 'resume-transition-timeout');
            continue;
          }
          run.resume = null;
        }
        const session = record.session;
        if (!session) {
          if (upstreamTerminal.has(record.status)) this.remove(id, 'agent-ended-before-session-available');
          else if (this.now() - run.since > 5000) this.remove(id, 'session-creation-timeout');
          continue;
        }
        if (run.session && run.session !== session) { this.remove(id, 'session-replaced'); continue; }
        if (!run.session) {
          if (typeof session.subscribe !== 'function' || typeof session.sessionManager?.getCwd !== 'function') {
            this.remove(id, 'live-session-api-unavailable'); continue;
          }
          run.session = session;
          run.identity = { task: 'Unassigned', role: text(record.type) || 'subagent', provider: text(session.model?.provider) || 'unknown',
            model: text(session.model?.id) || 'unknown', thinking: text(session.thinkingLevel) || 'unknown',
            worktree: realpathSync(session.sessionManager.getCwd()), attempt: id };
          this.emit(run, `Observing ${text(record.description)}`);
          if (this.closed || this.runs.get(id) !== run) continue;
          run.unsubscribe = session.subscribe(event => {
            try {
              if (this.closed || this.runs.get(id) !== run) return;
              if (event.type === 'message_update' && event.assistantMessageEvent?.type === 'text_delta') {
                run.pending = text((run.pending ?? '') + text(event.assistantMessageEvent.delta));
              } else if (event.type === 'message_end' && event.message?.role === 'assistant') {
                run.pending = assistantText(event.message) || run.pending;
              } else if (event.type === 'tool_execution_start' || event.type === 'tool_execution_end') {
                run.pending = text(`${run.pending || ''} · ${event.type === 'tool_execution_start' ? 'Tool' : 'Finished'}: ${text(event.toolName)}`);
              }
            } catch { this.remove(id, 'session-observation-unavailable'); }
          });
          // Startup precedes session creation upstream; recover bounded finalized output missed before attachment.
          run.pending = lastText(session);
          this.diagnose('Observing supported top-level Agent session');
        }
        if (upstreamTerminal.has(record.status)) {
          this.emit(run, lastText(session) || run.pending || `Upstream ${record.status}; no assistant text available`, record.status);
          this.remove(id);
        } else {
          this.emit(run, run.pending || 'Running'); run.pending = '';
        }
      } catch { this.remove(id, 'session-observation-unavailable'); }
    }
  }
  close() {
    if (this.closed || this.closing) return;
    this.closing = true;
    // Capture already-finalized outcomes before detaching. No prompt, abort or session.dispose call exists here.
    this.tick(); this.closed = true;
    for (const id of this.runs.keys()) this.remove(id);
  }
}
