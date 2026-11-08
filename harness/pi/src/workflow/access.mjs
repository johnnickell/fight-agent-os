import { isAbsolute, resolve } from 'node:path';
import { normalizeRole, stageRole } from '../stage-roles.mjs';

class Unavailable extends Error {
  constructor(code) { super(code); this.code = code; }
}
export const skillEffects = Object.freeze({
  work: 'source', review: 'review-report', qa: 'qa-report', land: 'delivery', build: 'coordination', sync: 'coordination',
  next: 'inspect', fix: 'bug-planning', audit: 'inspect', architecture: 'inspect', 'security-audit': 'inspect',
  adopt: 'inspect', 'design-review': 'inspect', grill: 'epic-planning', 'to-tickets': 'ticket-planning',
  'to-tasks': 'task-planning', wayfinder: 'map-planning', research: 'research', design: 'scratch',
  prototype: 'scratch', 'writing-for-agents': 'instructions', graphify: 'graph', release: 'release',
});
const resolvesCheckout = skill => skill === 'build' || skill === 'sync';
const skillRole = skill => ({ work: 'engineer', review: 'reviewer', qa: 'qa', land: 'land', build: 'qa', sync: 'qa' })[skill] ?? 'reviewer';
const subject = value => typeof value === 'string' && /^(TASK|TICKET|EPIC|WF)-\d{3,5}$/.test(value);
const releaseVersion = value => typeof value === 'string' && value.length <= 96 ?
  /^v?((?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?)$/.exec(value)?.[1] ?? null : null;
const scopedSubject = (skill, value) => skill === 'sync' ? typeof value === 'string' && /^TASK-\d{5}$/.test(value) : skill === 'release' ? releaseVersion(value) !== null : subject(value) || (!['work', 'review', 'qa', 'land', 'build'].includes(skill) &&
  typeof value === 'string' && /^[A-Za-z0-9][A-Za-z0-9._-]{0,95}$/.test(value));
/** Bind workflow responsibilities to a launcher or explicit operator, never model-authored input */
export class WorkflowAccess {
  constructor(phase = 'local', scope = {}) {
    this.role = 'engineer'; this.revision = 0;
    this.defaultCheckout = scope.checkout ?? null;
    this.skill = 'work'; this.taskId = scope.taskId ?? null; this.checkout = this.defaultCheckout; this.mode = scope.mode ?? 'default';
    if (phase !== 'local') this.select(phase);
    this.launcher = phase === 'local' ? null : this.role;
  }
  /** Restore current authority from append order, never from the navigated conversation branch */
  static restore({ phase, sessionId, entries, parentSession, taskId, checkout, mode }) {
    const access = new WorkflowAccess(phase, { taskId, checkout, mode });
    // An explicit launcher is a new trusted selection on every startup/reload.
    if (access.launcher !== null) return access;
    const prior = [...entries].reverse().find(entry => entry.type === 'custom' &&
      ['fight-workflow-role-v1', 'fight-workflow-role-v2', 'fight-workflow-role-v3'].includes(entry.customType));
    const data = prior?.data;
    const checkoutRoot = data?.checkoutRoot ?? data?.syncRoot;
    if (['fight-workflow-role-v2', 'fight-workflow-role-v3'].includes(prior?.customType) && data?.sessionId === sessionId &&
      data.launcher === null && stageRole(data.role) === data.role) {
      access.select(data.role);
      if (prior.customType === 'fight-workflow-role-v3' && Object.hasOwn(skillEffects, data.skill) &&
          skillRole(data.skill) === data.role && (data.taskId === null || scopedSubject(data.skill, data.taskId)) &&
          ((typeof data.checkout === 'string' && isAbsolute(data.checkout)) ||
            (resolvesCheckout(data.skill) && data.taskId !== null && data.checkout === null &&
              typeof checkoutRoot === 'string' && isAbsolute(checkoutRoot) && checkoutRoot === checkout)) &&
          ['default', 'query', 'status'].includes(data.mode)) {
        access.skill = data.skill; access.taskId = data.taskId; access.checkout = data.checkout; access.mode = data.mode;
      } else if (prior.customType === 'fight-workflow-role-v3') access.select('review');
    }
    else if (prior || parentSession || entries.some(entry => ['message', 'custom_message'].includes(entry.type))) {
      // Forked/legacy/conflicting provenance is readable but cannot inherit source-write authority.
      access.select('reviewer');
    }
    return access;
  }
  checkpoint(sessionId, role = this.role) {
    const current = this.pending && this.pending.role === role ? this.pending : this;
    return { sessionId, role, launcher: this.launcher,
      ...(current.checkout || (resolvesCheckout(current.skill) && current.role === role) ?
        { skill: current.role === role ? current.skill : 'review', taskId: current.taskId,
          checkout: current.checkout, mode: current.mode,
          ...(current.checkout === null ? { checkoutRoot: this.defaultCheckout } : {}) } : {}) };
  }
  select(value, persist = () => {}) {
    const key = normalizeRole(value);
    const role = Object.hasOwn(skillEffects, key) ? skillRole(key) : key === 'team-lead' ? 'qa' : stageRole(key);
    if (!role) throw new Unavailable('unknown-workflow-role');
    const skill = Object.hasOwn(skillEffects, key) ? key : key === 'team-lead' ? 'build' : ({ engineer: 'work', reviewer: 'review', qa: 'qa', land: 'land' })[role];
    const next = { role, skill, taskId: this.taskId, checkout: this.checkout ?? this.defaultCheckout,
      mode: this.mode, ...this.requested };
    this.pending = next;
    try { persist(role); } finally { this.pending = undefined; }
    if (['role', 'skill', 'taskId', 'checkout', 'mode'].some(key => next[key] !== this[key])) {
      Object.assign(this, next); this.revision++;
    }
  }
  input(text, source, persist) {
    if (source !== 'interactive') return;
    const selected = /^\/skill:([a-z-]+)(?:\s|$)([\s\S]*)$/.exec(text);
    if (!selected) return;
    if (!Object.hasOwn(skillEffects, selected[1])) throw new Unavailable('unknown-workflow-role');
    // Sync selects exactly one TASK; extra subjects/options must not broaden integration authority.
    const sync = selected[1] === 'sync';
    const syncInput = sync ? /^\s*(TASK-\d{5})(?:\s+--checkout\s+("[^"\r\n]+"|'[^'\r\n]+'|\/[^\s"']+))?\s*$/.exec(selected[2]) : null;
    if (sync && !syncInput) throw new Unavailable('workflow-subject-required');
    const taskId = sync ? syncInput[1] : selected[1] === 'release' ? (() => {
      const version = selected[2].trim().split(/\s+/)[0];
      if (!version) return null;
      const normalized = releaseVersion(version);
      if (!normalized) throw new Unavailable('workflow-subject-required');
      return normalized;
    })() : selected[2].match(/\b(?:TASK|TICKET|EPIC|WF)-\d{3,5}\b/)?.[0] ??
      (/^[A-Za-z0-9][A-Za-z0-9._-]{0,95}(?:\s|$)/.exec(selected[2])?.[0].trim() ?? null);
    if (taskId !== null && !scopedSubject(selected[1], taskId)) throw new Unavailable('workflow-subject-required');
    const checkout = (sync ? syncInput[2] : /(?:^|\s)--checkout\s+(.+)$/.exec(selected[2])?.[1])?.replace(/^(?:"(.*)"|'(.*)')$/, '$1$2');
    if (checkout !== undefined && (!isAbsolute(checkout) || /[\x00-\x1f\x7f]/.test(checkout))) throw new Unavailable('workflow-checkout-required');
    this.requested = { taskId, checkout: checkout ? resolve(checkout) :
      resolvesCheckout(selected[1]) && taskId !== null ? null : this.defaultCheckout,
      mode: sync ? 'default' : /\b(query|trace)\b/.test(selected[2]) ? 'query' : /\b(status|preview|dry-run)\b/.test(selected[2]) ? 'status' : 'default' };
    try { this.select(selected[1], persist); } finally { this.requested = undefined; }
    return true;
  }
}
