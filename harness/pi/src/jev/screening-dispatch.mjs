import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, realpathSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { randomUUID } from 'node:crypto';
import { privateDirectory, readPrivate, writePrivate } from './local-store.mjs';
import { hash } from './screening-profile.mjs';
import { Unavailable } from './readiness.mjs';

export const buildMonitor = fileURLToPath(new URL('../../../../.pi/skills/build/scripts/run-stage.py', import.meta.url));
const fail = code => { throw new Unavailable(code); };
const identifier = v => typeof v === 'string' && /^[a-f0-9-]{36}$/.test(v);

/** Admit only declared observation fields across the child/parent content boundary */
export function safeChildObservation(line) {
  try {
    if (line.startsWith('Fight screening: ')) {
      const item = JSON.parse(line.slice('Fight screening: '.length));
      if (item.version !== 'fight-file-screen-observation-v1' || !['screen-start', 'screen-complete', 'cache-reuse', 'receipt-admission', 'exception', 'blocked-read', 'blocked-write', 'session-bound'].includes(item.kind) || !/^[a-f0-9]{64}$/.test(item.session)) return null;
      const safe = { kind: item.kind, session: item.session };
      if (Number.isSafeInteger(item.sequence) && item.sequence > 0) safe.sequence = item.sequence;
      if (typeof item.phase === 'string' && /^[A-Za-z0-9-]{1,48}$/.test(item.phase)) safe.phase = item.phase;
      for (const key of ['reason', 'decision']) if (typeof item[key] === 'string' && /^[a-z-]{1,80}$/.test(item[key])) safe[key] = item[key];
      for (const key of ['pathId', 'needId']) if (typeof item[key] === 'string' && /^[a-f0-9]{64}$/.test(item[key])) safe[key] = item[key];
      if (identifier(item.operationId)) safe.operationId = item.operationId;
      for (const [key, max] of [['elapsedMs', 1800000], ['offset', 1000000000], ['lines', 1000]]) {
        if (Number.isSafeInteger(item[key]) && item[key] >= 0 && item[key] <= max) safe[key] = item[key];
      }
      safe.costUsd = typeof item.costUsd === 'number' && Number.isFinite(item.costUsd) && item.costUsd >= 0 ? item.costUsd : null;
      if (item.kind === 'cache-reuse') safe.providerCalls = 0;
      return safe;
    }
    if (line.startsWith('Fight screening status: ')) {
      const item = JSON.parse(line.slice('Fight screening status: '.length));
      if (!['active', 'degraded', 'disabled'].includes(item.state)) return null;
      const safe = { kind: 'dispatch-progress', decision: item.state,
        reason: typeof item.reason === 'string' && /^[a-z-]{1,80}$/.test(item.reason) ? item.reason : undefined, budget: {} };
      if (typeof item.session === 'string' && /^[a-f0-9]{64}$/.test(item.session)) safe.session = item.session;
      if (typeof item.phase === 'string' && /^[A-Za-z0-9-]{1,48}$/.test(item.phase)) safe.phase = item.phase;
      for (const key of ['availableMicroUsd', 'reservedMicroUsd', 'settledMicroUsd']) {
        if (Number.isSafeInteger(item.budget?.[key]) && item.budget[key] >= 0) safe.budget[key] = item.budget[key];
      }
      if (typeof item.budget?.resetsAt === 'string' && /^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/.test(item.budget.resetsAt) && Number.isFinite(Date.parse(item.budget.resetsAt)) && new Date(item.budget.resetsAt).toISOString() === item.budget.resetsAt) safe.budget.resetsAt = item.budget.resetsAt;
      if (['enroll-project', 'reconcile-legacy', 'configure-credentials', 'wait-month-reset', 'inspect-reload', 'screen-then-read'].includes(item.nextActionCode)) safe.nextActionCode = item.nextActionCode;
      return safe;
    }
  } catch { /* Nonprotocol diagnostics never become Agent-visible source */ }
  return null;
}

