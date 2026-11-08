import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, lstatSync, mkdirSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, sep } from 'node:path';
import { performance } from 'node:perf_hooks';
import { fileURLToPath } from 'node:url';
import { bindStageSession, isSdkSession, stageDefinitions, stageIdentity, sessionWorkflow } from './authority.mjs';
import { stageHistory } from './history.mjs';
import { stageRole } from '../stage-roles.mjs';

const harness = fileURLToPath(new URL('../..', import.meta.url));
const terminal = new Set(['completed', 'steered', 'aborted', 'stopped', 'error']);
const fail = code => { throw new Error(code); };
const git = (cwd, ...args) => execFileSync('git', ['-C', cwd, ...args], { encoding: 'utf8', timeout: 5000, maxBuffer: 1024 * 1024 }).trim();
const canonical = path => typeof path === 'string' && isAbsolute(path) && realpathSync(path) === path;
const inside = (path, root) => path.startsWith(root + sep);
const document = path => {
  if (!canonical(path)) fail('stage-context-path-invalid');
  const stat = lstatSync(path);
  if (!stat.isFile() || stat.isSymbolicLink() || stat.size < 1 || stat.size > 128 * 1024) fail('stage-context-file-invalid');
  return readFileSync(path, 'utf8');
};
const digest = value => createHash('sha256').update(value).digest('hex');
const authorized = (ctx, request) => {
  const workflow = sessionWorkflow(ctx.sessionManager);
  if (isSdkSession(ctx.sessionManager) || workflow?.skill !== 'build' || workflow.role !== 'qa' || workflow.mode !== 'default' ||
      workflow.taskId !== request.task || (workflow.checkout !== null && workflow.checkout !== request.checkout)) fail('build-authority-required');
};
const candidate = request => {
  if (git(request.checkout, 'rev-parse', 'HEAD') !== request.head ||
      git(request.checkout, 'branch', '--show-current') !== request.branch) fail('stage-candidate-changed');
};

