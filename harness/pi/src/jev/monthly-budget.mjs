import { createRequire } from 'node:module';
import { createHash, randomUUID } from 'node:crypto';
import { constants, closeSync, existsSync, lstatSync, openSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { operatorDirectory, privateDirectory, readPrivate } from './local-store.mjs';
import { Unavailable } from './readiness.mjs';

export const monthlyCeilingMicroUsd = 10000000;
const hash = value => createHash('sha256').update(value).digest('hex');
const money = value => Number.isSafeInteger(value) && value >= 0;
const fail = code => { throw new Unavailable(code); };
const parts = time => Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
  timeZone: 'America/Chicago', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', hourCycle: 'h23',
}).formatToParts(time).filter(p => p.type !== 'literal').map(p => [p.type, Number(p.value)]));

/** Attribute liabilities to their reservation month, never their eventual settlement month */
export function budgetPeriod(time) {
  const p = parts(time), next = new Date(Date.UTC(p.year, p.month, 1));
  let reset = Date.UTC(next.getUTCFullYear(), next.getUTCMonth(), 1, 6);
  // Chicago's first-of-month midnight is either UTC-5 or UTC-6, including DST transition months.
  reset -= parts(reset).hour * 3600000;
  return { month: `${p.year}-${String(p.month).padStart(2, '0')}`, resetsAt: new Date(reset).toISOString() };
}