/** Dispatch only immutable, single-use operator/host-prepared build requests; never accept shell or prompt input */
export class ScreeningDispatch {
  constructor(profiles, { now = Date.now, monitor = buildMonitor } = {}) { this.profiles = profiles; this.now = now; this.monitor = monitor; }
  prepare({ cwd, request, task, role, provider, model, thinking, timeout = 300, intake = null, build = null }) {
    const selected = this.profiles.selection(cwd);
    if (!selected || process.env.FIGHT_SCREENING_CHILD === '1') fail('dispatch-not-authorized');
    if (typeof request !== 'string' || !request.trim() || Buffer.byteLength(request) > 32768 ||
      !/^TASK-\d{5}$/.test(task) || !/^[A-Za-z][A-Za-z0-9-]{0,47}$/.test(role) ||
      !/^[a-z0-9-]{1,64}$/.test(provider) || !/^[A-Za-z0-9._/-]{1,128}$/.test(model) ||
      !['off', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'].includes(thinking) ||
      !Number.isInteger(timeout) || timeout < 1 || timeout > 1800) fail('invalid-dispatch-plan');
    const id = randomUUID(), directory = join(this.profiles.directory, 'dispatch', id), attempt = join(directory, 'attempt');
    privateDirectory(directory); mkdirSync(attempt, { mode: 0o700 });
    // The request is held outside Agent-write roots. A ticket cannot be repurposed after preview.
    const requestPath = join(attempt, 'request.md');
    const manifest = { version: 1, id, cwd: realpathSync.native(cwd), authority: selected.identity,
      task, role, provider, model, thinking, timeout, expiresAt: Math.min(selected.profile.expiresAt, this.now() + 3600000),
      monitor: this.monitor, monitorDigest: hash(readFileSync(this.monitor)), requestDigest: hash(request), intake, build };
    if (intake) {
      // Only already established, digest-bound authority may be carried into a child.
      const established = this.profiles.intake(cwd, intake);
      manifest.intake = hash(JSON.stringify(established));
      writePrivate(join(directory, 'intake.json'), established);
    }
    writePrivate(join(directory, 'ticket.json'), manifest);
    // Plain Markdown is consumed by the existing native monitor, not JSON-encoded source.
    writeFileSync(requestPath, request, { flag: 'wx', mode: 0o600 });
    return { id, ...manifest, requestPath, attempt };
  }
  inspect(cwd) {
    const parent = join(this.profiles.directory, 'dispatch');
    if (!existsSync(parent) || process.env.FIGHT_SCREENING_CHILD === '1') return [];
    return readdirSync(parent).filter(identifier).flatMap(id => {
      try {
        const m = this.#load(cwd, id), result = readPrivate(join(parent, id, 'result.json'), null);
        const state = result?.ticket === id && ['completed', 'incomplete'].includes(result.state) ? result.state :
          existsSync(join(parent, id, 'claimed')) ? 'consumed-or-uncertain' : 'available';
        return [{ ticket: id, task: m.task, role: m.role, provider: m.provider, model: m.model,
          thinking: m.thinking, expiresAt: m.expiresAt, state }];
      }
      catch { return []; }
    }).slice(0, 32);
  }
  #load(cwd, id) {
    if (!identifier(id) || process.env.FIGHT_SCREENING_CHILD === '1') fail('dispatch-not-authorized');
    const directory = join(this.profiles.directory, 'dispatch', id);
    if (!existsSync(directory)) fail('dispatch-not-authorized');
    privateDirectory(directory);
    const m = readPrivate(join(directory, 'ticket.json'), null), selected = this.profiles.selection(cwd);
    if (!m || m.version !== 1 || m.id !== id || m.cwd !== realpathSync.native(cwd) || m.authority !== selected?.identity ||
      m.expiresAt <= this.now() || m.monitor !== this.monitor || m.monitorDigest !== hash(readFileSync(this.monitor)) ||
      m.requestDigest !== hash(readFileSync(join(directory, 'attempt/request.md')))) fail('stale-dispatch-plan');
    return m;
  }
  claim(cwd, id) {
    const manifest = this.#load(cwd, id), directory = join(this.profiles.directory, 'dispatch', id);
    try { mkdirSync(join(directory, 'claimed'), { mode: 0o700 }); } catch { fail('dispatch-consumed-or-uncertain'); }
    return { ...manifest, directory, attempt: join(directory, 'attempt') };
  }
  async run(cwd, id, { signal, observe = () => {}, validate = () => {} } = {}) {
    if (signal?.aborted) fail('dispatch-cancelled');
    validate(); const plan = this.claim(cwd, id); validate();
    observe({ kind: 'dispatch-start', operationId: id, decision: 'bound' });
    const args = [plan.monitor, '--attempt', plan.attempt, '--task', plan.task, '--role', plan.role,
      '--provider', plan.provider, '--model', plan.model, '--thinking', plan.thinking, '--timeout', String(plan.timeout), '--screening-dispatch'];
    const child = spawn('python3', args, { cwd: plan.cwd, shell: false, detached: true,
      stdio: ['ignore', 'pipe', 'pipe'], env: { ...process.env, FIGHT_SCREENING_CHILD: '1', FIGHT_SCREENING_TICKET: id,
        FIGHT_SCREENING_INTAKE: plan.intake ? join(plan.directory, 'intake.json') : '' } });
    let timer, watchdog, stopping = false, output = '', observations = 0;
    const stop = () => {
      if (stopping) return; stopping = true;
      try { process.kill(-child.pid, 'SIGTERM'); } catch { /* Already exited */ }
      timer = setTimeout(() => { try { process.kill(-child.pid, 'SIGKILL'); } catch { /* Reaped */ } }, 7000);
    };
    signal?.addEventListener('abort', stop, { once: true });
    // Policy revocation/drift stops an already-running child, not only future dispatches.
    watchdog = setInterval(() => { try { validate(); this.#load(cwd, id); } catch { stop(); } }, 500);
    child.stderr.on('data', () => {}); // Raw diagnostics stay in the monitor's private evidence, never in a tool result.
    child.stdout.on('data', chunk => {
      output += chunk.toString();
      while (output.includes('\n')) {
        const at = output.indexOf('\n'), line = output.slice(0, at); output = output.slice(at + 1);
        const event = line.length <= 65536 ? safeChildObservation(line) : null;
        if (event) { observations++; observe(event); }
      }
      if (output.length > 65536) { output = ''; stop(); }
    });
    try {
      const code = await new Promise((resolve, reject) => { child.once('error', reject); child.once('close', resolve); });
      const result = { ticket: id, state: !stopping && code === 0 ? 'completed' : 'incomplete', observations,
        childOutput: 'private-evidence-only; use separately authorized bounded reads', provider: plan.provider, model: plan.model, thinking: plan.thinking };
      writePrivate(join(plan.directory, 'result.json'), result);
      observe({ kind: 'dispatch-complete', operationId: id, decision: result.state });
      return result;
    } finally { clearTimeout(timer); clearInterval(watchdog); signal?.removeEventListener('abort', stop); }
  }
}
