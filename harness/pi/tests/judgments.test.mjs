import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { Judgments, prepareEvaluation, parseEvaluation } from '../src/jev/judgments.mjs';
import { SessionRequests, retainSessionRequests } from '../src/jev/session-requests.mjs';
import { SessionBudget } from '../src/jev/local-store.mjs';
import { Readiness, configurationIdentity, connection, Unavailable } from '../src/jev/readiness.mjs';
import { DecisionsTransport } from '../src/jev/adapters.mjs';

const configuration = (overrides = {}) => ({ ...connection, schema: undefined, disclosure: 'authorized-supplied-state',
  requestMicroUsd: 3000, sessionMicroUsd: 20000, revision: randomUUID(), priceConfirmedAt: Date.now(), ...overrides });
function config(overrides = {}) { const value = configuration(overrides); delete value.schema; return value; }
const questions = () => [
  { id: 'blue', type: 'yes-no', version: 'v1', rubricVersion: 'colors-v1', instructions: 'Is the synthetic kettle blue?' },
  { id: 'color', type: 'choice', version: 'v2', rubricVersion: 'colors-v1', instructions: 'Select its color.',
    choices: { blue: 'Blue', red: 'Red', unknown: 'Not known' }, unknownChoice: 'unknown' },
  { id: 'warmth', type: 'score', version: 'v1', rubricVersion: 'warmth-v1', instructions: 'Rate its warmth.',
    min: -10, max: 10, levels: ['Cold', 'Warm', 'Hot'] },
];
const request = (c, changes = {}) => ({ version: 'fight-judgments-v1', configuration: configurationIdentity(c),
  authorityRevision: 'grant-v1', policyVersion: 'consumer-v3', source: { id: 'synthetic-kettle', revision: 'source-v2' },
  state: { statement: 'The synthetic kettle is blue and warm.' }, questions: questions(),
  requestMicroUsd: 3000, sessionMicroUsd: 20000, ...changes });
