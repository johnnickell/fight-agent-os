import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { appendFileSync, mkdtempSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
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

test('Linux creation-time capability requires the native statx mask, never ctime fallback', () => {
  const helper = fileURLToPath(new URL('../src/observation/session-file-birth.py', import.meta.url));
  // Exercise the owning ABI reader with controlled kernel evidence; not a Linux platform observation.
  const probe = `
import ctypes, runpy, types
from unittest.mock import patch
m = runpy.run_path(${JSON.stringify(helper)})
g = m['birth'].__globals__
g['sys'] = types.SimpleNamespace(platform='linux')
class Call:
    mask = 0x900
    nanos = 123456789
    ino = 42
    result = 0
    def __call__(self, fd, path, flags, mask, pointer):
        assert fd == 3 and path == b'' and flags == 0x1000 and mask == 0x900
        value = pointer._obj
        value.mask, value.ino, value.btime.seconds, value.btime.nanoseconds = self.mask, self.ino, 1700000000, self.nanos
        return self.result
call = Call()
with patch.object(ctypes, 'CDLL', return_value=types.SimpleNamespace(statx=call)), patch.object(g['os'], 'fstat', return_value=types.SimpleNamespace(st_ino=42, st_dev=0)):
    assert m['birth'](3) == {'birthtimeNs': '1700000000123456789'}
    for field, bad in [('mask', 0x100), ('mask', 0), ('mask', 0x800), ('nanos', 1000000000), ('ino', 43), ('result', -1)]:
        old = getattr(call, field)
        setattr(call, field, bad)
        try:
            m['birth'](3)
        except ValueError:
            pass
        else:
            raise AssertionError(field)
        setattr(call, field, old)
print('native mask, timestamp, identity and syscall failures: passed')
`;
  assert.match(execFileSync('python3', ['-c', probe], { encoding: 'utf8', timeout: 2000, maxBuffer: 4096 }), /passed/);
});
