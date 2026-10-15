import { randomUUID } from 'node:crypto';
import { dirname, resolve } from 'node:path';
import { FileSource, evidenceByteLimit, sourceByteLimit } from './file-screening.mjs';
import { hash } from './screening-profile.mjs';
import { configurationIdentity, validateConfiguration, Unavailable } from './readiness.mjs';

const fail = code => { throw new Unavailable(code); };
const range = input => {
  if (!Number.isSafeInteger(input.offset) || input.offset < 1 || !Number.isSafeInteger(input.limit) || input.limit < 1 || input.limit > 1000) fail('bounded-range-required');
  if (typeof input.need !== 'string' || !input.need.trim() || Buffer.byteLength(input.need) > 4096) fail('information-need-required');
  return { offset: input.offset, limit: input.limit };
};

/** Admit exact current bytes from trusted in-process decisions or independently established evidence */
export class FileAdmission {
  #receipts = new Map();
  #evidence = new Map();
  #closed = false;
  #lookups = 0;
  #instructions = [];
  #targets = [];
  #intakeAuthority;
  constructor({ profiles, configuration, sessionId, cwd, source = new FileSource(), observe = () => {}, intake = null }) {
    this.profiles = profiles; this.configuration = configuration; this.sessionId = sessionId; this.cwd = cwd; this.source = source; this.observe = observe;
    this.#intakeAuthority = profiles.selection(cwd)?.identity;
    if (intake) {
      if (intake.version !== 1 || intake.authority !== profiles.selection(cwd)?.identity || intake.cwd !== cwd) fail('stale-trusted-intake');
      this.#instructions = intake.instructions; this.#targets = intake.targets;
    }
  }
  /** The native host's actual loaded context files, never an Agent certainty/path assertion */
  ingestNativeInstructions(files) {
    if (!Array.isArray(files) || files.length > 48) fail('invalid-trusted-intake');
    if (this.profiles.selection(this.cwd)?.identity !== this.#intakeAuthority) fail('stale-trusted-intake');
    for (const file of files) {
      try {
        const { snapshot } = this.#current(file.path, true);
        if (hash(file.content) !== snapshot.digest) continue;
        if (!this.#instructions.some(i => i.path === snapshot.canonical && i.digest === snapshot.digest)) this.#instructions.push({ path: snapshot.canonical, digest: snapshot.digest });
      } catch { /* Outside local authority stays unavailable, even when the host loaded it independently */ }
    }
  }
  close() { this.#closed = true; this.#receipts.clear(); this.#evidence.clear(); }
  #current(path, instruction = false, exactEvidence = instruction) {
    if (this.#closed) fail('session-changed');
    const selected = this.profiles.selection(this.cwd);
    if (!selected) fail('profile-unavailable');
    const settings = this.profiles.fileSettings(this.sessionId, this.cwd).load();
    const resolved = resolve(this.cwd, path);
    const exactInstruction = instruction && selected.project.instructions.some(i => i.path === resolved);
    const snapshot = this.source.snapshot(resolved, exactInstruction ? { ...settings, allowedRoots: [dirname(resolved)] } : settings,
      { sourceLimit: exactEvidence ? evidenceByteLimit : sourceByteLimit });
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
  locate({ path, symbol, need }) {
    if (typeof need !== 'string' || !need.trim() || Buffer.byteLength(need) > 4096 || typeof symbol !== 'string' || !/^[A-Za-z_][A-Za-z0-9_]{2,127}$/.test(symbol)) fail('invalid-structural-query');
    if (++this.#lookups > 32) fail('structural-query-limit');
    const { snapshot, identity } = this.#current(path);
    const expression = new RegExp(`\\b(?:class|function|interface|trait|enum|type)\\s+${symbol}\\b`);
    const lines = snapshot.text.split('\n'), matches = [];
    for (let i = 0; i < lines.length && matches.length < 8; i++) if (expression.test(lines[i])) matches.push(i + 1);
    if (matches.length !== 1) fail('exact-symbol-target-required');
    const offset = matches[0], token = randomUUID();
    this.#evidence.set(token, { authority: identity, source: { id: snapshot.id, digest: snapshot.digest, revision: snapshot.revision },
      need, offset, limit: Math.min(40, lines.length - offset + 1), kind: 'verified-symbol', symbolId: hash(symbol) });
    while (this.#evidence.size > 64) this.#evidence.delete(this.#evidence.keys().next().value);
    return { evidence: token, pathId: snapshot.id, offset, maxLines: Math.min(40, lines.length - offset + 1), kind: 'verified-symbol' };
  }
  read(input) {
    range(input);
    if ([input.receipt, input.exception, input.evidence].filter(Boolean).length !== 1) fail('unambiguous-evidence-required');
    const { snapshot, project, identity, profile } = this.#current(input.path, input.exception === 'instruction', Boolean(input.exception));
    let kind;
    if (input.receipt) {
      if (!this.configuration || configurationIdentity(validateConfiguration(this.configuration())) !== profile.configuration) fail('stale-configuration');
      const held = this.#receipts.get(input.receipt);
      if (!held || held.session !== this.sessionId || held.authority !== identity || held.need !== input.need || held.questionDigest !== input.questionDigest ||
        held.source.id !== snapshot.id || held.source.digest !== snapshot.digest || held.source.revision !== snapshot.revision) fail('current-positive-receipt-required');
      kind = 'receipt-admission';
    } else if (input.exception === 'instruction') {
      if (![...project.instructions, ...(identity === this.#intakeAuthority ? this.#instructions : [])].some(i => i.path === snapshot.canonical && i.digest === snapshot.digest)) fail('unestablished-instruction');
      kind = 'mandatory-instruction';
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
}
