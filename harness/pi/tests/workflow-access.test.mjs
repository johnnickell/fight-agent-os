import test from 'node:test';
import assert from 'node:assert/strict';
import { WorkflowAccess } from '../src/jev/workflow-access.mjs';

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
