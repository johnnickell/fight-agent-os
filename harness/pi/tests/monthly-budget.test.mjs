import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, readFileSync, existsSync, chmodSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { MonthlyLedger, ProfileBudget, budgetPeriod } from '../src/jev/monthly-budget.mjs';
import { SessionBudget } from '../src/jev/local-store.mjs';

const modulePath = new URL('../src/jev/monthly-budget.mjs', import.meta.url).href;
function fixture(t, now = Date.now) {
  const directory = mkdtempSync(join(tmpdir(), 'monthly-screen-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  return { directory, ledger: new MonthlyLedger(directory, now) };
}
function child(program, directory, extra = []) {
  const process = spawn(globalThis.process.execPath, ['--input-type=module', '-e', `import {MonthlyLedger,ProfileBudget} from ${JSON.stringify(modulePath)}; ${program}`, directory, ...extra]);
  let stdout = '', stderr = '';
  process.stdout.on('data', chunk => { stdout += chunk; });
  process.stderr.on('data', chunk => { stderr += chunk; });
  return { process, done: new Promise((resolve, reject) => {
    process.on('error', reject);
    process.on('close', (code, signal) => code === 0 || signal === 'SIGKILL' ? resolve(stdout) : reject(new Error(`child exit ${code}: ${stderr}`)));
  }) };
}

test('status on a fresh profile is read-only and does not initialize accounting', t => {
  const f = fixture(t);
  assert.equal(f.ledger.inspect().availableMicroUsd, 10000000);
  assert.equal(existsSync(f.ledger.path), false);
});

test('calendar months use Chicago midnight and no rollover across DST offsets', () => {
  assert.deepEqual(budgetPeriod(Date.parse('2026-11-01T04:59:59.999Z')), { month: '2026-10', resetsAt: '2026-11-01T05:00:00.000Z' });
  assert.deepEqual(budgetPeriod(Date.parse('2026-11-01T05:00:00Z')), { month: '2026-11', resetsAt: '2026-12-01T06:00:00.000Z' });
  assert.deepEqual(budgetPeriod(Date.parse('2026-03-01T05:59:59Z')), { month: '2026-02', resetsAt: '2026-03-01T06:00:00.000Z' });
  assert.deepEqual(budgetPeriod(Date.parse('2026-12-31T23:59:00Z')), { month: '2026-12', resetsAt: '2027-01-01T06:00:00.000Z' });
});

test('settlement rounds at the provider boundary, counts once and cannot reset through another budget instance', t => {
  const f = fixture(t), budget = new ProfileBudget('parent', f.directory, f.ledger);
  const id = budget.reserve(2688, 5376); budget.dispatch(id); budget.settle(id, 17);
  f.ledger.settle(id, 17);
  assert.throws(() => f.ledger.settle(id, 0), { code: 'already-settled' });
  assert.equal(f.ledger.inspect().settledMicroUsd, 17);
  assert.equal(new ProfileBudget('parent', f.directory).reserve(2688, 5376).length, 36);
  assert.throws(() => budget.reserve(2688, 5376), { code: 'session-budget' });
  assert.equal(f.ledger.inspect().reservedMicroUsd, 2688);
});

test('predispatch release is isolated; unknown paid attempts and retries retain independent reservations', t => {
  const f = fixture(t);
  const first = f.ledger.reserve('parent', 2688, 20000), failed = f.ledger.reserve('child', 2688, 20000);
  f.ledger.dispatch(first); f.ledger.settle(failed, 0);
  const retry = f.ledger.reserve('parent', 2688, 20000); f.ledger.dispatch(retry);
  const view = new MonthlyLedger(f.directory).inspect();
  assert.equal(view.reservedMicroUsd, 5376); assert.equal(view.availableMicroUsd, 9994624);
  assert.equal(view.pending.length, 2); assert.ok(view.pending.every(p => p.phase === 'dispatched'));
  assert.throws(() => f.ledger.dispatch(first), { code: 'budget-unavailable' });
});

test('a killed parent cannot release or reuse an uncertain request after restart', async t => {
  const f = fixture(t);
  const p = child(`const l=new MonthlyLedger(process.argv[1]); const id=l.reserve('crashed-parent',2688,2688); l.dispatch(id); console.log(id); setTimeout(()=>{},30000);`, f.directory);
  await new Promise(resolve => p.process.stdout.once('data', resolve));
  p.process.kill('SIGKILL'); await p.done;
  assert.equal(f.ledger.inspect().reservedMicroUsd, 2688);
  assert.throws(() => f.ledger.reserve('crashed-parent', 2688, 2688), { code: 'session-budget' });
  const id = f.ledger.inspect().pending[0].id;
  f.ledger.settle(id, 31, 'operator-billing-receipt');
  assert.equal(f.ledger.inspect().availableMicroUsd, 9999969);
});

test('old-month unknown effects stay attributed and session consumption survives month rollover', t => {
  let now = Date.parse('2026-11-01T04:59:59Z');
  const f = fixture(t, () => now), id = f.ledger.reserve('resumed', 2688, 5376);
  f.ledger.dispatch(id);
  now = Date.parse('2026-11-01T05:00:00Z');
  assert.equal(f.ledger.inspect().availableMicroUsd, 10000000);
  assert.equal(f.ledger.inspect().outstandingAllMonths.reserved, 2688);
  const next = f.ledger.reserve('resumed', 2688, 5376); f.ledger.dispatch(next);
  assert.throws(() => f.ledger.reserve('resumed', 2688, 5376), { code: 'session-budget' });
  f.ledger.settle(id, 7);
  assert.equal(f.ledger.inspect().settledMicroUsd, 0);
  assert.equal(f.ledger.inspect().reservedMicroUsd, 2688);
});

test('two parents and children atomically share exactly $10 under real concurrent processes', async t => {
  const f = fixture(t);
  // Sixteen $1 attempts race; exactly ten can acquire the $10 ceiling, regardless of identities.
  const processes = ['parent-a', 'child-a', 'parent-b', 'child-b'].map(id => child(`
    const l=new MonthlyLedger(process.argv[1]); let admitted=0,blocked=0;
    for(let i=0;i<4;i++) {try {l.reserve(process.argv[2],1000000,10000000); admitted++;}
      catch(e) {if(e.code!=='monthly-budget') throw e; blocked++;}}
    console.log(JSON.stringify({admitted,blocked}));`, f.directory, [id]));
  const rows = (await Promise.all(processes.map(p => p.done))).map(s => JSON.parse(s));
  assert.equal(rows.reduce((n, r) => n + r.admitted, 0), 10);
  assert.equal(rows.reduce((n, r) => n + r.blocked, 0), 6);
  const view = f.ledger.inspect();
  assert.equal(view.availableMicroUsd, 0); assert.equal(view.reservedMicroUsd, 10000000);
  assert.throws(() => f.ledger.reserve('fresh-fork', 2688, 10000000), { code: 'monthly-budget' });
});

test('concurrent runtimes resuming the same identity cannot multiply the session ceiling', async t => {
  const f = fixture(t);
  const processes = Array.from({ length: 4 }, () => child(`
    const b=new ProfileBudget('same-session',process.argv[1]); let admitted=false;
    try {b.reserve(2688,2688); admitted=true;} catch(e) {if(e.code!=='session-budget') throw e;}
    console.log(JSON.stringify(admitted));`, f.directory));
  assert.equal((await Promise.all(processes.map(p => p.done))).filter(s => JSON.parse(s)).length, 1);
  assert.equal(f.ledger.inspect().reservedMicroUsd, 2688);
});

test('legacy settled and pending exposure remains intact and constrains resumed allocations', t => {
  const f = fixture(t), old = new SessionBudget('legacy', f.directory);
  const id = old.reserve(2688, 10000); old.settle(id, 1000); old.reserve(2688, 10000);
  const before = readFileSync(old.path);
  const next = new ProfileBudget('legacy', f.directory);
  assert.throws(() => new ProfileBudget('unrelated-fresh-child', f.directory).reserve(2688, 10000), { code: 'legacy-reconciliation-required' });
  const snapshot = f.ledger.legacySnapshot(); f.ledger.importLegacy(snapshot.digest, 'verified-quiescent-inventory');
  assert.throws(() => next.reserve(2688, 5376), { code: 'session-budget' });
  assert.deepEqual(readFileSync(old.path), before);
  const key = next.reserve(2688, 10000); next.settle(key, 0);
  assert.deepEqual(readFileSync(old.path), before);
  assert.equal(f.ledger.inspect().settledMicroUsd, 0); // Conservative legacy accounting is separate from measured new requests.
  assert.equal(f.ledger.inspect().legacy.chargedMicroUsd, 3688);
  assert.equal(f.ledger.inspect().availableMicroUsd, 9996312);
});

test('a price breach persists a profile-wide halt even in a new month/process/session', t => {
  let now = Date.parse('2026-10-15T10:00:00Z');
  const f = fixture(t, () => now), id = f.ledger.reserve('parent', 2688, 50000);
  f.ledger.settle(id, 3000);
  now = Date.parse('2026-11-15T10:00:00Z');
  assert.equal(new MonthlyLedger(f.directory, () => now).inspect().halted, true);
  assert.throws(() => f.ledger.reserve('fresh-child', 2688, 50000), { code: 'price-bound-exceeded' });
});

test('malformed/unsafe accounting cannot turn into a fresh budget', t => {
  const f = fixture(t); f.ledger.reserve('a', 2688, 10000);
  chmodSync(f.ledger.path, 0o644);
  assert.throws(() => f.ledger.reserve('b', 2688, 10000), { code: 'budget-unavailable' });
  chmodSync(f.ledger.path, 0o600); writeFileSync(f.ledger.path, 'not sqlite');
  assert.throws(() => f.ledger.reserve('c', 2688, 10000), { code: 'budget-unavailable' });
  const oldPath = join(f.directory, `budget-${createHash('sha256').update('bad-legacy').digest('hex')}.json`);
  writeFileSync(oldPath, JSON.stringify({ settled: -1, pending: {} }), { mode: 0o600 });
  assert.throws(() => new ProfileBudget('bad-legacy', f.directory).reserve(2688, 10000), { code: 'budget-unavailable' });
});


test('legacy liabilities hold every unrelated identity until a digest-bound trusted import; pending survives rollover', t => {
  let now = Date.parse('2026-10-31T12:00:00Z');
  const f = fixture(t, () => now), old = new SessionBudget('undated-other-session', f.directory);
  const paid = old.reserve(9000000, 10000000); old.settle(paid, 9000000); old.reserve(2688, 10000000);
  const before = readFileSync(old.path), snapshot = f.ledger.legacySnapshot();
  assert.equal(f.ledger.inspect().legacy.held, true);
  assert.equal(f.ledger.inspect().legacy.unimportedExposureMicroUsd, 9002688);
  assert.equal(f.ledger.inspect().reservedMicroUsd, 2688);
  assert.equal(f.ledger.inspect().availableMicroUsd, 0);
  assert.throws(() => f.ledger.reserve('brand-new-child', 2688, 10000000), { code: 'legacy-reconciliation-required' });
  assert.throws(() => f.ledger.importLegacy('forged', 'inventory-1'), { code: 'legacy-snapshot-changed' });
  f.ledger.importLegacy(snapshot.digest, 'inventory-quiescent-1');
  f.ledger.importLegacy(snapshot.digest, 'inventory-quiescent-1');
  assert.equal(f.ledger.inspect().availableMicroUsd, 997312);
  assert.throws(() => f.ledger.reserve('another-parent', 1000000, 10000000), { code: 'monthly-budget' });
  now = Date.parse('2026-11-01T06:00:00Z');
  assert.equal(f.ledger.inspect().availableMicroUsd, 9997312);
  assert.equal(f.ledger.inspect().legacy.pendingMicroUsd, 2688);
  assert.equal(f.ledger.inspect().reservedMicroUsd, 2688); // Ordinary status must not label legacy unknown usage zero
  assert.equal(f.ledger.inspect().newRequestReservedMicroUsd, 0);
  const digest = snapshot.files[0].digest;
  f.ledger.reconcileLegacy(digest, 17, '2026-10', 'billing-receipt-1');
  f.ledger.reconcileLegacy(digest, 17, '2026-10', 'billing-receipt-1');
  assert.throws(() => f.ledger.reconcileLegacy(digest, 0, '2026-10', 'no-proof'), { code: 'already-settled' });
  assert.equal(f.ledger.inspect().availableMicroUsd, 10000000);
  assert.deepEqual(readFileSync(old.path), before);
});

test('concurrent legacy import is idempotent; changed or missing old records never release imported exposure', async t => {
  const f = fixture(t), old = new SessionBudget('quiescent', f.directory);
  old.reserve(2688, 10000);
  const snapshot = f.ledger.legacySnapshot();
  await Promise.all(Array.from({ length: 4 }, () => child(`const l=new MonthlyLedger(process.argv[1]); l.importLegacy(process.argv[2],'inventory');`, f.directory, [snapshot.digest]).done));
  assert.equal(f.ledger.inspect().legacy.chargedMicroUsd, 2688);
  old.reserve(2688, 10000);
  assert.equal(f.ledger.inspect().legacy.held, true);
  assert.throws(() => f.ledger.reserve('fresh', 2688, 10000), { code: 'legacy-reconciliation-required' });
  f.ledger.importLegacy(f.ledger.legacySnapshot().digest, 'changed-inventory');
  assert.equal(f.ledger.inspect().legacy.chargedMicroUsd, 8064); // Full new exposure plus preserved prior unknown, never a reset.
  rmSync(old.path);
  assert.equal(f.ledger.inspect().legacy.held, true);
  assert.equal(f.ledger.inspect().legacy.chargedMicroUsd, 8064);
});

test('legacy writer locks, unsafe records and price breaches fail closed profile-wide', t => {
  const f = fixture(t), old = new SessionBudget('old', f.directory); old.reserve(2688, 10000);
  writeFileSync(old.path + '.lock', '', { mode: 0o600 });
  assert.throws(() => f.ledger.inspect(), { code: 'legacy-accounting-busy' }); rmSync(old.path + '.lock');
  const snapshot = f.ledger.legacySnapshot(); f.ledger.importLegacy(snapshot.digest, 'inventory');
  f.ledger.reconcileLegacy(snapshot.files[0].digest, 3000, f.ledger.inspect().month, 'actual-bill');
  assert.throws(() => f.ledger.reserve('new-child', 2688, 10000), { code: 'price-bound-exceeded' });
});
