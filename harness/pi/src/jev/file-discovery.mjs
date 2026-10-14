import { createHash } from 'node:crypto';
import { opendirSync, closeSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { resolve, relative, sep } from 'node:path';
import { Unavailable } from './readiness.mjs';

export const batchLimits = Object.freeze({ files: 50, sourceBytes: 1048576, entries: 10000, milliseconds: 60000, resultBytes: 65536, cacheEntries: 128, cacheEntryBytes: 8192 });
const hash = value => createHash('sha256').update(value).digest('hex');

// Segment matching uses a bounded dynamic program, not shell expansion or backtracking regexes.
function segmentMatches(name, pattern) {
  let row = [true, ...Array(name.length).fill(false)];
  for (const token of pattern) {
    const next = Array(name.length + 1).fill(false);
    if (token === '*') next[0] = row[0];
    for (let n = 1; n <= name.length; n++) next[n] = token === '*' ? row[n] || next[n - 1] : row[n - 1] && (token === '?' || token === name[n - 1]);
    row = next;
  }
  return row[name.length];
}
function matches(parts, pattern) {
  let states = new Set([0]);
  const expand = () => { for (let n = 0; n < pattern.length; n++) if (states.has(n) && pattern[n] === '**') states.add(n + 1); };
  expand();
  for (const part of parts) {
    const next = new Set();
    for (const n of states) if (pattern[n] === '**') next.add(n); else if (pattern[n] && segmentMatches(part, pattern[n])) next.add(n + 1);
    states = next; expand();
  }
  return states.has(pattern.length);
}

/** Enumerate only authorized directories through held descriptors, stopping before unbounded traversal */
export async function discoverFiles({ input, cwd, policy, source, stopped, signal, remainingMs = batchLimits.milliseconds }) {
  const candidates = [], seen = new Set(), skips = {};
  let visitedEntries = 0, duplicates = 0, complete = true, reason = null;
  const skip = code => { skips[code] = (skips[code] ?? 0) + 1; };
  const add = path => {
    if (candidates.length >= batchLimits.files) { complete = false; reason = 'file-count-limit'; return false; }
    try {
      const canonical = source.authorize(path, policy);
      if (seen.has(canonical)) { duplicates++; return true; }
      seen.add(canonical);
      const display = relative(cwd, canonical);
      if (Buffer.byteLength(display) > 512) { skip('path-display-limit'); complete = false; return true; }
      candidates.push({ path: canonical, display, id: hash(canonical) });
    } catch (error) {
      // Denied paths are never exposed. Explicit lists retain their caller index only.
      candidates.push({ index: candidates.length, status: error instanceof Unavailable ? error.code : 'source-unavailable' });
    }
    return true;
  };
  if (input.paths) {
    for (const path of input.paths) {
      if (stopped()) { complete = false; reason = stopped(); break; }
      if (!add(resolve(cwd, path))) break;
    }
  } else {
    const full = resolve(cwd, input.glob), parts = full.split(sep).filter(Boolean);
    const magic = parts.findIndex(part => /[*?]/.test(part));
    if (magic < 0) add(full);
    else {
      const root = sep + parts.slice(0, magic).join(sep), pattern = parts.slice(magic), stack = [];
      const push = (path, trail) => {
        let fd;
        try {
          fd = source.openDirectory(path, policy);
          const directory = opendirSync(`${process.platform === 'linux' ? '/proc/self/fd' : '/dev/fd'}/${fd}`, { bufferSize: 1 });
          stack.push({ directory, fd, trail });
        } catch (error) { if (fd !== undefined) closeSync(fd); skip(error instanceof Unavailable ? error.code : 'enumeration-unavailable'); complete = false; }
      };
      if (process.platform === 'darwin') {
        const enumerated = await enumerateMacDirectory({ root, pattern, policy, source, signal, remainingMs });
        visitedEntries = enumerated.visitedEntries; complete = enumerated.complete; reason = enumerated.reason;
        for (const [code, count] of Object.entries(enumerated.skips)) skips[code] = count;
        for (const path of enumerated.paths) add(path);
      } else {
        push(root, []);
        try {
          while (stack.length) {
            const stop = stopped();
            if (stop || visitedEntries >= batchLimits.entries || candidates.length >= batchLimits.files) {
              complete = false; reason = stop || (visitedEntries >= batchLimits.entries ? 'enumeration-limit' : 'file-count-limit'); break;
            }
            const current = stack.at(-1), entry = current.directory.readSync();
            if (!entry) { current.directory.closeSync(); closeSync(current.fd); stack.pop(); continue; }
            visitedEntries++;
            const trail = [...current.trail, entry.name], path = resolve(root, ...trail);
            if (entry.isSymbolicLink()) { skip('symlink-denied'); complete = false; continue; }
            if (entry.isDirectory()) {
              if (stack.length >= 128) { skip('directory-depth-limit'); complete = false; continue; }
              // Do not descend when no glob state can possibly reach this depth.
              if (!pattern.includes('**') && trail.length >= pattern.length) continue;
              push(path, trail);
            } else if (matches(trail, pattern)) add(path);
          }
        } catch { complete = false; reason = 'enumeration-unavailable'; }
        finally { for (const item of stack) { item.directory.closeSync(); closeSync(item.fd); } }
      }
    }
  }
  return { candidates, coverage: { scope: input.paths ? 'explicit-list' : 'glob', complete, additionalCandidatesMayExist: !complete,
    visitedEntries, candidateCount: candidates.length, duplicates, skips, reason } };
}

/** Use macOS fdopendir through the system Python stdlib: Node cannot opendir /dev/fd directories */
async function enumerateMacDirectory({ root, pattern, policy, source, signal, remainingMs }) {
  let fd;
  const unavailable = () => ({ paths: [], visitedEntries: null, skips: { 'enumeration-unavailable': 1 }, complete: false, reason: 'enumeration-unavailable' });
  try {
    fd = source.openDirectory(root, policy);
    const payload = JSON.stringify({ root, pattern, excludedPaths: policy.excludedPaths, excludedNames: policy.excludedNames });
    if (Buffer.byteLength(payload) > 65536 || signal?.aborted || remainingMs <= 0) return unavailable();
    return await new Promise(resolveResult => {
      // Fixed packaged helper, isolated stdlib, descriptor 3 only. No shell, source code execution or ambient Python paths.
      const child = spawn('/usr/bin/python3', ['-I', '-S', fileURLToPath(new URL('./file-discovery.py', import.meta.url))], {
        stdio: ['pipe', 'pipe', 'pipe', fd], env: { PATH: '/usr/bin:/bin' }, signal,
        timeout: Math.min(batchLimits.milliseconds, remainingMs), killSignal: 'SIGKILL',
      });
      const chunks = []; let bytes = 0, failed = false;
      child.stdout.on('data', chunk => { bytes += chunk.length; if (bytes > 65536) { failed = true; child.kill('SIGKILL'); } else chunks.push(chunk); });
      child.stderr.resume(); // Raw helper errors never reach caller metadata or presentation.
      child.on('error', () => { failed = true; });
      child.stdin.on('error', () => { failed = true; });
      child.on('close', code => {
        try {
          if (failed || code !== 0) return resolveResult(unavailable());
          const value = JSON.parse(Buffer.concat(chunks).toString('utf8'));
          if (!Array.isArray(value.paths) || value.paths.length > 50 || !value.paths.every(path => typeof path === 'string' && path.length <= 4096) ||
            !Number.isInteger(value.visitedEntries) || value.visitedEntries < 0 || value.visitedEntries > 10000 ||
            typeof value.complete !== 'boolean' || !value.skips || Object.values(value.skips).some(count => !Number.isInteger(count) || count < 0)) return resolveResult(unavailable());
          resolveResult(value);
        } catch { resolveResult(unavailable()); }
      });
      child.stdin.end(payload);
    });
  } catch (error) {
    return { ...unavailable(), skips: { [error instanceof Unavailable ? error.code : 'enumeration-unavailable']: 1 } };
  } finally { if (fd !== undefined) closeSync(fd); }
}
