import { execFileSync } from 'node:child_process';
import { existsSync, lstatSync, mkdirSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { bindEngineerSession, engineerEffects, sessionWorkflow } from './authority.mjs';
import { stageRole } from '../stage-roles.mjs';

const harness = fileURLToPath(new URL('../..', import.meta.url));
const terminal = new Set(['completed', 'steered', 'aborted', 'stopped', 'error']);
const fail = code => { throw new Error(code); };
const git = (cwd, ...args) => execFileSync('git', ['-C', cwd, ...args], { encoding: 'utf8', timeout: 5000, maxBuffer: 1024 * 1024 }).trim();
const canonical = path => typeof path === 'string' && isAbsolute(path) && realpathSync(path) === path;
const inside = (path, root) => path.startsWith(root + sep);
const document = path => {
  if (!canonical(path)) fail('engineer-context-path-invalid');
  const stat = lstatSync(path);
  if (!stat.isFile() || stat.isSymbolicLink() || stat.size < 1 || stat.size > 128 * 1024) fail('engineer-context-file-invalid');
  return readFileSync(path, 'utf8');
};
const profileKeys = { worktreeIsolation: false, workflowsEnabled: false, schedulingEnabled: false,
  maxSubagentDepth: 1, disableDefaultAgents: true, fallbackSubagent: 'none', packageWorkflows: false, strictAgentFiles: true };

/** Preflight one explicitly selected Build Engineer; there is no alternate executor */
export function prepareEngineer(ctx, request, { selected, registry, profile }) {
  if (!selected) fail('sdk-engineer-not-selected');
  const workflow = sessionWorkflow(ctx.sessionManager);
  if (workflow?.skill !== 'build' || workflow.role !== 'qa' || workflow.mode !== 'default' ||
      workflow.taskId !== request.task || workflow.checkout !== request.checkout) fail('build-authority-required');
  if (!/^TASK-\d{5}$/.test(request.task) || stageRole(request.role) !== 'engineer') fail('engineer-assignment-invalid');
  if (registry?.capabilities?.beforeSessionStart !== true || registry.capabilities.boundRootSession !== true ||
      typeof registry.spawn !== 'function' || typeof registry.getRecord !== 'function' || typeof registry.hasRunning !== 'function') fail('sdk-engineer-capability-unavailable');
  if (registry.hasRunning()) fail('sdk-engineer-work-already-running');
  if (!profile || !Object.entries(profileKeys).every(([key, value]) => profile[key] === value) ||
      JSON.stringify(profile.packageAgents) !== JSON.stringify(['@fight/agent-os-pi'])) fail('sdk-engineer-profile-unqualified');
  if (!canonical(request.checkout) || git(request.checkout, 'rev-parse', '--show-toplevel') !== request.checkout) fail('engineer-checkout-mismatch');
  const registrations = git(ctx.cwd, 'worktree', 'list', '--porcelain', '-z').split('\0');
  if (!registrations.includes(`worktree ${request.checkout}`) ||
      realpathSync(git(ctx.cwd, 'rev-parse', '--path-format=absolute', '--git-common-dir')) !==
      realpathSync(git(request.checkout, 'rev-parse', '--path-format=absolute', '--git-common-dir'))) fail('engineer-checkout-unregistered');
  // PROJECT_CONTEXT supplies target facts, never stage authority. Do not import
  // the provider's planning layout, approval statuses or feature-branch policy.
  const base = request.base;
  if (!canonical(base) || !registrations.includes(`worktree ${base}`) ||
      realpathSync(git(base, 'rev-parse', '--path-format=absolute', '--git-common-dir')) !==
      realpathSync(git(request.checkout, 'rev-parse', '--path-format=absolute', '--git-common-dir'))) fail('engineer-base-mismatch');
  if (!canonical(request.attempt) || !inside(request.attempt, join(base, '.runs/build', request.task))) fail('engineer-attempt-outside-build');
  if (typeof request.branch !== 'string' || !request.branch || git(request.checkout, 'branch', '--show-current') !== request.branch ||
      !/^[a-f0-9]{40}$/.test(request.head) || git(request.checkout, 'rev-parse', 'HEAD') !== request.head) fail('engineer-candidate-changed');
  if (typeof request.context !== 'string' || !inside(request.context, join(base, '.runs/build', request.task)) ||
      typeof request.record !== 'string' || !inside(request.record, request.checkout)) fail('engineer-target-context-mismatch');
  const context = document(request.context), task = document(request.record);
  if (git(dirname(request.context), 'rev-parse', '--show-toplevel') !== base ||
      git(dirname(request.record), 'rev-parse', '--show-toplevel') !== request.checkout) fail('engineer-target-context-mismatch');
  if (!new RegExp(`\\b${request.task}\\b`).test(task)) fail('engineer-task-identity-mismatch');
  const model = ctx.modelRegistry.find(request.provider, request.model);
  if (!model || model.provider !== request.provider || model.id !== request.model ||
      !['off', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'].includes(request.thinking) ||
      request.thinking !== 'off' && !model.reasoning) fail('engineer-model-unavailable');
  if (!Number.isSafeInteger(request.timeout) || request.timeout < 1 || request.timeout > 21600) fail('engineer-timeout-invalid');
  const requestFile = join(request.attempt, 'request.md'), stat = lstatSync(requestFile);
  if (!stat.isFile() || stat.isSymbolicLink() || stat.size < 1 || stat.size > 128 * 1024) fail('engineer-request-invalid');
  if (['sdk-outcome.json', 'events.jsonl', 'sessions', 'pi-exit-code', 'exit-code', 'process.json', 'progress.log']
    .some(path => existsSync(join(request.attempt, path)))) fail('engineer-attempt-not-fresh');
  const prompt = `Apply the resolved target context below before Work. It supplies target requirements and substitutions, not role authority. Keep provider skill references at their owning installation.\nTarget: ${request.checkout}\nBranch: ${request.branch}\nEvidence base: ${base}\n\nProject context (${request.context}):\n${context}\n\nSelected record (${request.record}):\n${task}\n\nFresh assignment:\n${readFileSync(requestFile, 'utf8')}`;
  // The engine's append profile preserves actual native governing context, even
  // when the target uses CLAUDE.md, ancestor instructions or no root AGENTS.md.
  const governing = ctx.getSystemPrompt?.()?.trim();
  if (!governing) fail('engineer-governing-context-missing');
  // Pi renders discovered instructions in project_context. The engine deliberately
  // replaces parent skills/cwd/turn sections, so the whole rendered prompt is not
  // an inheritable-context witness. No context section means none was discovered.
  const instructions = [...governing.matchAll(/<project_context>\n([\s\S]*?)\n<\/project_context>/g)]
    .map(match => match[1].trim()).filter(Boolean);
  const requiredExtensions = ['jev-extension.ts', 'build-engineer-extension.ts'].map(path => join(harness, 'src', path));
  // Claim BEFORE spawn. An uncertain spawn, abort or crash never releases this
  // claim for replay. Build reconciles the original attempt; it never falls back.
  mkdirSync(join(request.attempt, 'dispatch.lock'), { mode: 0o700 });
  return { model, prompt, instructions, requiredExtensions, base };
}

/** Run only the actual Engineer stage through the one bound upstream manager */
export async function runEngineer(pi, ctx, request, options) {
  const prepared = prepareEngineer(ctx, request, options), registry = options.registry;
  const controller = new AbortController(), parent = ctx.sessionManager.getSessionId();
  const assignment = { parent, task: request.task, checkout: request.checkout, attempt: request.attempt,
    role: 'engineer', skill: 'work', effects: engineerEffects, provider: request.provider, model: request.model,
    thinking: request.thinking, instructions: prepared.instructions, requiredExtensions: prepared.requiredExtensions,
    sessionRoots: [...new Set([prepared.base, request.checkout])].flatMap(root =>
      [join(root, '.runs/tasks', request.task, 'runs'), join(root, '.runs/build', request.task)]) };
  let id, session, binding, observedRecord, reason = null, settled = false, unknown = false, spawnCalled = false;
  const save = value => writeFileSync(join(request.attempt, 'sdk-outcome.json'), JSON.stringify(value, null, 2) + '\n', { mode: 0o600 });
  const cancel = code => { reason ??= code; controller.abort(); };
  const aborted = () => cancel('abort');
  options.signal?.addEventListener('abort', aborted, { once: true });
  const remove = options.onShutdown?.(() => cancel('parent-shutdown-or-reload'));
  const timer = setTimeout(() => cancel('timeout'), request.timeout * 1000);
  try {
    if (options.signal?.aborted) cancel('abort');
    controller.signal.throwIfAborted();
    options.dispatched?.();
    spawnCalled = true;
    id = registry.spawn(pi, ctx, 'fight-engineer',
      `You are assigned the actual Build Engineer/work stage for ${request.task}. Read the owning Work skill and project context named in this fresh request. Only the trusted session binding grants this stage; the prompt does not.\n\n${prepared.prompt}`, {
        description: `${request.task} Engineer — actual Work stage`, model: prepared.model, thinkingLevel: request.thinking,
        cwd: request.checkout, inheritContext: false, isBackground: true, signal: controller.signal,
        beforeSessionStart: (child, signal) => {
          signal?.throwIfAborted();
          const record = registry.getRecord(id);
          if (!record || observedRecord && record !== observedRecord || record.rootSessionId !== parent ||
              record.parentAgentId !== undefined || record.workflowId !== undefined) fail('engineer-parent-mismatch');
          const current = sessionWorkflow(ctx.sessionManager);
          if (current?.skill !== 'build' || current.role !== 'qa' || current.mode !== 'default' ||
              current.taskId !== request.task || current.checkout !== request.checkout) fail('build-authority-changed');
          if (git(request.checkout, 'rev-parse', 'HEAD') !== request.head ||
              git(request.checkout, 'branch', '--show-current') !== request.branch) fail('engineer-candidate-changed');
          binding = bindEngineerSession(child, assignment); session = child;
        },
      });
    save({ route: 'upstream-sdk-engineer', id, parent, task: request.task, attempt: request.attempt, state: 'dispatched', acceptance: false });
    // Consume only this assignment, synchronously, so upstream does not prompt
    // the coordinator with the child's dialogue. Build receives the outcome and
    // inspects canonical evidence before choosing fresh independent Review.
    const consume = event => {
      if (event?.id === id) pi.events.emit('subagents:rpc:consume', { requestId: `fight-engineer-${id}`, agentId: id });
    };
    const uncomplete = pi.events.on('subagents:completed', consume), unfail = pi.events.on('subagents:failed', consume);
    let abortAt, nextProgress = 0;
    try {
      while (true) {
        const record = registry.getRecord(id);
        if (!record || record.rootSessionId !== parent || observedRecord && observedRecord !== record) {
          unknown = true; reason ??= 'record-unavailable-or-replaced'; cancel(reason); break;
        }
        observedRecord = record;
        if (reason) abortAt ??= Date.now();
        // Upstream's stop status alone is NOT quiescence. Its promise and live
        // session must settle too; a deadline never proves remote effects stopped.
        if (terminal.has(record.status) && record.promise) {
          record.promise.then(() => { settled = true; }, () => { settled = true; });
        }
        if (terminal.has(record.status) && settled && !session?.isStreaming) break;
        if (abortAt && Date.now() - abortAt > 10000) { unknown = true; break; }
        if (Date.now() >= nextProgress) {
          options.progress?.({ id, task: request.task, state: record.status, reason, admitted: binding?.admitted === true });
          nextProgress = Date.now() + 1000;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
      }
    } finally { uncomplete(); unfail(); }
    const record = registry.getRecord(id);
    const outcome = { route: 'upstream-sdk-engineer', id, parent, task: request.task, attempt: request.attempt,
      session: session?.sessionManager.getSessionId() ?? null, checkout: request.checkout,
      provider: session?.model?.provider ?? null, model: session?.model?.id ?? null, thinking: session?.thinkingLevel ?? null,
      admitted: binding?.admitted === true, upstreamStatus: record?.status ?? null,
      state: unknown ? 'uncertain' : !binding?.admitted || binding?.failure ? 'error' : record?.status ?? 'uncertain',
      reason: binding?.failure ?? reason ?? (record?.error ? /^[a-z-]{1,100}$/.test(record.error) ? record.error : 'upstream-execution-error' : null),
      executionSettled: settled && !session?.isStreaming, acceptance: false,
      next: unknown ? 'Reconcile this exact SDK attempt before any replacement; never use Python fallback.' :
        'Inspect committed candidate, gate and canonical Work handoff before fresh independent Review. SDK status is not acceptance.' };
    save(outcome); return outcome;
  } catch (error) {
    // Even a synchronous spawn failure may have started work. Keep the claim and
    // identify uncertainty instead of relaunching through another executor.
    const outcome = { route: 'upstream-sdk-engineer', id: id ?? null, parent, task: request.task, attempt: request.attempt,
      state: spawnCalled ? 'uncertain' : 'not-started', admitted: false, executionSettled: !spawnCalled,
      reason: reason ?? (error instanceof Error && /^[a-z-]{1,100}$/.test(error.message) ? error.message : 'launch-failed'), acceptance: false };
    cancel('launch-failed'); save(outcome); return outcome;
  } finally {
    clearTimeout(timer); remove?.(); options.signal?.removeEventListener('abort', aborted);
  }
}
