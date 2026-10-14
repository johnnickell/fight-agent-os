import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { chmodSync, existsSync, linkSync, mkdirSync, mkdtempSync, readFileSync, realpathSync,
  rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { spawn } from 'node:child_process';
import { join } from 'node:path';
import { canonicalPath, ToolPolicy } from '../src/tool-policy/policy.mjs';
import { fileOperations, runScript as executeScript } from '../src/tool-policy/effects.mjs';

// The focused check deliberately mounts /tmp noexec. Only tests inject a trusted interpreter;
// production's default direct spawn is separately qualified in real Pi with disposable scripts.
const runScript = (policy, operation) => executeScript(policy, operation,
  (path, args, options) => spawn('/bin/sh', [path, ...args], options));

function fixture(t, change = () => {}) {
  const base = realpathSync(mkdtempSync(join(tmpdir(), 'fight-policy-')));
  t.after(() => rmSync(base, { recursive: true, force: true }));
  const root = join(base, 'workspace'), config = join(base, 'operator'), source = join(root, 'guard.mjs');
  mkdirSync(root); mkdirSync(join(root, '.git')); mkdirSync(config, { mode: 0o700 });
  writeFileSync(source, '// trusted source');
  const script = join(config, 'check');
  writeFileSync(script, '#!/bin/sh\nprintf "approved:%s" "$1"\nprintf done > script-effect\n', { mode: 0o700 });
  const policyPath = join(config, 'policy.json');
  const data = { version: 1, profile: 'local-check', revision: 1, roots: [root],
    protectedPaths: [join(root, 'private')], tools: ['read', 'write', 'edit', 'bash'],
    effects: ['read', 'write', 'execute'], scripts: [{ path: script,
      sha256: createHash('sha256').update(readFileSync(script)).digest('hex'), cwd: root,
      args: [['check']], effects: ['write', 'execute'] }] };
  change(data);
  const save = () => writeFileSync(policyPath, JSON.stringify(data), { mode: 0o600 });
  save();
  const guard = new ToolPolicy({ policyPath, cwd: root, resources: [source], sources: [source] });
  const runtime = { version: '1.2.0', platform: 'darwin', hooks: true };
  const start = () => guard.start('session-one', runtime);
  const begin = (tool, input, signal) => guard.begin(tool, input, 'session-one', root, signal);
  return { base, root, script, source, config, policyPath, data, save, guard, runtime, start, begin };
}
const blocked = (action, code) => assert.throws(action, (error) => error.code === code && !error.message.includes('PRIVATE'));

test('explicit versioned policy and runtime qualifications are required, with redacted status', (t) => {
  const f = fixture(t);
  assert.equal(f.guard.inspect().state, 'unavailable');
  assert.equal(f.start().state, 'active');
  assert.equal(f.guard.inspect().protection, 'deterministic protection only');
  assert.equal(f.guard.inspect().profile, 'local-check');
  assert.equal(f.guard.start('session-one', { ...f.runtime, version: '1.2.1' }).reason, 'unsupported-runtime-or-hooks');
  assert.equal(f.guard.start('session-one', { ...f.runtime, hooks: false }).state, 'unavailable');
  f.start(); chmodSync(f.policyPath, 0o644);
  assert.equal(f.guard.inspect().reason, 'unsafe-policy-file');
  assert.ok(!JSON.stringify(f.guard.inspect()).includes(f.base));
  assert.equal(canonicalPath(join(f.base, '@scope', 'package')), join(f.base, '@scope', 'package'));
});

test('new files, nested directories, reads and edits use admitted effects', async (t) => {
  const f = fixture(t); f.start();
  const input = { path: 'new/child.txt', content: 'first' };
  const op = f.begin('write', input);
  input.path = '../PRIVATE'; input.content = 'changed';
  const ops = fileOperations(f.guard, op);
  await ops.mkdir(join(f.root, 'new'));
  await ops.writeFile(op.input.path, op.input.content); op.close();
  assert.equal(readFileSync(join(f.root, 'new/child.txt'), 'utf8'), 'first');
  const edit = f.begin('edit', { path: 'new/child.txt', edits: [{ oldText: 'first', newText: 'second' }] });
  await fileOperations(f.guard, edit).writeFile(edit.input.path, 'second'); edit.close();
  const read = f.begin('read', { path: 'new/child.txt' });
  assert.equal((await fileOperations(f.guard, read).readFile(read.input.path)).toString(), 'second'); read.close();
});

test('out-of-root, policy, guard, credentials and nested protected resources cannot be accessed', (t) => {
  const f = fixture(t); f.start();
  for (const path of ['../PRIVATE', 'private/new', '.git/config', 'nested/.pi/settings.json', 'nested/.env.local',
    f.policyPath, f.source, join(f.config, 'new-policy')]) {
    blocked(() => f.begin('write', { path, content: 'PRIVATE' }), path.startsWith('../') || path === f.policyPath ||
      path.startsWith(f.config) ? 'out-of-root' : 'protected-path');
  }
  blocked(() => f.begin('read', { path: 'private/new' }), 'protected-path');
  assert.equal(existsSync(join(f.root, 'private')), false);
});

test('canonical symlinks admit internal targets but never escapes, dangling links or hardlinks', async (t) => {
  const f = fixture(t); writeFileSync(join(f.root, 'text'), 'safe');
  symlinkSync(join(f.root, 'text'), join(f.root, 'internal'));
  symlinkSync(f.config, join(f.root, 'escape'));
  symlinkSync(join(f.base, 'missing'), join(f.root, 'dangling'));
  f.start();
  const op = f.begin('read', { path: 'internal' });
  assert.equal((await fileOperations(f.guard, op).readFile(op.input.path)).toString(), 'safe'); op.close();
  blocked(() => f.begin('write', { path: 'escape/new', content: 'PRIVATE' }), 'out-of-root');
  blocked(() => f.begin('write', { path: 'dangling/new', content: 'PRIVATE' }), 'dangling-link');
  linkSync(join(f.root, 'text'), join(f.root, 'alias'));
  blocked(() => f.begin('write', { path: 'alias', content: 'PRIVATE' }), 'hard-link');
});

test('a target replaced after admission is checked again before the effect', async (t) => {
  const f = fixture(t); f.start();
  const op = f.begin('write', { path: 'later/file', content: 'PRIVATE' });
  symlinkSync(f.config, join(f.root, 'later'));
  await assert.rejects(fileOperations(f.guard, op).writeFile(op.input.path, op.input.content), { code: 'out-of-root' });
  assert.equal(existsSync(join(f.config, 'file')), false); op.close();
});

test('tool/effect restrictions and malformed final hook inputs fail before effects', (t) => {
  const f = fixture(t, (p) => { p.tools = ['read', 'write', 'edit']; p.effects = ['read']; }); f.start();
  blocked(() => f.begin('bash', { command: f.script + ' check' }), 'disallowed-tool');
  blocked(() => f.begin('write', { path: 'no', content: 'PRIVATE' }), 'disallowed-effect');
  blocked(() => f.begin('read', { path: 'text', limit: -1 }), 'invalid-input');
  blocked(() => f.begin('edit', { path: 'text', edits: 'PRIVATE' }), 'invalid-input');
  blocked(() => f.begin('write', { path: 'no', content: {} }), 'invalid-content');
  for (const path of ['%2fPRIVATE', '@PRIVATE', '~/PRIVATE', 'a\\PRIVATE', 'a\nPRIVATE']) {
    blocked(() => f.begin('read', { path }), 'invalid-path');
  }
});

test('only the exact approved script, cwd, digest and finite argv alternatives execute', async (t) => {
  const f = fixture(t); f.start();
  const op = f.begin('bash', { command: f.script + ' check' });
  const result = await runScript(f.guard, op); op.close();
  assert.equal(result.exitCode, 0); assert.equal(result.output, 'approved:check');
  assert.equal(readFileSync(join(f.root, 'script-effect'), 'utf8'), 'done');
  for (const command of [f.script + ' other', f.script + ' check; touch PRIVATE', f.script + ' $(id)',
    'sh ' + f.script, f.script + ' "check"', f.script + '\ncheck', f.script + '  check', 'env ' + f.script,
    f.script + ' check > PRIVATE', 'echo PRIVATE', f.script + ' check/../PRIVATE']) {
    assert.throws(() => f.begin('bash', { command }), /Fight policy blocked/);
  }
  const pending = f.begin('bash', { command: f.script + ' check' });
  writeFileSync(f.script, '#!/bin/sh\nprintf changed > PRIVATE\n');
  await assert.rejects(runScript(f.guard, pending), { code: 'script-changed' }); pending.close();
  assert.equal(existsSync(join(f.root, 'PRIVATE')), false);
});

test('script effect class is authoritative even for an exact command', (t) => {
  const f = fixture(t, (p) => { p.effects = ['read', 'execute']; }); f.start();
  blocked(() => f.begin('bash', { command: f.script + ' check' }), 'disallowed-effect');
  assert.equal(existsSync(join(f.root, 'script-effect')), false);
});

test('policy/source/session drift and explicit cancellation invalidate waiting file operations', async (t) => {
  for (const change of ['policy', 'source', 'shutdown', 'session', 'abort']) {
    const f = fixture(t); f.start();
    const controller = new AbortController();
    const op = f.begin('write', { path: 'never', content: 'PRIVATE' }, controller.signal);
    if (change === 'policy') { f.data.revision++; f.save(); }
    if (change === 'source') writeFileSync(f.source, '// changed source');
    if (change === 'shutdown') f.guard.cancel('shutdown-or-reload');
    if (change === 'session') f.guard.start('session-two', f.runtime);
    if (change === 'abort') controller.abort();
    await assert.rejects(fileOperations(f.guard, op).writeFile(op.input.path, op.input.content), /Fight policy blocked/);
    assert.equal(existsSync(join(f.root, 'never')), false); op.close();
  }
});

test('shutdown cancels a running approved direct process without admitting its delayed effect', async (t) => {
  const f = fixture(t);
  writeFileSync(f.script, '#!/bin/sh\nprintf started\nsleep 2\nprintf PRIVATE > delayed\n');
  f.data.scripts[0].sha256 = createHash('sha256').update(readFileSync(f.script)).digest('hex'); f.save(); f.start();
  const op = f.begin('bash', { command: f.script + ' check' });
  const pending = runScript(f.guard, op);
  setTimeout(() => f.guard.cancel('shutdown-or-reload'), 50);
  await assert.rejects(pending, { code: 'stale-operation' }); op.close();
  assert.equal(existsSync(join(f.root, 'delayed')), false);
});

test('unsafe operator policies, mutable scripts and workspace policies never activate', (t) => {
  const f = fixture(t); f.data.version = 2; f.save();
  assert.equal(f.start().state, 'unavailable');
  f.data.version = 1; f.data.scripts[0].args = [['$(id)']]; f.save();
  assert.equal(f.start().reason, 'invalid-script');
  const p = fixture(t);
  const local = join(p.root, 'policy.json'); writeFileSync(local, JSON.stringify(p.data), { mode: 0o600 });
  const guard = new ToolPolicy({ policyPath: local, cwd: p.root });
  assert.equal(guard.start('s', p.runtime).reason, 'policy-in-workspace');
  writeFileSync(f.policyPath, '{broken json');
  assert.equal(f.start().reason, 'invalid-policy');
  rmSync(f.policyPath);
  assert.equal(f.start().reason, 'resource-unavailable');
  rmSync(join(p.root, '.git'), { recursive: true });
  assert.equal(p.start().state, 'active', 'An operator policy outside a non-Git workspace remains usable');
  p.data.roots = ['/']; p.save();
  assert.equal(p.start().reason, 'invalid-root');
});

test('image signatures are read only through the same protected file operations', async (t) => {
  const f = fixture(t); f.start();
  const cases = [
    [Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), 'image/png'],
    [Buffer.from([255, 216, 255]), 'image/jpeg'],
    [Buffer.from('GIF89a'), 'image/gif'], [Buffer.from('RIFF0000WEBP'), 'image/webp'],
    [Buffer.from('BM0000'), 'image/bmp'], [Buffer.from('plain text'), undefined],
  ];
  for (let i = 0; i < cases.length; i++) {
    const path = join(f.root, 'image-' + i); writeFileSync(path, cases[i][0]);
    const op = f.begin('read', { path });
    assert.equal(await fileOperations(f.guard, op).detectImageMimeType(path), cases[i][1]); op.close();
  }
});

test('script output is bounded and timeouts cannot admit a delayed effect', async (t) => {
  const f = fixture(t);
  writeFileSync(f.script, '#!/bin/sh\n/usr/bin/head -c 70000 /dev/zero\n');
  f.data.scripts[0].sha256 = createHash('sha256').update(readFileSync(f.script)).digest('hex'); f.save(); f.start();
  const op = f.begin('bash', { command: f.script + ' check' });
  const result = await runScript(f.guard, op); op.close();
  assert.equal(result.exitCode, 0); assert.equal(result.truncated, true);
  assert.equal(Buffer.byteLength(result.output), 65536);
  writeFileSync(f.script, '#!/bin/sh\nsleep 2\nprintf PRIVATE > timed-out-effect\n');
  f.data.scripts[0].sha256 = createHash('sha256').update(readFileSync(f.script)).digest('hex'); f.save(); f.start();
  const timed = f.begin('bash', { command: f.script + ' check', timeout: 0.05 });
  await assert.rejects(runScript(f.guard, timed), { code: 'script-timeout' }); timed.close();
  assert.equal(existsSync(join(f.root, 'timed-out-effect')), false);
});
