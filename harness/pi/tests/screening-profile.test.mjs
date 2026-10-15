import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync, realpathSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ScreeningProfiles, validateProfile, hash } from '../src/jev/screening-profile.mjs';
import { writePrivate } from '../src/jev/local-store.mjs';
import { MonthlyLedger, ProfileBudget } from '../src/jev/monthly-budget.mjs';
import { validateFilePolicy } from '../src/jev/file-screening.mjs';
import { connection } from '../src/jev/readiness.mjs';

function fixture(t) {
  const base = realpathSync.native(mkdtempSync(join(tmpdir(), 'standing-profile-'))), root = join(base, 'project');
  mkdirSync(root); writeFileSync(join(root, 'AGENTS.md'), 'Nonsecret fixture instructions');
  t.after(() => rmSync(base, { recursive: true, force: true }));
  const { schema, ...fixed } = connection;
  const config = { ...fixed, revision: randomUUID(), priceConfirmedAt: Date.now(), disclosure: 'authorized-supplied-state', requestMicroUsd: 2688, sessionMicroUsd: 100000 };
  const profiles = new ScreeningProfiles(join(base, 'operator'));
  profiles.save(profiles.preview({ cwd: root, configuration: config, providerDisclosure: true, commandDisclosure: true, instructions: ['AGENTS.md'] }), []);
  return { root, profiles, config };
}

test('standing enrollment has no timer and binds reload/new sessions without changing identity or budgets', t => {
  const f = fixture(t), before = readFileSync(f.profiles.path), identity = f.profiles.selection(f.root).identity;
  assert.equal(f.profiles.load().expiresAt, null);
  assert.equal(validateProfile(f.profiles.load(), Date.parse('2031-01-01T00:00:00Z')).expiresAt, null);
  assert.throws(() => f.profiles.preview({ cwd: f.root, configuration: f.config, providerDisclosure: true, commandDisclosure: true, days: 1 }), { code: 'timeboxed-enrollment-no-longer-supported' });
  for (const session of ['original-session', 'new-session-next-month', 'resumed-session']) {
    const fresh = new ScreeningProfiles(f.profiles.directory);
    const policy = validateFilePolicy(fresh.fileSettings(session, f.root).load());
    assert.equal(policy.sessions[hash(session)].expiresAt, null);
    assert.equal(policy.sessions[hash(session)].sessionMicroUsd, 100000);
    assert.equal(fresh.selection(f.root).identity, identity);
  }
  assert.deepEqual(readFileSync(f.profiles.path), before);
});

test('explicit historical conversion preserves original accounting/pending liabilities and requires no recurring leases', t => {
  const f = fixture(t), current = f.profiles.load();
  const { commandDisclosure, revoked, ...legacy } = current;
  legacy.version = 'screening-profile-v1'; legacy.expiresAt = 1; // Previously expired, expressly bounded disclosure.
  writePrivate(f.profiles.path, legacy);
  const original = readFileSync(f.profiles.path);
  const legacyLedger = join(f.profiles.directory, `budget-${hash('original-session')}.json`);
  writePrivate(legacyLedger, { settled: 13, pending: { 'unknown-old-effect': 2688 } });
  let clock = Date.parse('2026-10-31T12:00:00Z');
  const ledger = new MonthlyLedger(f.profiles.directory, () => clock);
  ledger.importLegacy(ledger.legacySnapshot().digest, 'controlled-old-writers-stopped');
  const id = ledger.reserve('original-session', 2688, 100000, 2701); ledger.dispatch(id);
  const databaseBefore = readFileSync(ledger.path), legacyBefore = readFileSync(legacyLedger);
  assert.throws(() => f.profiles.selection(f.root), { code: 'historical-profile-conversion-required' });
  assert.throws(() => f.profiles.preview({ cwd: f.root, configuration: f.config, providerDisclosure: true, commandDisclosure: true }), { code: 'historical-profile-conversion-required' });
  const candidate = f.profiles.convert(); // Preview alone cannot enlarge disclosure.
  assert.deepEqual(readFileSync(f.profiles.path), original);
  assert.deepEqual(candidate.projects, legacy.projects); assert.equal(candidate.sessionMicroUsd, legacy.sessionMicroUsd);
  assert.notEqual(candidate.revision, legacy.revision); assert.equal(candidate.expiresAt, null);
  f.profiles.save(candidate, []); // Explicit operator conversion; no ledger writes.
  assert.deepEqual(readFileSync(ledger.path), databaseBefore); assert.deepEqual(readFileSync(legacyLedger), legacyBefore);
  assert.equal(new ProfileBudget('original-session', f.profiles.directory, ledger).inspect(100000).session.usedMicroUsd, 5389);
  clock = Date.parse('2026-12-01T12:00:00Z');
  assert.equal(ledger.inspect().reservedMicroUsd, 2688); // Legacy unknown still binds this new month.
  assert.equal(ledger.inspect().outstandingAllMonths.reserved, 2688); // New request keeps its old billing month.
  assert.equal(f.profiles.selection(f.root).profile.expiresAt, null);
});

test('revocation is a durable hold for fresh/resumed sessions, not a deleted implicit opt-out or account reset', t => {
  const f = fixture(t), ledger = new MonthlyLedger(f.profiles.directory);
  const id = ledger.reserve('session', 2688, 100000); ledger.dispatch(id);
  const before = readFileSync(ledger.path);
  f.profiles.revoke(f.root);
  for (const session of ['session', 'new-session']) assert.throws(() => new ScreeningProfiles(f.profiles.directory).fileSettings(session, f.root).load(), { code: 'enrollment-revoked' });
  assert.deepEqual(readFileSync(ledger.path), before); assert.equal(f.profiles.load().revoked, true);
  const reenrolled = f.profiles.preview({ cwd: f.root, configuration: f.config, providerDisclosure: true, commandDisclosure: true });
  f.profiles.save(reenrolled, []);
  assert.equal(new ProfileBudget('session', f.profiles.directory, ledger).inspect(100000).session.usedMicroUsd, 2688);
  assert.equal(ledger.inspect().reservedMicroUsd, 2688);
});
