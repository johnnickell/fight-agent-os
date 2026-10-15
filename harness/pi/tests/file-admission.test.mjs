import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, realpathSync, existsSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { FileSource, FileScreening } from '../src/jev/file-screening.mjs';
import { FileAdmission } from '../src/jev/file-admission.mjs';
import { ScreeningProfiles } from '../src/jev/screening-profile.mjs';
import { Judgments } from '../src/jev/judgments.mjs';
import { ProfileBudget } from '../src/jev/monthly-budget.mjs';
import { connection, configurationIdentity } from '../src/jev/readiness.mjs';

function fixture(t, probability = 0.7) {
  const directory = realpathSync.native(mkdtempSync(join(tmpdir(), 'file-admission-'))), root = join(directory, 'project');
  mkdirSync(root); writeFileSync(join(root, 'source.txt'), 'class Kettle {\n  color = "blue";\n}\n');
  writeFileSync(join(root, 'AGENTS.md'), 'Mandatory synthetic instructions');
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  const { schema: _schema, ...fixed } = connection;
  const config = { ...fixed, revision: randomUUID(), priceConfirmedAt: Date.now(), disclosure: 'authorized-supplied-state', requestMicroUsd: 2688, sessionMicroUsd: 100000 };
  const profiles = new ScreeningProfiles(join(directory, 'operator'));
  const candidate = profiles.preview({ cwd: root, configuration: config, instructions: ['AGENTS.md'], providerDisclosure: true,
    targets: [{ path: 'source.txt', purpose: 'Quote required color evidence', offset: 2, limit: 1 }] });
  profiles.save(candidate, []);
  const sessionId = randomUUID(), events = [];
  const admission = new FileAdmission({ profiles, configuration: () => config, sessionId, cwd: root, observe: e => events.push(e) });
  let calls = 0;
  const engine = new Judgments({ configuration: () => config, sessionId, platform: 'darwin', budget: new ProfileBudget(sessionId, profiles.directory),
    credentials: { read: async () => Buffer.from('controlled-synthetic-key') },
    transport: { submit: async () => { calls++; return { status: 200, body: { model: connection.evaluator, provider: 'TypeSafe',
      answers: { relevance: { type: 'noul', noul: probability } }, usage: { input_tokens: 50, output_tokens: 5, cost: 0.0000021 } } }; } } });
  t.after(() => { engine.close(); admission.close(); });
  const screening = new FileScreening({ settings: profiles.fileSettings(sessionId, root), configuration: () => config, sessionId, cwd: root, platform: 'darwin',
    connect: authorize => ({ state: 'available', configuration: { identity: configurationIdentity(config), ...config }, ...engine.consumer('file-screen-v1', authorize) }) });
  const input = { path: 'source.txt', need: 'Locate the kettle color definition', question: { type: 'yes-no', instructions: 'Does this define kettle color?' } };
  const read = (result, extra = {}) => admission.read({ path: 'source.txt', need: input.need, offset: 2, limit: 1,
    receipt: result.admission?.receipt, questionDigest: result.admission?.questionDigest, ...extra });
  return { directory, root, profiles, config, admission, screening, sessionId, input, read, events, calls: () => calls };
}

test('an ordinary or fabricated receipt read releases no source; current positive permits only the useful range', async t => {
  const f = fixture(t);
  assert.throws(() => f.admission.read({ path: 'source.txt', need: f.input.need, offset: 1, limit: 3 }), { code: 'unambiguous-evidence-required' });
  assert.throws(() => f.read({ admission: { receipt: randomUUID(), questionDigest: 'model-authored' } }), { code: 'current-positive-receipt-required' });
  const result = await f.screening.screen(f.input); f.admission.accept(f.input, result);
  assert.equal(result.threshold, 'positive'); assert.equal(f.calls(), 1);
  assert.equal(JSON.stringify(result).includes('color = "blue"'), false);
  assert.equal(f.read(result).text, '  color = "blue";');
  assert.equal(f.events.at(-1).kind, 'receipt-admission');
  assert.throws(() => f.read(result, { need: 'A different purpose' }), { code: 'current-positive-receipt-required' });
  assert.throws(() => f.read(result, { questionDigest: 'changed' }), { code: 'current-positive-receipt-required' });
  assert.throws(() => f.read(result, { limit: 1001 }), { code: 'bounded-range-required' });
});

