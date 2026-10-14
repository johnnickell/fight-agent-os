import { constants, closeSync, existsSync, fstatSync, fsyncSync, lstatSync, mkdirSync, openSync, readFileSync,
  realpathSync, renameSync, unlinkSync, writeFileSync } from 'node:fs';
import { createHash, randomUUID } from 'node:crypto';
import { homedir } from 'node:os';
import { dirname, join, resolve, sep } from 'node:path';
import { Unavailable, validateConfiguration } from './readiness.mjs';

export function operatorDirectory() { return join(homedir(), 'Library', 'Application Support', 'Fight Agent OS', 'jev'); }

function privateDirectory(path) {
  // Reject symlinked path components. Permissions protect against other users, not this same-user Agent.
  const absolute = resolve(path);
  let cursor = sep;
  for (const part of absolute.split(sep).filter(Boolean)) {
    cursor = join(cursor, part);
    if (!existsSync(cursor)) mkdirSync(cursor, { mode: 0o700 });
    if (!lstatSync(cursor).isDirectory() || lstatSync(cursor).isSymbolicLink()) throw new Unavailable('invalid-setup');
  }
  const info = lstatSync(absolute);
  if (info.uid !== process.getuid() || (info.mode & 0o077) !== 0 || realpathSync(absolute) !== absolute) {
    throw new Unavailable('invalid-setup');
  }
}

function readPrivate(path, missing) {
  let fd;
  try {
    fd = openSync(path, constants.O_RDONLY | constants.O_NOFOLLOW);
    const info = fstatSync(fd);
    if (!info.isFile() || info.uid !== process.getuid() || (info.mode & 0o077) !== 0 || info.size > 65536) {
      throw new Unavailable('invalid-setup');
    }
    return JSON.parse(readFileSync(fd, 'utf8'));
  } catch (error) {
    if (error.code === 'ENOENT') return missing;
    throw new Unavailable('invalid-setup');
  } finally { if (fd !== undefined) closeSync(fd); }
}

function writePrivate(path, value) {
  const temp = join(dirname(path), `.pending-${randomUUID()}`);
  let fd;
  try {
    fd = openSync(temp, constants.O_WRONLY | constants.O_CREAT | constants.O_EXCL | constants.O_NOFOLLOW, 0o600);
    writeFileSync(fd, JSON.stringify(value)); fsyncSync(fd); closeSync(fd); fd = undefined;
    renameSync(temp, path);
  } finally {
    if (fd !== undefined) closeSync(fd);
    if (existsSync(temp)) unlinkSync(temp);
  }
}

/** Store only validated nonsecret settings in an operator-owned location */
export class LocalSettings {
  constructor(directory = operatorDirectory()) { this.directory = directory; }
  load() {
    if (!existsSync(this.directory)) return null;
    privateDirectory(this.directory);
    const value = readPrivate(join(this.directory, 'connection.json'), null);
    return value === null ? null : validateConfiguration(value);
  }
  save(value) {
    const config = validateConfiguration(value);
    privateDirectory(this.directory);
    writePrivate(join(this.directory, 'connection.json'), config);
    return config;
  }
}

/** Persist conservative session charges before credential retrieval; reload never resets spending */
export class SessionBudget {
  constructor(sessionId, directory = operatorDirectory()) {
    if (typeof sessionId !== 'string' || !sessionId) throw new Unavailable('budget-unavailable');
    this.directory = directory;
    this.path = join(directory, `budget-${createHash('sha256').update(sessionId).digest('hex')}.json`);
  }
  #change(update) {
    let lock;
    try {
      privateDirectory(this.directory);
      lock = openSync(`${this.path}.lock`, constants.O_WRONLY | constants.O_CREAT | constants.O_EXCL | constants.O_NOFOLLOW, 0o600);
      const ledger = readPrivate(this.path, { settled: 0, pending: {} });
      if (!Number.isSafeInteger(ledger.settled) || ledger.settled < 0 || !ledger.pending ||
        typeof ledger.pending !== 'object' || Array.isArray(ledger.pending) ||
        (ledger.halted !== undefined && ledger.halted !== true) ||
        Object.keys(ledger.pending).length > 128 || Object.values(ledger.pending).some(v => !Number.isSafeInteger(v) || v < 0)) {
        throw new Unavailable('budget-unavailable');
      }
      const result = update(ledger);
      writePrivate(this.path, ledger);
      return result;
    } catch (error) {
      if (error instanceof Unavailable && error.code === 'session-budget') throw error;
      throw new Unavailable('budget-unavailable');
    } finally {
      if (lock !== undefined) { closeSync(lock); unlinkSync(`${this.path}.lock`); }
    }
  }
  reserve(amount, ceiling) {
    return this.#change(ledger => {
      if (ledger.halted === true || !Number.isSafeInteger(amount) || amount < 1 ||
        !Number.isSafeInteger(ceiling) || ceiling < amount) throw new Unavailable('budget-unavailable');
      const used = ledger.settled + Object.values(ledger.pending).reduce((sum, value) => sum + value, 0);
      if (!Number.isSafeInteger(used + amount) || used + amount > ceiling || Object.keys(ledger.pending).length >= 128) {
        throw new Unavailable('session-budget');
      }
      const id = randomUUID(); ledger.pending[id] = amount; return id;
    });
  }
  settle(id, amount) {
    this.#change(ledger => {
      if (!Object.hasOwn(ledger.pending, id) || !Number.isSafeInteger(amount) || amount < 0 ||
        !Number.isSafeInteger(ledger.settled + amount)) throw new Unavailable('budget-unavailable');
      // A breached reservation invalidates the price bound for this session, including after reload.
      if (amount > ledger.pending[id]) ledger.halted = true;
      delete ledger.pending[id]; ledger.settled += amount;
    });
  }
}
