import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, realpathSync, existsSync, symlinkSync, linkSync } from 'node:fs';
import { tmpdir, homedir } from 'node:os';
import { dirname, join, relative } from 'node:path';
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

test('ordinary project reads and portable core instructions need only a path without provider effects', t => {
  const f = fixture(t);
  mkdirSync(join(f.root, 'planning/tasks'), { recursive: true });
  for (const path of ['README.md', 'ROADMAP.md', 'planning/ROADMAP.md', 'planning/tasks/BOARD.md', '.env.example']) {
    writeFileSync(join(f.root, path), `Current ${path}`);
    assert.equal(f.admission.read({ path }).text, `Current ${path}`);
  }
  assert.match(f.admission.read({ path: 'source.txt' }).text, /Kettle/);
  assert.equal(f.calls(), 0);
  writeFileSync(join(f.root, '.env.local'), 'CONTROLLED-SECRET');
  assert.throws(() => f.admission.read({ path: '.env.local' }), { code: 'sensitive-file' });
});

test('direct large-file reads return bounded content and a usable continuation without Jev', t => {
  const f = fixture(t), lines = Array.from({ length: 500 }, (_, i) => `${i}: ${'x'.repeat(200)}`);
  writeFileSync(join(f.root, 'large.md'), lines.join('\n'));
  const first = f.admission.read({ path: 'large.md', limit: 1000 });
  assert.ok(Buffer.byteLength(first.text) <= 32768);
  assert.ok(first.admission.nextOffset > 1);
  const next = f.admission.read({ path: 'large.md', offset: first.admission.nextOffset, limit: 1 });
  assert.equal(next.text, lines[first.admission.nextOffset - 1]);
  assert.equal(f.calls(), 0);
});

test('installed Pi documentation reads directly without granting its runtime or Jev disclosure', async t => {
  const f = fixture(t), runtime = join(f.directory, 'pi'), docsPath = join(runtime, 'docs');
  mkdirSync(docsPath, { recursive: true });
  const readmePath = join(runtime, 'README.md'), extensions = join(docsPath, 'extensions.md');
  writeFileSync(readmePath, '# Pi');
  writeFileSync(extensions, '# Extensions\nRead [external](../../private.md)');
  writeFileSync(join(docsPath, 'sdk.md'), '# SDK\nSession API');
  writeFileSync(join(runtime, 'private.md'), 'Runtime sibling');
  writeFileSync(join(f.directory, 'private.md'), 'External sibling');
  writeFileSync(join(docsPath, 'implementation.js'), 'Runtime code');
  const nativeDocumentation = { readmePath, docsPath };
  const admission = new FileAdmission({ profiles: f.profiles, configuration: () => f.config,
    sessionId: f.sessionId, cwd: f.root, nativeDocumentation });
  t.after(() => admission.close());
  nativeDocumentation.docsPath = f.directory; // Caller mutation cannot expand access.
  admission.ingestNativeInstructions([], []); // Normal turn intake must not withdraw runtime docs.
  assert.equal(admission.read({ path: readmePath }).text, '# Pi');
  assert.equal(admission.read({ path: extensions }).admission.kind, 'native-documentation-read');
  const located = admission.locate({ path: join(docsPath, 'sdk.md'), heading: 'SDK', need: 'Read SDK documentation' });
  assert.equal(admission.read({ path: join(docsPath, 'sdk.md'), evidence: located.evidence,
    need: 'Read SDK documentation', offset: located.offset, limit: located.maxLines }).text, '# SDK\nSession API');
  for (const path of [join(runtime, 'private.md'), join(f.directory, 'private.md'), join(docsPath, 'implementation.js')]) {
    assert.throws(() => admission.read({ path }), { code: 'root-denied' });
  }
  assert.equal((await f.screening.screen({ ...f.input, path: extensions })).status, 'root-denied');
  assert.equal(f.calls(), 0);
  writeFileSync(extensions, '# Replaced runtime documentation');
  assert.throws(() => admission.read({ path: extensions }), { code: 'native-documentation-changed' });
  admission.close();
  assert.throws(() => admission.read({ path: readmePath }), { code: 'session-changed' });
});

