import { isAbsolute, join, relative, resolve, sep } from 'node:path';
import { Unavailable } from './readiness.mjs';

const roles = { work: 'engineer', engineer: 'engineer', 'software-engineer': 'engineer',
  review: 'reviewer', reviewer: 'reviewer', 'senior-engineer': 'reviewer',
  qa: 'qa', 'qa-engineer': 'qa', build: 'qa', 'team-lead': 'qa', land: 'land', 'release-manager': 'land' };
const normalize = value => typeof value === 'string' ? value.trim().toLowerCase().replace(/\s+/g, '-') : '';
export const skillEffects = Object.freeze({
  work: 'source', review: 'review-report', qa: 'qa-report', land: 'delivery', build: 'coordination',
  next: 'inspect', fix: 'bug-planning', audit: 'inspect', architecture: 'inspect', 'security-audit': 'inspect',
  adopt: 'inspect', 'design-review': 'inspect', grill: 'epic-planning', 'to-tickets': 'ticket-planning',
  'to-tasks': 'task-planning', wayfinder: 'map-planning', research: 'research', design: 'scratch',
  prototype: 'scratch', 'writing-for-agents': 'instructions', graphify: 'graph', release: 'release',
});
const skillRole = skill => ({ work: 'engineer', review: 'reviewer', qa: 'qa', land: 'land', build: 'qa' })[skill] ?? 'reviewer';
const subject = value => typeof value === 'string' && /^(TASK|TICKET|EPIC|WF)-\d{3,5}$/.test(value);
const releaseVersion = value => typeof value === 'string' && value.length <= 96 ?
  /^v?((?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?)$/.exec(value)?.[1] ?? null : null;
const scopedSubject = (skill, value) => skill === 'release' ? releaseVersion(value) !== null : subject(value) || (!['work', 'review', 'qa', 'land', 'build'].includes(skill) &&
  typeof value === 'string' && /^[A-Za-z0-9][A-Za-z0-9._-]{0,95}$/.test(value));
const within = (path, root) => path === root || path.startsWith(root + sep);

/** Bind file effects to a launcher or explicit operator workflow, never model-authored tool input */
export class WorkflowAccess {
  constructor(phase = 'local', scope = {}) {
    this.role = 'engineer'; this.revision = 0;
    this.skill = 'work'; this.taskId = scope.taskId ?? null; this.checkout = scope.checkout ?? null; this.mode = scope.mode ?? 'default';
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
    if (['fight-workflow-role-v2', 'fight-workflow-role-v3'].includes(prior?.customType) && data?.sessionId === sessionId &&
      data.launcher === null && Object.values(roles).includes(data.role)) {
      access.select(data.role);
      if (prior.customType === 'fight-workflow-role-v3' && Object.hasOwn(skillEffects, data.skill) &&
          skillRole(data.skill) === data.role && (data.taskId === null || scopedSubject(data.skill, data.taskId)) &&
          typeof data.checkout === 'string' && isAbsolute(data.checkout) && ['default', 'query', 'status'].includes(data.mode)) {
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
      ...(this.checkout ? { skill: current.role === role ? current.skill : 'review', taskId: current.taskId,
        checkout: current.checkout, mode: current.mode } : {}) };
  }
  select(value, persist = () => {}) {
    const key = normalize(value);
    const role = Object.hasOwn(skillEffects, key) ? skillRole(key) : Object.hasOwn(roles, key) ? roles[key] : null;
    if (!role) throw new Unavailable('unknown-workflow-role');
    const skill = Object.hasOwn(skillEffects, key) ? key : key === 'team-lead' ? 'build' : ({ engineer: 'work', reviewer: 'review', qa: 'qa', land: 'land' })[role];
    const next = { role, skill, taskId: this.taskId, checkout: this.checkout, mode: this.mode, ...this.requested };
    this.pending = next;
    try { persist(role); } finally { this.pending = undefined; }
    if (['role', 'skill', 'taskId', 'checkout', 'mode'].some(key => next[key] !== this[key])) {
      Object.assign(this, next); this.revision++;
    }
  }
  input(text, source, persist) {
    if (source !== 'interactive') return;
    const selected = /^\/skill:([a-z-]+)(?:\s|$)(.*)/.exec(text);
    if (!selected) return;
    if (!Object.hasOwn(skillEffects, selected[1])) throw new Unavailable('unknown-workflow-role');
    const taskId = selected[1] === 'release' ? (() => {
      const version = selected[2].trim().split(/\s+/)[0];
      if (!version) return null;
      const normalized = releaseVersion(version);
      if (!normalized) throw new Unavailable('workflow-subject-required');
      return normalized;
    })() : selected[2].match(/\b(?:TASK|TICKET|EPIC|WF)-\d{3,5}\b/)?.[0] ??
      (/^[A-Za-z0-9][A-Za-z0-9._-]{0,95}(?:\s|$)/.exec(selected[2])?.[0].trim() ?? null);
    if (taskId !== null && !scopedSubject(selected[1], taskId)) throw new Unavailable('workflow-subject-required');
    const checkout = /(?:^|\s)--checkout\s+(.+)$/.exec(selected[2])?.[1]?.replace(/^(?:"(.*)"|'(.*)')$/, '$1$2');
    if (checkout !== undefined && (!isAbsolute(checkout) || /[\x00-\x1f\x7f]/.test(checkout))) throw new Unavailable('workflow-checkout-required');
    this.requested = { taskId, checkout: checkout ? resolve(checkout) : this.checkout,
      mode: /\b(query|trace)\b/.test(selected[2]) ? 'query' : /\b(status|preview|dry-run)\b/.test(selected[2]) ? 'status' : 'default' };
    try { this.select(selected[1], persist); } finally { this.requested = undefined; }
    return true;
  }
  assertWrite(path, root) {
    if (typeof root !== 'string') return this.#assertScopedWrite(path, root);
    if (!path.startsWith(root + sep)) throw new Unavailable('write-outside-project');
    if (['reviewer', 'qa'].includes(this.role) && !path.startsWith(join(root, '.runs') + sep)) {
      throw new Unavailable('workflow-write-restricted');
    }
  }
  #assertScopedWrite(path, scope) {
    const checkout = this.checkout ?? scope.checkout;
    const taskId = this.taskId ?? scope.taskId;
    if (!checkout || !scope.roots.includes(checkout)) throw new Unavailable('workflow-checkout-unavailable');
    const skill = this.skill, effect = skillEffects[skill];
    if (!effect) throw new Unavailable('unknown-workflow-role');
    const version = skill === 'release' ? releaseVersion(taskId) : null;
    if (skill === 'release' && !version) throw new Unavailable('workflow-subject-required');
    const owned = taskId && scopedSubject(skill, taskId);
    const folder = ({ review: 'reviews', qa: 'qa', build: 'build', research: 'notes',
      design: 'prototypes', prototype: 'prototypes', 'design-review': 'prototypes' })[skill];
    if (owned && [scope.base, checkout].some(root =>
      (folder && within(path, join(root, '.runs', folder, taskId))) ||
      (subject(taskId) && within(path, join(root, '.runs/tasks', taskId, 'runs'))))) return;
    if (version && [scope.base, checkout].some(root => ['handoffs', 'notes'].some(category =>
      within(path, join(root, '.runs', category, `release-${version}`))))) return;
    if (['query', 'status'].includes(this.mode)) throw new Unavailable('workflow-write-restricted');
    const member = scope.roots.filter(root => within(path, root)).sort((a, b) => b.length - a.length)[0];
    if (!member) throw new Unavailable('write-outside-project');
    const local = relative(member, path);
    if (local === '.runs' || local.startsWith('.runs' + sep)) throw new Unavailable('workflow-write-restricted');
    if (effect === 'delivery' && member === scope.base && local.startsWith('planning' + sep)) return;
    if (member !== checkout) throw new Unavailable('workflow-write-restricted');
    if (['source', 'delivery'].includes(effect)) return;
    const prefix = (value) => local.startsWith(value + sep);
    if ((effect === 'bug-planning' || effect === 'task-planning') && (prefix('planning/tasks') || ['planning/ROADMAP.md'].includes(local))) return;
    if (effect === 'epic-planning' && prefix('planning/epics')) return;
    if (effect === 'ticket-planning' && (prefix('planning/tickets') || prefix('planning/epics'))) return;
    if (effect === 'map-planning' && prefix('planning/wayfinder')) return;
    if (effect === 'research' && prefix('planning/wayfinder/research')) return;
    if (effect === 'instructions' && (local === 'AGENTS.md' || prefix('.pi/skills') || prefix('docs/engineering'))) return;
    if (effect === 'graph' && prefix('graphify-out')) return;
    if (effect === 'release' && (local === 'CHANGELOG.md' || local === 'VERSION' || prefix('docs/releases') || prefix('planning'))) return;
    throw new Unavailable('workflow-write-restricted');
  }
}
