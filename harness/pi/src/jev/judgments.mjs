import { createHash, randomUUID } from 'node:crypto';
import { connection, limits, reservationMicroUsd, configurationIdentity, validateConfiguration, httpFailure, Unavailable } from './readiness.mjs';
import { SessionRequests } from './session-requests.mjs';

const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const finite = value => typeof value === 'number' && Number.isFinite(value);
const probability = value => finite(value) && value >= 0 && value <= 1;
const identity = value => typeof value === 'string' && /^[A-Za-z0-9][A-Za-z0-9._:/-]{0,127}$/.test(value);
const positive = value => Number.isSafeInteger(value) && value > 0;
const reject = (code = 'invalid-input') => { throw new Unavailable(code); };
function keys(value, required, optional = []) {
  return object(value) && required.every(key => Object.hasOwn(value, key)) &&
    Object.keys(value).every(key => [...required, ...optional].includes(key));
}
function text(value) { return typeof value === 'string' && value.trim().length > 0; }

/** Accept only bounded, lossless JSON data, not accessors, custom serializers or executable objects */
function copyInput(input, byteLimit) {
  const seen = new Set(); let nodes = 0, stringBytes = 0;
  function visit(value, depth) {
    if (++nodes > byteLimit || depth > 32) reject();
    if (typeof value === 'string') { stringBytes += Buffer.byteLength(value); if (stringBytes > byteLimit) reject('payload-limit'); return; }
    if (value === null || typeof value === 'boolean' || finite(value)) return;
    if (typeof value !== 'object' || seen.has(value)) reject();
    if (!Array.isArray(value) && ![Object.prototype, null].includes(Object.getPrototypeOf(value))) reject();
    seen.add(value);
    const names = Reflect.ownKeys(value);
    if (names.length > byteLimit) reject('payload-limit');
    for (const name of names) {
      if (Array.isArray(value) && name === 'length') continue;
      if (typeof name !== 'string' || ['__proto__', 'constructor', 'prototype', 'toJSON'].includes(name) ||
        (Array.isArray(value) && (!/^(0|[1-9][0-9]*)$/.test(name) || Number(name) >= value.length))) reject();
      const descriptor = Object.getOwnPropertyDescriptor(value, name);
      if (!descriptor.enumerable || !Object.hasOwn(descriptor, 'value')) reject();
      stringBytes += Buffer.byteLength(name); if (stringBytes > byteLimit) reject('payload-limit');
      visit(descriptor.value, depth + 1);
    }
    if (Array.isArray(value) && names.length !== value.length + 1) reject();
    seen.delete(value);
  }
  visit(input, 0);
  const serialized = JSON.stringify(input);
  if (Buffer.byteLength(serialized) > byteLimit) reject('payload-limit');
  return JSON.parse(serialized);
}