test('installed Pi examples read as documentation without granting sibling code or evaluator disclosure', async t => {
  const f = fixture(t), runtime = join(f.directory, 'pi'), examplesPath = join(runtime, 'examples');
  mkdirSync(join(examplesPath, 'extensions/nested'), { recursive: true });
  const example = join(examplesPath, 'extensions/timed-confirm.ts');
  writeFileSync(example, 'export default function timedConfirm() {}');
  const metadata = { readmePath: join(runtime, 'README.md'), docsPath: join(runtime, 'docs'), examplesPath };
  const admission = new FileAdmission({ profiles: f.profiles, configuration: () => f.config,
    sessionId: f.sessionId, cwd: f.root, nativeDocumentation: metadata });
  t.after(() => admission.close());
  metadata.examplesPath = f.directory; // Host metadata is copied, not a mutable caller grant.
  admission.ingestNativeInstructions([], []);
  assert.equal(admission.read({ path: example }).text, 'export default function timedConfirm() {}');
  assert.equal(admission.read({ path: example }).admission.kind, 'native-documentation-read');
  for (const name of ['modal-editor.ts', 'question.ts', 'commands.ts', 'nested/view.tsx', 'README.md', 'package.json', 'helper.mjs']) {
    const path = join(examplesPath, 'extensions', name); writeFileSync(path, 'Published example');
    assert.equal(admission.read({ path }).text, 'Published example');
  }
  const located = admission.locate({ path: example, symbol: 'timedConfirm', need: 'Use the published dialog API' });
  assert.match(admission.read({ path: example, evidence: located.evidence, need: 'Use the published dialog API',
    offset: located.offset, limit: located.maxLines }).text, /timedConfirm/);
  for (const path of [join(runtime, 'runtime.ts'), join(runtime, 'examples-other.ts'),
    join(examplesPath, '../outside.ts'), join(examplesPath, 'extensions/image.png'),
    join(examplesPath, 'extensions/node_modules/dependency/index.ts')]) {
    mkdirSync(dirname(path), { recursive: true }); writeFileSync(path, 'Outside published example text');
    assert.throws(() => admission.read({ path }), { code: 'root-denied' });
  }
  assert.equal((await f.screening.screen({ ...f.input, path: example })).status, 'root-denied');
  assert.equal(f.calls(), 0);
  writeFileSync(example, 'Changed installation');
  assert.throws(() => admission.read({ path: example }), { code: 'native-documentation-changed' });
  admission.close();
  assert.throws(() => admission.read({ path: example }), { code: 'session-changed' });
});

test('installed example reads preserve exclusions, inode checks, limits and enrollment revocation', t => {
  const f = fixture(t), examplesPath = join(f.directory, 'examples'); mkdirSync(examplesPath);
  const source = join(examplesPath, 'dialog.ts'), excluded = join(examplesPath, 'excluded.ts');
  writeFileSync(source, 'Published source'); writeFileSync(excluded, 'Excluded source');
  writeFileSync(join(examplesPath, '.env.local.ts'), 'CONTROLLED-SENSITIVE');
  writeFileSync(join(examplesPath, 'auth.json'), 'CONTROLLED-SENSITIVE');
  symlinkSync(source, join(examplesPath, 'alias.ts'));
  symlinkSync(f.root, join(examplesPath, 'escape'));
  writeFileSync(join(f.root, 'outside.ts'), 'Outside root');
  writeFileSync(join(examplesPath, 'original.ts'), 'Hardlink');
  linkSync(join(examplesPath, 'original.ts'), join(examplesPath, 'hardlink.ts'));
  writeFileSync(join(examplesPath, 'large.ts'), 'x'.repeat(1048577));
  writeFileSync(join(examplesPath, 'binary.ts'), Buffer.from([0, 1, 2]));
  writeFileSync(join(examplesPath, 'bounded.ts'), Array.from({ length: 500 }, () => 'x'.repeat(200)).join('\n'));
  f.profiles.save(f.profiles.preview({ cwd: f.root, configuration: f.config, excludedPaths: [excluded], providerDisclosure: true }), []);
  const admission = new FileAdmission({ profiles: f.profiles, configuration: () => f.config, sessionId: f.sessionId,
    cwd: f.root, nativeDocumentation: { readmePath: join(f.directory, 'README.md'), docsPath: join(f.directory, 'docs'), examplesPath } });
  t.after(() => admission.close());
  assert.equal(admission.read({ path: source }).text, 'Published source');
  for (const path of [excluded, join(examplesPath, '.env.local.ts'), join(examplesPath, 'auth.json')]) {
    assert.throws(() => admission.read({ path }), { code: 'sensitive-file' });
  }
  assert.throws(() => admission.read({ path: join(examplesPath, 'alias.ts') }), { code: 'symlink-denied' });
  assert.throws(() => admission.read({ path: join(examplesPath, 'escape/outside.ts') }), { code: 'invalid-file-policy' });
  assert.throws(() => admission.read({ path: join(examplesPath, 'hardlink.ts') }), { code: 'unsupported-hardlink' });
  assert.throws(() => admission.read({ path: join(examplesPath, 'large.ts') }), { code: 'source-limit' });
  assert.throws(() => admission.read({ path: join(examplesPath, 'binary.ts') }), { code: 'binary-source' });
  const bounded = admission.read({ path: join(examplesPath, 'bounded.ts'), limit: 1000 });
  assert.ok(Buffer.byteLength(bounded.text) <= 32768); assert.ok(bounded.admission.nextOffset > 1);
  f.profiles.save(f.profiles.preview({ cwd: f.root, configuration: f.config, providerDisclosure: true }), []);
  assert.throws(() => admission.read({ path: source }), { code: 'root-denied' });
});

