import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync, realpathSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn } from 'node:child_process';
import { ScreeningProfiles } from '../src/jev/screening-profile.mjs';
import { ScreeningDispatch, safeChildObservation } from '../src/jev/screening-dispatch.mjs';
import { connection } from '../src/jev/readiness.mjs';

function fixture(t) {
  const directory = realpathSync.native(mkdtempSync(join(tmpdir(), 'screening-dispatch-'))), root = join(directory, 'project');
  mkdirSync(root); writeFileSync(join(root, 'source.txt'), 'Assigned content');
  const { schema, ...fixed } = connection;
  const configuration = { ...fixed, revision: randomUUID(), priceConfirmedAt: Date.now(), disclosure: 'authorized-supplied-state', requestMicroUsd: 2688, sessionMicroUsd: 100000 };
  const profiles = new ScreeningProfiles(join(directory, 'operator'));
  profiles.save(profiles.preview({ cwd: root, configuration, providerDisclosure: true }), []);
  const monitor = join(directory, 'trusted-provider.py'); writeFileSync(monitor, '# trusted immutable provider artifact');
  const dispatch = new ScreeningDispatch(profiles, { monitor });
  const input = { cwd: root, request: 'Investigate the exact assigned fixture. No delegation.', task: 'TASK-00309', role: 'qualification', provider: 'openai-codex', model: 'gpt-6.1-sol', thinking: 'high', timeout: 30 };
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  return { directory, root, profiles, monitor, dispatch, input };
}

test('trusted dispatch pins target/provider/request and is single-use even without a completion receipt', t => {
  const f = fixture(t), plan = f.dispatch.prepare(f.input);
  assert.equal(f.dispatch.inspect(f.root)[0].state, 'available');
  const claimed = f.dispatch.claim(f.root, plan.id);
  assert.equal(claimed.cwd, f.root); assert.equal(claimed.monitor, f.monitor);
  assert.equal(claimed.model, 'gpt-6.1-sol'); assert.equal(claimed.thinking, 'high');
  assert.equal(readFileSync(join(claimed.attempt, 'request.md'), 'utf8'), f.input.request);
  assert.throws(() => new ScreeningDispatch(f.profiles, { monitor: f.monitor }).claim(f.root, plan.id), { code: 'dispatch-consumed-or-uncertain' });
  assert.equal(f.dispatch.inspect(f.root)[0].state, 'consumed-or-uncertain');
  assert.throws(() => f.dispatch.claim(f.root, '../../unapproved-shell'), { code: 'dispatch-not-authorized' });
});

test('source, authority, request, target and expiry drift cannot repurpose a prepared ticket', t => {
  const f = fixture(t), first = f.dispatch.prepare(f.input);
  writeFileSync(join(first.attempt, 'request.md'), 'Changed unscreened assignment');
  assert.throws(() => f.dispatch.claim(f.root, first.id), { code: 'stale-dispatch-plan' });
  const second = f.dispatch.prepare(f.input); writeFileSync(f.monitor, '# changed provider code');
  assert.throws(() => f.dispatch.claim(f.root, second.id), { code: 'stale-dispatch-plan' });
  const third = f.dispatch.prepare(f.input);
  assert.throws(() => new ScreeningDispatch(f.profiles, { monitor: f.monitor, now: () => Date.now() + 3600001 }).claim(f.root, third.id), { code: 'stale-dispatch-plan' });
  const fourth = f.dispatch.prepare(f.input), changed = f.profiles.load(); changed.revision = randomUUID(); f.profiles.save(changed, []);
  assert.throws(() => f.dispatch.claim(f.root, fourth.id), { code: 'stale-dispatch-plan' });
  assert.throws(() => f.dispatch.claim(f.directory, fourth.id), { code: 'stale-dispatch-plan' });
});

test('dispatch carries only trusted source-bound assignment intake and forbids generic recursion', t => {
  const f = fixture(t);
  const plan = f.dispatch.prepare({ ...f.input, intake: { assignments: [{ path: 'source.txt', purpose: 'Verify assigned fixture', offset: 1, limit: 1, reference: 'assignment-1' }] } });
  assert.ok(plan.intake); assert.equal(JSON.parse(readFileSync(join(f.profiles.directory, 'dispatch', plan.id, 'intake.json'))).targets.length, 1);
  assert.throws(() => f.dispatch.prepare({ ...f.input, timeout: 1801 }), { code: 'invalid-dispatch-plan' });
  const previous = process.env.FIGHT_SCREENING_CHILD; process.env.FIGHT_SCREENING_CHILD = '1';
  try {
    assert.throws(() => f.dispatch.claim(f.root, plan.id), { code: 'dispatch-not-authorized' });
    assert.throws(() => f.dispatch.prepare(f.input), { code: 'dispatch-not-authorized' });
    assert.deepEqual(f.dispatch.inspect(f.root), []);
  } finally { if (previous === undefined) delete process.env.FIGHT_SCREENING_CHILD; else process.env.FIGHT_SCREENING_CHILD = previous; }
});