/** Pin native score levels to zero-based indices and map their weighted average to the requested anchored scale */
export function prepareEvaluation(input, config, ceilings = limits) {
  const request = copyInput(input, ceilings.bytes);
  if (!keys(request, ['version', 'configuration', 'authorityRevision', 'policyVersion', 'source', 'state', 'questions',
    'requestMicroUsd', 'sessionMicroUsd']) || request.version !== 'fight-judgments-v1' ||
    !identity(request.authorityRevision) || !identity(request.policyVersion) ||
    !keys(request.source, ['id', 'revision']) || !identity(request.source.id) || !identity(request.source.revision) ||
    !(typeof request.state === 'string' || object(request.state) || Array.isArray(request.state)) ||
    !positive(request.requestMicroUsd) || !positive(request.sessionMicroUsd) || request.requestMicroUsd > request.sessionMicroUsd) reject();
  if (request.configuration !== configurationIdentity(config)) reject('stale-configuration');
  if (!Array.isArray(request.questions) || request.questions.length < 1 || request.questions.length > ceilings.questions) reject('question-limit');
  const questions = Object.create(null), ids = new Set();
  for (const q of request.questions) {
    if (!keys(q, ['id', 'version', 'rubricVersion', 'type', 'instructions'], ['choices', 'unknownChoice', 'levels', 'min', 'max']) ||
      !identity(q.id) || !identity(q.version) || !identity(q.rubricVersion) || !text(q.instructions) || ids.has(q.id) ||
      ['__proto__', 'constructor', 'prototype'].includes(q.id)) reject();
    ids.add(q.id);
    if (q.type === 'yes-no') {
      if (!keys(q, ['id', 'version', 'rubricVersion', 'type', 'instructions'])) reject();
      questions[q.id] = { type: 'noul', instructions: q.instructions };
    } else if (q.type === 'choice') {
      if (!keys(q, ['id', 'version', 'rubricVersion', 'type', 'instructions', 'choices', 'unknownChoice']) ||
        !object(q.choices) || Object.keys(q.choices).length < 2 || Object.keys(q.choices).length > ceilings.choices ||
        !Object.entries(q.choices).every(([id, description]) => identity(id) && text(description)) ||
        !identity(q.unknownChoice) || !Object.hasOwn(q.choices, q.unknownChoice)) reject('choice-limit');
      questions[q.id] = { type: 'choice', instructions: q.instructions, criteria: q.choices };
    } else if (q.type === 'score') {
      if (!keys(q, ['id', 'version', 'rubricVersion', 'type', 'instructions', 'levels', 'min', 'max']) ||
        !Array.isArray(q.levels) || q.levels.length < 2 || q.levels.length > 10 || !q.levels.every(text) ||
        !finite(q.min) || !finite(q.max) || q.min >= q.max || !finite(q.max - q.min)) reject();
      questions[q.id] = { type: 'score', instructions: q.instructions, criteria: q.levels };
    } else reject();
  }
  const payload = { model: config.model,
    provider: { allow_fallbacks: false, only: ['TypeSafe'], max_price: { prompt: '0.042', completion: '0' } },
    state: request.state, questions };
  if (Buffer.byteLength(JSON.stringify(payload)) > ceilings.bytes) reject('payload-limit');
  const sourceDigest = createHash('sha256').update(JSON.stringify(request.state)).digest('hex');
  const questionDigest = createHash('sha256').update(JSON.stringify(request.questions)).digest('hex');
  const requestDigest = createHash('sha256').update(JSON.stringify(payload)).digest('hex');
  return { request, payload, sourceDigest, questionDigest, requestDigest };
}

export function evaluationUsage(body, config) {
  if (!object(body) || body.model !== config.evaluator || body.provider !== 'TypeSafe') reject('invalid-result');
  const u = body.usage;
  if (!object(u) || !Number.isSafeInteger(u.input_tokens) || u.input_tokens < 0 || u.input_tokens > 64000 ||
    !Number.isSafeInteger(u.output_tokens) || u.output_tokens < 0 || u.output_tokens > 64000 ||
    (u.cost !== undefined && (!finite(u.cost) || u.cost < 0 || !Number.isSafeInteger(Math.ceil(u.cost * 1000000))))) reject('invalid-result');
  return { inputTokens: u.input_tokens, outputTokens: u.output_tokens, costUsd: u.cost ?? null };
}

function distribution(value, ids) {
  if (!object(value) || Object.keys(value).length !== ids.length || !ids.every(id => Object.hasOwn(value, id) && probability(value[id])) ||
    Math.abs(Object.values(value).reduce((sum, p) => sum + p, 0) - 1) > 0.000001) reject('invalid-result');
  return Object.fromEntries(ids.map(id => [id, value[id]]));
}

export function parseEvaluation(body, request, config) {
  if (body === null || body === undefined) reject('absent-result');
  if (body.refusal === true) reject('refusal'); // Controlled convention; live refusal schema is not qualified.
  evaluationUsage(body, config);
  if (!object(body.answers) || Object.keys(body.answers).length !== request.questions.length ||
    !request.questions.every(q => Object.hasOwn(body.answers, q.id))) reject('invalid-result');
  let incomplete = false;
  const answers = request.questions.map(q => {
    const a = body.answers[q.id], base = { id: q.id, version: q.version, rubricVersion: q.rubricVersion, type: q.type };
    if (!object(a)) reject('invalid-result');
    if (q.type === 'yes-no') {
      if (!keys(a, ['type', 'noul']) || a.type !== 'noul' || !probability(a.noul)) reject('invalid-result');
      return { ...base, status: 'answered', yesProbability: a.noul };
    }
    if (!probability(a.confidence)) reject('invalid-result');
    if (q.type === 'choice') {
      if (!keys(a, ['type', 'choice', 'confidence', 'probabilities']) || a.type !== 'choice' ||
        typeof a.choice !== 'string' || !Object.hasOwn(q.choices, a.choice)) reject('invalid-result');
      const probabilities = distribution(a.probabilities, Object.keys(q.choices));
      if (probabilities[a.choice] < Math.max(...Object.values(probabilities))) reject('invalid-result');
      const unknown = a.choice === q.unknownChoice;
      incomplete ||= unknown;
      return { ...base, status: unknown ? 'unknown' : 'answered', choice: a.choice, confidence: a.confidence, probabilities };
    }
    if (!keys(a, ['type', 'score', 'confidence', 'probabilities', 'legend']) || a.type !== 'score' ||
      !finite(a.score) || a.score < 0 || a.score > q.levels.length - 1) reject('invalid-result');
    const ids = q.levels.map((_, i) => String(i)), probabilities = distribution(a.probabilities, ids);
    if (!object(a.legend) || Object.keys(a.legend).length !== ids.length || !ids.every(id => a.legend[id] === q.levels[Number(id)])) reject('invalid-result');
    const mean = ids.reduce((sum, id) => sum + Number(id) * probabilities[id], 0);
    if (Math.abs(mean - a.score) > 0.000001) reject('invalid-result');
    const fraction = a.score / (q.levels.length - 1), span = q.max - q.min;
    // Interpolate from the nearer anchor to preserve endpoints and reduce cancellation.
    const mapped = fraction <= 0.5 ? q.min + fraction * span : q.max - (1 - fraction) * span;
    if (!finite(mapped)) reject('invalid-result');
    // Native bounds/distribution are already validated; contain only mapping roundoff.
    const score = Math.max(q.min, Math.min(q.max, mapped));
    return { ...base, status: 'answered', score, min: q.min, max: q.max, providerIndex: a.score, confidence: a.confidence, probabilities };
  });
  return { state: incomplete ? 'incomplete' : 'complete', answers };
}