test('installed documentation retains explicit exclusions, sensitive names and inode protections', t => {
  const f = fixture(t), docsPath = join(f.directory, 'docs'); mkdirSync(docsPath);
  const doc = join(docsPath, 'sdk.md'), excluded = join(docsPath, 'excluded.md');
  writeFileSync(doc, '# SDK'); writeFileSync(excluded, 'Excluded');
  writeFileSync(join(docsPath, '.env.local.md'), 'CONTROLLED-SENSITIVE');
  symlinkSync(doc, join(docsPath, 'alias.md'));
  writeFileSync(join(docsPath, 'original.md'), 'Hardlinked');
  linkSync(join(docsPath, 'original.md'), join(docsPath, 'hardlink.md'));
  const candidate = f.profiles.preview({ cwd: f.root, configuration: f.config,
    excludedPaths: [excluded], providerDisclosure: true });
  f.profiles.save(candidate, []);
  const admission = new FileAdmission({ profiles: f.profiles, configuration: () => f.config,
    sessionId: f.sessionId, cwd: f.root, nativeDocumentation: { readmePath: join(f.directory, 'README.md'), docsPath } });
  t.after(() => admission.close());
  assert.equal(admission.read({ path: doc }).text, '# SDK');
  for (const path of [excluded, join(docsPath, '.env.local.md')]) {
    assert.throws(() => admission.read({ path }), { code: 'sensitive-file' });
  }
  assert.throws(() => admission.read({ path: join(docsPath, 'alias.md') }), { code: 'symlink-denied' });
  assert.throws(() => admission.read({ path: join(docsPath, 'hardlink.md') }), { code: 'unsupported-hardlink' });
  f.profiles.save(f.profiles.preview({ cwd: f.root, configuration: f.config, providerDisclosure: true }), []);
  assert.throws(() => admission.read({ path: doc }), { code: 'root-denied' });
});

test('native instructions and their exact local Markdown references read across projects without sibling grants', t => {
  const f = fixture(t), provider = join(f.directory, 'provider'); mkdirSync(provider);
  const skill = join(provider, 'SKILL.md'), reference = join(provider, 'GUIDE.md');
  writeFileSync(skill, 'Read [the guide](GUIDE.md) and [project source](../project/source.txt.md)');
  writeFileSync(join(f.root, 'source.txt.md'), 'Ordinary project document');
  writeFileSync(reference, 'Portable provider instructions');
  writeFileSync(join(provider, 'other.md'), 'Not linked');
  f.admission.ingestNativeInstructions([], [{ filePath: skill }]);
  assert.match(f.admission.read({ path: skill }).text, /guide/);
  assert.equal(f.admission.read({ path: reference }).text, 'Portable provider instructions');
  assert.throws(() => f.admission.read({ path: join(provider, 'other.md') }), { code: 'root-denied' });
  writeFileSync(skill, 'Read [unassigned](other.md)');
  f.admission.read({ path: skill });
  assert.throws(() => f.admission.read({ path: join(provider, 'other.md') }), { code: 'root-denied' });
  assert.throws(() => f.admission.read({ path: reference }), { code: 'stale-required-reference' });
  assert.equal(f.admission.read({ path: 'source.txt.md' }).text, 'Ordinary project document');
  f.admission.ingestNativeInstructions([], []);
  assert.throws(() => f.admission.read({ path: skill }), { code: 'root-denied' });
});

