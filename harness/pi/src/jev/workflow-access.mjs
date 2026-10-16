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
  }
  select(value) {
    const key = normalize(value);
    const role = Object.hasOwn(roles, key) ? roles[key] : null;
    if (!role) throw new Unavailable('unknown-workflow-role');
    if (role !== this.role) { this.role = role; this.revision++; }
  }
  input(text, source) {
    if (source !== 'interactive') return;
    const selected = /^\/skill:(work|review|qa|land)(?:\s|$)/.exec(text);
    if (selected) this.select(selected[1]);
  }
  assertWrite(path, root) {
    if (!path.startsWith(root + sep)) throw new Unavailable('write-outside-project');
    if (['reviewer', 'qa'].includes(this.role) && !path.startsWith(join(root, '.runs') + sep)) {
      throw new Unavailable('workflow-write-restricted');
    }
  }
}