const safeCodes = new Set(['invalid-input', 'payload-limit', 'question-limit', 'choice-limit', 'stale-configuration', 'disclosure-denied',
  'invalid-setup', 'price-unqualified', 'request-budget', 'session-budget', 'budget-unavailable', 'credential-unavailable',
  'provider-denied', 'billing', 'timeout', 'refusal', 'provider-rejected', 'provider-unavailable', 'invalid-result', 'absent-result',
  'cancelled', 'price-bound-exceeded', 'unsupported-host', 'closed', 'concurrency-limit']);

/** Evaluate only trusted consumers' supplied state; consumer authorization never comes from provider content */
export class Judgments {
  #active = new Set();
  #closed = false;
  #receipts = [];
  #priceBreach;
  constructor({ credentials, transport, budget, configuration, scope = new SessionRequests(), now = Date.now,
    platform = process.platform, ceilings = {}, sessionId = null }) {
    this.credentials = credentials; this.transport = transport; this.budget = budget;
    this.configuration = configuration; this.scope = scope; this.now = now; this.platform = platform; this.sessionId = sessionId;
    this.ceilings = { ...limits };
    for (const [key, value] of Object.entries(ceilings)) {
      if (!['bytes', 'questions', 'choices', 'timeoutMs'].includes(key) || !positive(value) || value > limits[key]) reject('invalid-setup');
      this.ceilings[key] = value;
    }
  }
  invalidate() { for (const controller of this.#active) controller.abort(); this.#receipts = []; }
  close() { if (!this.#closed) { this.#closed = true; this.invalidate(); } }
  inspect(count = 8) {
    if (!Number.isSafeInteger(count) || count < 1 || count > 32) reject('invalid-input');
    return structuredClone(this.#receipts.slice(-count));
  }
  consumer(callerId, authorize) {
    if (!identity(callerId) || typeof authorize !== 'function') reject();
    return Object.freeze({ evaluate: (request, options) => this.#evaluate(callerId, authorize, request, options),
      inspect: (count = 8) => this.inspect(count).filter(receipt => receipt.callerId === callerId) });
  }
  async #evaluate(callerId, authorize, input, { signal } = {}) {
    const started = this.now(), evaluationId = randomUUID(), controller = new AbortController();
    let config, prepared, ticket, dispatched = false, acquired = false, timer, abortListener, usage = null, result, currentPermission;
    let state = 'provider-unavailable';
    const cancelled = new Promise((_, rejectPromise) => {
      controller.signal.addEventListener('abort', () => rejectPromise(new Unavailable(state === 'timeout' ? 'timeout' : 'cancelled')), { once: true });
    });
    // Install the race immediately, before any admission path can abort.
    const operation = async () => {
      if (this.#closed) reject('closed');
      if (this.platform !== 'darwin') reject('unsupported-host');
      config = validateConfiguration(this.configuration(), this.now());
      prepared = prepareEvaluation(input, config, this.ceilings);
      if (this.#priceBreach === prepared.request.configuration) reject('price-bound-exceeded');
      const { request } = prepared;
      currentPermission = () => {
        if (this.#closed || controller.signal.aborted) reject('cancelled');
        const current = validateConfiguration(this.configuration(), this.now());
        if (configurationIdentity(current) !== request.configuration) reject('stale-configuration');
        // Authorizers are synchronous current checks. Promises/truthy values do not confer permission.
        if (current.disclosure !== 'authorized-supplied-state') reject('disclosure-denied');
        let permission;
        try {
          permission = authorize(Object.freeze({ callerId, sessionId: this.sessionId,
            authorityRevision: request.authorityRevision, configuration: request.configuration,
            source: Object.freeze({ ...request.source, digest: prepared.sourceDigest }),
            policyVersion: request.policyVersion, requestMicroUsd: request.requestMicroUsd, sessionMicroUsd: request.sessionMicroUsd,
            requestDigest: prepared.requestDigest, questionDigest: prepared.questionDigest }));
        } catch { reject('disclosure-denied'); }
        if (permission instanceof Promise) void permission.catch(() => {});
        if (permission !== true) reject('disclosure-denied');
      };
      currentPermission();
      if (signal?.aborted) reject('cancelled');
      this.scope.acquire(controller); acquired = true; this.#active.add(controller);
      abortListener = () => controller.abort(); signal?.addEventListener('abort', abortListener, { once: true });
      timer = setTimeout(() => { state = 'timeout'; controller.abort(); }, this.ceilings.timeoutMs);
      if (request.requestMicroUsd > config.requestMicroUsd || request.requestMicroUsd < reservationMicroUsd) reject('request-budget');
      if (request.sessionMicroUsd > config.sessionMicroUsd || request.sessionMicroUsd < request.requestMicroUsd) reject('session-budget');
      ticket = this.budget.reserve(reservationMicroUsd, request.sessionMicroUsd);
      const credential = await this.credentials.read(controller.signal);
      try {
        currentPermission();
        dispatched = true;
        const response = await this.transport.submit(config.endpoint, prepared.payload, credential, controller.signal);
        currentPermission();
        if (response.status !== 200) reject(httpFailure(response.status));
        if (response.body === null || response.body === undefined) reject('absent-result');
        if (response.body.refusal === true) reject('refusal');
        usage = evaluationUsage(response.body, config);
        if (usage.costUsd !== null) {
          const charge = Math.ceil(usage.costUsd * 1000000);
          this.budget.settle(ticket, charge); ticket = undefined;
          if (charge > reservationMicroUsd) { this.#priceBreach = request.configuration; reject('price-bound-exceeded'); }
        }
        return parseEvaluation(response.body, request, config);
      } finally { if (Buffer.isBuffer(credential)) credential.fill(0); }
    };
    const running = operation().finally(() => {
      if (acquired) { this.#active.delete(controller); this.scope.release(controller); }
    });
    try {
      result = await Promise.race([running, cancelled]);
      currentPermission();
      state = result.state;
    } catch (error) {
      result = undefined;
      state = error instanceof Unavailable && safeCodes.has(error.code) ? error.code : 'provider-unavailable';
    }
    finally {
      clearTimeout(timer); if (abortListener) signal?.removeEventListener('abort', abortListener);
      if (ticket !== undefined && !dispatched) {
        try { this.budget.settle(ticket, 0); ticket = undefined; } catch { state = 'budget-unavailable'; }
      }
    }
    if (this.#closed) { state = 'cancelled'; result = undefined; }
    const receipt = { evaluationId, callerId, state, schema: connection.schema,
      configuration: prepared?.request.configuration ?? null, configurationRevision: config?.revision ?? null,
      evaluator: config?.evaluator ?? null, endpoint: connection.endpoint,
      authorityRevision: prepared?.request.authorityRevision ?? null, policyVersion: prepared?.request.policyVersion ?? null,
      source: prepared ? { ...prepared.request.source, digest: prepared.sourceDigest } : null,
      requestDigest: prepared?.requestDigest ?? null, questionDigest: prepared?.questionDigest ?? null,
      sessionDigest: this.sessionId ? createHash('sha256').update(this.sessionId).digest('hex') : null,
      questions: prepared?.request.questions.map(q => ({ id: q.id, version: q.version, rubricVersion: q.rubricVersion, type: q.type })) ?? [],
      elapsedMs: Math.max(0, this.now() - started), usage, reservationRetained: ticket !== undefined && dispatched };
    if (!this.#closed) { this.#receipts.push(receipt); if (this.#receipts.length > 32) this.#receipts.shift(); }
    return structuredClone({ state, answers: result?.answers ?? null, receipt });
  }
}
