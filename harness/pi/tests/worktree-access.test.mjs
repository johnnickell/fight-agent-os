import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, realpathSync, rmSync, renameSync, symlinkSync, linkSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { ScreeningProfiles } from '../src/jev/screening-profile.mjs';
import { FileAdmission } from '../src/jev/file-admission.mjs';
import { WorkflowAccess, skillEffects } from '../src/jev/workflow-access.mjs';
import { ToolPolicy } from '../src/tool-policy/policy.mjs';
import { fileOperations } from '../src/tool-policy/effects.mjs';
import { connection } from '../src/jev/readiness.mjs';
import { prepareScreening } from '../src/screening-bootstrap.mjs';

// Owning-tool fixtures use Git's on-disk registration format. Native qualification also runs real git.
export function worktreeFixture(t) {
  const directory = realpathSync.native(mkdtempSync(join(tmpdir(), 'repository-access-')));
  const root = join(directory, 'main'), checkout = join(directory, 'external');
  mkdirSync(join(root, '.git/worktrees'), { recursive: true });
  const { schema, ...fixed } = connection;
  const configuration = { ...fixed, revision: randomUUID(), priceConfirmedAt: Date.now(), disclosure: 'authorized-supplied-state', requestMicroUsd: 2688, sessionMicroUsd: 100000 };
  const profiles = new ScreeningProfiles(join(directory, 'operator'));
  profiles.save(profiles.preview({ cwd: root, configuration, excludedPaths: ['private'], providerDisclosure: true, commandDisclosure: true }), []);
  const add = (target = checkout, name = 'external') => {
    mkdirSync(target); const gitdir = join(root, '.git/worktrees', name); mkdirSync(gitdir);
    writeFileSync(join(target, '.git'), `gitdir: ${gitdir}\n`);
    writeFileSync(join(gitdir, 'commondir'), '../..\n');
    writeFileSync(join(gitdir, 'gitdir'), join(target, '.git') + '\n');
    writeFileSync(join(target, 'source.txt'), 'Original task source');
    return gitdir;
  };
  const sessionId = randomUUID();
  const admission = cwd => new FileAdmission({ profiles, configuration: () => configuration, sessionId, cwd });
  const guard = (cwd, role, resources = []) => {
    const selected = profiles.selection(cwd);
    const policy = new ToolPolicy({ policyPath: selected.enforcementPath, cwd, resources,
      workflowAccess: { root: selected.project.root, editableInstructions: [],
        scope: () => profiles.repository(cwd),
        revision: () => role.revision,
        authorizeWrite: path => role.assertWrite(path, { ...profiles.repository(cwd), checkout, taskId: 'TASK-00489' }) } });
    assert.equal(policy.start(sessionId, { version: '1.3.0', platform: 'darwin', hooks: true }).state, 'active');
    t.after(() => policy.cancel());
    return policy;
  };
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  return { directory, root, checkout, profiles, configuration, add, admission, guard, sessionId };
}

test('regression: a main-anchored enrolled session immediately reads and edits a newly registered external worktree', async t => {
  const f = worktreeFixture(t), admission = f.admission(f.root), before = readFileSync(f.profiles.path);
  f.add();
  assert.equal(admission.read({ path: join(f.checkout, 'source.txt') }).text, 'Original task source');
  const policy = f.guard(f.root, new WorkflowAccess('work'));
  const operation = policy.begin('write', { path: join(f.checkout, 'source.txt'), content: 'Updated' }, f.sessionId, f.root);
  await fileOperations(policy, operation).writeFile(operation.input.path, 'Updated'); operation.close();
  assert.equal(readFileSync(join(f.checkout, 'source.txt'), 'utf8'), 'Updated');
  assert.deepEqual(readFileSync(f.profiles.path), before);
});

test('fresh worktree sessions and Build preflight inherit repository authority without rewriting enrollment', t => {
  const f = worktreeFixture(t); f.add(); const before = readFileSync(f.profiles.path);
  const fresh = new ScreeningProfiles(f.profiles.directory);
  assert.equal(prepareScreening(f.checkout, fresh).enrolled, true);
  assert.equal(fresh.selection(f.checkout).identity, f.profiles.selection(f.root).identity);
  assert.deepEqual(fresh.repository(f.checkout).roots, [f.root, f.checkout]);
  assert.equal(f.admission(f.checkout).read({ path: join(f.checkout, 'source.txt') }).text, 'Original task source');
  assert.deepEqual(readFileSync(f.profiles.path), before);
});

