import test from 'node:test';
import assert from 'node:assert/strict';
import { WorkflowAccess } from '../src/workflow/access.mjs';
import { randomUUID } from 'node:crypto';

const hasWorkflowSkill = name => ['build', 'land', 'qa', 'release', 'research', 'review', 'sync', 'work'].includes(name);
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
  fork.input('/skill:land TASK-00488', 'rpc', hasWorkflowSkill); assert.equal(fork.role, 'reviewer');
  fork.input('/skill:land TASK-00488', 'interactive', hasWorkflowSkill);
  assert.equal(WorkflowAccess.restore({ sessionId, entries: [entry(fork.checkpoint(sessionId))], parentSession: '/parent/session.jsonl' }).role, 'land');
});

test('selection is persisted before authority changes and failed persistence cannot promote writes', () => {
  const access = new WorkflowAccess('review');
  const revision = access.revision;
  assert.throws(() => access.select('land', () => { throw Error('Cannot persist'); }), /Cannot persist/);
  assert.equal(access.role, 'reviewer'); assert.equal(access.revision, revision);
  let persisted;
  access.input('/skill:land TASK-00488', 'interactive', hasWorkflowSkill, role => { persisted = role; assert.equal(access.role, 'reviewer'); });
  assert.equal(persisted, 'land'); assert.equal(access.role, 'land'); assert.equal(access.revision, revision + 1);
});

test('loaded standalone skills never select or persist workflow authority', () => {
  const access = new WorkflowAccess('local', { checkout: '/project/linked' });
  const sessionId = randomUUID();
  const hasSkill = name => ['job-search', 'job-search2', 'review'].includes(name);
  let count = 0;
  const persist = () => count++;
  const invoke = text => access.input(text, 'interactive', hasSkill, persist);
  const unchanged = () => {
    const before = access.checkpoint(sessionId), previousCount = count;
    for (const command of ['/skill:job-search Demo', '/skill:job-search2']) {
      assert.equal(invoke(command), undefined);
      assert.deepEqual(access.checkpoint(sessionId), before);
      assert.equal(count, previousCount);
    }
  };
  unchanged();
  assert.equal(invoke('/skill:review TASK-00586 --checkout /project/selected'), true);
  assert.equal(count, 1);
  unchanged();
});

test('skill availability and reserved input are validated before persisting', () => {
  const access = new WorkflowAccess('review');
  const neverPersist = () => assert.fail('Unexpected checkpoint');
  for (const text of ['/skill:work TASK-00586', '/skill:not-loaded', '/skill:work\tTASK-00586']) {
    assert.throws(() => access.input(text, 'interactive', () => false, neverPersist), { code: 'workflow-skill-not-loaded' });
  }
  for (const hasSkill of [hasWorkflowSkill, () => false]) {
    assert.throws(() => access.input('/skill:sync', 'interactive', hasSkill, neverPersist), { code: 'workflow-subject-required' });
  }
  assert.equal(access.skill, 'review');
});

test('operator workflow selections bind responsibilities without model or RPC promotion', () => {
  const access = new WorkflowAccess('review');
  for (const source of ['extension', 'rpc']) access.input('/skill:work TASK-00001', source, hasWorkflowSkill);
  assert.equal(access.role, 'reviewer');
  access.input('/skill:work TASK-00001', 'interactive', hasWorkflowSkill);
  assert.equal(access.role, 'engineer');
  access.input('/skill:qa TASK-00001', 'interactive', hasWorkflowSkill);
  assert.equal(access.role, 'qa');
  access.select('land');
  assert.equal(access.role, 'land');
  for (const value of ['anything', 'constructor', '__proto__']) {
    assert.throws(() => access.select(value), { code: 'unknown-workflow-role' });
    assert.throws(() => new WorkflowAccess(value), { code: 'unknown-workflow-role' });
  }
  assert.equal(new WorkflowAccess('Senior Engineer').role, 'reviewer');
  assert.equal(new WorkflowAccess('QA-Engineer').role, 'qa');
  assert.equal(new WorkflowAccess('Release Manager').role, 'land');
});

