import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { existsSync, lstatSync } from 'node:fs';
import { FileSource, evidenceByteLimit } from './file-screening.mjs';
import { hash } from './screening-profile.mjs';
import { Unavailable } from './readiness.mjs';

/** Execute fixed read-only Git queries without external diff/textconv helpers */
export function subjectGit(cwd, ...args) {
  return execFileSync('git', ['--no-pager', '-c', 'core.fsmonitor=false', '-C', cwd, ...args],
    { encoding: 'utf8', timeout: 5000, maxBuffer: 2097152 });
}

/** Bind tracked and untracked review bytes, not merely a head OID or dirty marker */
export function sourceSubject(cwd, base, settings) {
  const head = subjectGit(cwd, 'rev-parse', 'HEAD').trim();
  const status = subjectGit(cwd, 'status', '--porcelain=v1', '--untracked-files=all', '--ignore-submodules=all').replace(/\n$/, '');
  const changed = subjectGit(cwd, 'diff', '--no-ext-diff', '--no-textconv', '--ignore-submodules=all', '--name-only', '-z', base, '--').split('\0').filter(Boolean);
  const untracked = subjectGit(cwd, 'ls-files', '--others', '--exclude-standard', '-z').split('\0').filter(Boolean);
  const paths = [...new Set([...changed, ...untracked])].sort();
  if (paths.length > 128 || Buffer.byteLength(status) > 16384) throw new Unavailable('build-subject-limit');
  const source = new FileSource();
  let bytes = 0;
  const files = paths.map(path => {
    if (path.length > 4096 || /[\x00-\x1f\x7f]/.test(path)) throw new Unavailable('build-subject-limit');
    const absolute = resolve(cwd, path);
    source.authorize(absolute, settings, { allowMissing: true });
    if (!existsSync(absolute)) return { path, digest: null, mode: null };
    const snapshot = source.snapshot(absolute, settings, { sourceLimit: evidenceByteLimit });
    bytes += snapshot.bytes;
    if (bytes > 8388608) throw new Unavailable('build-subject-limit');
    return { path, digest: snapshot.digest, mode: Number(lstatSync(absolute).mode) & 0o111 };
  });
  const subject = { head, base, status: status || 'clean', sourceDigest: hash(JSON.stringify(files)), files };
  if (Buffer.byteLength(JSON.stringify(subject)) > 20000) throw new Unavailable('build-subject-limit');
  return subject;
}

/** Validate the mechanical envelope; semantic evidence and independence remain the coordinator's judgment */
export function validateRoleReport(markdown, plan, subject) {
  const fail = () => { throw new Unavailable('build-report-subject-or-format'); };
  const match = markdown.replaceAll('\r\n', '\n').match(/^---\n([\s\S]{1,16384}?)\n---(?:\n|$)([\s\S]+)$/);
  if (!match) fail();
  const fields = {}, lines = match[1].split('\n');
  for (let i = 0; i < lines.length; i++) {
    if (!lines[i]) continue;
    const row = lines[i].match(/^([a-z_]+): (.*)$/);
    if (!row || Object.hasOwn(fields, row[1])) fail();
    let value = row[2];
    if (value === '|') {
      const block = [];
      while (i + 1 < lines.length && lines[i + 1].startsWith('  ')) block.push(lines[++i].slice(2));
      value = block.join('\n');
    }
    fields[row[1]] = value;
  }
  const role = plan.role, body = match[2];
  if (fields.task !== plan.task || fields.target_branch !== plan.build.branch || fields.base_commit !== plan.build.base ||
    fields.head_commit !== subject.head || fields.target_status !== subject.status ||
    !/^[a-f0-9]{40,64}$/.test(fields.head_commit) || !/^[a-f0-9]{40,64}$/.test(fields.base_commit)) fail();
  // Review and QA cannot silently relabel a new implementation as their assigned subject. Engineer/land
  // may make owned commits/reconciliation; the current declared subject is retained, never host-stamped over it.
  if (['review', 'qa'].includes(role) && fields.head_commit !== plan.build.head) fail();
  if (role === 'review') {
    if (!['2', '3'].includes(fields.review_handoff_version) || fields.qa_handoff_version || fields.build_handoff_version ||
      (fields.role && fields.role !== role) || !['accept', 'revise'].includes(fields.verdict)) fail();
    for (const id of ['SP-01', 'SP-02', 'SP-03', 'SP-04', 'SP-05', 'ST-01', 'ST-02', 'ST-03', 'ST-04', 'ST-05']) if (!body.includes(id)) fail();
    for (const section of ['Spec', 'Standards', 'Findings', 'Limitations']) if (!new RegExp(`^## .*${section}.*$`, 'mi').test(body)) fail();
  } else if (role === 'qa') {
    if (fields.qa_handoff_version !== '1' || fields.review_handoff_version || fields.build_handoff_version ||
      (fields.role && fields.role !== role) || !['PASS', 'FAIL', 'INCOMPLETE', 'N/A'].includes(fields.disposition)) fail();
    if (!body.includes(`- QA disposition: ${fields.disposition}\n`)) fail();
    for (const section of ['Identity and disposition', 'Runtime and scope', 'Scenarios', 'Findings and repair handoff', 'Evidence manifest', 'Reuse and limitations', 'Publication and next action']) {
      if (!body.includes(`## ${section}\n`)) fail();
    }
    if (!/^Totals: .+/m.test(body) || !/^\|.+\|/m.test(body)) fail();
  } else if (fields.build_handoff_version !== '1' || fields.role !== role || fields.review_handoff_version || fields.qa_handoff_version || fields.verdict || fields.disposition) fail();
  return { role, head: fields.head_commit, base: fields.base_commit, status: fields.target_status,
    sourceDigest: subject.sourceDigest, verdict: fields.verdict ?? fields.disposition ?? null };
}
