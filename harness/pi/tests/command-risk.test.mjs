import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, mkdirSync, rmSync, realpathSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { CommandRisk } from '../src/jev/command-risk.mjs';
import { ScreeningProfiles } from '../src/jev/screening-profile.mjs';
import { Judgments } from '../src/jev/judgments.mjs';
import { MonthlyLedger, ProfileBudget } from '../src/jev/monthly-budget.mjs';
import { connection } from '../src/jev/readiness.mjs';

function fixture(t, { choice = 'ordinary', confidence = 0.98, status = 200, cost = 0.000003 } = {}) {
  const base = realpathSync.native(mkdtempSync(join(tmpdir(), 'command-risk-'))), root = join(base, 'project');
  mkdirSync(root); t.after(() => rmSync(base, { recursive: true, force: true }));
  const { schema, ...fixed } = connection;
  const config = { ...fixed, revision: randomUUID(), priceConfirmedAt: Date.now(), disclosure: 'authorized-supplied-state', requestMicroUsd: 2688, sessionMicroUsd: 100000 };
  const profiles = new ScreeningProfiles(join(base, 'operator'));
  profiles.save(profiles.preview({ cwd: root, configuration: config, providerDisclosure: true, commandDisclosure: true }), []);
  const sessionId = randomUUID(), ledger = new MonthlyLedger(profiles.directory), events = [], payloads = [];
  let during = () => {}, credentials = 0;
  const context = { phase: 'engineer', commandPrefix: 'printf prefix', shellPath: '/bin/bash' };
  const judgments = new Judgments({ sessionId, configuration: () => config, platform: 'darwin', budget: new ProfileBudget(sessionId, profiles.directory, ledger),
    credentials: { read: async () => { credentials++; return Buffer.from('controlled-nonsecret-fixture'); } },
    transport: { submit: async (_endpoint, payload) => {
      payloads.push(payload); during();
      const others = (1 - confidence) / 2;
      return { status, body: { model: connection.evaluator, provider: 'TypeSafe', usage: { input_tokens: 10, output_tokens: 2, cost },
        answers: { 'command-risk': { type: 'choice', choice, confidence,
          probabilities: { ordinary: choice === 'ordinary' ? confidence : others, risky: choice === 'risky' ? confidence : others, unknown: choice === 'unknown' ? confidence : others } } } } };
    } } });
  t.after(() => judgments.close());
  const risk = new CommandRisk({ profiles, configuration: () => config, sessionId, cwd: root, context: () => context, observe: e => events.push(e),
    connect: authorize => ({ state: 'available', ...judgments.consumer('shell-risk-v1', authorize) }) });
  return { root, profiles, config, sessionId, ledger, events, payloads, risk, context, during: fn => { during = fn; }, credentials: () => credentials };
}

test('ordinary final commands use shared judgments/accounting without a new permission grant or relevance result', async t => {
  const f = fixture(t);
  const command = 'rg -n "shipping" . | head -5; cat notes.md; git diff; git status --porcelain';
  let decisions = 0;
  const result = await f.risk.assess(command, f.root, { confirm: async () => { decisions++; return false; } });
  assert.equal(result.outcome, 'ordinary'); assert.equal(decisions, 0);
  assert.equal(f.credentials(), 1); assert.equal(f.payloads.length, 1);
  assert.deepEqual(Object.keys(f.payloads[0].state).sort(), ['command', 'commandPrefix', 'cwd', 'phase', 'root', 'shellPath']);
  assert.equal(f.payloads[0].state.command, command);
  assert.equal(f.payloads[0].state.phase, 'engineer');
  assert.equal(f.ledger.inspect().settledMicroUsd, 3); assert.equal(f.ledger.inspect().reservedMicroUsd, 0);
  assert.deepEqual(f.events.map(e => e.kind), ['command-risk-start', 'command-risk-complete']);
  assert.equal(JSON.stringify(f.events).includes('git diff'), false);
});