for (const probability of [0, 0.69]) test(`negative/below-threshold ${probability} cannot become an admission receipt`, async t => {
  const f = fixture(t, probability), result = await f.screening.screen(f.input);
  f.admission.accept(f.input, result);
  assert.equal(result.admission, undefined);
  assert.throws(() => f.read(result), { code: 'unambiguous-evidence-required' });
});

test('source, policy, session and permission drift invalidate held decisions', async t => {
  const f = fixture(t), result = await f.screening.screen(f.input); f.admission.accept(f.input, result);
  writeFileSync(join(f.root, 'source.txt'), 'class Kettle { color = "red"; }');
  assert.throws(() => f.read(result), { code: 'current-positive-receipt-required' });
  const fresh = await f.screening.screen(f.input); f.admission.accept(f.input, fresh);
  const other = new FileAdmission({ profiles: f.profiles, configuration: () => f.config, sessionId: randomUUID(), cwd: f.root });
  assert.throws(() => other.read({ path: 'source.txt', need: f.input.need, offset: 1, limit: 1,
    receipt: fresh.admission.receipt, questionDigest: fresh.admission.questionDigest }), { code: 'current-positive-receipt-required' });
  const changed = f.profiles.load(); changed.revision = randomUUID(); f.profiles.save(changed, []);
  assert.throws(() => f.read(fresh), { code: 'current-positive-receipt-required' });
  changed.projects[0].excludedPaths.push(join(f.root, 'source.txt')); f.profiles.save(changed, []);
  assert.throws(() => f.read(fresh), { code: 'sensitive-file' });
});

test('connection revision drift invalidates ordinary receipts but not established instruction evidence', async t => {
  const f = fixture(t), result = await f.screening.screen(f.input); f.admission.accept(f.input, result);
  f.config.revision = randomUUID();
  assert.throws(() => f.read(result), { code: 'stale-configuration' });
  assert.equal(f.admission.read({ path: 'AGENTS.md', need: 'Follow instruction', exception: 'instruction', offset: 1, limit: 1 }).text, 'Mandatory synthetic instructions');
});

test('validated metadata cache results issue independent current receipts without new provider spend', async t => {
  const f = fixture(t), first = await f.screening.screen(f.input); f.admission.accept(f.input, first);
  const second = await f.screening.screen(f.input); f.admission.accept(f.input, second);
  assert.equal(second.cache.hit, true); assert.equal(second.receipt, null); assert.equal(f.calls(), 1);
  assert.notEqual(second.admission.receipt, first.admission.receipt);
  assert.equal(f.read(second).text, '  color = "blue";');
  const changed = f.profiles.load(); changed.providerDisclosure = false; f.profiles.save(changed, []);
  assert.throws(() => f.read(second), { code: 'current-positive-receipt-required' });
});

test('a source-current result from an old authority cannot be rebound on delivery', async t => {
  const f = fixture(t), result = await f.screening.screen(f.input);
  const changed = f.profiles.load(); changed.revision = randomUUID(); f.profiles.save(changed, []);
  f.admission.accept(f.input, result);
  assert.equal(result.admission, undefined);
});