test('workflow checkpoints preserve subject, selected checkout and query/status effects', () => {
  const sessionId = randomUUID();
  const access = new WorkflowAccess('local', { checkout: '/project/linked' });
  access.input('/skill:build TASK-00586 --checkout /project/selected', 'interactive', hasWorkflowSkill);
  const restore = () => WorkflowAccess.restore({ sessionId, entries: [{ type: 'custom',
    customType: 'fight-workflow-role-v3', data: access.checkpoint(sessionId) }] });
  assert.equal(restore().skill, 'build'); assert.equal(restore().role, 'qa');
  assert.equal(restore().taskId, 'TASK-00586'); assert.equal(restore().checkout, '/project/selected');
  access.input('/skill:research EPIC-00006 query', 'interactive', hasWorkflowSkill);
  assert.equal(restore().mode, 'query'); assert.equal(restore().role, 'reviewer');
  access.input('/skill:release v0.4.0 status', 'interactive', hasWorkflowSkill);
  assert.equal(restore().taskId, '0.4.0'); assert.equal(restore().mode, 'status');
  assert.throws(() => access.input('/skill:release not-a-version', 'interactive', hasWorkflowSkill), { code: 'workflow-subject-required' });
  assert.throws(() => access.input('/skill:work TASK-00586 --checkout relative', 'interactive', hasWorkflowSkill), { code: 'workflow-checkout-required' });
  assert.equal(restore().skill, 'release');
});

test('release assessors select and restore a version and explicit checkout', () => {
  const sessionId = randomUUID(), checkout = '/Users/john/Projects/Fight/fight-common';
  const access = new WorkflowAccess('local', { checkout: '/project/provider' });
  for (const [skill, role] of [['review', 'reviewer'], ['qa', 'qa']]) {
    for (const version of ['', '1.4', '../1.4.2', 'TASK-00139']) {
      assert.throws(() => access.input(`/skill:${skill} release ${version} --checkout ${checkout}`, 'interactive', hasWorkflowSkill),
        { code: 'workflow-subject-required' });
    }
    assert.equal(access.input(`/skill:${skill} release 1.4.2 --checkout ${checkout}`, 'interactive', hasWorkflowSkill), true);
    const restored = WorkflowAccess.restore({ sessionId, entries: [{ type: 'custom',
      customType: 'fight-workflow-role-v3', data: access.checkpoint(sessionId) }] });
    for (const workflow of [access, restored]) {
      assert.equal(workflow.role, role);
      assert.equal(workflow.skill, skill);
      assert.equal(workflow.taskId, 'release 1.4.2');
      assert.equal(workflow.checkout, checkout);
    }
    assert.equal(access.input(`/skill:${skill} TASK-00139`, 'interactive', hasWorkflowSkill), true);
    assert.equal(access.taskId, 'TASK-00139');
  }
  assert.throws(() => access.input('/skill:build release 1.4.2', 'interactive', hasWorkflowSkill), { code: 'workflow-subject-required' });
});

test('Sync defaults to isolated checkout resolution and preserves explicit checkout selection', () => {
  const sessionId = randomUUID();
  const checkout = '/project/main';
  const access = new WorkflowAccess('local', { checkout });
  const restore = (cwd = checkout) => WorkflowAccess.restore({ sessionId, checkout: cwd, entries: [{
    type: 'custom', customType: 'fight-workflow-role-v3', data: access.checkpoint(sessionId),
  }] });

  access.input('/skill:sync TASK-00139 --checkout /project/previous', 'interactive', hasWorkflowSkill);
  assert.equal(restore().checkout, '/project/previous');
  access.input('/skill:sync TASK-00139', 'interactive', hasWorkflowSkill);
  const resumed = restore();
  assert.equal(resumed.skill, 'sync');
  assert.equal(resumed.taskId, 'TASK-00139');
  assert.equal(resumed.checkout, null);
  assert.equal(restore('/other/project').skill, 'review');
  resumed.input('/skill:work TASK-00139', 'interactive', hasWorkflowSkill);
  assert.equal(resumed.checkout, checkout);
});
