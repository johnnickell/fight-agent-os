import { randomUUID } from 'node:crypto';
import { dirname, isAbsolute, relative, resolve, sep } from 'node:path';
import { homedir } from 'node:os';
import { FileSource, evidenceByteLimit, sourceByteLimit } from './file-screening.mjs';
import { hash } from './screening-profile.mjs';
import { configurationIdentity, validateConfiguration, Unavailable } from './readiness.mjs';

const fail = code => { throw new Unavailable(code); };
// Match native ~/ reads before applying the same canonical authority and inode checks.
const requestedPath = (cwd, path) => resolve(cwd, path === '~' ? homedir() : path.startsWith('~/') ? resolve(homedir(), path.slice(2)) : path);
const range = input => {
  if (!Number.isSafeInteger(input.offset) || input.offset < 1 || !Number.isSafeInteger(input.limit) || input.limit < 1 || input.limit > 1000) fail('bounded-range-required');
  if (typeof input.need !== 'string' || !input.need.trim() || Buffer.byteLength(input.need) > 4096) fail('information-need-required');
  return { offset: input.offset, limit: input.limit };
};

/** Locate one textual ATX section, excluding frontmatter, fenced examples and HTML comments */
function markdownHeadingTarget(lines, title) {
  let frontmatter = /^\uFEFF?---\s*$/.test(lines[0]), comment = false, fence = null, target = null;
  for (let index = 0; index < lines.length; index++) {
    const line = index === 0 ? lines[index].replace(/^\uFEFF/, '') : lines[index];
    if (frontmatter) {
      if (index > 0 && /^(---|\.\.\.)\s*$/.test(line)) frontmatter = false;
      continue;
    }
    if (comment) { if (line.includes('-->')) comment = false; continue; }
    const marker = /^ {0,3}(`{3,}|~{3,})(.*)$/.exec(line);
    if (fence) {
      if (marker && marker[1][0] === fence.character && marker[1].length >= fence.length && !marker[2].trim()) fence = null;
      continue;
    }
    if (marker && (marker[1][0] !== '`' || !marker[2].includes('`'))) {
      fence = { character: marker[1][0], length: marker[1].length }; continue;
    }
    if (line.includes('<!--')) { comment = !line.slice(line.indexOf('<!--') + 4).includes('-->'); continue; }
    const heading = /^ {0,3}(#{1,6})(?:[ \t]+(.*?)|[ \t]*)$/.exec(line);
    if (!heading) continue;
    const level = heading[1].length;
    if (target && target.end === lines.length && level <= target.level) target.end = index;
    const text = (heading[2] ?? '').replace(/(?:^|[ \t]+)#+[ \t]*$/, '').trim();
    if (text !== title) continue;
    if (target) fail('exact-heading-target-required');
    target = { start: index, end: lines.length, level };
  }
  if (!target) fail('exact-heading-target-required');
  return { offset: target.start + 1, limit: Math.min(40, target.end - target.start) };
}

/** Admit exact current bytes from trusted in-process decisions or independently established evidence */
export class FileAdmission {
  #receipts = new Map();
  #evidence = new Map();
  #closed = false;
  #lookups = 0;
  #instructions = [];
  #nativeInstructions = [];
  #nativeSkills = new Map();
  #linkedInstructions = new Map();
  #targets = [];
  #intakeAuthority;
  #nativeDocumentation;
  #documentationDigests = new Map();
  constructor({ profiles, configuration, sessionId, cwd, source = new FileSource(), observe = () => {}, intake = null, nativeDocumentation = null }) {
    this.profiles = profiles; this.configuration = configuration; this.sessionId = sessionId; this.cwd = cwd; this.source = source; this.observe = observe;
    this.#intakeAuthority = profiles.selection(cwd)?.identity;
    // Installation-owned paths, never tool arguments or text parsed from a system prompt.
    if (nativeDocumentation !== null) {
      if (![nativeDocumentation.readmePath, nativeDocumentation.docsPath,
        ...(nativeDocumentation.examplesPath === undefined ? [] : [nativeDocumentation.examplesPath])].every(path =>
        typeof path === 'string' && isAbsolute(path) && path.length <= 4096 && !path.includes('\0'))) fail('invalid-trusted-intake');
      this.#nativeDocumentation = { readmePath: resolve(nativeDocumentation.readmePath), docsPath: resolve(nativeDocumentation.docsPath),
        ...(nativeDocumentation.examplesPath === undefined ? {} : { examplesPath: resolve(nativeDocumentation.examplesPath) }) };
    }
    if (intake) {
      if (intake.version !== 1 || intake.authority !== profiles.selection(cwd)?.identity || intake.cwd !== cwd) fail('stale-trusted-intake');
      this.#instructions = intake.instructions; this.#targets = intake.targets;
    }
  }
  /** Take exact native context/advertised skill witnesses, never model-supplied paths or prompt XML */
  ingestNativeInstructions(files, skills = [], invokedSkill = null) {
    if (this.#closed) fail('session-changed');
    // Replacing the host view withdraws removed witnesses; it never replenishes lookup/spend limits.
    this.#nativeInstructions = []; this.#nativeSkills.clear(); this.#linkedInstructions.clear();
    if (!Array.isArray(files) || files.length > 48 || !Array.isArray(skills)) fail('invalid-trusted-intake');
    if (this.profiles.selection(this.cwd)?.identity !== this.#intakeAuthority) fail('stale-trusted-intake');
    const settings = this.profiles.fileSettings(this.sessionId, this.cwd).load();
    for (const file of files) {
      try {
        const path = resolve(this.cwd, file.path);
        // Only this exact host-provided path gets local access; exclusions and inode checks remain.
        const snapshot = this.source.snapshot(path, { ...settings, allowedRoots: [dirname(path)] }, { sourceLimit: evidenceByteLimit });
        if (hash(file.content) === snapshot.digest) this.#nativeInstructions.push({ path: snapshot.canonical, digest: snapshot.digest });
      } catch { /* Invalid, excluded or stale native context never becomes a witness */ }
    }
    for (const skill of skills) {
      if (skill && (!skill.disableModelInvocation || skill.name === invokedSkill) && typeof skill.filePath === 'string' &&
        skill.filePath.length <= 4096 && !skill.filePath.includes('\0') && isAbsolute(skill.filePath)) {
        // Metadata only: do not open every advertised skill. Pin bytes when the selected skill is read.
        this.#nativeSkills.set(resolve(skill.filePath), null);
      }
    }
  }
  close() {
    this.#closed = true; this.#receipts.clear(); this.#evidence.clear();
    this.#nativeInstructions = []; this.#nativeSkills.clear(); this.#linkedInstructions.clear();
    this.#documentationDigests.clear();
  }
  #isNativeDocumentation(path) {
    return this.#nativeDocumentation !== undefined && this.profiles.selection(this.cwd)?.identity === this.#intakeAuthority &&
      (path === this.#nativeDocumentation.readmePath ||
        (path.startsWith(this.#nativeDocumentation.docsPath + sep) && /\.md$/i.test(path)) ||
        (this.#nativeDocumentation.examplesPath !== undefined && path.startsWith(this.#nativeDocumentation.examplesPath + sep) &&
          /\.(?:md|[cm]?[jt]s|[jt]sx|json)$/i.test(path) &&
          !relative(this.#nativeDocumentation.examplesPath, path).split(sep).includes('node_modules')));
  }
  #current(path, instruction = false, exactEvidence = instruction) {
    if (this.#closed) fail('session-changed');
    const selected = this.profiles.selection(this.cwd);
    if (!selected) fail('profile-unavailable');
    const settings = this.profiles.fileSettings(this.sessionId, this.cwd).load();
    const resolved = requestedPath(this.cwd, path);
    const runtimeWitness = selected.identity === this.#intakeAuthority &&
      ([...this.#instructions, ...this.#nativeInstructions].some(i => i.path === resolved) || this.#nativeSkills.has(resolved) || this.#linkedInstructions.has(resolved));
    const exactInstruction = instruction && (selected.project.instructions.some(i => i.path === resolved) || runtimeWitness);
    const documentation = exactEvidence && this.#isNativeDocumentation(resolved);
    const snapshot = this.source.snapshot(resolved, exactInstruction || documentation ? { ...settings, allowedRoots: [dirname(resolved)] } : settings,
      { sourceLimit: exactEvidence ? evidenceByteLimit : sourceByteLimit });
    if (documentation) {
      const previous = this.#documentationDigests.get(snapshot.canonical);
      if (previous !== undefined && previous !== snapshot.digest) fail('native-documentation-changed');
      this.#documentationDigests.set(snapshot.canonical, snapshot.digest);
    }
    return { ...selected, snapshot };
  }
  /** Only the consumer's actual returned/cache-validated result calls this, never tool input */
  accept(input, result) {
    const selected = this.profiles.selection(this.cwd);
    if (!selected || this.#closed) return;
    const expectedPolicy = hash(JSON.stringify(this.profiles.fileSettings(this.sessionId, this.cwd).load()));
    for (const value of result.results ?? [result]) {
      const scope = value.authority ?? result.authority;
      if (scope?.policyDigest !== expectedPolicy || scope?.configuration !== selected.profile.configuration ||
        scope?.revision !== selected.profile.revision || scope?.sessionDigest !== hash(this.sessionId)) continue;
      if (value.status !== 'complete' || value.threshold !== 'positive' || value.coverage?.complete !== true || !value.source || !value.question?.digest) continue;
      if (!value.cache?.hit && (value.receipt?.state !== 'complete' || !value.receipt?.evaluationId)) continue;
      if (!value.cache?.hit && !result.results && (value.receipt?.sessionDigest !== hash(this.sessionId) ||
        value.receipt?.configuration !== selected.profile.configuration || value.receipt?.authorityRevision !== selected.profile.revision ||
        value.receipt?.questionDigest !== value.question.digest)) continue;
      const token = randomUUID();
      value.admission = { receipt: token, questionDigest: value.question.digest };
      if (Buffer.byteLength(JSON.stringify(result)) > 65536) {
        delete value.admission;
        Object.assign(value, { status: 'admission-presentation-limit', threshold: 'unresolved', judgment: null,
          source: null, receipt: null, coverage: { complete: false } });
        result.coverage.complete = false;
        result.status = result.results ? 'incomplete' : 'admission-presentation-limit';
        continue;
      }
      this.#receipts.set(token, { authority: selected.identity, need: input.need, questionDigest: value.question.digest,
        source: structuredClone(value.source), session: this.sessionId });
      while (this.#receipts.size > 128) this.#receipts.delete(this.#receipts.keys().next().value);
    }
  }
  /** Return only definition locations, never source lines, under a finite structural discovery allowance */
  locate({ path, symbol, heading, need }) {
    const byHeading = heading !== undefined;
    if (typeof need !== 'string' || !need.trim() || Buffer.byteLength(need) > 4096 ||
      (byHeading ? symbol !== undefined || typeof heading !== 'string' || !heading.trim() || heading.length > 128 || /[\x00-\x1f\x7f]/.test(heading)
        : typeof symbol !== 'string' || !/^[A-Za-z_][A-Za-z0-9_]{2,127}$/.test(symbol))) fail('invalid-structural-query');
    if (++this.#lookups > 32) fail('structural-query-limit');
    // Structural evidence uses the existing 1 MiB local exact-evidence cap, not evaluator disclosure.
    const { snapshot, identity } = this.#current(path, false, true);
    const lines = snapshot.text.split('\n');
    let target;
    if (byHeading) target = markdownHeadingTarget(lines, heading.trim());
    else {
      const expression = new RegExp(`\\b(?:class|function|interface|trait|enum|type)\\s+${symbol}\\b`);
      const matches = [];
      for (let i = 0; i < lines.length && matches.length < 8; i++) if (expression.test(lines[i])) matches.push(i + 1);
      if (matches.length !== 1) fail('exact-symbol-target-required');
      target = { offset: matches[0], limit: Math.min(40, lines.length - matches[0] + 1) };
    }
    const token = randomUUID(), kind = byHeading ? 'verified-heading' : 'verified-symbol';
    this.#evidence.set(token, { authority: identity, source: { id: snapshot.id, digest: snapshot.digest, revision: snapshot.revision },
      need, ...target, kind, targetId: hash(byHeading ? heading.trim() : symbol) });
    while (this.#evidence.size > 64) this.#evidence.delete(this.#evidence.keys().next().value);
    return { evidence: token, pathId: snapshot.id, offset: target.offset, maxLines: target.limit, kind };
  }
  read(input) {
    if (![input.receipt, input.exception, input.evidence].some(Boolean)) return this.#readDirect(input);
    if (input.exception === 'instruction' && !input.receipt && !input.evidence) return this.#readDirect(input, true);
    range(input);
    if ([input.receipt, input.exception, input.evidence].filter(Boolean).length !== 1) fail('unambiguous-evidence-required');
    const { snapshot, project, identity, profile } = this.#current(input.path, input.exception === 'instruction', Boolean(input.exception || input.evidence));
    let kind;
    if (input.receipt) {
      if (!this.configuration || configurationIdentity(validateConfiguration(this.configuration())) !== profile.configuration) fail('stale-configuration');
      const held = this.#receipts.get(input.receipt);
      if (!held || held.session !== this.sessionId || held.authority !== identity || held.need !== input.need || held.questionDigest !== input.questionDigest ||
        held.source.id !== snapshot.id || held.source.digest !== snapshot.digest || held.source.revision !== snapshot.revision) fail('current-positive-receipt-required');
      kind = 'receipt-admission';
    } else if (input.exception === 'required-evidence') {
      const target = [...project.targets, ...(identity === this.#intakeAuthority ? this.#targets : [])].find(t => t.path === snapshot.canonical && t.digest === snapshot.digest && t.purpose === input.need && input.offset >= t.offset && input.offset + input.limit <= t.offset + t.limit);
      if (!target) fail('unestablished-evidence-obligation');
      if (target.evidence?.kind === 'required-reference') {
        const { snapshot: establishing } = this.#current(target.evidence.from, true);
        if (establishing.digest !== target.evidence.digest) fail('stale-required-reference');
      }
      kind = target.evidence?.kind ?? 'required-evidence';
    } else if (input.evidence) {
      const held = this.#evidence.get(input.evidence);
      if (!held || held.authority !== identity || held.need !== input.need || held.source.id !== snapshot.id || held.source.digest !== snapshot.digest ||
        held.source.revision !== snapshot.revision || input.offset < held.offset || input.offset + input.limit > held.offset + held.limit) fail('current-target-evidence-required');
      kind = held.kind;
    } else fail('screen-or-verified-exception-required');
    const lines = snapshot.text.split('\n');
    if (input.offset > lines.length) fail('range-outside-source');
    const text = lines.slice(input.offset - 1, input.offset - 1 + input.limit).join('\n');
    if (Buffer.byteLength(text) > sourceByteLimit) fail('admission-byte-limit');
    this.observe({ kind: kind === 'receipt-admission' ? kind : 'exception', reason: kind,
      pathId: snapshot.id, needId: hash(input.need), offset: input.offset, lines: Math.min(input.limit, lines.length - input.offset + 1), decision: 'admitted' });
    return { text, admission: { kind, pathId: snapshot.id, digest: snapshot.digest, offset: input.offset, limit: input.limit } };
  }

  /** Read normal project files locally; discovery screening is encouraged, not an access prerequisite */
  #readDirect(input, requireInstruction = false) {
    input = { ...input, offset: input.offset ?? 1, limit: input.limit ?? 200, need: input.need ?? 'Read requested file' };
    range(input);
    const selectedProject = this.profiles.selection(this.cwd);
    const native = selectedProject?.identity === this.#intakeAuthority;
    const resolved = requestedPath(this.cwd, input.path);
    const inProject = selectedProject && this.profiles.fileSettings(this.sessionId, this.cwd).load().allowedRoots
      .some(root => resolved === root || resolved.startsWith(root + sep));
    let linked = native ? this.#linkedInstructions.get(resolved) : undefined;
    for (const witness of linked ?? []) {
      try {
        if (this.#current(witness.path, true, true).snapshot.digest !== witness.digest) fail('stale-required-reference');
      } catch (error) {
        // A withdrawn external witness cannot revoke otherwise ordinary in-project access.
        if (requireInstruction || !inProject) throw error;
        linked = undefined; break;
      }
    }
    const witnesses = linked ?? [];
    const { snapshot, project } = this.#current(input.path, true, true);
    const instruction = project.instructions.some(i => i.path === snapshot.canonical) || (native &&
      ([...this.#instructions, ...this.#nativeInstructions].some(i => i.path === snapshot.canonical) || this.#nativeSkills.has(snapshot.canonical) || Boolean(linked)));
    const nativeSkill = native && this.#nativeSkills.has(snapshot.canonical);
    if (nativeSkill && this.#nativeSkills.get(snapshot.canonical) === null) this.#nativeSkills.set(snapshot.canonical, snapshot.digest);
    const trustedLinks = Boolean(linked) || [...project.instructions, ...(native ? [...this.#instructions, ...this.#nativeInstructions] : [])]
      .some(i => i.path === snapshot.canonical && i.digest === snapshot.digest) ||
      (nativeSkill && this.#nativeSkills.get(snapshot.canonical) === snapshot.digest);
    if (requireInstruction && !trustedLinks && !this.#isNativeDocumentation(snapshot.canonical)) fail('unestablished-instruction');
    // Only host-established instructions seed external exact Markdown references. Ordinary project
    // content cannot grant outside-root access. Each link retains the complete source witness chain.
    if (trustedLinks && witnesses.length < 8) {
      const chain = [...witnesses, { path: snapshot.canonical, digest: snapshot.digest }];
      for (const match of snapshot.text.matchAll(/\[[^\]\n]+\]\(([^)\s]+)\)/g)) {
        const link = match[1].split('#')[0];
        if (!link || !/\.md$/i.test(link) || /^(?:[a-z]+:|\/|~)/i.test(link) || link.includes('%') || link.includes('\\')) continue;
        const path = resolve(dirname(snapshot.canonical), link);
        if (!chain.some(w => w.path === path) && this.#linkedInstructions.size < 128) this.#linkedInstructions.set(path, chain);
      }
    }
    const lines = snapshot.text.split('\n');
    if (input.offset > lines.length) fail('range-outside-source');
    const selected = [];
    let bytes = 0;
    for (const line of lines.slice(input.offset - 1, input.offset - 1 + input.limit)) {
      const size = Buffer.byteLength(line) + (selected.length ? 1 : 0);
      if (bytes + size > sourceByteLimit) break;
      selected.push(line); bytes += size;
    }
    if (!selected.length) fail('admission-byte-limit');
    const nextOffset = input.offset + selected.length;
    const kind = requireInstruction ? 'mandatory-instruction' : this.#isNativeDocumentation(snapshot.canonical) ? 'native-documentation-read' : instruction ? 'instruction-read' : 'project-read';
    this.observe({ kind: requireInstruction ? 'exception' : kind, reason: kind, pathId: snapshot.id, needId: hash(input.need), offset: input.offset, lines: selected.length, decision: 'admitted' });
    return { text: selected.join('\n'), admission: { kind, pathId: snapshot.id, digest: snapshot.digest,
      offset: input.offset, limit: selected.length, ...(nextOffset <= lines.length ? { nextOffset } : {}) } };
  }
}
