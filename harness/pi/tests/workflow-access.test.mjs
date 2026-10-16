import test from 'node:test';
import assert from 'node:assert/strict';
import { WorkflowAccess } from '../src/jev/workflow-access.mjs';
import { randomUUID } from 'node:crypto';

const entry = data => ({ type: 'custom', customType: 'fight-workflow-role-v2', data });

test('restore uses session-wide append order and a trusted launcher overrides historical roles', () => {
  const sessionId = randomUUID();
  const entries = [entry({ sessionId, role: 'land', launcher: null }), entry({ sessionId, role: 'reviewer', launcher: null })];
  assert.equal(WorkflowAccess.restore({ sessionId, entries }).role, 'reviewer');
  assert.equal(WorkflowAccess.restore({ sessionId, entries: entries.slice(0, 1), phase: 'review' }).role, 'reviewer');
  assert.equal(WorkflowAccess.restore({ sessionId, entries, phase: 'qa' }).role, 'qa');
  assert.equal(WorkflowAccess.restore({ sessionId, entries, phase: 'work' }).role, 'engineer');
  assert.throws(() => WorkflowAccess.restore({ sessionId, entries, phase: 'unknown' }), { code: 'unknown-workflow-role' });
});

test('forks, legacy or conflicting provenance cannot recover privilege without operator selection', () => {
  const sessionId = randomUUID(), parentId = randomUUID();
  for (const entries of [
    [entry({ sessionId: parentId, role: 'land', launcher: null })],
    [{ type: 'custom', customType: 'fight-workflow-role-v1', data: { role: 'land' } }],
    [entry({ sessionId, role: 'land', launcher: 'land' })],
    [entry({ sessionId, role: 'land' })],
    [entry({ sessionId, role: 'unknown', launcher: null })],
  ]) assert.equal(WorkflowAccess.restore({ sessionId, entries }).role, 'reviewer');
  assert.equal(WorkflowAccess.restore({ sessionId, entries: [], parentSession: '/parent/session.jsonl' }).role, 'reviewer');
  assert.equal(WorkflowAccess.restore({ sessionId, entries: [{ type: 'message' }] }).role, 'reviewer');
  assert.equal(WorkflowAccess.restore({ sessionId, entries: [] }).role, 'engineer');
  const fork = WorkflowAccess.restore({ sessionId, entries: [], parentSession: '/parent/session.jsonl' });
  fork.input('/skill:land TASK-00488', 'rpc'); assert.equal(fork.role, 'reviewer');
  fork.input('/skill:land TASK-00488', 'interactive');
  assert.equal(WorkflowAccess.restore({ sessionId, entries: [entry(fork.checkpoint(sessionId))], parentSession: '/parent/session.jsonl' }).role, 'land');
});

test('selection is persisted before authority changes and failed persistence cannot promote writes', () => {
  const access = new WorkflowAccess('review');
  const revision = access.revision;
  assert.throws(() => access.select('land', () => { throw Error('Cannot persist'); }), /Cannot persist/);
  assert.equal(access.role, 'reviewer'); assert.equal(access.revision, revision);
  assert.throws(() => access.assertWrite('/project/source.php', '/project'), { code: 'workflow-write-restricted' });
  let persisted;
  access.input('/skill:land TASK-00488', 'interactive', role => { persisted = role; assert.equal(access.role, 'reviewer'); });
  assert.equal(persisted, 'land'); assert.equal(access.role, 'land'); assert.equal(access.revision, revision + 1);
});

test('operator workflow selections bind role writes without model or RPC promotion', () => {
  const access = new WorkflowAccess('review');
  access.assertWrite('/project/.runs/reviews/report.md', '/project');
  for (const path of ['/project/source.php', '/project/.runs-other/report.md', '/other/.runs/report.md']) {
    assert.throws(() => access.assertWrite(path, '/project'));
  }
  for (const source of ['extension', 'rpc']) access.input('/skill:work TASK-00001', source);
  assert.equal(access.role, 'reviewer');
  access.input('/skill:work TASK-00001', 'interactive');
  assert.equal(access.role, 'engineer');
  access.assertWrite('/project/source.php', '/project');
  access.input('/skill:qa TASK-00001', 'interactive');
  assert.throws(() => access.assertWrite('/project/source.php', '/project'), { code: 'workflow-write-restricted' });
  access.select('land');
  access.assertWrite('/project/source.php', '/project');
  for (const value of ['anything', 'constructor', '__proto__']) {
    assert.throws(() => access.select(value), { code: 'unknown-workflow-role' });
    assert.throws(() => new WorkflowAccess(value), { code: 'unknown-workflow-role' });
  }
  assert.equal(new WorkflowAccess('Senior Engineer').role, 'reviewer');
  assert.equal(new WorkflowAccess('QA-Engineer').role, 'qa');
  assert.equal(new WorkflowAccess('Release Manager').role, 'land');
});