test('every installed skill has a narrower effect contract and query/status modes cannot promote source writes', t => {
  const f = worktreeFixture(t); f.add(); const scope = { ...f.profiles.repository(f.root), checkout: f.checkout, taskId: 'TASK-00489' };
  const expected = {
    work: 'source.txt', review: null, qa: null, land: 'source.txt', build: null,
    next: null, fix: 'planning/tasks/00489-TASK.md', audit: null, architecture: null, 'security-audit': null,
    adopt: null, 'design-review': null, grill: 'planning/epics/00001-EPIC.md',
    'to-tickets': 'planning/tickets/00001-TICKET.md', 'to-tasks': 'planning/tasks/00489-TASK.md',
    wayfinder: 'planning/wayfinder/map.md', research: 'planning/wayfinder/research/note.md', design: null,
    prototype: null, 'writing-for-agents': '.pi/skills/selected/SKILL.md', graphify: 'graphify-out/graph.json', release: 'CHANGELOG.md',
  };
  assert.deepEqual(Object.keys(skillEffects).sort(), Object.keys(expected).sort());
  for (const [skill, target] of Object.entries(expected)) {
    const selectedSubject = skill === 'release' ? '0.5.0' : 'TASK-00489';
    const access = new WorkflowAccess(skill, { checkout: f.checkout, taskId: selectedSubject });
    access.assertWrite(join(f.root, skill === 'release' ? '.runs/handoffs/release-0.5.0/evidence.md' : '.runs/tasks/TASK-00489/runs/native/evidence.md'), scope);
    assert.throws(() => access.assertWrite(join(f.root, '.runs/tasks/TASK-00999/runs/native/evidence.md'), scope));
    if (target) access.assertWrite(join(f.checkout, target), scope);
    if (!['work', 'land'].includes(skill)) assert.throws(() => access.assertWrite(join(f.checkout, 'source.txt'), scope));
    if (skill !== 'work' && skill !== 'land') assert.throws(() => access.assertWrite(join(f.checkout, '.env.example'), scope));
    else access.assertWrite(join(f.checkout, '.env.example'), scope);
    assert.throws(() => access.assertWrite(join(f.root, 'source.txt'), scope));
    for (const mode of ['query', 'status']) {
      const query = new WorkflowAccess(skill, { checkout: f.checkout, taskId: selectedSubject, mode });
      assert.throws(() => query.assertWrite(join(f.checkout, target ?? 'source.txt'), scope));
    }
  }
  for (const skill of ['design', 'prototype', 'design-review', 'research']) {
    const access = new WorkflowAccess(skill, { checkout: f.checkout, taskId: 'selected-topic' });
    access.assertWrite(join(f.root, '.runs', skill === 'research' ? 'notes' : 'prototypes', 'selected-topic', 'evidence.md'), scope);
    assert.throws(() => access.assertWrite(join(f.root, '.runs/prototypes/other-topic/evidence.md'), scope));
  }
  for (const alias of ['software-engineer', 'senior-engineer', 'qa-engineer', 'team-lead', 'release-manager']) {
    const role = new WorkflowAccess(alias, { checkout: f.checkout, taskId: 'TASK-00489' });
    if (alias === 'team-lead') { assert.equal(role.skill, 'build'); assert.throws(() => role.assertWrite(join(f.checkout, 'source.txt'), scope)); }
  }
});

test('final effects retain role restrictions, relative exclusions, secrets, inode checks and runtime protection', async t => {
  const f = worktreeFixture(t); f.add();
  writeFileSync(join(f.root, 'source.txt'), 'Main source');
  mkdirSync(join(f.checkout, 'private')); writeFileSync(join(f.checkout, 'private/data.txt'), 'Excluded');
  const runtime = join(f.checkout, 'live-harness.mjs'); writeFileSync(runtime, 'Protected runtime');
  symlinkSync(f.directory, join(f.checkout, 'escape'));
  linkSync(join(f.checkout, 'source.txt'), join(f.checkout, 'hardlink.txt'));
  const role = new WorkflowAccess('work'), policy = f.guard(f.root, role, [runtime]);
  for (const path of ['private/data.txt', '.env', '.env.local', '.env.production', '.pi/settings.json', '.git', 'escape/effect.txt', 'hardlink.txt', 'live-harness.mjs']) {
    assert.throws(() => policy.begin('write', { path: join(f.checkout, path), content: 'Never' }, f.sessionId, f.root), path);
  }
  assert.throws(() => f.admission(f.root).read({ path: join(f.checkout, 'private/data.txt') }), { code: 'sensitive-file' });
  assert.equal(readFileSync(runtime, 'utf8'), 'Protected runtime');
  assert.equal(readFileSync(join(f.root, 'source.txt'), 'utf8'), 'Main source');
  const pending = policy.begin('write', { path: join(f.checkout, '.env.example'), content: 'Template' }, f.sessionId, f.root);
  role.select('review');
  await assert.rejects(fileOperations(policy, pending).writeFile(pending.input.path, 'Never'), { code: 'stale-operation' });
  pending.close(); assert.equal(existsSync(join(f.checkout, '.env.example')), false);
});