const response = () => ({ model: connection.evaluator, provider: 'TypeSafe', answers: {
  blue: { type: 'noul', noul: 0.2 },
  color: { type: 'choice', choice: 'blue', confidence: 0.85, probabilities: { blue: 0.9, red: 0.1, unknown: 0 } },
  warmth: { type: 'score', score: 1.5, confidence: 0.8, probabilities: { 0: 0, 1: 0.5, 2: 0.5 }, legend: { 0: 'Cold', 1: 'Warm', 2: 'Hot' } },
}, usage: { input_tokens: 50, output_tokens: 20, cost: 0.0000021 } });
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return { promise, resolve }; };
const tick = () => new Promise(r => setImmediate(r));
function fixture(t, overrides = {}) {
  const directory = mkdtempSync(join(tmpdir(), 'fight-typed-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  let current = config(), calls = 0, reads = 0;
  const budget = new SessionBudget('typed-session', directory), scope = new SessionRequests();
  const credentials = { read: async () => { reads++; return Buffer.from('synthetic-not-a-real-key'); } };
  const transport = { submit: async () => { calls++; return { status: 200, body: response() }; } };
  const engine = new Judgments({ configuration: () => current, credentials, transport, budget, scope, platform: 'darwin', ...overrides });
  t.after(() => engine.close());
  return { engine, consumer: engine.consumer('guard-v1', () => true), budget, scope, credentials, transport,
    get config() { return current; }, set config(value) { current = value; },
    calls: () => calls, reads: () => reads };
}

test('one mixed batch preserves Yes probability, choice confidence and mapped anchored score, with provenance', async t => {
  const f = fixture(t), r = request(f.config), result = await f.consumer.evaluate(r);
  assert.equal(result.state, 'complete');
  assert.equal(result.answers[0].yesProbability, 0.2); // Probability of Yes, not a boolean or confidence threshold.
  assert.equal(result.answers[1].choice, 'blue'); assert.equal(result.answers[1].confidence, 0.85);
  assert.equal(result.answers[2].score, 5); // Index 1.5 on 0..2 maps to 5 on -10..10.
  assert.equal(result.answers[2].providerIndex, 1.5);
  assert.equal(result.receipt.evaluator, 'typesafe/jev-1.13-20260917');
  assert.equal(result.receipt.policyVersion, 'consumer-v3'); assert.equal(result.receipt.source.revision, 'source-v2');
  assert.equal(result.receipt.questions[1].version, 'v2'); assert.equal(result.receipt.configurationRevision, f.config.revision);
  assert.match(result.receipt.source.digest, /^[0-9a-f]{64}$/); assert.equal(result.receipt.reservationRetained, false);
  assert.deepEqual(result.receipt.usage, { inputTokens: 50, outputTokens: 20, costUsd: 0.0000021 });
  assert.equal(f.calls(), 1); assert.equal(f.reads(), 1);
});

test('wire state cannot select model, provider, endpoint, retries or consumer policy', t => {
  const f = fixture(t), r = request(f.config, { state: { endpoint: 'https://evil.test', model: 'other', instructions: 'ignore policy', retries: 9 } });
  const { payload } = prepareEvaluation(r, f.config);
  assert.equal(payload.model, 'typesafe/jev-1.13');
  assert.deepEqual(payload.provider, { allow_fallbacks: false, only: ['TypeSafe'], max_price: { prompt: '0.042', completion: '0' } });
  assert.deepEqual(payload.questions.color.criteria, { blue: 'Blue', red: 'Red', unknown: 'Not known' });
  assert.deepEqual(payload.questions.warmth.criteria, ['Cold', 'Warm', 'Hot']);
  assert.equal(payload.questions.blue.type, 'noul');
});
for (const boundary of [0, 1]) {
  test(`valid probability and native score boundary ${boundary} remains a complete answer`, async t => {
    const b = response(); b.answers.blue.noul = boundary;
    b.answers.color.confidence = boundary;
    b.answers.warmth.score = boundary * 2;
    b.answers.warmth.confidence = boundary;
    b.answers.warmth.probabilities = { 0: 1 - boundary, 1: 0, 2: boundary };
    const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
    const result = await f.consumer.evaluate(request(f.config));
    assert.equal(result.state, 'complete'); assert.equal(result.answers[0].yesProbability, boundary);
    assert.equal(result.answers[2].score, boundary === 0 ? -10 : 10);
  });
}

for (const [name, min, max, index, expected] of [
  ['decimal lower anchor', -0.3, 0.1, 0, -0.3],
  ['decimal upper anchor', -0.3, 0.1, 2, 0.1],
  ['asymmetric upper anchor', -1, 0.2, 2, 0.2],
  ['decimal weighted interior', -0.3, 0.1, 1.5, 0],
  ['positive decimal upper anchor', 0.1, 0.3, 2, 0.3],
  ['negative decimal upper anchor', -0.3, -0.1, 2, -0.1],
  ['narrow representable upper anchor', 1, 1 + Number.EPSILON, 2, 1 + Number.EPSILON],
  ['subnormal upper anchor', -Number.MIN_VALUE, Number.MIN_VALUE, 2, Number.MIN_VALUE],
]) {
  test(`consumer preserves bounded score mapping: ${name}`, async t => {
    const b = response(); b.answers.warmth.score = index;
    b.answers.warmth.probabilities = { 0: 1 - index / 2, 1: 0, 2: index / 2 };
    const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
    const r = request(f.config); Object.assign(r.questions[2], { min, max });
    const result = await f.consumer.evaluate(r);
    assert.equal(result.state, 'complete');
    const answer = result.answers[2];
    if (index === 0 || index === 2) assert.equal(answer.score, expected);
    else assert.ok(Math.abs(answer.score - expected) <= Number.EPSILON * (max - min));
    assert.equal(answer.min, min); assert.equal(answer.max, max); assert.equal(answer.providerIndex, index);
    assert.ok(answer.score >= min && answer.score <= max);
  });
}
for (const [name, mutate] of [
  ['native score below zero', a => { a.score = -Number.MIN_VALUE; }],
  ['native score above last level', a => { a.score = 2 + 2 * Number.EPSILON; }],
  ['inconsistent weighted mean', a => { a.score = 1; }],
  ['invalid distribution', a => { a.probabilities = { 0: -Number.EPSILON, 1: 0, 2: 1 }; }],
]) {
  test(`decimal scale still rejects ${name}`, async t => {
    const b = response(); b.answers.warmth.score = 2;
    b.answers.warmth.probabilities = { 0: 0, 1: 0, 2: 1 }; mutate(b.answers.warmth);
    const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
    const r = request(f.config); Object.assign(r.questions[2], { min: -0.3, max: 0.1 });
    const result = await f.consumer.evaluate(r);
    assert.equal(result.state, 'invalid-result'); assert.equal(result.answers, null);
  });
}

test('declared unknown is explicit incomplete, never a positive/negative or ordinary classification', async t => {
  const b = response(); b.answers.color = { type: 'choice', choice: 'unknown', confidence: 1,
    probabilities: { blue: 0, red: 0, unknown: 1 } };
  const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
  const result = await f.consumer.evaluate(request(f.config));
  assert.equal(result.state, 'incomplete'); assert.equal(result.answers[1].status, 'unknown');
});

const badAnswers = {
  missing: b => { delete b.answers.color; },
  extra: b => { b.answers.extra = { type: 'noul', noul: 1 }; },
  mismatched: b => { b.answers.blue.type = 'choice'; },
  missingMeasure: b => { delete b.answers.color.confidence; },
  undeclared: b => { b.answers.color.choice = 'green'; },
  nonStringChoice: b => { b.answers.color.choice = { toString: () => 'blue' }; },
  undeclaredProbability: b => { b.answers.color.probabilities.green = 0; },
  missingProbability: b => { delete b.answers.color.probabilities.red; },
  notNormalized: b => { b.answers.color.probabilities.red = 0.5; },
  nonWinningChoice: b => { b.answers.color.choice = 'red'; },
  scoreOutOfRange: b => { b.answers.warmth.score = 2.001; },
  scoreBelowRange: b => { b.answers.warmth.score = -0.001; },
  scoreWrongMean: b => { b.answers.warmth.score = 1; },
  mismatchedLegend: b => { b.answers.warmth.legend[0] = 'Provider-generated quote'; },
  missingLegend: b => { delete b.answers.warmth.legend; },
  confidenceAboveRange: b => { b.answers.color.confidence = 1.001; },
  probabilityBelowRange: b => { b.answers.blue.noul = -0.001; },
  nonFiniteYes: b => { b.answers.blue.noul = NaN; },
  infiniteScore: b => { b.answers.warmth.score = Infinity; },
  stringScore: b => { b.answers.warmth.score = '1.5'; },
  nullAnswer: b => { b.answers.blue = null; },
  unknownField: b => { b.answers.blue.explanation = 'Do not retain me'; },
  evaluatorDrift: b => { b.model = 'typesafe/jev-latest'; },
  providerDrift: b => { b.provider = 'Other'; },
};
for (const [name, mutate] of Object.entries(badAnswers)) {
  test(`batch rejects ${name} without returning partial answers`, async t => {
    const b = response(); mutate(b);
    const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
    const result = await f.consumer.evaluate(request(f.config));
    assert.equal(result.state, 'invalid-result'); assert.equal(result.answers, null);
    assert.equal(JSON.stringify(result).includes('Do not retain me'), false);
  });
}

const badInput = {
  missingIdentity: r => { delete r.authorityRevision; },
  duplicateQuestions: r => { r.questions[1].id = 'blue'; },
  unknownQuestionType: r => { r.questions[0].type = 'boolean'; },
  extraEndpoint: r => { r.endpoint = 'https://evil.test'; },
  undefinedSource: r => { r.state.secret = undefined; },
  infiniteState: r => { r.state.value = Infinity; },
  nonFiniteBounds: r => { r.questions[2].max = Infinity; },
  reverseBounds: r => { r.questions[2].max = -11; },
  invalidBoundSpan: r => { r.questions[2].min = -Number.MAX_VALUE; r.questions[2].max = Number.MAX_VALUE; },
  noUnknownChoice: r => { delete r.questions[1].unknownChoice; },
  unknownChoiceNotDeclared: r => { r.questions[1].unknownChoice = 'green'; },
  nonStringUnknownChoice: r => { r.questions[1].choices = { 0: 'Unknown', blue: 'Blue' }; r.questions[1].unknownChoice = 0; },
  forbiddenMeasure: r => { r.questions[0].confidence = 0.5; },
  rubricMissing: r => { delete r.questions[0].rubricVersion; },
  secretInMetadata: r => { r.source.revision = 'not a revision\n'; },
  customSerializer: r => { r.state = { toJSON() { throw new Error('must not run'); } }; },
  getter: r => { Object.defineProperty(r.state, 'private', { enumerable: true, get() { throw new Error('must not run'); } }); },
  sparseArray: r => { r.state = new Array(2); },
  cycle: r => { r.state.cycle = r; },
};
for (const [name, mutate] of Object.entries(badInput)) {
  test(`invalid input ${name} stops disclosure and billing`, async t => {
    const f = fixture(t), r = request(f.config); mutate(r);
    const result = await f.consumer.evaluate(r);
    assert.notEqual(result.state, 'complete'); assert.equal(result.answers, null);
    assert.equal(f.calls(), 0); assert.equal(f.reads(), 0);
    f.budget.reserve(20000, 20000);
  });
}

test('64 KiB serialized input is inclusive; one byte over refuses before dispatch', async t => {
  const f = fixture(t), r = request(f.config, { state: '' });
  r.state = 'x'.repeat(65536 - Buffer.byteLength(JSON.stringify(r)));
  assert.equal(Buffer.byteLength(JSON.stringify(r)), 65536);
  assert.equal((await f.consumer.evaluate(r)).state, 'complete');
  r.state += 'x'; assert.equal((await f.consumer.evaluate(r)).state, 'payload-limit'); assert.equal(f.calls(), 1);
});
test('eight questions and sixteen declared choices are admitted; larger batches are refused', t => {
  const f = fixture(t), r = request(f.config);
  const choices = Object.fromEntries(Array.from({ length: 16 }, (_, i) => [`c${i}`, `Choice ${i}`]));
  r.questions = Array.from({ length: 8 }, (_, i) => ({ ...questions()[1], id: `q${i}`, choices, unknownChoice: 'c15' }));
  assert.equal(Object.keys(prepareEvaluation(r, f.config).payload.questions).length, 8);
  r.questions.push({ ...r.questions[0], id: 'q8' }); assert.throws(() => prepareEvaluation(r, f.config), { code: 'question-limit' });
  r.questions.pop(); r.questions[0].choices.c16 = 'Seventeenth';
  assert.throws(() => prepareEvaluation(r, f.config), { code: 'choice-limit' });
});

test('tighter host ceilings apply and cannot expand accepted limits', async t => {
  const f = fixture(t, { ceilings: { questions: 2, bytes: 2048, timeoutMs: 10 } });
  assert.equal((await f.consumer.evaluate(request(f.config))).state, 'question-limit'); assert.equal(f.calls(), 0);
  assert.throws(() => new Judgments({ ceilings: { bytes: 65537 } }), { code: 'invalid-setup' });
});
for (const permission of [false, undefined, 'true', Promise.resolve(true)]) {
  test(`only current synchronous true disclosure authorization permits egress (${typeof permission})`, async t => {
    const f = fixture(t), consumer = f.engine.consumer('screen-v1', () => permission);
    assert.equal((await consumer.evaluate(request(f.config))).state, 'disclosure-denied');
    assert.equal(f.calls(), 0); assert.equal(f.reads(), 0);
  });
}
test('fixed-synthetic setup, stale config, expired pricing and unsupported host refuse supplied state', async t => {
  const f = fixture(t);
  f.config = config({ disclosure: 'fixed-synthetic-only' });
  assert.equal((await f.consumer.evaluate(request(f.config))).state, 'disclosure-denied');
  f.config = config(); const stale = request(f.config); f.config = config();
  assert.equal((await f.consumer.evaluate(stale)).state, 'stale-configuration');
  f.config = config({ priceConfirmedAt: 0 });
  assert.equal((await f.consumer.evaluate(request(f.config))).state, 'price-unqualified');
  const other = fixture(t, { platform: 'linux' });
  assert.equal((await other.consumer.evaluate(request(other.config))).state, 'unsupported-host'); assert.equal(other.calls(), 0);
  assert.equal(f.calls(), 0); assert.equal(f.reads(), 0);
});

test('permission and configuration are rechecked after credential resolution, before dispatch', async t => {
  for (const change of ['authority', 'configuration']) {
    const pending = deferred(); let allowed = true, dispatches = 0;
    const f = fixture(t, { credentials: { read: () => pending.promise },
      transport: { submit: async () => { dispatches++; return { status: 200, body: response() }; } } });
    const consumer = f.engine.consumer('screen-v1', () => allowed), result = consumer.evaluate(request(f.config));
    await tick(); if (change === 'authority') allowed = false; else f.config = config();
    const key = Buffer.from('synthetic-not-real'); pending.resolve(key);
    assert.equal((await result).state, change === 'authority' ? 'disclosure-denied' : 'stale-configuration');
    assert.equal(dispatches, 0); assert.equal(key.every(v => v === 0), true); f.budget.reserve(20000, 20000);
  }
});
test('state is snapshotted before asynchronous credential resolution', async t => {
  const pending = deferred(); let received;
  const f = fixture(t, { credentials: { read: () => pending.promise }, transport: { submit: async (_e, p) => {
    received = p.state; return { status: 200, body: response() };
  } } });
  const r = request(f.config), result = f.consumer.evaluate(r); r.state.statement = 'changed'; r.questions[0].id = 'changed';
  pending.resolve(Buffer.from('synthetic-not-real'));
  assert.equal((await result).state, 'complete'); assert.equal(received.statement, 'The synthetic kettle is blue and warm.');
});

for (const [status, expected] of [[401, 'provider-denied'], [402, 'billing'], [422, 'provider-rejected'], [503, 'provider-unavailable'], [504, 'timeout']]) {
  test(`HTTP ${status} stays ${expected} without retry or raw error disclosure`, async t => {
    let calls = 0;
    const f = fixture(t, { transport: { submit: async () => { calls++; return { status, body: { secret: 'PRIVATE' } }; } } });
    const result = await f.consumer.evaluate(request(f.config));
    assert.equal(result.state, expected); assert.equal(result.receipt.reservationRetained, true);
    assert.equal(result.answers, null); assert.equal(calls, 1); assert.equal(JSON.stringify(result).includes('PRIVATE'), false);
  });
}
for (const [body, expected] of [[null, 'absent-result'], [{ refusal: true }, 'refusal']]) {
  test(`${expected} is explicit and retains uncertain billing`, async t => {
    const f = fixture(t, { transport: { submit: async () => ({ status: 200, body }) } });
    const result = await f.consumer.evaluate(request(f.config));
    assert.equal(result.state, expected); assert.equal(result.answers, null); assert.equal(result.receipt.reservationRetained, true);
  });
}
test('credential unavailability releases unspent funds and raw failures are opaque', async t => {
  const f = fixture(t, { credentials: { read: async () => { throw new Unavailable('credential-unavailable'); } } });
  assert.equal((await f.consumer.evaluate(request(f.config))).state, 'credential-unavailable');
  assert.equal(f.calls(), 0); f.budget.reserve(20000, 20000);
  const other = fixture(t, { transport: { submit: async () => { throw new Error('PRIVATE'); } } });
  const result = await other.consumer.evaluate(request(other.config));
  assert.equal(result.state, 'provider-unavailable'); assert.equal(JSON.stringify(result).includes('PRIVATE'), false);
});

test('request and session spend ceilings are explicit; concurrent reservations prevent overspending', async t => {
  const pending = deferred();
  const f = fixture(t, { transport: { submit: () => pending.promise } });
  const r = request(f.config, { requestMicroUsd: 2687 });
  assert.equal((await f.consumer.evaluate(r)).state, 'request-budget'); assert.equal(f.reads(), 0);
  r.requestMicroUsd = 3001; assert.equal((await f.consumer.evaluate(r)).state, 'request-budget');
  r.requestMicroUsd = 2688; r.sessionMicroUsd = 5375;
  const first = f.consumer.evaluate(r); await tick();
  assert.equal((await f.consumer.evaluate(r)).state, 'session-budget');
  pending.resolve({ status: 503 }); await first;
  assert.equal((await f.consumer.evaluate(r)).state, 'session-budget');
});
test('known usage settles once with round-up, invalid answers still account reported cost', async t => {
  const b = response(); delete b.answers.color;
  const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
  const result = await f.consumer.evaluate(request(f.config));
  assert.equal(result.state, 'invalid-result'); assert.equal(result.receipt.reservationRetained, false);
  // $0.0000021 rounds up to 3 micro-USD, so exactly 19997 remains, not 19998 or 17312.
  f.budget.reserve(19997, 20000); assert.throws(() => f.budget.reserve(1, 20000), { code: 'session-budget' });
});
test('unknown cost is not zero; reservations survive new budget readers', async t => {
  const b = response(); delete b.usage.cost;
  const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
  const result = await f.consumer.evaluate(request(f.config));
  assert.equal(result.state, 'complete'); assert.equal(result.receipt.usage.costUsd, null); assert.equal(result.receipt.reservationRetained, true);
  assert.throws(() => f.budget.reserve(17313, 20000), { code: 'session-budget' });
});
test('untrustworthy usage cannot release a conservative reservation', async t => {
  for (const cost of [-1, NaN, Infinity, Number.MAX_VALUE]) {
    const b = response(); b.usage.cost = cost;
    const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
    const result = await f.consumer.evaluate(request(f.config));
    assert.equal(result.state, 'invalid-result'); assert.equal(result.receipt.usage, null); assert.equal(result.receipt.reservationRetained, true);
  }
});
test('a violated price bound settles the actual charge and stops further admission', async t => {
  const b = response(); b.usage.cost = 0.003;
  const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: b }) } });
  assert.equal((await f.consumer.evaluate(request(f.config))).state, 'price-bound-exceeded');
  assert.equal((await f.consumer.evaluate(request(f.config))).state, 'price-bound-exceeded');
  assert.equal(f.reads(), 1);
});

