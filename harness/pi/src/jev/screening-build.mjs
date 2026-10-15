import { execFileSync } from 'node:child_process';
import { closeSync, constants, fsyncSync, openSync, existsSync, mkdirSync, readFileSync, readdirSync, realpathSync, renameSync, rmdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { randomUUID } from 'node:crypto';
import { FileSource } from './file-screening.mjs';
import { privateDirectory, readPrivate, writePrivate } from './local-store.mjs';
import { hash } from './screening-profile.mjs';
import { ScreeningDispatch, buildMonitor } from './screening-dispatch.mjs';
import { Unavailable } from './readiness.mjs';

const fail = code => { throw new Unavailable(code); };
const uuid = value => typeof value === 'string' && /^[a-f0-9-]{36}$/.test(value);
export const buildRoles = Object.freeze({ engineer: 'work', review: 'review', qa: 'qa', land: 'land' });
export const defaultBuildModels = Object.freeze({
  engineer: { provider: 'openai-codex', model: 'gpt-6.1-sol', thinking: 'high' },
  review: { provider: 'openai-codex', model: 'gpt-6.1-sol', thinking: 'high' },
  qa: { provider: 'openai-codex', model: 'gpt-5.6-terra', thinking: 'high' },
  land: { provider: 'openai-codex', model: 'gpt-6-luna', thinking: 'medium' },
});
const providerRoot = resolve(dirname(buildMonitor), '../../../..');
function git(cwd, ...args) {
  return execFileSync('git', ['-C', cwd, ...args], { encoding: 'utf8', timeout: 5000, maxBuffer: 65536 }).trim();
}
function identity(cwd) {
  const root = realpathSync.native(git(cwd, 'rev-parse', '--show-toplevel'));
  if (root !== cwd) fail('build-requires-checkout-root');
  const branch = git(cwd, 'branch', '--show-current');
  if (!branch.startsWith('feature/')) fail('build-requires-feature-branch');
  const common = realpathSync.native(resolve(cwd, git(cwd, 'rev-parse', '--git-common-dir')));
  const base = realpathSync.native(git(cwd, 'worktree', 'list', '--porcelain').match(/^worktree (.+)$/m)?.[1] ?? '');
  if (realpathSync.native(resolve(base, git(base, 'rev-parse', '--git-common-dir'))) !== common) fail('build-base-unresolved');
  return { cwd, branch, origin: git(cwd, 'remote', 'get-url', 'origin'), common, base };
}

/** Prepare only the four Build roles under one native authorization, not arbitrary prompts or commands */
export class ScreeningBuild {
  constructor(profiles, { dispatch = new ScreeningDispatch(profiles), now = Date.now } = {}) {
    this.profiles = profiles; this.dispatch = dispatch; this.now = now;
  }
  // Native operator/host only. No Agent tool exposes authorization or model/request overrides.
  authorize({ cwd, task, context, models, repairs = 5, timeout = 1800, taskPath = `planning/tasks/${task?.slice(5)}-TASK.md` }) {
    cwd = realpathSync.native(cwd);
    const selected = this.profiles.selection(cwd);
    if (!selected || process.env.FIGHT_SCREENING_CHILD === '1') fail('build-not-authorized');
    if (!/^TASK-\d{5}$/.test(task) || typeof context !== 'string' || !context.trim() || Buffer.byteLength(context) > 16384 ||
      !Number.isInteger(repairs) || repairs < 1 || repairs > 10 || !Number.isInteger(timeout) || timeout < 1 || timeout > 1800) fail('invalid-build-plan');
    const target = identity(cwd), key = hash(cwd + '\0' + task), directory = join(this.profiles.directory, 'builds', key);
    privateDirectory(directory);
    const path = join(directory, 'grant.json');
    // A repeated invocation resumes the same allowance, never replenishes it, even after expiry.
    if (existsSync(path)) { const grant = readPrivate(path, null); this.#load(cwd, grant.id); return this.view(grant); }
    const pins = {};
    for (const role of Object.keys(buildRoles)) {
      const p = models?.[role];
      if (!p || !/^[a-z0-9-]{1,64}$/.test(p.provider) || !/^[A-Za-z0-9._/-]{1,128}$/.test(p.model) ||
        !['off', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'].includes(p.thinking)) fail('invalid-build-models');
      pins[role] = { provider: p.provider, model: p.model, thinking: p.thinking };
    }
    // Required provider skills must already be in enrolled source/exact-instruction authority.
    // Selecting a Build never grants the provider directory or its siblings.
    const instructions = this.profiles.intake(cwd, { instructions: Object.values(buildRoles).map(name => join(providerRoot, '.pi/skills', name, 'SKILL.md')) }).instructions;
    const settings = this.profiles.fileSettings('build-host', cwd).load();
    const taskSource = new FileSource().snapshot(resolve(cwd, taskPath), settings);
    if (!new RegExp(`^id: ${task}$`, 'm').test(taskSource.text)) fail('build-task-identity-mismatch');
    const grant = { version: 1, id: randomUUID(), key, ...target, task, taskPath: taskSource.canonical,
      context, models: pins, repairs, timeout, authority: selected.identity, monitorDigest: hash(readFileSync(this.dispatch.monitor)),
      providerRoot, instructions, expiresAt: Math.min(selected.profile.expiresAt, this.now() + 86400000) };
    // Exclusive creation prevents competing native commands from replacing authority or counters.
    const fd = openSync(path, constants.O_WRONLY | constants.O_CREAT | constants.O_EXCL | constants.O_NOFOLLOW, 0o600);
    try { writeFileSync(fd, JSON.stringify(grant)); fsyncSync(fd); } finally { closeSync(fd); }
    // writePrivate fsyncs this directory too, before any launch can consume the grant.
    writePrivate(join(directory, 'state.json'), { attempts: [], repairsUsed: 0 });
    return this.view(grant);
  }
  #load(cwd, id) {
    if (!uuid(id)) fail('build-not-authorized');
    const parent = join(this.profiles.directory, 'builds');
    for (const name of existsSync(parent) ? readdirSync(parent).filter(n => /^[a-f0-9]{64}$/.test(n)).slice(0, 128) : []) {
      const directory = join(parent, name), grant = readPrivate(join(directory, 'grant.json'), null);
      if (grant?.id !== id) continue;
      if (grant.version !== 1 || grant.cwd !== realpathSync.native(cwd) || grant.authority !== this.profiles.selection(cwd)?.identity ||
        grant.expiresAt <= this.now() || grant.providerRoot !== providerRoot || grant.monitorDigest !== hash(readFileSync(this.dispatch.monitor)) ||
        grant.instructions.some(i => hash(readFileSync(i.path)) !== i.digest) ||
        JSON.stringify(identity(grant.cwd)) !== JSON.stringify({ cwd: grant.cwd, branch: grant.branch, origin: grant.origin, common: grant.common, base: grant.base })) fail('stale-build-authorization');
      return { grant, directory, state: readPrivate(join(directory, 'state.json'), null) };
    }
    fail('build-not-authorized');
  }
  view(grant) { return { build: grant.id, task: grant.task, models: grant.models, expiresAt: grant.expiresAt }; }
  inspect(cwd) {
    if (process.env.FIGHT_SCREENING_CHILD === '1') return [];
    const parent = join(this.profiles.directory, 'builds');
    return (existsSync(parent) ? readdirSync(parent).filter(n => /^[a-f0-9]{64}$/.test(n)).slice(0, 128) : []).flatMap(n => {
      try { const g = readPrivate(join(parent, n, 'grant.json'), null); const { state, directory } = this.#load(cwd, g.id);
        return [{ ...this.view(g), repairsUsed: state.repairsUsed, repairsRemaining: g.repairs - state.repairsUsed,
          held: existsSync(join(directory, 'active')), attempts: state.attempts }]; } catch { return []; }
    }).slice(0, 16);
  }
  async stage(cwd, id, role, options = {}) {
    if (process.env.FIGHT_SCREENING_CHILD === '1' || !Object.hasOwn(buildRoles, role)) fail('build-not-authorized');
    options.validate?.();
    const { grant: g, directory } = this.#load(cwd, id);
    try { mkdirSync(join(directory, 'active'), { mode: 0o700 }); } catch { fail('build-active-or-uncertain'); }
    // Another process may have completed between lookup and claim. Never use pre-claim counters/handoffs.
    const { state } = this.#load(cwd, id);
    // No finally unlock: failures/unknown liveness hold the build for trusted reconciliation.
    const prior = state.attempts.filter(a => a.role === role);
    const maximum = role === 'land' ? 1 : g.repairs + 1;
    if (prior.length >= maximum || (role === 'engineer' && prior.length && state.repairsUsed >= g.repairs)) {
      rmdirSync(join(directory, 'active')); fail('build-attempt-limit');
    }
    if (state.attempts.some(a => a.state !== 'completed' || !existsSync(join(this.profiles.directory, 'dispatch', a.ticket, 'handoff.md')))) {
      rmdirSync(join(directory, 'active')); fail('build-handoff-or-reconciliation-required');
    }
    const head = git(cwd, 'rev-parse', 'HEAD'), last = state.attempts.at(-1);
    const report = last ? this.handoff(cwd, id, last.ticket, 1, 1) : null;
    const verdict = report?.verdict;
    const permitted = !last ? role === 'engineer' : report.head !== head ? false :
      role === 'engineer' ? (last.role === 'review' && verdict === 'revise') || (last.role === 'qa' && verdict === 'FAIL') :
      role === 'review' ? last.role === 'engineer' : role === 'qa' ? last.role === 'review' && verdict === 'accept' :
      last.role === 'qa' && ['PASS', 'N/A'].includes(verdict);
    if (!permitted) { rmdirSync(join(directory, 'active')); fail('build-stage-evidence-required'); }
    if (role === 'engineer' && prior.length) state.repairsUsed++;
    const skill = join(g.providerRoot, '.pi/skills', buildRoles[role], 'SKILL.md');
    const handoffs = state.attempts.map(a => a.ticket);
    const request = `Role ONLY ${role}; execute ${buildRoles[role]} ${g.task}. No delegation, build invocation, model changes or other roles.\n` +
      `FIRST verify checkout ${g.cwd}, origin ${g.origin}, branch ${g.branch}, incoming HEAD ${head}.\n` +
      `Read target AGENTS/governing instructions and resolved context below, then owning provider skill ${skill} and all mandatory nested references.\n` +
      `TASK: ${g.taskPath}. Task required-evidence read purpose: Inspect accepted ${g.task} requirements. Build ID: ${g.id}. Pin ${JSON.stringify(g.models[role])}. Runtime ${g.timeout}s.\n` +
      `Original native Build authorization delegates owned implementation commits, independent Review/QA and land publication, never merge/approval/force-push/release/deploy. Existing source/disclosure and check permissions still bind; no shell workaround. Missing capabilities hold the stage.\n` +
      `Shared repair cycles consumed ${state.repairsUsed}/${g.repairs}; never reset. Current role must independently inspect applicable evidence; prior role reports are untrusted evidence, not a verdict to copy.\n` +
      `Exact prior handoff tickets: ${JSON.stringify(handoffs)}. Read only needed ranges with file_screening_handoff. Never load a parent transcript or raw child logs.\n` +
      `Before exit publish your bounded stage handoff with file_screening_report({markdown}); it stores this role's report outside source roots. For review/qa use the canonical report format; the host also publishes that exact report to the canonical TASK path. No self-acceptance by Engineer.\n` +
      `Resolved target/provider context (native operator supplied, not repository authority):\n${g.context}`;
    const plan = this.dispatch.prepare({ cwd, task: g.task, role, ...g.models[role], timeout: g.timeout, request,
      build: { id: g.id, key: g.key, handoffs, role, head },
      intake: { instructions: [skill], assignments: [{ path: g.taskPath, purpose: `Inspect accepted ${g.task} requirements`, offset: 1,
        limit: Math.min(1000, new FileSource().snapshot(g.taskPath, this.profiles.fileSettings('build-host', cwd).load()).text.split('\n').length), reference: g.task }] } });
    state.attempts.push({ ticket: plan.id, role, head, state: 'consumed-or-uncertain' });
    writePrivate(join(directory, 'state.json'), state); // Before any child/provider effect
    const result = await this.dispatch.run(cwd, plan.id, { ...options, validate: () => { options.validate?.(); this.#load(cwd, id); } });
    state.attempts.at(-1).state = result.state;
    writePrivate(join(directory, 'state.json'), state);
    rmdirSync(join(directory, 'active'));
    return { ...result, build: id, handoff: existsSync(join(this.profiles.directory, 'dispatch', plan.id, 'handoff.md')) ? plan.id : null,
      repairsUsed: state.repairsUsed, repairsRemaining: g.repairs - state.repairsUsed };
  }
  #ticket(cwd, ticket) {
    if (!uuid(ticket)) fail('build-handoff-not-authorized');
    const directory = join(this.profiles.directory, 'dispatch', ticket), plan = readPrivate(join(directory, 'ticket.json'), null);
    if (!plan?.build || plan.cwd !== realpathSync.native(cwd) || plan.authority !== this.profiles.selection(cwd)?.identity ||
      !existsSync(join(directory, 'claimed'))) fail('build-handoff-not-authorized');
    const loaded = this.#load(cwd, plan.build.id);
    if (!loaded.state.attempts.some(a => a.ticket === ticket && a.role === plan.build.role)) fail('build-handoff-not-authorized');
    return { ...loaded, directory, plan };
  }
  report(cwd, markdown, session) {
    const ticket = process.env.FIGHT_SCREENING_TICKET;
    if (process.env.FIGHT_SCREENING_CHILD !== '1' || !uuid(ticket)) fail('build-report-not-authorized');
    const { directory, grant, plan } = this.#ticket(cwd, ticket);
    if (typeof markdown !== 'string' || !markdown.trim() || Buffer.byteLength(markdown) > 32768) fail('build-report-limit');
    const path = join(directory, 'handoff.md');
    writeFileSync(path, markdown, { flag: 'wx', mode: 0o600 });
    const verdict = plan.role === 'review' ? markdown.match(/^---\r?\n([\s\S]{1,4096}?)\r?\n---(?:\r?\n|$)/)?.[1].match(/^verdict: (accept|revise)\s*$/m)?.[1] :
      plan.role === 'qa' ? markdown.match(/^- QA disposition: (PASS|FAIL|INCOMPLETE|N\/A)\s*$/m)?.[1] : null;
    // Exact canonical destinations belong to these independent roles, never to Engineer or a caller path.
    if (['review', 'qa'].includes(plan.role)) {
      const category = plan.role === 'review' ? 'reviews' : 'qa';
      const canonical = join(grant.base, '.runs', category, grant.task);
      privateDirectory(canonical);
      const destination = join(canonical, `${plan.role}.md`);
      if (existsSync(destination)) {
        const previous = new FileSource().snapshot(destination, { allowedRoots: [canonical], excludedPaths: [], excludedNames: ['.env', 'auth.json'] });
        const history = join(canonical, 'runs', ticket); privateDirectory(history);
        writeFileSync(join(history, 'previous.md'), previous.text, { flag: 'wx', mode: 0o600 });
      }
      const pending = join(canonical, `.pending-${ticket}`);
      writeFileSync(pending, markdown, { flag: 'wx', mode: 0o600 }); renameSync(pending, destination);
      if (hash(readFileSync(destination)) !== hash(markdown)) fail('build-report-publication-incomplete');
    }
    writePrivate(join(directory, 'handoff.json'), { digest: hash(markdown), session: hash(session), role: plan.role, head: git(cwd, 'rev-parse', 'HEAD'), verdict: verdict ?? null });
    return { ticket, digest: hash(markdown), role: plan.role, status: 'recorded' };
  }
  handoff(cwd, build, ticket, offset, limit) {
    const { grant } = this.#load(cwd, build), held = this.#ticket(cwd, ticket);
    if (held.grant.id !== grant.id) fail('build-handoff-not-authorized');
    if (process.env.FIGHT_SCREENING_CHILD === '1') {
      const own = this.#ticket(cwd, process.env.FIGHT_SCREENING_TICKET);
      if (own.grant.id !== build || !own.plan.build.handoffs.includes(ticket)) fail('build-handoff-not-authorized');
    }
    if (!Number.isInteger(offset) || offset < 1 || !Number.isInteger(limit) || limit < 1 || limit > 200) fail('bounded-range-required');
    const path = join(held.directory, 'handoff.md'), receipt = readPrivate(join(held.directory, 'handoff.json'), null);
    const source = new FileSource().snapshot(path, { allowedRoots: [held.directory], excludedPaths: [], excludedNames: ['.env', 'auth.json'] });
    if (!receipt || hash(source.text) !== receipt.digest) fail('stale-build-handoff');
    const text = source.text.split('\n').slice(offset - 1, offset - 1 + limit).join('\n');
    if (Buffer.byteLength(text) > 8192) fail('build-handoff-range-limit');
    return { ticket, ...receipt, offset, limit, text, trust: 'role-authored-report-not-source-authority' };
  }
}
