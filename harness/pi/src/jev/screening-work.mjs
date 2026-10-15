import { existsSync, mkdirSync, readdirSync, rmdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { randomUUID } from 'node:crypto';
import { FileSource, evidenceByteLimit } from './file-screening.mjs';
import { readPrivate, writePrivate, privateDirectory } from './local-store.mjs';
import { hash } from './screening-profile.mjs';
import { sourceSubject, subjectGit } from './screening-subject.mjs';
import { Unavailable } from './readiness.mjs';

const fail = code => { throw new Unavailable(code); };
const same = (a, b) => a.head === b.head && a.base === b.base && a.status === b.status && a.sourceDigest === b.sourceDigest;
const operations = { engineer: ['check', 'commit'], review: ['check'], qa: ['check'], land: ['check', 'commit', 'publication'] };
const current = scope => sourceSubject(scope.grant.cwd, scope.plan.build.base, scope.settings);
const entries = directory => existsSync(directory) ? readdirSync(directory).filter(n => /^[a-f0-9-]{36}\.json$/.test(n)).sort() : [];

/** Finite role work: read-only identity/exact source evidence, or a protected external execution handoff */
export async function roleWork(scope, input, { signal, validate = () => {}, wait = true, onPending = () => {} } = {}) {
  validate();
  const { grant, plan, directory, settings } = scope, cwd = grant.cwd;
  const subject = current(scope);
  if (input.operation === 'identity') return { task: grant.task, checkout: cwd, origin: grant.origin, branch: grant.branch,
    base: subject.base, head: subject.head, status: subject.status, sourceDigest: subject.sourceDigest, role: plan.role };
  const work = join(directory, 'work'); privateDirectory(work);
  if (input.operation === 'changes') {
    const files = subject.files.map(file => {
      const entry = subjectGit(cwd, 'ls-tree', '-z', subject.base, '--', file.path).split('\0').filter(Boolean)[0];
      const target = plan.build.evidenceTargets.find(t => t.path === resolve(cwd, file.path));
      return { ...file, baseMode: entry?.split(' ')[0] ?? null, evidence: hash(plan.id + '\0' + subject.base + '\0' + file.path),
        authority: target?.kind ?? (!entry ? 'new-self-authored-file' : null), maxLines: target?.limit ?? null };
    });
    if (Buffer.byteLength(JSON.stringify({ subject, files })) > 60000) fail('build-subject-limit');
    writePrivate(join(work, 'changes.json'), { subject, files });
    return { base: subject.base, head: subject.head, sourceDigest: subject.sourceDigest, files,
      purpose: `Inspect exact effective changes for ${grant.task}; no directory/discovery authority` };
  }
  if (input.operation === 'evidence') {
    const held = readPrivate(join(work, 'changes.json'), null), file = held?.files.find(f => f.evidence === input.evidence);
    if (!file || !same(held.subject, subject)) fail('build-work-stale-evidence');
    // A model cannot manufacture an edit and use Git to read the previously unadmitted original.
    // Only the immutable host-assigned diff/task, or genuinely new self-authored source, qualifies here.
    if (!file.authority) fail('build-work-evidence-not-assigned');
    if (!['base', 'current'].includes(input.side) || !Number.isInteger(input.offset) || input.offset < 1 ||
      !Number.isInteger(input.limit) || input.limit < 1 || input.limit > 200) fail('bounded-range-required');
    if (file.maxLines !== null && input.offset + input.limit > file.maxLines + 1) fail('build-work-evidence-not-assigned');
    const path = resolve(cwd, file.path), source = new FileSource();
    source.authorize(path, settings, { allowMissing: true });
    let text;
    if (input.side === 'current') {
      if (file.digest === null) return { absent: true, side: 'current', evidence: input.evidence };
      text = source.snapshot(path, settings, { sourceLimit: evidenceByteLimit }).text;
      if (hash(text) !== file.digest) fail('build-work-stale-evidence');
    } else {
      const paths = subjectGit(cwd, 'ls-tree', '-z', subject.base, '--', file.path).split('\0').filter(Boolean);
      if (!paths.length) return { absent: true, side: 'base', evidence: input.evidence };
      if (paths.length !== 1 || !/^100(?:644|755) blob [a-f0-9]{40,64}\t/.test(paths[0])) fail('unsupported-file');
      const oid = paths[0].split(/[ \t]/)[2];
      if (Number(subjectGit(cwd, 'cat-file', '-s', oid).trim()) > evidenceByteLimit) fail('source-limit');
      text = subjectGit(cwd, 'cat-file', 'blob', oid);
      if (Buffer.byteLength(text) > evidenceByteLimit || /[\x00-\x08\x0b\x0c\x0e-\x1f\x7f\ufffd]/.test(text)) fail('binary-source');
    }
    const lines = text.split('\n');
    if (input.offset > lines.length) fail('range-outside-source');
    const selected = lines.slice(input.offset - 1, input.offset - 1 + input.limit).join('\n');
    if (Buffer.byteLength(selected) > 8192) fail('build-work-range-limit');
    validate();
    if (!same(subject, current(scope))) fail('build-work-stale-evidence');
    return { evidence: input.evidence, side: input.side, offset: input.offset, limit: input.limit, digest: hash(text),
      text: selected, reason: 'assigned-effective-change-evidence' };
  }
  if (!operations[plan.role]?.includes(input.operation)) fail('build-work-operation-denied');
  const requests = join(work, 'requests'); privateDirectory(requests);
  // Serialize request creation across sibling tool calls. A pending/uncertain request is never reset/replayed.
  const lock = join(work, 'request-active');
  try { mkdirSync(lock, { mode: 0o700 }); } catch { fail('build-work-busy'); }
  let request, path;
  try {
    const prior = entries(requests).map(name => ({ path: join(requests, name), value: readPrivate(join(requests, name), null) }));
    const found = prior.find(({ value }) => value.operation === input.operation &&
      (same(value.before, subject) || (value.result && same(value.result.subject, subject))));
    if (found) { request = found.value; path = found.path; }
    else {
      if (prior.length >= 16 || prior.some(({ value }) => !value.result || value.result.outcome === 'unknown')) fail('build-work-pending-or-limit');
      request = { version: 1, request: randomUUID(), build: grant.id, ticket: plan.id, task: grant.task, role: plan.role,
        operation: input.operation, before: subject, expiresAt: Math.min(grant.expiresAt, plan.expiresAt) };
      path = join(requests, `${request.request}.json`); writePrivate(path, request);
    }
  } finally { rmdirSync(lock); }
  const view = value => value.result ? { request: value.request, operation: value.operation, state: 'complete', outcome: value.result.outcome,
    head: value.result.subject.head, base: value.result.subject.base, status: value.result.subject.status,
    sourceDigest: value.result.subject.sourceDigest, evidenceDigest: value.result.evidenceDigest, summary: value.result.summary ?? null, publication: value.result.publication ?? null,
    trust: 'external-host-attestation-not-independent-acceptance' } :
    { request: value.request, operation: value.operation, state: 'pending', head: value.before.head, sourceDigest: value.before.sourceDigest,
      nextAction: 'Trusted executor: inspect and complete this exact request using screening-work-host.mjs; no Agent shell or raw output intake' };
  if (!request.result) onPending(view(request));
  if (!wait) return view(request);
  while (!request.result) {
    validate();
    if (signal?.aborted || Date.now() >= request.expiresAt) fail('build-work-pending');
    await new Promise(resolve => setTimeout(resolve, 250));
    request = readPrivate(path, null);
    if (!request) fail('build-work-pending');
  }
  validate();
  if (!same(request.result.subject, current(scope))) fail('build-work-stale-result');
  return view(request);
}

/** Complete only from an external trusted executor after its already-authorized work, never an Agent tool */
export function completeRoleWork(scope, requestId, result) {
  if (process.env.FIGHT_SCREENING_CHILD === '1') fail('build-work-host-only');
  if (!/^[a-f0-9-]{36}$/.test(requestId) || !['pass', 'fail', 'unknown'].includes(result.outcome) ||
    !/^[a-f0-9]{64}$/.test(result.evidenceDigest) || Object.keys(result).some(k => !['outcome', 'evidenceDigest', 'publication', 'summary'].includes(k))) fail('build-work-invalid-result');
  const path = join(scope.directory, 'work', 'requests', `${requestId}.json`), request = readPrivate(path, null);
  if (!request || request.ticket !== scope.plan.id || request.build !== scope.grant.id || request.expiresAt <= Date.now()) fail('build-work-not-authorized');
  const lock = join(scope.directory, 'work', 'completion-active');
  try { mkdirSync(lock, { mode: 0o700 }); } catch { fail('build-work-busy'); }
  try {
    const latest = readPrivate(path, null);
    if (latest.result) fail('build-work-already-completed');
    const subject = current(scope);
    if (request.operation === 'commit' && result.outcome === 'pass') {
      if (subject.sourceDigest !== request.before.sourceDigest || subject.base !== request.before.base || subject.head === request.before.head) fail('build-work-subject-drift');
      subjectGit(scope.grant.cwd, 'merge-base', '--is-ancestor', request.before.head, subject.head);
    } else if (!same(request.before, subject)) fail('build-work-subject-drift');
    if (request.operation === 'check' && result.outcome !== 'unknown') {
      const s = result.summary;
      if (!s || Object.keys(s).some(k => !['kind', 'exitCode', 'tests', 'assertions', 'warnings', 'finding'].includes(k)) ||
        !['full-gate', 'focused', 'scenario'].includes(s.kind) || !Number.isInteger(s.exitCode) || s.exitCode < 0 || s.exitCode > 255 ||
        (result.outcome === 'pass') !== (s.exitCode === 0) ||
        ['tests', 'assertions', 'warnings'].some(k => s[k] !== null && (!Number.isSafeInteger(s[k]) || s[k] < 0 || s[k] > 1000000000))) fail('build-work-invalid-result');
      if (s.finding) {
        const f = s.finding, held = readPrivate(join(scope.directory, 'work', 'changes.json'), null);
        const target = held?.files.find(file => file.evidence === f.evidence && file.authority);
        if (!target || !same(held.subject, subject) || Object.keys(f).sort().join(',') !== 'code,evidence,offset' ||
          !Number.isInteger(f.offset) || f.offset < 1 || f.offset > (target.maxLines ?? 1000000000) ||
          !['assertion-failed', 'test-failed', 'syntax-error', 'type-error', 'check-failed'].includes(f.code)) fail('build-work-invalid-result');
      }
    } else if (result.summary) fail('build-work-invalid-result');
    if (request.operation === 'publication' && result.outcome === 'pass') {
      const p = result.publication;
      if (!p || Object.keys(p).sort().join(',') !== 'head,url' || p.head !== subject.head ||
        typeof p.url !== 'string' || !/^https:\/\/[A-Za-z0-9.-]+\/[A-Za-z0-9_./-]{1,240}$/.test(p.url)) fail('build-work-invalid-result');
    } else if (result.publication) fail('build-work-invalid-result');
    writePrivate(path, { ...latest, result: { ...result, subject, completedAt: Date.now() } });
    return { request: requestId, state: 'complete', head: subject.head, outcome: result.outcome };
  } finally { rmdirSync(lock); }
}

export function pendingRoleWork(directory) {
  const requests = join(directory, 'work', 'requests');
  return entries(requests).map(n => readPrivate(join(requests, n), null)).filter(r => r && (!r.result || r.result.outcome === 'unknown'));
}