test('two concurrent requests across consumers and readiness share session admission', async t => {
  const pending = deferred();
  const f = fixture(t, { transport: { submit: () => pending.promise } });
  const readiness = new Readiness({ credentials: f.credentials, transport: { submit: () => pending.promise },
    budget: f.budget, scope: f.scope, platform: 'darwin' });
  readiness.configure(f.config); t.after(() => readiness.close());
  const first = f.consumer.evaluate(request(f.config)), second = readiness.check(); await tick();
  const other = f.engine.consumer('screen-v1', () => true);
  assert.equal((await other.evaluate(request(f.config))).state, 'concurrency-limit');
  pending.resolve({ status: 503 }); await Promise.all([first, second]);
});
test('cancellation retains ambiguous charge, aborts transport, and late completion cannot double count', async t => {
  const pending = deferred(), controller = new AbortController(); let signal;
  const f = fixture(t, { transport: { submit: (_e, _p, _k, s) => { signal = s; return pending.promise; } } });
  const first = f.consumer.evaluate(request(f.config), { signal: controller.signal }); await tick(); controller.abort();
  const result = await first; assert.equal(result.state, 'cancelled'); assert.equal(signal.aborted, true);
  assert.equal(result.receipt.reservationRetained, true);
  pending.resolve({ status: 200, body: response() }); await tick();
  assert.equal(f.consumer.inspect()[0].state, 'cancelled');
  f.budget.reserve(17312, 20000); assert.throws(() => f.budget.reserve(1, 20000), { code: 'session-budget' });
});
test('already cancelled requests never resolve authentication or dispatch', async t => {
  const f = fixture(t), controller = new AbortController(); controller.abort();
  assert.equal((await f.consumer.evaluate(request(f.config), { signal: controller.signal })).state, 'cancelled');
  assert.equal(f.reads(), 0); assert.equal(f.calls(), 0);
});
test('timeout retains slots for an uncooperative source; late credentials never dispatch', async t => {
  const pending = deferred(); let calls = 0;
  const f = fixture(t, { ceilings: { timeoutMs: 10 }, credentials: { read: () => pending.promise },
    transport: { submit: async () => { calls++; return { status: 200, body: response() }; } } });
  const a = f.consumer.evaluate(request(f.config)), b = f.consumer.evaluate(request(f.config));
  assert.deepEqual((await Promise.all([a, b])).map(r => r.state), ['timeout', 'timeout']);
  assert.equal((await f.consumer.evaluate(request(f.config))).state, 'concurrency-limit');
  pending.resolve(Buffer.from('synthetic-not-real')); await tick(); assert.equal(calls, 0);
  f.budget.reserve(20000, 20000); // No dispatch; both reservations were released.
});
test('shutdown and config invalidation are idempotent and reject stale result delivery', async t => {
  for (const action of ['close', 'invalidate']) {
    const pending = deferred(); let signal;
    const f = fixture(t, { transport: { submit: (_e, _p, _k, s) => { signal = s; return pending.promise; } } });
    const result = f.consumer.evaluate(request(f.config)); await tick(); f.engine[action](); f.engine[action]();
    assert.equal((await result).state, 'cancelled'); assert.equal(signal.aborted, true);
    pending.resolve({ status: 200, body: response() }); await tick();
    assert.equal(f.engine.inspect().some(r => r.state === 'complete'), false);
    if (action === 'close') assert.equal((await f.consumer.evaluate(request(f.config))).state, 'cancelled');
  }
});