for (const [choice, confidence, expected] of [['risky', 0.98, 'risky'], ['unknown', 0.98, 'uncertain'], ['ordinary', 0.89, 'uncertain']]) {
  test(`${choice}/${confidence} visibly holds without an operator decision; one explicit decision is not a cached permit`, async t => {
    const f = fixture(t, { choice, confidence });
    await assert.rejects(f.risk.assess('printf harmless-probe', f.root), { code: 'command-operator-decision-required' });
    assert.equal(f.events.at(-1).kind, 'command-held');
    let decisions = 0;
    const result = await f.risk.assess('printf harmless-probe', f.root, { confirm: async decision => {
      assert.equal(decision.outcome, expected); assert.equal(decision.command, 'printf harmless-probe'); decisions++; return true;
    } });
    assert.equal(result.outcome, expected); assert.equal(decisions, 1); assert.equal(f.payloads.length, 2);
    assert.equal(f.events.at(-1).kind, 'command-approved');
    assert.equal(f.ledger.inspect().settledMicroUsd, 6);
  });
}
for (const [status, reason] of [[403, 'provider-denied'], [500, 'provider-unavailable']]) {
  test(`unavailable/denied ${status} is not fabricated safe or automatically retried`, async t => {
    const f = fixture(t, { status });
    await assert.rejects(f.risk.assess('printf harmless', f.root), { code: 'command-operator-decision-required' });
    assert.equal(f.payloads.length, 1); assert.equal(f.events.at(-1).decision, 'unavailable');
    assert.equal(f.events.find(e => e.kind === 'command-risk-complete').reason, reason);
    assert.equal(f.ledger.inspect().reservedMicroUsd, 2688); // Dispatched unknown billing stays reserved.
  });
}

test('budget exhaustion holds without provider effects or replenishing the original account', async t => {
  const f = fixture(t), id = f.ledger.reserve('previous-session', 10000000, 10000000); f.ledger.dispatch(id);
  await assert.rejects(f.risk.assess('git diff', f.root), { code: 'command-operator-decision-required' });
  assert.equal(f.credentials(), 0); assert.equal(f.payloads.length, 0);
  assert.equal(f.ledger.inspect().reservedMicroUsd, 10000000);
  assert.equal(f.events.find(e => e.kind === 'command-risk-complete').reason, 'monthly-budget');
});

test('revocation and authority/context changes during judgment prevent command admission', async t => {
  for (const change of ['revoke', 'phase', 'configuration']) {
    const f = fixture(t);
    f.during(() => {
      if (change === 'revoke') f.profiles.revoke(f.root);
      if (change === 'phase') f.context.phase = 'land';
      if (change === 'configuration') f.config.revision = randomUUID();
    });
    await assert.rejects(f.risk.assess('printf harmless', f.root, { confirm: async () => { assert.fail('Drift cannot be approved'); } }),
      { code: change === 'revoke' ? 'enrollment-revoked' : change === 'configuration' ? 'command-authority-changed' : 'command-context-changed' });
    assert.equal(f.payloads.length, 1);
    assert.equal(f.ledger.inspect().reservedMicroUsd, 2688); // Supplier cannot settle an unverifiable post-revocation outcome.
  }
});

test('revocation during operator confirmation is not overridable', async t => {
  const f = fixture(t, { choice: 'risky' });
  await assert.rejects(f.risk.assess('printf harmless', f.root, { confirm: async () => { f.profiles.revoke(f.root); return true; } }), { code: 'enrollment-revoked' });
});

for (const command of ['API_KEY=fixture-not-a-key printf harmless', 'OPENROUTER_API_KEY=fixture-not-a-key printf harmless', 'tool --access-token fixture-not-a-key', 'curl -H "Authorization: Bearer fixture-not-a-key" https://example.invalid',
  'cat ~/.ssh/id_ed25519', 'cat auth.json', 'cat .env', 'echo $OPENROUTER_API_KEY', 'pi auth print-api-key --provider openrouter']) {
  test('known secret-bearing command is held before supplier disclosure', async t => {
    const f = fixture(t);
    await assert.rejects(f.risk.assess(command, f.root, { confirm: async () => { assert.fail('No secret override'); } }), { code: 'command-secret-disclosure-held' });
    assert.equal(f.credentials(), 0); assert.equal(f.payloads.length, 0); assert.equal(f.ledger.inspect().pending.length, 0);
    assert.equal(JSON.stringify(f.events).includes(command), false);
  });
}

test('missing command consent fails before paid effects', async t => {
  const f = fixture(t), profile = f.profiles.load(); profile.commandDisclosure = false; f.profiles.save(profile, []);
  await assert.rejects(f.risk.assess('git status', f.root), { code: 'command-disclosure-not-approved' });
  assert.equal(f.payloads.length, 0);
});