test('regression: documented Release version, status and resume invocations retain only their owned ignored evidence', async t => {
  const f = worktreeFixture(t); f.add();
  const scope = { ...f.profiles.repository(f.root), checkout: f.checkout, taskId: 'TASK-00489' };
  const access = new WorkflowAccess('local', { checkout: f.checkout });
  for (const invocation of ['0.5.0', '0.5.0 status', 'v0.5.0 resume after signing']) {
    access.input(`/skill:release ${invocation}`, 'interactive', () => {});
    assert.equal(access.taskId, '0.5.0');
    for (const root of [f.root, f.checkout]) for (const category of ['handoffs', 'notes']) {
      const path = join(root, '.runs', category, 'release-0.5.0', 'ledger.md');
      const policy = f.guard(f.checkout, access);
      const operation = policy.begin('write', { path, content: invocation }, f.sessionId, f.checkout);
      const files = fileOperations(policy, operation);
      await files.mkdir(join(path, '..')); await files.writeFile(path, invocation); operation.close();
      assert.equal(readFileSync(path, 'utf8'), invocation);
    }
    for (const path of ['.runs/handoffs/release-0.4.0/ledger.md', '.runs/notes/release-0.5.00/ledger.md',
      '.runs/handoffs/release-0.5.0-other/ledger.md', '.runs/reviews/TASK-00489/review.md',
      '.runs/qa/TASK-00489/qa.md', '.runs/tasks/TASK-00489/runs/evidence.md', 'source.txt', '.env.local']) {
      assert.throws(() => access.assertWrite(join(f.root, path), scope));
      assert.equal(existsSync(join(f.root, path)), false);
    }
    if (invocation.includes('status')) assert.throws(() => access.assertWrite(join(f.checkout, 'CHANGELOG.md'), scope));
    else access.assertWrite(join(f.checkout, 'CHANGELOG.md'), scope);
  }
  access.input('/skill:release 0.4.0 status', 'interactive', () => {});
  access.assertWrite(join(f.root, '.runs/handoffs/release-0.4.0/ledger.md'), scope);
  assert.throws(() => access.assertWrite(join(f.root, '.runs/handoffs/release-0.5.0/ledger.md'), scope));
  access.input('/skill:review TASK-00489', 'interactive', () => {});
  assert.throws(() => access.assertWrite(join(f.root, '.runs/handoffs/release-0.4.0/ledger.md'), scope));
  access.input('/skill:release', 'interactive', () => {});
  assert.throws(() => access.assertWrite(join(f.checkout, 'CHANGELOG.md'), scope));
  for (const version of ['TASK-00489', '../0.4.0', '0.4', '00.4.0']) {
    assert.throws(() => access.input(`/skill:release ${version}`, 'interactive', () => {}), { code: 'workflow-subject-required' });
  }
  access.input('/skill:release v0.5.0-rc.1+build.2 status', 'interactive', () => {});
  const entry = { type: 'custom', customType: 'fight-workflow-role-v3', data: access.checkpoint(f.sessionId) };
  const restored = WorkflowAccess.restore({ sessionId: f.sessionId, entries: [entry], checkout: f.checkout });
  restored.assertWrite(join(f.root, '.runs/handoffs/release-0.5.0-rc.1+build.2/ledger.md'), scope);
  assert.throws(() => restored.assertWrite(join(f.checkout, 'CHANGELOG.md'), scope));
  assert.throws(() => restored.assertWrite(join(f.root, '.runs/handoffs/release-0.5.0/ledger.md'), scope));
});

test('removed, moved, replaced and forged registrations affect only their own membership', t => {
  const f = worktreeFixture(t), gitdir = f.add(), admission = f.admission(f.root);
  admission.read({ path: join(f.checkout, 'source.txt') });
  const other = join(f.directory, 'other'); f.add(other, 'other');
  const moved = join(f.directory, 'moved'); renameSync(f.checkout, moved);
  writeFileSync(join(gitdir, 'gitdir'), join(moved, '.git') + '\n');
  assert.equal(admission.read({ path: join(moved, 'source.txt') }).text, 'Original task source');
  const pendingRole = new WorkflowAccess('work', { checkout: moved, taskId: 'TASK-00489' });
  const policy = f.guard(f.root, pendingRole);
  const pending = policy.begin('write', { path: join(moved, 'new.txt'), content: 'Never' }, f.sessionId, f.root);
  rmSync(gitdir, { recursive: true });
  assert.throws(() => policy.target(pending, pending.input.path)); pending.close();
  assert.throws(() => new ScreeningProfiles(f.profiles.directory).selection(moved), { code: 'repository-metadata-unavailable' });
  assert.equal(admission.read({ path: join(other, 'source.txt') }).text, 'Original task source');
  const forged = join(f.directory, 'forged'); mkdirSync(forged);
  writeFileSync(join(forged, '.git'), readFileSync(join(other, '.git'))); writeFileSync(join(forged, 'source.txt'), 'Never');
  assert.throws(() => admission.read({ path: join(forged, 'source.txt') }));
  const old = join(f.directory, 'old-other'); renameSync(other, old); mkdirSync(other);
  writeFileSync(join(other, '.git'), readFileSync(join(old, '.git'))); writeFileSync(join(other, 'source.txt'), 'Replacement');
  assert.throws(() => admission.read({ path: join(other, 'source.txt') }));
  assert.equal(existsSync(join(moved, 'new.txt')), false);
});