test('reload retains occupied session slots while independent sessions remain independent', () => {
  const id = randomUUID(), a = retainSessionRequests(id), first = new AbortController(), second = new AbortController();
  a.scope.acquire(first); a.scope.acquire(second); a.release(); a.release();
  const b = retainSessionRequests(id), other = retainSessionRequests(randomUUID());
  assert.throws(() => b.scope.acquire(new AbortController()), { code: 'concurrency-limit' });
  const independent = new AbortController(); other.scope.acquire(independent); other.scope.release(independent); other.release();
  a.scope.release(first); a.scope.release(second); b.release();
});
test('receipts omit state, question/rubric prose, provider quotations and secrets; inspection is bounded and isolated', async t => {
  const f = fixture(t), r = request(f.config, { state: 'PRIVATE SOURCE BODY' }); r.questions[0].instructions = 'PRIVATE QUESTION';
  for (let i = 0; i < 35; i++) await f.consumer.evaluate(r);
  const receipts = f.consumer.inspect(32); assert.equal(receipts.length, 32);
  const serialized = JSON.stringify(receipts);
  for (const forbidden of ['PRIVATE SOURCE BODY', 'PRIVATE QUESTION', 'Cold', 'synthetic-not-a-real-key']) assert.equal(serialized.includes(forbidden), false);
  assert.equal(f.consumer.inspect().length, 8); assert.throws(() => f.consumer.inspect(33), { code: 'invalid-input' });
  receipts[0].source.revision = 'tampered'; assert.equal(f.consumer.inspect(32)[0].source.revision, 'source-v2');
  assert.equal(f.engine.consumer('other-v1', () => true).inspect(32).length, 0);
});
test('duplicate escaped identities, invalid JSON and excessive response nesting cannot qualify a batch', async () => {
  for (const raw of ['{"answers":{"blue":{},"bl\\u0075e":{}}}', '{"answers":', '['.repeat(34) + '0' + ']'.repeat(34)]) {
    const transport = new DecisionsTransport(async () => new Response(raw));
    await assert.rejects(transport.submit(connection.endpoint, {}, Buffer.from('synthetic-not-real'), new AbortController().signal), { code: 'invalid-result' });
  }
});
test('authorization binds session, exact questions, source and explicit allocation without disclosing prose', async t => {
  const f = fixture(t, { sessionId: 'synthetic-session-id' }); let admitted;
  const consumer = f.engine.consumer('screen-v1', context => { admitted = context; return true; });
  const result = await consumer.evaluate(request(f.config));
  assert.equal(result.state, 'complete'); assert.equal(admitted.sessionId, 'synthetic-session-id');
  assert.equal(admitted.requestMicroUsd, 3000); assert.equal(admitted.sessionMicroUsd, 20000);
  assert.match(admitted.requestDigest, /^[0-9a-f]{64}$/); assert.match(admitted.questionDigest, /^[0-9a-f]{64}$/);
  assert.equal(admitted.requestDigest, result.receipt.requestDigest); assert.equal(admitted.source.digest, result.receipt.source.digest);
  assert.equal(JSON.stringify(admitted).includes('Is the synthetic kettle blue?'), false);
});
test('authorizer exceptions and rejected async checks deny egress without raw errors', async t => {
  const f = fixture(t);
  for (const authorize of [() => { throw new Error('PRIVATE'); }, () => Promise.reject(new Error('PRIVATE'))]) {
    const result = await f.engine.consumer('screen-v1', authorize).evaluate(request(f.config));
    assert.equal(result.state, 'disclosure-denied'); assert.equal(JSON.stringify(result).includes('PRIVATE'), false);
  }
  await tick(); assert.equal(f.calls(), 0); assert.equal(f.reads(), 0);
});
test('settlement cannot double count an already reconciled ticket', t => {
  const f = fixture(t), ticket = f.budget.reserve(2688, 20000);
  f.budget.settle(ticket, 3);
  assert.throws(() => f.budget.settle(ticket, 3), { code: 'budget-unavailable' });
  f.budget.reserve(19997, 20000);
});
test('ledger failure prevents disclosure and a reconciliation failure cannot claim success', async t => {
  const f = fixture(t, { budget: { reserve() { throw new Unavailable('budget-unavailable'); } } });
  assert.equal((await f.consumer.evaluate(request(f.config))).state, 'budget-unavailable'); assert.equal(f.reads(), 0);
  const other = fixture(t, { budget: { reserve() { return 'synthetic-ticket'; }, settle() { throw new Unavailable('budget-unavailable'); } } });
  const result = await other.consumer.evaluate(request(other.config));
  assert.equal(result.state, 'budget-unavailable'); assert.equal(result.answers, null); assert.equal(result.receipt.reservationRetained, true);
});
test('permission revocation and configuration drift during dispatch discard answers and retain uncertain billing', async t => {
  for (const change of ['authority', 'configuration']) {
    const pending = deferred(); let allowed = true;
    const f = fixture(t, { transport: { submit: () => pending.promise } });
    const result = f.engine.consumer('screen-v1', () => allowed).evaluate(request(f.config)); await tick();
    if (change === 'authority') allowed = false; else f.config = config();
    pending.resolve({ status: 200, body: response() });
    const outcome = await result;
    assert.equal(outcome.state, change === 'authority' ? 'disclosure-denied' : 'stale-configuration');
    assert.equal(outcome.answers, null); assert.equal(outcome.receipt.reservationRetained, true);
  }
});
test('configuration drift queued at completion cannot escape the final delivery check', async t => {
  const f = fixture(t); let checks = 0;
  const consumer = f.engine.consumer('screen-v1', () => {
    if (++checks === 3) queueMicrotask(() => { f.config = config(); });
    return true;
  });
  const outcome = await consumer.evaluate(request(f.config));
  assert.equal(outcome.state, 'stale-configuration'); assert.equal(outcome.answers, null);
});
test('timeout propagates to transport, retains billing and never publishes a late result', async t => {
  const pending = deferred(); let signal;
  const f = fixture(t, { ceilings: { timeoutMs: 10 }, transport: { submit: (_e, _p, _k, s) => { signal = s; return pending.promise; } } });
  const outcome = await f.consumer.evaluate(request(f.config));
  assert.equal(outcome.state, 'timeout'); assert.equal(signal.aborted, true); assert.equal(outcome.receipt.reservationRetained, true);
  pending.resolve({ status: 200, body: response() }); await tick(); assert.equal(f.consumer.inspect()[0].state, 'timeout');
});
test('direct parser never treats an absent or refused result as a No', () => {
  const c = config(), r = request(c);
  assert.throws(() => parseEvaluation(null, r, c), { code: 'absent-result' });
  assert.throws(() => parseEvaluation({ refusal: true }, r, c), { code: 'refusal' });
});