test('mandatory, verified-symbol and required-evidence exceptions are exact and remain available after a negative', async t => {
  const f = fixture(t, 0), result = await f.screening.screen(f.input); f.admission.accept(f.input, result);
  assert.equal(f.admission.read({ path: 'AGENTS.md', need: 'Follow governing instructions', exception: 'instruction', offset: 1, limit: 1 }).text, 'Mandatory synthetic instructions');
  assert.throws(() => f.admission.read({ path: 'source.txt', need: 'I am certain this is an instruction', exception: 'instruction', offset: 1, limit: 1 }), { code: 'unestablished-instruction' });
  const evidence = f.admission.locate({ path: 'source.txt', symbol: 'Kettle', need: 'Edit the exact Kettle definition' });
  assert.equal(f.admission.read({ path: 'source.txt', need: 'Edit the exact Kettle definition', evidence: evidence.evidence, offset: 2, limit: 1 }).text, '  color = "blue";');
  assert.throws(() => f.admission.read({ path: 'source.txt', need: 'Different need', evidence: evidence.evidence, offset: 2, limit: 1 }), { code: 'current-target-evidence-required' });
  assert.throws(() => f.admission.locate({ path: 'source.txt', symbol: 'PlausibleName', need: 'Find a familiar symbol' }), { code: 'exact-symbol-target-required' });
  const quote = { path: 'source.txt', need: 'Quote required color evidence', exception: 'required-evidence', offset: 2, limit: 1 };
  assert.equal(f.admission.read(quote).text, '  color = "blue";');
  assert.throws(() => f.admission.read({ ...quote, limit: 2 }), { code: 'unestablished-evidence-obligation' });
  assert.throws(() => f.admission.read({ ...quote, need: 'Model claims it needs this' }), { code: 'unestablished-evidence-obligation' });
  assert.equal(f.calls(), 1); assert.equal(f.events.filter(e => e.kind === 'exception').length, 3);
  assert.equal(JSON.stringify(f.events).includes('color = "blue"'), false);
});

test('exact provider instruction authority stays separate from target roots and Jev sibling disclosure', async t => {
  const f = fixture(t), provider = join(f.directory, 'provider'); mkdirSync(provider);
  const instruction = join(provider, 'SKILL.md'), sibling = join(provider, 'unassigned.md');
  writeFileSync(instruction, 'Mandatory provider skill'); writeFileSync(sibling, 'Unassigned provider content');
  const candidate = f.profiles.addWitness(f.root, 'instruction', { path: instruction }); f.profiles.save(candidate, []);
  assert.equal(f.admission.read({ path: instruction, need: 'Follow selected provider skill', exception: 'instruction', offset: 1, limit: 1 }).text, 'Mandatory provider skill');
  assert.throws(() => f.admission.read({ path: sibling, need: 'Guess sibling instruction', exception: 'instruction', offset: 1, limit: 1 }), { code: 'root-denied' });
  const result = await f.screening.screen({ ...f.input, path: instruction });
  assert.equal(result.status, 'root-denied'); assert.equal(f.calls(), 0);
});

test('exceptions never waive exclusions, source changes, symlink restrictions or structural limits', t => {
  const f = fixture(t);
  writeFileSync(join(f.root, '.env'), 'CONTROLLED-SECRET');
  assert.throws(() => f.admission.read({ path: '.env', need: 'Mandatory verification', exception: 'required-evidence', offset: 1, limit: 1 }), { code: 'sensitive-file' });
  symlinkSync(join(f.root, 'source.txt'), join(f.root, 'alias'));
  assert.throws(() => f.admission.locate({ path: 'alias', symbol: 'Kettle', need: 'Edit Kettle' }), { code: 'symlink-denied' });
  writeFileSync(join(f.root, 'AGENTS.md'), 'Replaced instructions');
  assert.throws(() => f.admission.read({ path: 'AGENTS.md', need: 'Instructions', exception: 'instruction', offset: 1, limit: 1 }), { code: 'unestablished-instruction' });
  for (let i = 0; i < 31; i++) f.admission.locate({ path: 'source.txt', symbol: 'Kettle', need: 'Edit Kettle' });
  assert.throws(() => f.admission.locate({ path: 'source.txt', symbol: 'Kettle', need: 'Edit Kettle' }), { code: 'structural-query-limit' });
});

