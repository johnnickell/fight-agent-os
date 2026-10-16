import { join, sep } from 'node:path';
import { Unavailable } from './readiness.mjs';

const roles = { work: 'engineer', engineer: 'engineer', 'software-engineer': 'engineer',
  review: 'reviewer', reviewer: 'reviewer', 'senior-engineer': 'reviewer',
  qa: 'qa', 'qa-engineer': 'qa', build: 'qa', 'team-lead': 'qa', land: 'land', 'release-manager': 'land' };
const normalize = value => typeof value === 'string' ? value.trim().toLowerCase().replace(/\s+/g, '-') : '';

/** Bind file effects to a launcher or explicit operator workflow, never model-authored tool input */
export class WorkflowAccess {
  constructor(phase = 'local') {
    this.role = 'engineer'; this.revision = 0;
    if (phase !== 'local') this.select(phase);
    this.launcher = phase === 'local' ? null : this.role;
  }
  /** Restore current authority from append order, never from the navigated conversation branch */
  static restore({ phase, sessionId, entries, parentSession }) {
    const access = new WorkflowAccess(phase);
    // An explicit launcher is a new trusted selection on every startup/reload.
    if (access.launcher !== null) return access;
    const prior = [...entries].reverse().find(entry => entry.type === 'custom' &&
      ['fight-workflow-role-v1', 'fight-workflow-role-v2'].includes(entry.customType));
    const data = prior?.data;
    if (prior?.customType === 'fight-workflow-role-v2' && data?.sessionId === sessionId &&
      data.launcher === null && Object.values(roles).includes(data.role)) access.select(data.role);
    else if (prior || parentSession || entries.some(entry => ['message', 'custom_message'].includes(entry.type))) {
      // Forked/legacy/conflicting provenance is readable but cannot inherit source-write authority.
      access.select('reviewer');
    }
    return access;
  }
  checkpoint(sessionId, role = this.role) {
    return { sessionId, role, launcher: this.launcher };
  }
  select(value, persist = () => {}) {
    const key = normalize(value);
    const role = Object.hasOwn(roles, key) ? roles[key] : null;
    if (!role) throw new Unavailable('unknown-workflow-role');
    persist(role);
    if (role !== this.role) { this.role = role; this.revision++; }
  }
  input(text, source, persist) {
    if (source !== 'interactive') return;
    const selected = /^\/skill:(work|review|qa|land)(?:\s|$)/.exec(text);
    if (selected) this.select(selected[1], persist);
  }
  assertWrite(path, root) {
    if (!path.startsWith(root + sep)) throw new Unavailable('write-outside-project');
    if (['reviewer', 'qa'].includes(this.role) && !path.startsWith(join(root, '.runs') + sep)) {
      throw new Unavailable('workflow-write-restricted');
    }
  }
}