test('unrelated clones, nested repositories and stale projects cannot enlarge or disable another valid repository', t => {
  const f = worktreeFixture(t); f.add();
  const nested = join(f.root, 'clone'); mkdirSync(join(nested, '.git'), { recursive: true }); writeFileSync(join(nested, 'source.txt'), 'Same remote does not matter');
  assert.throws(() => f.admission(f.root).read({ path: join(nested, 'source.txt') }), { code: 'repository-metadata-unavailable' });
  const profile = f.profiles.load(); profile.projects.push({ root: join(f.directory, 'deleted-project'), excludedPaths: [], instructions: [], targets: [] });
  f.profiles.save(profile, []);
  const policy = f.guard(f.checkout, new WorkflowAccess('work'));
  const operation = policy.begin('write', { path: join(f.checkout, 'source.txt'), content: 'Allowed' }, f.sessionId, f.checkout); operation.close();
  assert.equal(prepareScreening(f.checkout, f.profiles).enrolled, true);
  f.profiles.revoke(f.checkout);
  assert.throws(() => f.admission(f.checkout), { code: 'enrollment-revoked' });
  assert.throws(() => new ScreeningProfiles(f.profiles.directory).selection(f.root), { code: 'enrollment-revoked' });
});

test('trusted scope selection survives append-order restore but not fork or invalid provenance', t => {
  const f = worktreeFixture(t); f.add();
  const access = new WorkflowAccess('local', { checkout: f.root }); let data;
  access.input(`/skill:work TASK-00489 --checkout ${f.checkout}`, 'rpc', () => assert.fail());
  assert.equal(access.checkout, f.root);
  access.input(`/skill:work TASK-00489 --checkout ${f.checkout}`, 'interactive', role => { data = access.checkpoint(f.sessionId, role); });
  const entry = { type: 'custom', customType: 'fight-workflow-role-v3', data };
  const restored = WorkflowAccess.restore({ sessionId: f.sessionId, entries: [entry], checkout: f.root });
  assert.equal(restored.checkout, f.checkout); assert.equal(restored.taskId, 'TASK-00489'); assert.equal(restored.skill, 'work');
  assert.equal(WorkflowAccess.restore({ sessionId: randomUUID(), entries: [entry], checkout: f.checkout }).role, 'reviewer');
  assert.equal(WorkflowAccess.restore({ sessionId: f.sessionId, entries: [{ ...entry, data: { ...data, skill: 'unknown' } }], checkout: f.checkout }).role, 'reviewer');
  const launch = WorkflowAccess.restore({ sessionId: f.sessionId, entries: [entry], phase: 'build', taskId: 'TASK-00489', checkout: f.root });
  assert.equal(launch.skill, 'build'); assert.equal(launch.checkout, f.root);
});

test('regression: worktree-rooted Reviewer and QA publish canonical task reports in the base checkout', async t => {
  const f = worktreeFixture(t); f.add();
  const candidate = f.profiles.preview({ cwd: f.checkout, configuration: f.configuration, providerDisclosure: true, commandDisclosure: true });
  candidate.projects = candidate.projects.filter(p => p.root === f.checkout); f.profiles.save(candidate, []);
  for (const [role, folder] of [['review', 'reviews'], ['qa', 'qa'], ['build', 'build']]) {
    const policy = f.guard(f.checkout, new WorkflowAccess(role));
    const path = join(f.root, '.runs', folder, 'TASK-00489', 'report.md');
    const operation = policy.begin('write', { path, content: role }, f.sessionId, f.checkout);
    const operations = fileOperations(policy, operation);
    await operations.mkdir(join(path, '..')); await operations.writeFile(path, role); operation.close();
    assert.equal(f.admission(f.checkout).read({ path }).text, role);
    assert.throws(() => policy.begin('write', { path: join(f.checkout, 'source.txt'), content: 'Never' }, f.sessionId, f.checkout));
  }
});