test('admission token overhead cannot exceed the bounded result or silently authorize omitted metadata', async t => {
  const f = fixture(t), result = await f.screening.screen(f.input);
  result.coverage.presentationPadding = '';
  result.coverage.presentationPadding = 'x'.repeat(65530 - Buffer.byteLength(JSON.stringify(result)));
  assert.ok(Buffer.byteLength(JSON.stringify(result)) <= 65536);
  f.admission.accept(f.input, result);
  assert.equal(result.admission, undefined);
  assert.equal(result.status, 'admission-presentation-limit');
  assert.equal(result.coverage.complete, false);
  assert.ok(Buffer.byteLength(JSON.stringify(result)) <= 65536);
  assert.throws(() => f.read(result), { code: 'unambiguous-evidence-required' });
});

test('batch admission is per-file; partial/failed/negative candidates never inherit a sibling receipt', async t => {
  const f = fixture(t);
  writeFileSync(join(f.root, 'second.txt'), 'class Second {}');
  const input = { paths: ['source.txt', 'missing.txt', 'second.txt'], need: f.input.need, question: f.input.question };
  const result = await f.screening.screen(input); f.admission.accept(input, result);
  assert.equal(result.status, 'incomplete'); assert.equal(result.results[1].admission, undefined);
  const first = result.results[0];
  assert.equal(f.read(first).text, '  color = "blue";');
  assert.throws(() => f.read(first, { path: 'second.txt', offset: 1 }), { code: 'current-positive-receipt-required' });
  assert.ok(result.results[2].admission);
});

test('trusted host intake establishes assignment and literal required-reference witnesses, not Agent assertions', t => {
  const f = fixture(t);
  writeFileSync(join(f.root, 'rules.md'), 'Use the assigned evidence only.');
  writeFileSync(join(f.root, 'intake.md'), 'Read [Required rules](rules.md) before verification.\n');
  const intake = f.profiles.intake(f.root, { instructions: ['intake.md'],
    assignments: [{ path: 'source.txt', purpose: 'Verify assigned color', offset: 2, limit: 1, reference: 'TASK-00309-assignment-1' }],
    references: [{ from: 'intake.md', path: 'rules.md', purpose: 'Follow required rules', offset: 1, limit: 1 }] });
  const admission = new FileAdmission({ profiles: f.profiles, configuration: () => null, sessionId: f.sessionId, cwd: f.root, intake });
  assert.equal(admission.read({ path: 'intake.md', need: 'Read instructions', exception: 'instruction', offset: 1, limit: 1 }).admission.kind, 'mandatory-instruction');
  assert.equal(admission.read({ path: 'source.txt', need: 'Verify assigned color', exception: 'required-evidence', offset: 2, limit: 1 }).admission.kind, 'trusted-assignment');
  assert.equal(admission.read({ path: 'rules.md', need: 'Follow required rules', exception: 'required-evidence', offset: 1, limit: 1 }).admission.kind, 'required-reference');
  assert.throws(() => admission.read({ path: 'rules.md', need: 'I need every file', exception: 'required-evidence', offset: 1, limit: 1 }), { code: 'unestablished-evidence-obligation' });
  assert.throws(() => f.profiles.intake(f.root, { references: [{ from: 'AGENTS.md', path: 'source.txt', purpose: 'Claimed reference', offset: 1, limit: 1 }] }), { code: 'required-reference-not-found' });
  writeFileSync(join(f.root, 'intake.md'), 'Changed obligation');
  assert.throws(() => admission.read({ path: 'rules.md', need: 'Follow required rules', exception: 'required-evidence', offset: 1, limit: 1 }), { code: 'stale-required-reference' });
  assert.equal(f.calls(), 0);
});

