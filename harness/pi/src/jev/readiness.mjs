import { createHash } from 'node:crypto';
import { SessionRequests } from './session-requests.mjs';

export const connection = Object.freeze({
  endpoint: 'https://openrouter.ai/api/alpha/decisions',
  model: 'typesafe/jev-1.13',
  evaluator: 'typesafe/jev-1.13-20260917',
  schema: 'openrouter-decisions-2026-10-14',
  price: 'jev-1.13-usd-0.042-per-million-input-free-output',
});
export const limits = Object.freeze({ bytes: 65536, questions: 8, choices: 16, concurrent: 2, timeoutMs: 15000, retries: 0 });
// Reserve the entire advertised 64,000-token context, not an unqualified token estimate.
export const reservationMicroUsd = 2688;
export const questionVersion = 'fight-jev-readiness-v1';
const positiveInteger = (v) => Number.isSafeInteger(v) && v > 0;
const object = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);

export class Unavailable extends Error {
  constructor(code) { super(code); this.code = code; }
}

export function validateConfiguration(value, now = Date.now()) {
  if (!object(value) || Object.keys(value).sort().join(',') !==
    'disclosure,endpoint,evaluator,model,price,priceConfirmedAt,requestMicroUsd,revision,sessionMicroUsd') {
    throw new Unavailable('invalid-setup');
  }
  if (value.endpoint !== connection.endpoint || value.model !== connection.model || value.evaluator !== connection.evaluator ||
    value.price !== connection.price || !['fixed-synthetic-only', 'authorized-supplied-state'].includes(value.disclosure) ||
    !/^[0-9a-f-]{36}$/.test(value.revision) ||
    !positiveInteger(value.requestMicroUsd) || !positiveInteger(value.sessionMicroUsd) ||
    value.requestMicroUsd > value.sessionMicroUsd || !Number.isSafeInteger(value.priceConfirmedAt)) {
    throw new Unavailable('invalid-setup');
  }
  if (value.priceConfirmedAt > now || now - value.priceConfirmedAt > 86400000) throw new Unavailable('price-unqualified');
  return Object.freeze({ ...value });
}

export function configurationIdentity(config) {
  return createHash('sha256').update(JSON.stringify(config)).digest('hex');
}

export function readinessPayload(config) {
  const payload = {
    model: config.model,
    provider: { allow_fallbacks: false, only: ['TypeSafe'], max_price: { prompt: '0.042', completion: '0' } },
    state: { statement: 'The synthetic kettle is blue.' },
    questions: { kettle_blue: { type: 'noul', instructions: 'Does `statement` say that the synthetic kettle is blue?' } },
  };
  // Fixed input is the only submission path in this slice. Future callers must enforce all shared ceilings.
  if (Buffer.byteLength(JSON.stringify(payload)) > limits.bytes || Object.keys(payload.questions).length > limits.questions) {
    throw new Unavailable('payload-limit');
  }
  return payload;
}

export function parseResult(body, config) {
  if (!object(body)) throw new Unavailable('invalid-result');
  if (body.refusal === true) throw new Unavailable('refusal');
  if (body.model !== config.evaluator || body.provider !== 'TypeSafe' || !object(body.answers) ||
    Object.keys(body.answers).join(',') !== 'kettle_blue') throw new Unavailable('invalid-result');
  const answer = body.answers.kettle_blue;
  if (answer?.type !== 'noul' || typeof answer.noul !== 'number' || !Number.isFinite(answer.noul) ||
    answer.noul < 0 || answer.noul > 1) throw new Unavailable('invalid-result');
  const usage = body.usage;
  if (!object(usage) || !Number.isSafeInteger(usage.input_tokens) || usage.input_tokens < 0 || usage.input_tokens > 64000 ||
    !Number.isSafeInteger(usage.output_tokens) || usage.output_tokens < 0 ||
    (usage.cost !== undefined && (typeof usage.cost !== 'number' || !Number.isFinite(usage.cost) || usage.cost < 0))) {
    throw new Unavailable('invalid-result');
  }
  return { yesProbability: answer.noul, usage: { inputTokens: usage.input_tokens, outputTokens: usage.output_tokens,
    costUsd: usage.cost ?? null } };
}

export function httpFailure(status) {
  if (status === 401 || status === 403) return 'provider-denied';
  if (status === 402) return 'billing';
  if ([408, 504, 524].includes(status)) return 'timeout';
  if ([400, 413, 422].includes(status)) return 'provider-rejected';
  return 'provider-unavailable';
}