test('child observations preserve cache/exception/block identity without returning arbitrary child content', () => {
  const session = 'a'.repeat(64);
  const item = { version: 'fight-file-screen-observation-v1', session, phase: 'qualification-child', kind: 'cache-reuse', reason: 'current-cache',
    sourceBody: 'PRIVATE-SOURCE', privateReasoning: 'PRIVATE-REASONING', credential: 'PRIVATE-KEY', costUsd: null };
  const safe = safeChildObservation('Fight screening: ' + JSON.stringify(item));
  assert.equal(safe.kind, 'cache-reuse'); assert.equal(safe.phase, 'qualification-child'); assert.equal(safe.session, session); assert.equal(safe.providerCalls, 0);
  assert.equal(safe.costUsd, null); assert.equal(JSON.stringify(safe).includes('PRIVATE'), false);
  assert.equal(safeChildObservation('assistant says PRIVATE-SOURCE'), null);
  assert.equal(safeChildObservation('Fight screening: ' + JSON.stringify({ ...item, kind: 'arbitrary-source' })), null);
  assert.equal(safeChildObservation('Fight screening: ' + JSON.stringify({ ...item, session: 'PRIVATE-SOURCE' })), null);
  assert.equal(safeChildObservation('Fight screening: {not-json}'), null);
});

test('safe status retains bounded monthly diagnostics and an actionable code, never caller prose', () => {
  const status = { state: 'degraded', reason: 'monthly-budget', session: 'a'.repeat(64),
    budget: { availableMicroUsd: 12, reservedMicroUsd: 2688, settledMicroUsd: 33, resetsAt: '2026-11-01T05:00:00.000Z' },
    nextActionCode: 'wait-month-reset', nextAction: 'PRIVATE-CHILD-PROSE', privateReasoning: 'PRIVATE-REASONING' };
  const safe = safeChildObservation('Fight screening status: ' + JSON.stringify(status));
  assert.deepEqual(safe.budget, status.budget); assert.equal(safe.session, status.session);
  assert.equal(safe.nextActionCode, 'wait-month-reset'); assert.equal(JSON.stringify(safe).includes('PRIVATE'), false);
  const malformed = safeChildObservation('Fight screening status: ' + JSON.stringify({ ...status,
    budget: { availableMicroUsd: -1, reservedMicroUsd: 1e100, settledMicroUsd: true, resetsAt: 'PRIVATE-SOURCE' },
    nextActionCode: 'PRIVATE-SOURCE' }));
  assert.deepEqual(malformed.budget, {}); assert.equal(malformed.nextActionCode, undefined);
});

test('safe blocked reads preserve distinct file, need, operation and sequence identity', () => {
  const base = { version: 'fight-file-screen-observation-v1', kind: 'blocked-read', session: 'a'.repeat(64),
    reason: 'unambiguous-evidence-required', phase: 'review', costUsd: null };
  const first = safeChildObservation('Fight screening: ' + JSON.stringify({ ...base, pathId: 'b'.repeat(64), needId: 'c'.repeat(64), operationId: randomUUID(), sequence: 1 }));
  const second = safeChildObservation('Fight screening: ' + JSON.stringify({ ...base, pathId: 'd'.repeat(64), needId: 'e'.repeat(64), operationId: randomUUID(), sequence: 2 }));
  assert.notEqual(first.pathId, second.pathId); assert.notEqual(first.needId, second.needId);
  assert.notEqual(first.operationId, second.operationId); assert.equal(first.sequence, 1); assert.equal(second.sequence, 2);
});

test('competing coordinator processes cannot claim the same paid child twice', async t => {
  const f = fixture(t), plan = f.dispatch.prepare(f.input);
  const module = new URL('../src/jev/screening-dispatch.mjs', import.meta.url).href;
  const profilesModule = new URL('../src/jev/screening-profile.mjs', import.meta.url).href;
  const results = await Promise.all(Array.from({ length: 4 }, () => new Promise((resolve, reject) => {
    const p = spawn(process.execPath, ['--input-type=module', '-e', `import {ScreeningProfiles} from ${JSON.stringify(profilesModule)}; import {ScreeningDispatch} from ${JSON.stringify(module)};
      const d=new ScreeningDispatch(new ScreeningProfiles(process.argv[1]),{monitor:process.argv[2]});
      try {d.claim(process.argv[3],process.argv[4]);console.log('claimed');}catch(e){if(e.code!=='dispatch-consumed-or-uncertain')throw e;console.log('blocked');}`,
    f.profiles.directory, f.monitor, f.root, plan.id]);
    let output = ''; p.stdout.on('data', b => { output += b; }); p.stderr.on('data', () => {});
    p.on('error', reject); p.on('close', code => code === 0 ? resolve(output.trim()) : reject(new Error('claim process failed')));
  })));
  assert.equal(results.filter(s => s === 'claimed').length, 1); assert.equal(results.filter(s => s === 'blocked').length, 3);
});
