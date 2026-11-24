import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { appendFileSync, closeSync, mkdtempSync, openSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { sessionFileIdentity, sameSessionFile, validSessionFile } from '../src/observation/custody.mjs';
import { bindParentFile } from '../src/observation/custodian.mjs';
import { NativeObservation } from '../src/observation/native-observation.mjs';
import { NativeWorkspaceContext, validateContext } from '../src/herdr/context.mjs';
import { fileStats, recycledInode } from './helpers/session-file.mjs';

function fixture(t) {
  const cwd = realpathSync(mkdtempSync(join(tmpdir(), 'session-lifetime-'))), path = join(cwd, 'session.jsonl');
  t.after(() => rmSync(cwd, { recursive: true, force: true }));
  writeFileSync(path, 'native boundary fixture');
  return { cwd, path };
}

test('native lifetime is losslessly serializable and independently recoverable after append in another process', t => {
  const { path } = fixture(t), first = sessionFileIdentity(path);
  assert.equal(first.version, 2); assert.equal(typeof first.ino, 'string'); assert.equal(typeof first.birthtimeNs, 'string');
  appendFileSync(path, '\nnormal session progress');
  const module = new URL('../src/observation/custody.mjs', import.meta.url).href;
  const resumed = JSON.parse(execFileSync(process.execPath, ['--input-type=module', '-e',
    `import { sessionFileIdentity } from ${JSON.stringify(module)}; console.log(JSON.stringify(sessionFileIdentity(process.argv[1])))`, path],
  { encoding: 'utf8', timeout: 2000, maxBuffer: 4096 }));
  assert.deepEqual(resumed, first); assert.equal(sameSessionFile(first, resumed), true);
  assert.equal(sameSessionFile(first, Object.fromEntries(Object.entries(resumed).reverse())), true);
});

test('legacy, missing, coarse and malformed lifetime fields never compare equal, even to themselves', t => {
  const { path } = fixture(t), actual = sessionFileIdentity(path);
  const legacy = { path, dev: Number(actual.dev), ino: Number(actual.ino) };
  for (const value of [null, undefined, legacy, { ...actual, version: 1 }, { ...actual, ino: Number(actual.ino) },
    { ...actual, birthtimeNs: undefined }, { ...actual, birthtimeNs: '0' }, { ...actual, birthtimeNs: '-1' },
    { ...actual, birthtimeNs: '1700000000000000000' }, { ...actual, birthtimeNs: '1e20' }, { ...actual, extra: true }]) {
    assert.equal(Boolean(validSessionFile(value)), false);
    assert.equal(Boolean(sameSessionFile(value, value)), false);
    assert.throws(() => bindParentFile(path, value));
  }
});

test('native observation, IPC promotion and workspace context reject a recycled-inode replacement', t => {
  const { cwd, path } = fixture(t); recycledInode(t);
  const primary = { cwd, sessionId: 'actual-fixture-context', sessionFile: path, pid: process.pid };
  const ctx = { cwd, sessionManager: { getSessionId: () => primary.sessionId, getSessionFile: () => path } };
  const observation = new NativeObservation(ctx), native = new NativeWorkspaceContext(primary);
  t.after(() => { observation.close(); native.close(); });
  const binding = native.snapshot(), first = JSON.parse(JSON.stringify(observation.parentFile));
  assert.deepEqual(bindParentFile(path, first), first);
  appendFileSync(path, '\nappend'); observation.syncSessionFile(ctx);
  validateContext(binding, native.snapshot());
  rmSync(path); writeFileSync(path, 'replacement');
  assert.throws(() => bindParentFile(path, first), /witness refused/);
  assert.throws(() => observation.syncSessionFile(ctx), /replaced/);
  assert.throws(() => validateContext(binding, { ...binding, at: Date.now() }), /stale|replaced|mismatched/);
  assert.throws(() => native.snapshot(), /replaced/); assert.equal(native.closed, true);
});

test('workspace response validation rejects legacy witnesses rather than upgrading or admitting them', t => {
  const { cwd, path } = fixture(t);
  const native = new NativeWorkspaceContext({ cwd, sessionId: 'fixture', sessionFile: path, pid: process.pid });
  t.after(() => native.close());
  const value = native.snapshot(), legacy = { path, dev: Number(value.file.dev), ino: Number(value.file.ino) };
  for (const file of [legacy, {}, null]) assert.throws(() => validateContext(value, { ...value, file }));
  assert.throws(() => validateContext({ ...value, file: legacy }, value));
});

test('bigint native device and inode identities do not lose precision in IPC', t => {
  const { path } = fixture(t);
  fileStats(t, () => ({ dev: 9007199254740993n, ino: 9007199254740995n }));
  const value = sessionFileIdentity(path);
  assert.equal(value.dev, '9007199254740993'); assert.equal(value.ino, '9007199254740995');
  assert.equal(sameSessionFile(value, JSON.parse(JSON.stringify(value))), true);
});

test('Linux helper reads the inherited file and refuses missing native creation time', { skip: process.platform !== 'linux' }, t => {
  const { path } = fixture(t), witness = sessionFileIdentity(path);
  const helper = fileURLToPath(new URL('../src/observation/session-file-birth.php', import.meta.url));
  const probe = (fd, flags = []) => spawnSync('php', ['-d', 'register_argc_argv=1', ...flags, '-r', 'exit((require $argv[1])());', '--', helper],
    { stdio: ['ignore', 'pipe', 'pipe', fd], encoding: 'utf8', timeout: 2000, maxBuffer: 4096 });
  const refused = result => {
    assert.ifError(result.error);
    assert.equal(result.status, 1);
    assert.equal(result.stdout, '');
    assert.equal(result.stderr, '');
  };
  const file = openSync(path, 'r'), withoutBirth = openSync('/proc/self/status', 'r');
  try {
    const result = probe(file);
    assert.ifError(result.error);
    assert.equal(result.status, 0, result.stderr);
    assert.deepEqual(JSON.parse(result.stdout), { birthtimeNs: witness.birthtimeNs });
    refused(probe(withoutBirth));
    refused(probe('ignore'));
    refused(probe(file, ['-d', 'ffi.enable=false']));
    assert.throws(() => sessionFileIdentity('/proc/self/status'));
  } finally {
    closeSync(withoutBirth);
    closeSync(file);
  }
});
