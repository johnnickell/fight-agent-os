import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, chmodSync, writeFileSync, readFileSync, rmSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { Readiness, Unavailable, validateConfiguration, parseResult, readinessPayload, connection } from '../src/jev/readiness.mjs';
import { LocalSettings, SessionBudget } from '../src/jev/local-store.mjs';
import { DecisionsTransport, PiCredentials } from '../src/jev/adapters.mjs';

const config = (changes = {}) => ({ endpoint: 'https://openrouter.ai/api/alpha/decisions', model: 'typesafe/jev-1.13',
  evaluator: 'typesafe/jev-1.13-20260917', price: 'jev-1.13-usd-0.042-per-million-input-free-output',
  disclosure: 'fixed-synthetic-only', requestMicroUsd: 3000, sessionMicroUsd: 10000,
  priceConfirmedAt: Date.now(), revision: randomUUID(), ...changes });
const body = (changes = {}) => ({ model: 'typesafe/jev-1.13-20260917', provider: 'TypeSafe',
  answers: { kettle_blue: { type: 'noul', noul: 0.99 } }, usage: { input_tokens: 40, output_tokens: 2, cost: 0.00000168 }, ...changes });
function fixture(t, options = {}) {
  const directory = mkdtempSync(join(tmpdir(), 'fight-jev-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  let calls = 0;
  const budget = new SessionBudget('synthetic-session', directory);
  const readiness = new Readiness({ platform: 'darwin', budget,
    credentials: { read: async () => Buffer.from('synthetic-not-a-real-key') },
    transport: { submit: async () => { calls++; return { status: 200, body: body() }; } }, ...options });
  readiness.configure(config());
  t.after(() => readiness.close());
  return { readiness, directory, budget, calls: () => calls };
}
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return { promise, resolve }; };

// Expected outbound input is derived from TASK's fixed synthetic disclosure, not from the main Agent state.
test('fixed payload sends one proposition, no private source, fixed provider and price ceilings', () => {
  assert.deepEqual(readinessPayload(config()), { model: 'typesafe/jev-1.13',
    provider: { allow_fallbacks: false, only: ['TypeSafe'], max_price: { prompt: '0.042', completion: '0' } },
    state: { statement: 'The synthetic kettle is blue.' },
    questions: { kettle_blue: { type: 'noul', instructions: 'Does `statement` say that the synthetic kettle is blue?' } } });
});
for (const [name, changes] of Object.entries({ endpoint: { endpoint: 'https://evil.test' }, latest: { model: '~typesafe/jev-latest' },
  evaluator: { evaluator: 'typesafe/jev-next' }, disclosure: { disclosure: 'repository' }, unknownCost: { price: null },
  missingCeiling: { sessionMicroUsd: undefined }, fractional: { requestMicroUsd: 0.5 }, negative: { requestMicroUsd: -1 },
  exceedingSession: { requestMicroUsd: 10001 }, secretField: { apiKey: 'synthetic-not-real' }, duplicateCredentialStore: { credentialAccount: 'synthetic-account' } })) {
  test(`configuration rejects ${name} without including input in failure`, () => {
    assert.throws(() => validateConfiguration(config(changes)), error => error.code === 'invalid-setup' && error.message === 'invalid-setup');
  });
}
test('price confirmation has no age limit for unchanged pins and retains its original identity', () => {
  const confirmed = Date.UTC(2020, 0, 1), value = config({ priceConfirmedAt: confirmed });
  for (const elapsed of [0, 86400000, 86400001, 10 * 366 * 86400000]) {
    assert.deepEqual(validateConfiguration(value, confirmed + elapsed), value);
  }
});
test('invalid or future confirmation timestamps still fail closed', () => {
  const now = Date.now();
  for (const stamp of [-1, now + 1]) {
    assert.throws(() => validateConfiguration(config({ priceConfirmedAt: stamp }), now), { code: 'price-unqualified' });
  }
  for (const stamp of [undefined, null, '0', NaN, Infinity, 0.5]) {
    assert.throws(() => validateConfiguration(config({ priceConfirmedAt: stamp }), now), { code: 'invalid-setup' });
  }
});
test('old protected settings reload unchanged and readiness does not require renewal', async t => {
  const f = fixture(t), value = config({ priceConfirmedAt: Date.UTC(2020, 0, 1) });
  // Simulate an existing installation, without saving/migrating through the current validator.
  const path = join(f.directory, 'connection.json');
  const original = JSON.stringify(value);
  writeFileSync(path, original, { mode: 0o600 });
  const loaded = new LocalSettings(f.directory).load();
  assert.deepEqual(loaded, value);
  f.readiness.configure(loaded);
  const before = f.readiness.inspect().configuration;
  assert.equal((await f.readiness.check()).state, 'ready');
  f.readiness.invalidate();
  f.readiness.configure(new LocalSettings(f.directory).load());
  assert.deepEqual(f.readiness.inspect().configuration, before);
  assert.equal((await f.readiness.check()).state, 'ready');
  assert.equal(readFileSync(path, 'utf8'), original);
  assert.equal(f.calls(), 2);
});
test('repeating identical setup preserves connection identity and stored bytes despite new confirmation metadata', t => {
  const f = fixture(t), store = new LocalSettings(f.directory);
  const original = store.save(config({ priceConfirmedAt: 0 }));
  const before = readFileSync(join(f.directory, 'connection.json'));
  // Setup supplies a fresh UUID/time; reordered fields must not turn equal choices into new authority.
  const repeated = Object.fromEntries(Object.entries({ ...original, revision: randomUUID(), priceConfirmedAt: Date.now() }).reverse());
  assert.deepEqual(store.save(repeated), original);
  assert.deepEqual(readFileSync(join(f.directory, 'connection.json')), before);
  for (const change of [{ disclosure: 'authorized-supplied-state' }, { requestMicroUsd: 4000 }, { sessionMicroUsd: 12000 }]) {
    const next = { ...store.load(), ...change, revision: randomUUID() };
    assert.deepEqual(store.save(next), next);
  }
});
test('explicit setup can repair invalid stored pins while unsafe storage remains rejected', t => {
  const f = fixture(t), store = new LocalSettings(f.directory), value = config();
  store.save(value);
  writeFileSync(join(f.directory, 'connection.json'), JSON.stringify({ ...value, model: 'obsolete-model' }));
  assert.deepEqual(store.save(value), value);
  chmodSync(join(f.directory, 'connection.json'), 0o644);
  assert.throws(() => store.save(value), { code: 'invalid-setup' });
});
test('success is configuration-bound, usage is measured and credentials are absent from inspection', async t => {
  const f = fixture(t); const result = await f.readiness.check();
  assert.equal(result.state, 'ready'); assert.equal(result.yesProbability, 0.99);
  assert.deepEqual(result.usage, { inputTokens: 40, outputTokens: 2, costUsd: 0.00000168 });
  assert.equal(result.reservationRetained, false); assert.equal(f.calls(), 1);
  assert.equal(f.readiness.inspect().state, 'ready');
  assert.equal(JSON.stringify(f.readiness.inspect()).includes('synthetic-not-a-real-key'), false);
  f.readiness.configure(config()); assert.equal(f.readiness.inspect().state, 'unchecked');
});
for (const [name, response] of Object.entries({ missing: body({ answers: {} }), absentProbability: body({ answers: { kettle_blue: { type: 'noul' } } }),
  wrongType: body({ answers: { kettle_blue: { type: 'choice', noul: 1 } } }),
  nan: body({ answers: { kettle_blue: { type: 'noul', noul: NaN } } }),
  string: body({ answers: { kettle_blue: { type: 'noul', noul: '0.99' } } }),
  range: body({ answers: { kettle_blue: { type: 'noul', noul: 1.01 } } }),
  extra: body({ answers: { kettle_blue: { type: 'noul', noul: 0.99 }, other: {} } }),
  modelDrift: body({ model: 'typesafe/jev-1.14' }), providerDrift: body({ provider: 'Other' }),
  negativeCost: body({ usage: { input_tokens: 40, output_tokens: 2, cost: -1 } }),
  infiniteCost: body({ usage: { input_tokens: 40, output_tokens: 2, cost: Infinity } }),
  missingTokens: body({ usage: {} }), tooManyTokens: body({ usage: { input_tokens: 64001, output_tokens: 0 } }) })) {
  test(`malformed ${name} never becomes ready and retains uncertain reservation`, async t => {
    const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: response }) } });
    assert.equal((await f.readiness.check()).state, 'invalid-result');
    assert.equal(f.readiness.inspect().state, 'invalid-result');
    assert.throws(() => new SessionBudget('synthetic-session', f.directory).reserve(8000, 10000), { code: 'session-budget' });
  });
}
test('missing usage cost is unknown, not zero; reservation survives reload', async t => {
  const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: body({ usage: { input_tokens: 40, output_tokens: 2 } }) }) } });
  const result = await f.readiness.check(); assert.equal(result.usage.costUsd, null); assert.equal(result.reservationRetained, true);
  assert.throws(() => new SessionBudget('synthetic-session', f.directory).reserve(8000, 10000), { code: 'session-budget' });
});
for (const [status, state] of [[401, 'provider-denied'], [403, 'provider-denied'], [402, 'billing'], [422, 'provider-rejected'],
  [524, 'timeout'], [503, 'provider-unavailable'], [429, 'provider-unavailable'], [400, 'provider-rejected']]) {
  test(`HTTP ${status} is ${state}, never raw upstream data or retried`, async t => {
    let calls = 0;
    const f = fixture(t, { transport: { submit: async () => { calls++; return { status, body: { secret: 'DO-NOT-RENDER' } }; } } });
    const outcome = await f.readiness.check(); assert.equal(outcome.state, state); assert.equal(calls, 1);
    assert.equal(JSON.stringify(outcome).includes('DO-NOT-RENDER'), false); assert.equal(outcome.reservationRetained, true);
  });
}
for (const state of ['credential-unavailable']) {
  test(`${state} prevents dispatch and releases unspent reservation`, async t => {
    const f = fixture(t, { credentials: { read: async () => { throw new Unavailable(state); } } });
    assert.equal((await f.readiness.check()).state, state); assert.equal(f.calls(), 0);
    f.budget.reserve(10000, 10000);
  });
}
test('untrusted exceptions are never rendered', async t => {
  const f = fixture(t, { transport: { submit: async () => { throw new Error('DO-NOT-RENDER'); } } });
  assert.equal((await f.readiness.check()).state, 'provider-unavailable');
  assert.equal(JSON.stringify(f.readiness.inspect()).includes('DO-NOT-RENDER'), false);
});
test('request and repeated session ceilings reject before retrieving credentials', async t => {
  let reads = 0;
  const f = fixture(t, { credentials: { read: async () => { reads++; return Buffer.from('synthetic-not-a-real-key'); } },
    transport: { submit: async () => ({ status: 503 }) } });
  f.readiness.configure(config({ requestMicroUsd: 2687 }));
  assert.equal((await f.readiness.check()).state, 'request-budget'); assert.equal(reads, 0);
  f.readiness.configure(config({ requestMicroUsd: 2688, sessionMicroUsd: 5376 }));
  await f.readiness.check(); await f.readiness.check();
  assert.equal((await f.readiness.check()).state, 'session-budget'); assert.equal(reads, 2);
});
test('a reported price breach charges actual cost and stops readiness', async t => {
  const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: body({ usage: { input_tokens: 40, output_tokens: 2, cost: 0.02 } }) }) } });
  assert.equal((await f.readiness.check()).state, 'price-bound-exceeded');
  assert.throws(() => f.budget.reserve(1, 10000), { code: 'budget-unavailable' });
});
test('a price-bound violation blocks further session admission even after reload with ample remaining budget', async t => {
  const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: body({ usage: { input_tokens: 40, output_tokens: 2, cost: 0.003 } }) }) } });
  assert.equal((await f.readiness.check()).state, 'price-bound-exceeded');
  assert.throws(() => new SessionBudget('synthetic-session', f.directory).reserve(2688, 100000), { code: 'budget-unavailable' });
});
test('synthetic No remains a negative, not ready or a fabricated Yes', async t => {
  const f = fixture(t, { transport: { submit: async () => ({ status: 200, body: body({ answers: { kettle_blue: { type: 'noul', noul: 0 } } }) }) } });
  assert.equal((await f.readiness.check()).state, 'synthetic-negative');
});
test('explicit refusal never becomes a judgment', () => {
  assert.throws(() => parseResult({ refusal: true }, config()), { code: 'refusal' });
});
test('unsupported host and missing setup cause no transport effect', async t => {
  const f = fixture(t, { platform: 'linux' }); assert.equal((await f.readiness.check()).state, 'unsupported-host');
  const other = fixture(t); other.readiness.invalidate(); assert.equal((await other.readiness.check()).state, 'missing-setup');
  assert.equal(other.calls(), 0);
});
test('at most two outstanding checks; older completion cannot replace newer outcome', async t => {
  const first = deferred(), second = deferred(); let calls = 0;
  const f = fixture(t, { transport: { submit: async () => (++calls === 1 ? first.promise : second.promise) } });
  const a = f.readiness.check(), b = f.readiness.check();
  assert.equal((await f.readiness.check()).state, 'concurrency-limit');
  second.resolve({ status: 402 }); await b;
  first.resolve({ status: 200, body: body() }); await a;
  assert.equal(f.readiness.inspect().state, 'billing'); assert.equal(calls, 2);
});
test('timeout is bounded even if a collaborator ignores cancellation; no later ready result', async t => {
  const pending = deferred(); let signal;
  const f = fixture(t, { timeoutMs: 10, transport: { submit: async (_e, _p, _c, s) => { signal = s; return pending.promise; } } });
  const result = await f.readiness.check(); assert.equal(result.state, 'timeout'); assert.equal(signal.aborted, true);
  pending.resolve({ status: 200, body: body() }); await new Promise(r => setImmediate(r));
  assert.equal(f.readiness.inspect().state, 'timeout'); assert.equal(result.reservationRetained, true);
});
test('config replacement and idempotent shutdown cancel outstanding work and discard stale completion', async t => {
  for (const shutdown of [false, true]) {
    const pending = deferred(); let signal;
    const f = fixture(t, { transport: { submit: async (_e, _p, _c, s) => { signal = s; return pending.promise; } } });
    const check = f.readiness.check(); await new Promise(r => setImmediate(r));
    if (shutdown) { f.readiness.close(); f.readiness.close(); } else f.readiness.configure(config());
    assert.equal(signal.aborted, true); assert.equal((await check).state, 'cancelled');
    pending.resolve({ status: 200, body: body() }); await new Promise(r => setImmediate(r));
    assert.equal(f.readiness.inspect().state, shutdown ? 'closed' : 'unchecked');
  }
});
test('local settings require private ownership, reject symlinks, and store no credentials', t => {
  const f = fixture(t), store = new LocalSettings(f.directory), value = config();
  assert.equal(store.load(), null); store.save(value); assert.deepEqual(store.load(), value);
  assert.equal(readFileSync(join(f.directory, 'connection.json'), 'utf8').includes('synthetic-not-a-real-key'), false);
  chmodSync(join(f.directory, 'connection.json'), 0o644); assert.throws(() => store.load(), { code: 'invalid-setup' });
  rmSync(join(f.directory, 'connection.json')); symlinkSync(join(f.directory, 'target'), join(f.directory, 'connection.json'));
  assert.throws(() => store.load(), { code: 'invalid-setup' });
});
test('ledger corruption and contention fail closed rather than resetting charges', t => {
  const f = fixture(t);
  writeFileSync(f.budget.path, '{broken', { mode: 0o600 });
  assert.throws(() => f.budget.reserve(2688, 10000), { code: 'budget-unavailable' });
  rmSync(f.budget.path); writeFileSync(f.budget.path + '.lock', '', { mode: 0o600 });
  assert.throws(() => f.budget.reserve(2688, 10000), { code: 'budget-unavailable' });
});
test('transport uses one secret-bearing HTTPS boundary, no redirects, and clears credential buffer', async () => {
  const credential = Buffer.from('synthetic-not-a-real-key'); let calls = 0;
  const transport = new DecisionsTransport(async (url, options) => {
    calls++; assert.equal(url, 'https://openrouter.ai/api/alpha/decisions');
    assert.equal(options.headers.Authorization, 'Bearer synthetic-not-a-real-key');
    assert.equal(options.redirect, 'error'); assert.equal(options.method, 'POST');
    return new Response(JSON.stringify(body()));
  });
  const result = await transport.submit(connection.endpoint, readinessPayload(config()), credential, new AbortController().signal);
  assert.equal(result.status, 200); assert.equal(calls, 1); assert.equal(credential.every(v => v === 0), true);
});
test('transport discards error bodies, bounds success bodies and rejects invalid JSON', async () => {
  for (const [response, expected] of [[new Response('DO-NOT-RENDER', { status: 402 }), 402],
    [new Response('x'.repeat(65537)), 'invalid-result'], [new Response('{not-json'), 'invalid-result']]) {
    const transport = new DecisionsTransport(async () => response);
    const check = transport.submit(connection.endpoint, readinessPayload(config()), Buffer.from('synthetic-not-real'), new AbortController().signal);
    if (typeof expected === 'number') assert.deepEqual(await check, { status: expected, body: null });
    else await assert.rejects(check, { code: expected });
  }
});
test('transport rejects duplicate answer identities instead of accepting the last JSON member', async () => {
  const raw = '{"model":"typesafe/jev-1.13-20260917","provider":"TypeSafe","answers":{"kettle_blue":{"type":"noul","noul":0},"kettle_blue":{"type":"noul","noul":1}},"usage":{"input_tokens":1,"output_tokens":1,"cost":0}}';
  const transport = new DecisionsTransport(async () => new Response(raw));
  await assert.rejects(transport.submit(connection.endpoint, readinessPayload(config()),
    Buffer.from('synthetic-not-real'), new AbortController().signal), { code: 'invalid-result' });
});
test('Pi credentials resolve only the injected OpenRouter source and never render opaque errors', async () => {
  for (const resolve of [async () => undefined, async () => { throw new Error('DO-NOT-RENDER'); },
    async () => 'short', async () => 'synthetic-key\nunsafe-header']) {
    const credentials = new PiCredentials({ getApiKeyForProvider: async provider => {
      assert.equal(provider, 'openrouter'); return resolve();
    } });
    await assert.rejects(credentials.read(new AbortController().signal), { code: 'credential-unavailable', message: 'credential-unavailable' });
  }
  const credentials = new PiCredentials({ getApiKeyForProvider: async () => 'synthetic-not-a-real-key' });
  const buffer = await credentials.read(new AbortController().signal);
  assert.equal(buffer.toString(), 'synthetic-not-a-real-key'); buffer.fill(0);
});
test('cancelled Pi credential resolution cannot return a key for dispatch', async () => {
  const controller = new AbortController(), pending = deferred(); let resolutions = 0;
  const credentials = new PiCredentials({ getApiKeyForProvider: () => { resolutions++; return pending.promise; } });
  const check = credentials.read(controller.signal); controller.abort();
  pending.resolve('synthetic-not-a-real-key'); await assert.rejects(check, { code: 'cancelled' });
  await assert.rejects(credentials.read(controller.signal), { code: 'cancelled' });
  assert.equal(resolutions, 1);
});
test('uncancellable credential resolution holds concurrency slots after timeout and never dispatches late', async t => {
  const pending = deferred(); let resolutions = 0;
  const credentials = new PiCredentials({ getApiKeyForProvider: () => { resolutions++; return pending.promise; } });
  const f = fixture(t, { credentials, timeoutMs: 10 });
  const outcomes = await Promise.all([f.readiness.check(), f.readiness.check()]);
  assert.deepEqual(outcomes.map(o => o.state), ['timeout', 'timeout']);
  assert.equal((await f.readiness.check()).state, 'concurrency-limit'); assert.equal(resolutions, 2);
  pending.resolve('synthetic-not-a-real-key'); await new Promise(r => setImmediate(r));
  assert.equal(f.calls(), 0); assert.equal(f.readiness.inspect().state, 'timeout');
  assert.equal((await f.readiness.check()).state, 'ready');
});