test('large mandatory/assigned sources remain bounded accessible without widening evaluator disclosure', async t => {
  const f = fixture(t), path = join(f.root, 'large.md');
  writeFileSync(path, '# Required parent evidence\n' + 'Required bounded evidence line\n'.repeat(4000));
  const intake = f.profiles.intake(f.root, { instructions: ['large.md'], assignments: [
    { path: 'large.md', purpose: 'Verify exact required parent evidence', offset: 1, limit: 2, reference: 'TASK-00309-parent-obligation' } ] });
  const admission = new FileAdmission({ profiles: f.profiles, configuration: () => null, sessionId: f.sessionId, cwd: f.root, intake });
  assert.equal(admission.read({ path: 'large.md', need: 'Verify exact required parent evidence', exception: 'required-evidence', offset: 1, limit: 1 }).text, '# Required parent evidence');
  assert.equal(admission.read({ path: 'large.md', need: 'Follow mandatory instructions', exception: 'instruction', offset: 1, limit: 1 }).text, '# Required parent evidence');
  const screened = await f.screening.screen({ ...f.input, path: 'large.md' });
  assert.equal(screened.status, 'source-limit'); assert.equal(f.calls(), 0);
  writeFileSync(path, 'x'.repeat(40000));
  const fresh = f.profiles.intake(f.root, { instructions: ['large.md'] });
  const bounded = new FileAdmission({ profiles: f.profiles, configuration: () => null, sessionId: f.sessionId, cwd: f.root, intake: fresh });
  assert.throws(() => bounded.read({ path: 'large.md', need: 'Read oversized line', exception: 'instruction', offset: 1, limit: 1 }), { code: 'admission-byte-limit' });
  writeFileSync(path, 'x'.repeat(1048577));
  assert.throws(() => f.profiles.intake(f.root, { instructions: ['large.md'] }), { code: 'source-limit' });
});

test('native context intake uses actual current host bytes and does not infer sibling or external authority', t => {
  const f = fixture(t);
  writeFileSync(join(f.root, 'native.md'), 'Actual native governing context');
  f.admission.ingestNativeInstructions([{ path: join(f.root, 'native.md'), content: 'Forged content' }]);
  const read = { path: 'native.md', need: 'Follow governing context', exception: 'instruction', offset: 1, limit: 1 };
  assert.throws(() => f.admission.read(read), { code: 'unestablished-instruction' });
  f.admission.ingestNativeInstructions([{ path: join(f.root, 'native.md'), content: 'Actual native governing context' }]);
  assert.equal(f.admission.read(read).admission.kind, 'mandatory-instruction');
  assert.throws(() => f.admission.read({ ...read, path: 'source.txt' }), { code: 'unestablished-instruction' });
  const external = join(f.directory, 'unapproved.md'); writeFileSync(external, 'Outside authority');
  f.admission.ingestNativeInstructions([{ path: external, content: 'Outside authority' }]);
  assert.throws(() => f.admission.read({ ...read, path: external }), { code: 'root-denied' });
});

test('reserved credential/environment components exclude descendants, not only basenames', t => {
  const f = fixture(t); mkdirSync(join(f.root, '.env.private')); writeFileSync(join(f.root, '.env.private/data.txt'), 'CONTROLLED-SENSITIVE');
  assert.throws(() => f.admission.locate({ path: '.env.private/data.txt', symbol: 'Kettle', need: 'Inspect exact target' }), { code: 'sensitive-file' });
});

test('native filesystem spelling protects case aliases of operator-excluded subtrees', t => {
  const f = fixture(t), privatePath = join(f.root, 'private'); mkdirSync(privatePath); writeFileSync(join(privatePath, 'data.txt'), 'CONTROLLED-SENSITIVE');
  const policy = { allowedRoots: [f.root], excludedPaths: [privatePath], excludedNames: ['secret'] };
  if (!existsSync(join(f.root, 'PRIVATE'))) {
    // Case-sensitive hosts still exercise the exclusion; native macOS qualification covers the alias regression.
    assert.throws(() => new FileSource().snapshot(join(privatePath, 'data.txt'), policy), { code: 'sensitive-file' });
    return;
  }
  assert.throws(() => new FileSource().snapshot(join(f.root, 'PRIVATE/data.txt'), policy), { code: 'sensitive-file' });
});