test('explicit installed hidden skill seeds its exact portable reference chain, including native tilde reads', t => {
  const f = fixture(t), provider = join(f.directory, 'provider'); mkdirSync(provider);
  const skill = join(provider, 'SKILL.md'), context = join(provider, 'PROJECT_CONTEXT.md'), standards = join(provider, 'STANDARDS.md');
  writeFileSync(skill, 'Read [context](PROJECT_CONTEXT.md)');
  writeFileSync(context, 'Read [standards](STANDARDS.md)');
  writeFileSync(standards, 'Required exact standards');
  writeFileSync(join(provider, 'source.md'), 'Unlinked provider source');
  const metadata = [{ name: 'build', filePath: skill, disableModelInvocation: true }];
  f.admission.ingestNativeInstructions([], metadata);
  assert.throws(() => f.admission.read({ path: skill }), { code: 'root-denied' });
  f.admission.ingestNativeInstructions([], metadata, 'build');
  assert.match(f.admission.read({ path: skill }).text, /context/);
  assert.match(f.admission.read({ path: context }).text, /standards/);
  assert.equal(f.admission.read({ path: standards }).text, 'Required exact standards');
  for (const path of [join(provider, 'source.md'), join(homedir(), '.ssh/id_ed25519')]) {
    assert.throws(() => f.admission.read({ path }), /root-denied|sensitive-file/);
  }
  // Native read accepts ~/ for the very same authority, never as a new root.
  assert.equal(f.admission.read({ path: '~/' + relative(homedir(), standards) }).text, 'Required exact standards');
  f.admission.ingestNativeInstructions([], metadata, 'review');
  assert.throws(() => f.admission.read({ path: skill }), { code: 'root-denied' });
});

test('explicit instruction reads establish and validate the same nested references as direct reads', t => {
  const f = fixture(t), provider = join(f.directory, 'provider'); mkdirSync(provider);
  const skill = join(provider, 'SKILL.md'), guide = join(provider, 'QA.md'), template = join(provider, 'REPORT.md');
  writeFileSync(skill, 'Read [QA](QA.md)');
  writeFileSync(guide, 'Use [report](REPORT.md)');
  writeFileSync(template, 'Required report');
  f.admission.ingestNativeInstructions([], [{ name: 'qa', filePath: skill }], 'qa');
  const instruction = path => f.admission.read({ path, exception: 'instruction', need: 'Read mandatory QA instructions', offset: 1, limit: 200 });
  instruction(skill);
  assert.match(instruction(guide).text, /report/);
  assert.equal(instruction(template).text, 'Required report');
  writeFileSync(skill, 'Removed reference');
  assert.throws(() => instruction(template), { code: 'stale-required-reference' });
  assert.throws(() => f.admission.read({ path: template }), { code: 'stale-required-reference' });
  f.admission.ingestNativeInstructions([], []);
  assert.throws(() => instruction(template), { code: 'root-denied' });
});

test('mandatory project context seeds follow-up instructions but unrelated files are not instruction witnesses', t => {
  const f = fixture(t), agents = join(f.root, 'AGENTS.md');
  writeFileSync(agents, 'Read [profile](PROFILE.md)');
  writeFileSync(join(f.root, 'PROFILE.md'), 'Required project profile');
  f.admission.ingestNativeInstructions([{ path: agents, content: 'Read [profile](PROFILE.md)' }]);
  const instruction = path => f.admission.read({ path, exception: 'instruction', need: 'Read instructions', offset: 1, limit: 200 });
  instruction(agents);
  assert.equal(instruction('PROFILE.md').text, 'Required project profile');
  assert.throws(() => instruction('source.txt'), { code: 'unestablished-instruction' });
  assert.match(f.admission.read({ path: 'source.txt' }).text, /Kettle/);
});