/** Own configuration-bound readiness; collaborators never expose raw errors or credentials to presentation */
export class Readiness {
  #config;
  #identity;
  #generation = 0;
  #active = new Set();
  #outcome = null;
  #closed = false;
  #sequence = 0;
  constructor({ credentials, transport, budget, scope = new SessionRequests(), now = Date.now, timeoutMs = limits.timeoutMs, platform = process.platform }) {
    this.scope = scope;
    this.credentials = credentials; this.transport = transport; this.budget = budget; this.now = now;
    this.timeoutMs = Math.min(timeoutMs, limits.timeoutMs); this.platform = platform;
  }
  configure(value) {
    const config = validateConfiguration(value, this.now());
    const identity = configurationIdentity(config);
    if (identity !== this.#identity) { this.invalidate(); this.#config = config; this.#identity = identity; }
  }
  invalidate() {
    this.#generation++; this.#sequence++; this.#outcome = null; this.#config = undefined; this.#identity = undefined;
    for (const controller of this.#active) controller.abort();
  }
  close() { this.#closed = true; this.invalidate(); }
  inspect() {
    return { state: this.#closed ? 'closed' : this.platform !== 'darwin' ? 'unsupported-host' : !this.#config ? 'missing-setup' : this.#outcome?.state ?? 'unchecked',
      configuration: this.#config ? { revision: this.#config.revision, identity: this.#identity,
        endpoint: this.#config.endpoint, model: this.#config.model, evaluator: this.#config.evaluator,
        credentialStore: 'Pi-managed OpenRouter (resolution unchecked)', disclosure: this.#config.disclosure,
        requestMicroUsd: this.#config.requestMicroUsd, sessionMicroUsd: this.#config.sessionMicroUsd,
        reservationMicroUsd, price: this.#config.price, priceConfirmedAt: this.#config.priceConfirmedAt } : null,
      limits, outcome: this.#outcome };
  }
  async check() {
    const fail = (state) => ({ state, questionVersion, schema: connection.schema, elapsedMs: 0, usage: null });
    if (this.#closed) return fail('closed');
    if (this.platform !== 'darwin') return fail('unsupported-host');
    if (!this.#config) return fail('missing-setup');
    const config = this.#config, generation = this.#generation;
    const started = this.now(), controller = new AbortController();
    try { this.scope.acquire(controller); } catch { return fail('concurrency-limit'); }
    const sequence = ++this.#sequence;
    this.#active.add(controller);
    let timer, dispatched = false, ticket;
    // Race even a collaborator that ignores AbortSignal; stale completions cannot update readiness.
    const cancelled = new Promise((_, reject) => controller.signal.addEventListener('abort', () =>
      reject(new Unavailable(generation !== this.#generation || this.#closed ? 'cancelled' : 'timeout')), { once: true }));
    timer = setTimeout(() => controller.abort(), this.timeoutMs);
    const operation = async () => {
      validateConfiguration(config, this.now());
      if (config.requestMicroUsd < reservationMicroUsd) throw new Unavailable('request-budget');
      const payload = readinessPayload(config);
      ticket = this.budget.reserve(reservationMicroUsd, config.sessionMicroUsd);
      const credential = await this.credentials.read(controller.signal);
      try {
        if (controller.signal.aborted) throw new Unavailable('cancelled');
        this.budget.dispatch?.(ticket);
        dispatched = true;
        const response = await this.transport.submit(config.endpoint, payload, credential, controller.signal);
        if (controller.signal.aborted || generation !== this.#generation) throw new Unavailable('cancelled');
        if (response.status !== 200) throw new Unavailable(httpFailure(response.status));
        return parseResult(response.body, config);
      } finally { if (Buffer.isBuffer(credential)) credential.fill(0); }
    };
    // Keep the concurrency slot until the underlying collaborator settles, even if the caller times out.
    // An uncancellable credential command must not permit an unbounded series of overlapping resolutions.
    const running = operation().finally(() => { this.#active.delete(controller); this.scope.release(controller); });
    let outcome;
    try {
      const result = await Promise.race([running, cancelled]);
      const cost = result.usage.costUsd;
      if (cost !== null) {
        const microUsd = Math.ceil(cost * 1000000);
        this.budget.settle(ticket, microUsd);
        ticket = undefined;
        if (microUsd > reservationMicroUsd) throw new Unavailable('price-bound-exceeded');
      }
      outcome = { ...fail(result.yesProbability >= 0.70 ? 'ready' : 'synthetic-negative'), ...result };
    } catch (error) {
      const safeCodes = ['invalid-setup', 'price-unqualified', 'request-budget', 'session-budget', 'budget-unavailable',
        'credential-unavailable', 'provider-denied', 'monthly-budget',
        'billing', 'timeout', 'refusal', 'provider-rejected', 'provider-unavailable', 'invalid-result', 'cancelled', 'price-bound-exceeded'];
      outcome = fail(error instanceof Unavailable && safeCodes.includes(error.code) ? error.code : 'provider-unavailable');
    } finally {
      clearTimeout(timer);
      if (ticket !== undefined && !dispatched) {
        try { this.budget.settle(ticket, 0); } catch { outcome = fail('budget-unavailable'); }
      }
    }
    outcome = { ...outcome, elapsedMs: Math.max(0, this.now() - started), revision: config.revision,
      evaluator: config.evaluator, reservationRetained: ticket !== undefined && dispatched };
    if (generation !== this.#generation || this.#closed) return { ...outcome, state: 'cancelled' };
    if (sequence === this.#sequence) this.#outcome = outcome;
    return outcome;
  }
}