/** Serialize all enrolled projects/processes through SQLite's OS-owned transaction locks */
export class MonthlyLedger {
  constructor(directory = operatorDirectory(), now = Date.now) {
    this.directory = directory; this.now = now;
    this.path = join(directory, 'screening-monthly.sqlite');
  }
  #change(operation, readOnly = false) {
    let db;
    try {
      privateDirectory(this.directory);
      if (!readOnly) {
        try { const fd = openSync(this.path, constants.O_CREAT | constants.O_EXCL | constants.O_WRONLY | constants.O_NOFOLLOW, 0o600); closeSync(fd); }
        catch (error) { if (error.code !== 'EEXIST') throw error; }
      }
      const info = lstatSync(this.path);
      if (!info.isFile() || info.nlink !== 1 || info.uid !== process.getuid() || (info.mode & 0o077) !== 0) fail('budget-unavailable');
      const { DatabaseSync } = createRequire(import.meta.url)('node:sqlite');
      db = new DatabaseSync(this.path, { readOnly });
      db.exec('PRAGMA busy_timeout=5000');
      db.exec(readOnly ? 'BEGIN' : 'PRAGMA journal_mode=DELETE; PRAGMA synchronous=FULL; BEGIN IMMEDIATE');
      if (!readOnly) db.exec(`CREATE TABLE IF NOT EXISTS requests (
        id TEXT PRIMARY KEY, session TEXT NOT NULL, month TEXT NOT NULL, reserved INTEGER NOT NULL,
        actual INTEGER, phase TEXT NOT NULL, created INTEGER NOT NULL, evidence TEXT);
        CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS sessions (id TEXT PRIMARY KEY, inherited INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS legacy (digest TEXT PRIMARY KEY, file TEXT NOT NULL, settled INTEGER NOT NULL,
          pending INTEGER NOT NULL, month TEXT NOT NULL, actual INTEGER, actual_month TEXT, evidence TEXT NOT NULL);`);
      const result = operation(db);
      db.exec('COMMIT');
      return result;
    } catch (error) {
      try { db?.exec('ROLLBACK'); } catch { /* SQLite rolls back a terminated transaction on reopen */ }
      if (error instanceof Unavailable) throw error;
      fail('budget-unavailable');
    } finally { db?.close(); }
  }
  /** Inspect every undated ledger, not just the current session; never mutate the supplier's records */
  legacySnapshot() {
    if (!existsSync(this.directory)) return { digest: hash('[]'), files: [], exposureMicroUsd: 0 };
    privateDirectory(this.directory);
    const names = readdirSync(this.directory).filter(n => /^budget-[a-f0-9]{64}\.json(?:\.lock)?$/.test(n)).sort();
    if (names.length > 1024 || names.some(n => n.endsWith('.lock'))) fail('legacy-accounting-busy');
    const files = names.map(file => {
      const path = join(this.directory, file), old = readPrivate(path, null);
      if (!old || !money(old.settled) || !old.pending || typeof old.pending !== 'object' || Array.isArray(old.pending) ||
        Object.keys(old.pending).length > 128 || Object.values(old.pending).some(v => !money(v)) ||
        (old.halted !== undefined && old.halted !== true)) fail('legacy-accounting-invalid');
      if (old.halted) fail('price-bound-exceeded');
      const pending = Object.values(old.pending).reduce((sum, v) => sum + v, 0);
      if (!money(old.settled + pending)) fail('legacy-accounting-invalid');
      const bytes = readFileSync(path);
      if (JSON.stringify(JSON.parse(bytes.toString('utf8'))) !== JSON.stringify(old)) fail('legacy-snapshot-changed');
      return { file, digest: hash(Buffer.concat([Buffer.from(file + '\0'), bytes])), settled: old.settled, pending };
    });
    const exposureMicroUsd = files.reduce((sum, f) => sum + f.settled + f.pending, 0);
    if (!money(exposureMicroUsd)) fail('legacy-accounting-invalid');
    return { digest: hash(JSON.stringify(files)), files, exposureMicroUsd };
  }
  #legacy(db, month) {
    const snapshot = this.legacySnapshot();
    const rows = db?.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='legacy'").get() ? db.prepare('SELECT * FROM legacy').all() : [];
    const unimported = snapshot.files.filter(f => !rows.some(r => r.file === f.file && r.digest === f.digest));
    const held = unimported.some(f => f.settled + f.pending > 0) || rows.some(r => !snapshot.files.some(f => f.file === r.file));
    // Undated pending effects remain charged in EVERY month until an independently evidenced settlement.
    const used = rows.reduce((n, r) => n + (r.month === month ? r.settled : 0) +
      (r.actual === null ? r.pending : r.actual_month === month ? r.actual : 0), 0);
    if (!money(used)) fail('budget-unavailable');
    return { held, snapshotDigest: snapshot.digest, exposureMicroUsd: snapshot.exposureMicroUsd,
      chargedMicroUsd: used, unimportedExposureMicroUsd: unimported.reduce((n, f) => n + f.settled + f.pending, 0),
      pendingMicroUsd: rows.filter(r => r.actual === null).reduce((n, r) => n + r.pending, 0) + unimported.reduce((n, f) => n + f.pending, 0) };
  }
  /** Explicit trusted import charges all undated settled exposure to the current month, conservatively */
  importLegacy(snapshotDigest, evidence) {
    return this.#change(db => {
      if (typeof evidence !== 'string' || !/^[A-Za-z0-9._:/-]{1,128}$/.test(evidence)) fail('legacy-evidence-required');
      const snapshot = this.legacySnapshot();
      if (snapshot.digest !== snapshotDigest) fail('legacy-snapshot-changed');
      const { month } = budgetPeriod(this.now());
      for (const f of snapshot.files) {
        // Changed old records are additional conservative exposure, never a reset of an earlier import.
        db.prepare('INSERT OR IGNORE INTO legacy VALUES (?,?,?,?,?,NULL,NULL,?)').run(f.digest, f.file, f.settled, f.pending, month, evidence);
      }
      return this.#legacy(db, month);
    });
  }
  legacyPending() {
    if (!existsSync(this.path)) return [];
    return this.#change(db => db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='legacy'").get() ?
      db.prepare('SELECT digest,file,pending,month FROM legacy WHERE actual IS NULL AND pending>0 ORDER BY digest LIMIT 128').all() : [], true);
  }
  reconcileLegacy(digest, amount, month, evidence) {
    this.#change(db => {
      const row = db.prepare('SELECT * FROM legacy WHERE digest=?').get(digest);
      if (!row || !money(amount) || !/^\d{4}-(0[1-9]|1[0-2])$/.test(month) || month > budgetPeriod(this.now()).month ||
        typeof evidence !== 'string' || !/^[A-Za-z0-9._:/-]{1,128}$/.test(evidence)) fail('legacy-evidence-required');
      if (row.actual !== null) {
        if (row.actual !== amount || row.actual_month !== month) fail('already-settled');
        return;
      }
      if (amount > row.pending) db.prepare("INSERT OR REPLACE INTO settings VALUES ('halted','price-bound-exceeded')").run();
      db.prepare('UPDATE legacy SET actual=?,actual_month=?,evidence=? WHERE digest=?').run(amount, month, evidence, digest);
    });
  }
  #usage(db, column, value) {
    const row = db.prepare(`SELECT COALESCE(SUM(COALESCE(actual,reserved)),0) AS used,
      COALESCE(SUM(CASE WHEN actual IS NULL THEN reserved ELSE 0 END),0) AS reserved
      FROM requests WHERE ${column}=?`).get(value);
    if (!money(row.used) || !money(row.reserved)) fail('budget-unavailable');
    return row;
  }
  reserve(sessionId, amount, sessionCeiling, inheritedExposure = 0) {
    return this.#change(db => {
      if (typeof sessionId !== 'string' || !sessionId || !money(amount) || amount < 1 || !money(sessionCeiling) || sessionCeiling < amount || !money(inheritedExposure)) fail('budget-unavailable');
      if (db.prepare("SELECT value FROM settings WHERE key='halted'").get()) fail('price-bound-exceeded');
      const session = hash(sessionId), { month } = budgetPeriod(this.now());
      const legacy = this.#legacy(db, month);
      if (legacy.held) fail('legacy-reconciliation-required');
      const monthly = this.#usage(db, 'month', month), local = this.#usage(db, 'session', session);
      const inherited = Math.max(db.prepare('SELECT inherited FROM sessions WHERE id=?').get(session)?.inherited ?? 0, inheritedExposure);
      if (monthly.used + legacy.chargedMicroUsd + amount > monthlyCeilingMicroUsd) fail('monthly-budget');
      if (local.used + inherited + amount > sessionCeiling) fail('session-budget');
      db.prepare('INSERT OR REPLACE INTO sessions VALUES (?,?)').run(session, inherited);
      const id = randomUUID();
      db.prepare('INSERT INTO requests VALUES (?,?,?,?,NULL,?,?,NULL)').run(id, session, month, amount, 'reserved', this.now());
      return id;
    });
  }
  dispatch(id) {
    this.#change(db => {
      const row = db.prepare('SELECT * FROM requests WHERE id=?').get(id);
      if (!row || row.actual !== null || row.phase !== 'reserved') fail('budget-unavailable');
      db.prepare("UPDATE requests SET phase='dispatched' WHERE id=?").run(id);
    });
  }
  settle(id, amount, evidence = null) {
    this.#change(db => {
      const row = db.prepare('SELECT * FROM requests WHERE id=?').get(id);
      if (!row || !money(amount) || (evidence !== null && (typeof evidence !== 'string' || !/^[A-Za-z0-9._:/-]{1,128}$/.test(evidence)))) fail('budget-unavailable');
      if (row.actual !== null) {
        if (row.actual !== amount) fail('already-settled');
        return; // Recovery cannot double charge or release another request.
      }
      if (amount > row.reserved) db.prepare("INSERT OR REPLACE INTO settings VALUES ('halted','price-bound-exceeded')").run();
      db.prepare("UPDATE requests SET actual=?,phase='settled',evidence=? WHERE id=?").run(amount, evidence, id);
    });
  }
  inspect(sessionId = null) {
    if (!existsSync(this.path)) {
      const legacy = this.#legacy(null, budgetPeriod(this.now()).month);
      return { legacy, session: { used: 0, reserved: 0, inherited: 0 }, ...budgetPeriod(this.now()), timeZone: 'America/Chicago', ceilingMicroUsd: monthlyCeilingMicroUsd,
        settledMicroUsd: 0, reservedMicroUsd: legacy.pendingMicroUsd, newRequestReservedMicroUsd: 0,
        availableMicroUsd: legacy.held ? 0 : monthlyCeilingMicroUsd,
        outstandingAllMonths: { count: 0, reserved: 0 }, pending: [], halted: false };
    }
    return this.#change(db => {
      const period = budgetPeriod(this.now()), usage = this.#usage(db, 'month', period.month), legacy = this.#legacy(db, period.month);
      const pending = db.prepare('SELECT id,session,month,reserved,phase,created FROM requests WHERE actual IS NULL ORDER BY created LIMIT 128').all();
      const outstanding = db.prepare('SELECT COUNT(*) AS count, COALESCE(SUM(reserved),0) AS reserved FROM requests WHERE actual IS NULL').get();
      const session = sessionId ? { ...this.#usage(db, 'session', hash(sessionId)),
        inherited: db.prepare('SELECT inherited FROM sessions WHERE id=?').get(hash(sessionId))?.inherited ?? 0 } : null;
      return { session, legacy, ...period, timeZone: 'America/Chicago', ceilingMicroUsd: monthlyCeilingMicroUsd,
        settledMicroUsd: usage.used - usage.reserved, reservedMicroUsd: usage.reserved + legacy.pendingMicroUsd,
        newRequestReservedMicroUsd: usage.reserved,
        availableMicroUsd: legacy.held ? 0 : Math.max(0, monthlyCeilingMicroUsd - usage.used - legacy.chargedMicroUsd),
        outstandingAllMonths: outstanding, pending, halted: Boolean(db.prepare("SELECT value FROM settings WHERE key='halted'").get()) };
    }, true);
  }
}