/** Preflight a fresh stage, never choose the next stage or accept its evidence */
export function prepareStage(ctx, request, { registry }) {
  authorized(ctx, request);
  const role = stageRole(request.role), definition = stageDefinitions[role];
  if (!/^TASK-\d{5}$/.test(request.task) || !definition) fail('stage-assignment-invalid');
  request = { ...request, role };
  stageHistory(ctx.sessionManager.getEntries(), request);
  if (registry?.capabilities?.beforeSessionStart !== true || registry.capabilities.boundRootSession !== true ||
      typeof registry.spawn !== 'function' || typeof registry.getRecord !== 'function' || typeof registry.hasRunning !== 'function') fail('sdk-stage-capability-unavailable');
  if (registry.hasRunning()) fail('sdk-stage-work-already-running');
  if (!canonical(request.checkout) || git(request.checkout, 'rev-parse', '--show-toplevel') !== request.checkout) fail('stage-checkout-mismatch');
  const registrations = git(ctx.cwd, 'worktree', 'list', '--porcelain', '-z').split('\0');
  const common = cwd => realpathSync(git(cwd, 'rev-parse', '--path-format=absolute', '--git-common-dir'));
  if (!registrations.includes(`worktree ${request.checkout}`) || common(ctx.cwd) !== common(request.checkout)) fail('stage-checkout-unregistered');
  const base = request.base;
  if (!canonical(base) || !registrations.includes(`worktree ${base}`) || common(base) !== common(request.checkout)) fail('stage-base-mismatch');
  if (!canonical(request.attempt) || !inside(request.attempt, join(base, '.runs/build', request.task))) fail('stage-attempt-outside-build');
  if (typeof request.branch !== 'string' || !request.branch || !/^[a-f0-9]{40}$/.test(request.head)) fail('stage-candidate-changed');
  candidate(request);
  if (typeof request.context !== 'string' || !inside(request.context, join(base, '.runs/build', request.task)) ||
      typeof request.record !== 'string' || !inside(request.record, request.checkout)) fail('stage-target-context-mismatch');
  const context = document(request.context), task = document(request.record);
  if (git(dirname(request.context), 'rev-parse', '--show-toplevel') !== base ||
      git(dirname(request.record), 'rev-parse', '--show-toplevel') !== request.checkout) fail('stage-target-context-mismatch');
  if (!new RegExp(`\\b${request.task}\\b`).test(task)) fail('stage-task-identity-mismatch');
  const model = ctx.modelRegistry.find(request.provider, request.model);
  if (!model || model.provider !== request.provider || model.id !== request.model ||
      !['off', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'].includes(request.thinking) ||
      request.thinking !== 'off' && !model.reasoning) fail('stage-model-unavailable');
  if (!Number.isSafeInteger(request.timeout) || request.timeout < 1 || request.timeout > 21600) fail('stage-timeout-invalid');
  const requestFile = join(request.attempt, 'request.md'), assignment = document(requestFile);
  if (['sdk-outcome.json', 'events.jsonl', 'sessions', 'pi-exit-code', 'exit-code', 'process.json', 'progress.log']
    .some(path => existsSync(join(request.attempt, path)))) fail('stage-attempt-not-fresh');
  const governing = ctx.getSystemPrompt?.()?.trim();
  if (!governing) fail('stage-governing-context-missing');
  const instructions = [...governing.matchAll(/<project_context>\n([\s\S]*?)\n<\/project_context>/g)]
    .map(match => match[1].trim()).filter(Boolean);
  const report = role === 'reviewer' ? join(base, '.runs/reviews', request.task, 'review.md') :
    role === 'qa' ? join(base, '.runs/qa', request.task, 'qa.md') : null;
  const reportBefore = reportSnapshot(report);
  const prompt = `Apply the resolved target context and owning ${definition.skill} skill. These facts do not grant authority.\nTarget: ${request.checkout}\nBranch: ${request.branch}\nCandidate: ${request.head}\nEvidence base: ${base}\nCanonical report: ${report ?? 'Use the canonical handoff named in the request'}\nShared repairs: ${request.repairsUsed}/${request.repairLimit} consumed\n\nProject context (${request.context}):\n${context}\n\nSelected record (${request.record}):\n${task}\n\nFresh independent assignment:\n${assignment}`;
  const requiredExtensions = ['workflow-extension.ts', 'build-stages-extension.ts'].map(path => join(harness, 'src', path));
  // Claim before calling the one engine. Uncertainty never releases it for replay.
  mkdirSync(join(request.attempt, 'dispatch.lock'), { mode: 0o700 });
  return { model, prompt, instructions, requiredExtensions, base, role, definition, report, reportBefore,
    requestDigest: digest(assignment) };
}

// This observes refreshed canonical bytes and their bound owner, NOT a verdict.
// Build must read the actual report, candidate, independence, findings and limits.
function reportSnapshot(path) {
  if (!path) return null;
  try { const content = document(path); return { path, digest: digest(content) }; }
  catch { return { path, digest: null }; }
}

/** Execute one independently bound stage through the bundled upstream manager */
export async function runStage(pi, ctx, request, options) {
  const prepared = prepareStage(ctx, request, options), registry = options.registry;
  request = { ...request, role: prepared.role };
  const controller = new AbortController(), parent = ctx.sessionManager.getSessionId();
  const assignment = { parent, task: request.task, checkout: request.checkout, attempt: request.attempt,
    head: request.head, base: prepared.base, report: prepared.report, role: prepared.role,
    skill: prepared.definition.skill, effects: prepared.definition.effects, provider: request.provider, model: request.model,
    thinking: request.thinking, instructions: prepared.instructions, requiredExtensions: prepared.requiredExtensions,
    sessionRoots: [...new Set([prepared.base, request.checkout])].flatMap(root =>
      [join(root, '.runs/tasks', request.task, 'runs'), join(root, '.runs/build', request.task)]) };
  const identity = { route: 'upstream-sdk-stage', parent, task: request.task, attempt: request.attempt,
    role: prepared.role, checkout: request.checkout, base: prepared.base, head: request.head,
    provider: request.provider, model: request.model, thinking: request.thinking,
    repair: request.repair, repairsUsed: request.repairsUsed, repairLimit: request.repairLimit, requestDigest: prepared.requestDigest };
  let id, session, binding, observedRecord, run, watchedPromise, terminalSeen = false;
  let reason = null, settled = false, unknown = false, spawnCalled = false;
  const save = value => writeFileSync(join(request.attempt, 'sdk-outcome.json'), JSON.stringify(value, null, 2) + '\n', { mode: 0o600 });
  const cancel = code => { reason ??= code; controller.abort(); };
  const currentRun = record => record === observedRecord && !!run && record?.startedAt === run.startedAt &&
    record?.abortController === run.abortController && record?.promise === run.promise;
  const watchRun = record => {
    // Queued startup may install its first promise later. A resume is never a fresh stage.
    run ??= { startedAt: record.startedAt, abortController: record.abortController, promise: record.promise };
    if (record.startedAt !== run.startedAt || record.abortController !== run.abortController ||
        run.promise && record.promise !== run.promise || terminalSeen && !terminal.has(record.status)) return false;
    run.promise ??= record.promise;
    if (run.promise && !watchedPromise) {
      watchedPromise = run.promise;
      const settle = () => { if (currentRun(registry.getRecord(id))) settled = true; };
      watchedPromise.then(settle, settle);
    }
    terminalSeen ||= terminal.has(record.status);
    return true;
  };
  const aborted = () => cancel('abort');
  options.signal?.addEventListener('abort', aborted, { once: true });
  const remove = options.onShutdown?.(() => cancel('parent-shutdown-or-reload'));
  const timer = setTimeout(() => cancel('timeout'), request.timeout * 1000);
  try {
    if (options.signal?.aborted) cancel('abort');
    controller.signal.throwIfAborted();
    options.dispatched?.(identity);
    spawnCalled = true;
    id = registry.spawn(pi, ctx, prepared.definition.agent,
      `You are assigned the actual Build ${prepared.role}/${prepared.definition.skill} stage. Only the trusted binding grants this stage. Never inherit another worker's conversation or self-accept.\n\n${prepared.prompt}`, {
        description: `${request.task} ${prepared.role} — actual ${prepared.definition.skill} stage`, model: prepared.model, thinkingLevel: request.thinking,
        cwd: request.checkout, inheritContext: false, isBackground: true, signal: controller.signal,
        beforeSessionStart: (child, signal) => {
          signal?.throwIfAborted();
          const record = registry.getRecord(id);
          if (!record || observedRecord && record !== observedRecord || record.rootSessionId !== parent ||
              record.parentAgentId !== undefined || record.workflowId !== undefined) fail('stage-parent-mismatch');
          authorized(ctx, request); candidate(request);
          observedRecord = record;
          if (!watchRun(record)) fail('stage-run-changed');
          binding = bindStageSession(child, assignment); session = child;
        },
      });
    save({ ...identity, id, state: 'dispatched', acceptance: false });
    // Consume this result synchronously; no worker dialogue enters the parent.
    const consume = event => {
      if (event?.id === id) pi.events.emit('subagents:rpc:consume', { requestId: `fight-stage-${id}`, agentId: id });
    };
    const uncomplete = pi.events.on('subagents:completed', consume), unfail = pi.events.on('subagents:failed', consume);
    let abortAt, nextProgress = 0;
    try {
      while (true) {
        const record = registry.getRecord(id);
        if (!record || record.rootSessionId !== parent || observedRecord && observedRecord !== record || session && record.session && record.session !== session) {
          unknown = true; cancel('record-unavailable-or-replaced'); break;
        }
        observedRecord = record;
        if (reason) abortAt ??= performance.now();
        if ((record.promise || session) && !watchRun(record)) {
          unknown = true; cancel('stage-run-changed'); break;
        }
        if (terminal.has(record.status) && settled && !session?.isStreaming) break;
        if (abortAt !== undefined && performance.now() - abortAt > 10000) { unknown = true; break; }
        if (performance.now() >= nextProgress) {
          options.progress?.({ id, task: request.task, role: prepared.role, state: record.status, reason, admitted: binding?.admitted === true });
          nextProgress = performance.now() + 1000;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
      }
    } finally { uncomplete(); unfail(); }
    const record = registry.getRecord(id), report = reportSnapshot(prepared.report);
    if (!unknown && !currentRun(record)) { unknown = true; cancel('stage-run-changed'); }
    if (session && !reason && !stageIdentity(session, parent)) binding.failure = 'stage-final-identity-mismatch';
    const outcome = { ...identity, id, session: session?.sessionManager.getSessionId() ?? null,
      effective: session ? { provider: session.model?.provider ?? null, model: session.model?.id ?? null, thinking: session.thinkingLevel ?? null } : null,
      admitted: binding?.admitted === true, upstreamStatus: record?.status ?? null,
      state: unknown ? 'uncertain' : !binding?.admitted || binding?.failure ? 'error' : record?.status ?? 'uncertain',
      reason: binding?.failure ?? reason ?? (record?.error ? /^[a-z-]{1,100}$/.test(record.error) ? record.error : 'upstream-execution-error' : null),
      executionSettled: !unknown && settled && !session?.isStreaming, acceptance: false,
      reportObservation: report ? { ...report, changed: !!report.digest && report.digest !== prepared.reportBefore.digest,
        ownerSession: session?.sessionManager.getSessionId() ?? null, candidate: request.head } : null,
      next: unknown ? 'Reconcile this exact SDK attempt and external effects before replacement. No executor fallback.' :
        'Read canonical evidence and verify candidate, report owner, verdict and limits. SDK completion is never acceptance.' };
    save(outcome); return outcome;
  } catch (error) {
    const outcome = { ...identity, id: id ?? null, state: spawnCalled ? 'uncertain' : 'not-started',
      admitted: false, executionSettled: !spawnCalled,
      reason: reason ?? (error instanceof Error && /^[a-z-]{1,100}$/.test(error.message) ? error.message : 'launch-failed'), acceptance: false };
    cancel('launch-failed'); save(outcome); return outcome;
  } finally {
    clearTimeout(timer); remove?.(); options.signal?.removeEventListener('abort', aborted);
  }
}