test('direct reads need no receipt while fabricated or stale explicit receipts remain errors', async t => {
  const f = fixture(t);
  assert.match(f.admission.read({ path: 'source.txt', need: f.input.need, offset: 1, limit: 3 }).text, /Kettle/);
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
  assert.equal(f.read(result).text, '  color = "blue";');
  assert.equal(f.events.at(-1).kind, 'project-read');
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
  assert.equal(f.read(result).admission.kind, 'project-read');
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

test('native context intake admits exact host bytes across roots, never siblings or stale contents', t => {
  const f = fixture(t);
  writeFileSync(join(f.root, 'native.md'), 'Actual native governing context');
  f.admission.ingestNativeInstructions([{ path: join(f.root, 'native.md'), content: 'Forged content' }]);
  const read = { path: 'native.md', need: 'Follow governing context', exception: 'instruction', offset: 1, limit: 1 };
  assert.throws(() => f.admission.read(read), { code: 'unestablished-instruction' });
  f.admission.ingestNativeInstructions([{ path: join(f.root, 'native.md'), content: 'Actual native governing context' }]);
  assert.equal(f.admission.read(read).admission.kind, 'mandatory-instruction');
  assert.throws(() => f.admission.read({ ...read, path: 'source.txt' }), { code: 'unestablished-instruction' });
  const external = join(f.directory, 'unapproved.md'); writeFileSync(external, 'Outside authority');
  f.admission.ingestNativeInstructions([{ path: external, content: 'Wrong host bytes' }]);
  assert.throws(() => f.admission.read({ ...read, path: external }), { code: 'root-denied' });
  f.admission.ingestNativeInstructions([{ path: external, content: 'Outside authority' }]);
  assert.equal(f.admission.read({ ...read, path: external }).text, 'Outside authority');
  assert.throws(() => f.admission.read(read), { code: 'unestablished-instruction' });
  writeFileSync(external, 'Changed after host intake');
  assert.throws(() => f.admission.read({ ...read, path: external }), { code: 'unestablished-instruction' });
  f.admission.ingestNativeInstructions([]);
  assert.throws(() => f.admission.read({ ...read, path: external }), { code: 'root-denied' });
});

test('native registered skills are exact local exceptions, not source roots or evaluator disclosure', async t => {
  const f = fixture(t), provider = join(f.directory, 'canonical-skills'); mkdirSync(provider);
  const skill = join(provider, 'SKILL.md'), sibling = join(provider, 'notes.md');
  writeFileSync(skill, 'Required native skill\nSecond line'); writeFileSync(sibling, 'Unassigned sibling');
  const profile = JSON.stringify(f.profiles.load());
  f.admission.ingestNativeInstructions([], [{ filePath: skill, disableModelInvocation: false }]);
  const input = { path: skill, need: 'Follow selected native skill', exception: 'instruction', offset: 1, limit: 1 };
  assert.equal(f.admission.read(input).text, 'Required native skill');
  assert.throws(() => f.admission.read({ ...input, path: sibling }), { code: 'root-denied' });
  assert.throws(() => f.admission.read({ ...input, path: 'source.txt' }), { code: 'unestablished-instruction' });
  assert.throws(() => f.admission.read({ ...input, exception: undefined, receipt: randomUUID() }), { code: 'root-denied' });
  assert.throws(() => f.admission.read({ ...input, limit: 1001 }), { code: 'bounded-range-required' });
  assert.equal((await f.screening.screen({ ...f.input, path: skill })).status, 'root-denied');
  assert.equal(f.calls(), 0);
  assert.equal(JSON.stringify(f.profiles.load()), profile);
  writeFileSync(skill, 'Changed after first selected read');
  assert.throws(() => f.admission.read(input), { code: 'unestablished-instruction' });
  f.admission.ingestNativeInstructions([], [{ filePath: skill }]);
  assert.equal(f.admission.read(input).text, 'Changed after first selected read');
  f.admission.ingestNativeInstructions([], []);
  assert.throws(() => f.admission.read(input), { code: 'root-denied' });
});

test('native skill metadata is lazy, copied and never inferred from tool assertions', t => {
  const f = fixture(t), skill = join(f.root, 'SKILL.md'), hidden = join(f.root, 'hidden.md');
  writeFileSync(skill, 'Selected instruction'); writeFileSync(hidden, 'Not advertised');
  const source = new FileSource(), snapshots = [], original = source.snapshot.bind(source);
  source.snapshot = (...args) => { snapshots.push(args[0]); return original(...args); };
  const admission = new FileAdmission({ profiles: f.profiles, configuration: () => f.config, sessionId: f.sessionId, cwd: f.root, source });
  t.after(() => admission.close());
  const metadata = [{ filePath: skill }, { filePath: hidden, disableModelInvocation: true }];
  admission.ingestNativeInstructions([], metadata);
  assert.deepEqual(snapshots, []);
  metadata[0].filePath = hidden;
  const input = { path: skill, need: 'Follow native instruction', exception: 'instruction', offset: 1, limit: 1 };
  assert.equal(admission.read(input).text, 'Selected instruction');
  assert.throws(() => admission.read({ ...input, path: hidden }), { code: 'unestablished-instruction' });
  admission.ingestNativeInstructions([], []);
  assert.throws(() => admission.read(input), { code: 'unestablished-instruction' });
});

test('native instruction witnesses preserve exclusions, symlinks, source bounds and session authority', t => {
  const f = fixture(t), provider = join(f.directory, 'provider'); mkdirSync(provider);
  const skill = join(provider, 'SKILL.md'), link = join(provider, 'alias.md'), secret = join(provider, '.env');
  writeFileSync(skill, 'Native instruction'); writeFileSync(secret, 'CONTROLLED-SENSITIVE'); symlinkSync(skill, link);
  f.admission.ingestNativeInstructions([], [skill, link, secret].map(filePath => ({ filePath })));
  const input = { path: skill, need: 'Follow native instruction', exception: 'instruction', offset: 1, limit: 1 };
  assert.throws(() => f.admission.read({ ...input, path: secret }), { code: 'sensitive-file' });
  assert.throws(() => f.admission.read({ ...input, path: link }), { code: 'symlink-denied' });
  writeFileSync(skill, 'x'.repeat(1048577));
  assert.throws(() => f.admission.read(input), { code: 'source-limit' });
  writeFileSync(skill, 'Native instruction');
  assert.equal(f.admission.read(input).text, 'Native instruction');
  const changed = f.profiles.load(); changed.revision = randomUUID(); f.profiles.save(changed, []);
  assert.throws(() => f.admission.read(input), { code: 'root-denied' });
  assert.throws(() => f.admission.ingestNativeInstructions([], [{ filePath: skill }]), { code: 'stale-trusted-intake' });
  changed.projects[0].excludedPaths.push(skill); f.profiles.save(changed, []);
  assert.throws(() => f.admission.read(input), { code: 'sensitive-file' });
  f.admission.close();
  assert.throws(() => f.admission.read(input), { code: 'session-changed' });
});

test('native intake cannot replenish the finite structural lookup allowance', t => {
  const f = fixture(t);
  for (let i = 0; i < 32; i++) {
    f.admission.ingestNativeInstructions([], []);
    f.admission.locate({ path: 'source.txt', symbol: 'Kettle', need: 'Inspect exact definition' });
  }
  f.admission.ingestNativeInstructions([], []);
  assert.throws(() => f.admission.locate({ path: 'source.txt', symbol: 'Kettle', need: 'Inspect exact definition' }), { code: 'structural-query-limit' });
});

test('large Markdown Board headings yield bounded evidence without evaluator disclosure or sibling authority', async t => {
  const f = fixture(t), path = join(f.root, 'BOARD.md');
  const board = '# Board\n## Current Human Decision\nChoose the next approved task.\n### Context\nKeep the existing scope.\n## Active Work\nCurrent task.\n## Archive\n' + 'Historical planning row.\n'.repeat(2500);
  writeFileSync(path, board);
  const profile = JSON.stringify(f.profiles.load());
  assert.equal((await f.screening.screen({ ...f.input, path })).status, 'source-limit');
  const need = 'Read the Board current decision';
  const target = f.admission.locate({ path, heading: 'Current Human Decision', need });
  assert.equal(target.kind, 'verified-heading'); assert.equal(target.offset, 2); assert.equal(target.maxLines, 4);
  const input = { path, need, offset: target.offset, limit: target.maxLines, evidence: target.evidence };
  assert.equal(f.admission.read(input).text, '## Current Human Decision\nChoose the next approved task.\n### Context\nKeep the existing scope.');
  assert.throws(() => f.admission.read({ ...input, limit: 5 }), { code: 'current-target-evidence-required' });
  assert.throws(() => f.admission.read({ ...input, need: 'Read a different purpose' }), { code: 'current-target-evidence-required' });
  assert.throws(() => f.admission.read({ ...input, path: 'source.txt', offset: 1, limit: 1 }), { code: 'current-target-evidence-required' });
  writeFileSync(path, board + 'Changed archive.\n');
  assert.throws(() => f.admission.read(input), { code: 'current-target-evidence-required' });
  assert.equal(f.calls(), 0); assert.equal(JSON.stringify(f.profiles.load()), profile);
});

test('Markdown lookup ignores frontmatter, fenced examples and comments and respects section boundaries', t => {
  const f = fixture(t), path = join(f.root, 'guide.md');
  writeFileSync(path, ['---', 'description: |', '  ## Current Human Decision', '---',
    '<!--', '## Current Human Decision', '-->', '```markdown', '## Current Human Decision', '```',
    '~~~~', '## Current Human Decision', '~~~', '~~~~', '    ## Current Human Decision',
    '  ## Current Human Decision ###', 'Decide.', '### Details', 'Keep details.', '## Next', 'Unselected.'].join('\n'));
  const target = f.admission.locate({ path, heading: 'Current Human Decision', need: 'Locate actual decision' });
  assert.equal(target.offset, 16); assert.equal(target.maxLines, 4);
  assert.throws(() => f.admission.locate({ path, heading: 'Missing', need: 'Locate missing heading' }), { code: 'exact-heading-target-required' });
  writeFileSync(path, '## Duplicate\nFirst\n## Duplicate\nSecond');
  assert.throws(() => f.admission.locate({ path, heading: 'Duplicate', need: 'Locate ambiguous heading' }), { code: 'exact-heading-target-required' });
});

test('heading and symbol lookups share existing finite query and returned-line limits', t => {
  const f = fixture(t), path = join(f.root, 'BOARD.md');
  writeFileSync(path, '# Ready frontier\n' + 'Ready task\n'.repeat(80));
  const target = f.admission.locate({ path, heading: 'Ready frontier', need: 'Read first ready tasks' });
  assert.equal(target.maxLines, 40);
  assert.throws(() => f.admission.read({ path, need: 'Read first ready tasks', evidence: target.evidence, offset: 1, limit: 41 }), { code: 'current-target-evidence-required' });
  for (let i = 0; i < 31; i++) f.admission.locate({ path: 'source.txt', symbol: 'Kettle', need: 'Inspect exact definition' });
  assert.throws(() => f.admission.locate({ path, heading: 'Ready frontier', need: 'Read first ready tasks' }), { code: 'structural-query-limit' });
});

test('heading lookups retain exact-evidence byte, path, source and authority limits', t => {
  const f = fixture(t), path = join(f.root, 'large.md');
  writeFileSync(path, '# Decision\n' + 'x'.repeat(1048576));
  assert.throws(() => f.admission.locate({ path, heading: 'Decision', need: 'Locate bounded decision' }), { code: 'source-limit' });
  const input = { path: 'source.txt', heading: 'Decision', need: 'Locate bounded decision' };
  for (const extra of [{ heading: '' }, { symbol: 'Kettle' }, { heading: 'Two\nlines' }]) {
    assert.throws(() => f.admission.locate({ ...input, ...extra }), { code: 'invalid-structural-query' });
  }
  const outside = join(f.directory, 'outside.md'); writeFileSync(outside, '# Decision');
  assert.throws(() => f.admission.locate({ ...input, path: outside }), { code: 'root-denied' });
  writeFileSync(join(f.root, '.env'), '# Decision');
  assert.throws(() => f.admission.locate({ ...input, path: '.env' }), { code: 'sensitive-file' });
  writeFileSync(path, '# Decision\nRelevant text');
  const target = f.admission.locate({ ...input, path });
  const changed = f.profiles.load(); changed.revision = randomUUID(); f.profiles.save(changed, []);
  assert.throws(() => f.admission.read({ path, need: input.need, offset: 1, limit: 2, evidence: target.evidence }), { code: 'current-target-evidence-required' });
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