/** Preserve legacy session exposure without rewriting another runtime's session ledger */
export class ProfileBudget {
  constructor(sessionId, directory = operatorDirectory(), ledger = new MonthlyLedger(directory)) {
    this.sessionId = sessionId; this.directory = directory; this.ledger = ledger;
  }
  inheritedExposure() {
    const path = join(this.directory, `budget-${hash(this.sessionId)}.json`);
    if (existsSync(`${path}.lock`)) fail('budget-unavailable');
    const old = readPrivate(path, { settled: 0, pending: {} });
    if (!money(old.settled) || !old.pending || typeof old.pending !== 'object' || Array.isArray(old.pending) ||
      Object.keys(old.pending).length > 128 || Object.values(old.pending).some(v => !money(v)) || old.halted) fail('budget-unavailable');
    const inherited = old.settled + Object.values(old.pending).reduce((sum, v) => sum + v, 0);
    if (!money(inherited)) fail('budget-unavailable');
    return inherited;
  }
  inspect(ceiling) {
    const monthly = this.ledger.inspect(this.sessionId), local = monthly.session;
    const inherited = Math.max(local?.inherited ?? 0, this.inheritedExposure());
    return { ...monthly, session: { ceilingMicroUsd: ceiling, usedMicroUsd: (local?.used ?? 0) + inherited,
      inheritedMicroUsd: inherited, reservedMicroUsd: local?.reserved ?? 0,
      availableMicroUsd: Math.max(0, ceiling - (local?.used ?? 0) - inherited) } };
  }
  reserve(amount, ceiling) { return this.ledger.reserve(this.sessionId, amount, ceiling, this.inheritedExposure()); }
  dispatch(id) { this.ledger.dispatch(id); }
  settle(id, amount) { this.ledger.settle(id, amount); }
}
