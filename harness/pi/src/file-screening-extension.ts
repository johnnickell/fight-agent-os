import { readFileSync, readdirSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { dirname, join, resolve } from 'node:path';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { Type, type TSchema } from 'typebox';
import { createReadToolDefinition, createEditToolDefinition, createWriteToolDefinition,
  getAgentDir, getPackageDir, getReadmePath, getDocsPath, getExamplesPath, VERSION } from '@earendil-works/pi-coding-agent';
import type { ExtensionAPI, ExtensionContext, ToolDefinition } from '@earendil-works/pi-coding-agent';
import { reviewEnrollment } from './enrollment-preview.ts';
import { FileScreening, type ScreenResult } from './jev/file-screening.mjs';
import { FileScreeningSettings } from './jev/local-store.mjs';
import { ScreeningProfiles, hash } from './jev/screening-profile.mjs';
import { FileAdmission } from './jev/file-admission.mjs';
import { ImageAdmission, isImagePath } from './jev/image-admission.mjs';
import { WorkflowAccess, skillEffects } from './jev/workflow-access.mjs';
import { MonthlyLedger, ProfileBudget } from './jev/monthly-budget.mjs';
import { ToolPolicy, PolicyDenied } from './tool-policy/policy.mjs';
import { fileOperations } from './tool-policy/effects.mjs';
import { trackRuntimeHooks } from './tool-policy/runtime.mjs';
import type { ConsumerConnection, Connection, OperatorConfiguration, Json } from './jev/judgments.mjs';

const sourceDir = dirname(fileURLToPath(import.meta.url));
const rulePath = fileURLToPath(new URL('../FILE_SCREENING_RULE.md', import.meta.url));
const rule = readFileSync(rulePath, 'utf8');
const enforcedRule = rule;
const parameters = Type.Object({
  path: Type.Optional(Type.String({ minLength: 1, maxLength: 4096 })),
  paths: Type.Optional(Type.Array(Type.String({ minLength: 1, maxLength: 4096 }), { minItems: 1, maxItems: 50 })),
  glob: Type.Optional(Type.String({ minLength: 1, maxLength: 4096 })),
  need: Type.String({ minLength: 1, maxLength: 4096 }),
  question: Type.Union([
    Type.Object({ type: Type.Literal('yes-no'), instructions: Type.String({ minLength: 1, maxLength: 4096 }) }, { additionalProperties: false }),
    Type.Object({ type: Type.Literal('choice'), instructions: Type.String({ minLength: 1, maxLength: 4096 }),
      choices: Type.Record(Type.String(), Type.String({ minLength: 1, maxLength: 4096 })),
      unknownChoice: Type.String(), otherChoice: Type.String(), relevantChoices: Type.Array(Type.String(), { minItems: 1, maxItems: 16 }),
    }, { additionalProperties: false }),
  ]),
}, { additionalProperties: false });
const textResult = (value: unknown, isError = false) => ({ content: [{ type: 'text' as const, text: typeof value === 'string' ? value : JSON.stringify(value) }], details: value, isError });
const repositoryRecovery = 'Operator: inspect git worktree list --porcelain from the enrolled repository; repair reciprocal registration with Git worktree repair, or return to a valid registered checkout and reload. Do not enroll each worktree, edit Git pointers manually, reset accounting or bypass the denial with shell.';
const allowed = ['read', 'write', 'edit', 'bash', 'jev_read_file', 'file_locate', 'file_screening_status'];
const sources = [rulePath, fileURLToPath(import.meta.url), join(sourceDir, 'enrollment-preview.ts'), ...['jev', 'tool-policy'].flatMap(dir => readdirSync(join(sourceDir, dir))
  .filter(name => /\.(mjs|py)$/.test(name)).map(name => join(sourceDir, dir, name))), join(sourceDir, 'jev-extension.ts'), join(sourceDir, 'screening-bootstrap.mjs')];
type Dependencies = { settings?: FileScreeningSettings; configuration: () => OperatorConfiguration | null;
  profiles: ScreeningProfiles; credentialConfigured: (ctx: ExtensionContext) => boolean };

// Request/file refusals are not evaluator outages; unknown/provider failures remain visible.
const workflowInputFailures = new Set(['unknown-workflow-role', 'workflow-subject-required', 'workflow-checkout-required']);
const localScreeningFailures = new Set(['source-limit', 'batch-source-limit', 'root-denied', 'sensitive-file',
  'symlink-denied', 'symlink-escape', 'unsupported-file', 'unsupported-hardlink', 'unstable-source',
  'binary-source', 'source-unavailable', 'invalid-input', 'result-presentation-limit', 'admission-presentation-limit']);

/** Bind optional file screening to explicit file access without intercepting shell execution */
export function installFileScreening(pi: ExtensionAPI, dependencies: Dependencies) {
  const hooks = trackRuntimeHooks(pi, { screening: true });
  const profiles = dependencies.profiles;
  pi.registerFlag('fight-screening-required', { type: 'string', description: 'Trusted launcher enrollment identity; missing/changed authority holds the native tools' });
  let screening: FileScreening | undefined, admission: FileAdmission | undefined, images: ImageAdmission | undefined, guard: ToolPolicy | undefined;
  let selected = false, live = false, failure: string | undefined, inputFailure: string | undefined, evaluationFailure: string | undefined;
  let context: ExtensionContext | undefined;
  let workflow = new WorkflowAccess();
  let sessionId = '', authority = '', owner = '', epoch = 0, sequence = 0, lastNotice = '', lastWarning = '';
  const persistRole = (role: string) => {
    try { pi.appendEntry('fight-workflow-role-v3', workflow.checkpoint(sessionId, role)); }
    catch (error) {
      failure = 'workflow-role-persistence-failed';
      // Pi appends in memory before writing to disk. Supersede a failed privileged append before
      // a reload can restore it; if storage is still unavailable, Pi retains this restrictive entry.
      try { pi.appendEntry('fight-workflow-role-v3', workflow.checkpoint(sessionId, 'reviewer')); } catch { /* Remain held */ }
      throw error;
    }
  };
  const ledger = new MonthlyLedger(profiles.directory);
  const safeCode = (error: unknown) => error instanceof Error && 'code' in error && typeof error.code === 'string' && /^[a-z-]{1,80}$/.test(error.code) ? error.code : 'profile-unavailable';
  function imageRecovery(reason: string) {
    const actions: Record<string, string> = {
      'source-limit': 'The image exceeds 10 MiB. Submit a smaller PNG/JPEG/GIF/WebP export.',
      'sensitive-file': 'This image is protected. Provide non-sensitive evidence; do not bypass exclusions or widen roots.',
      'unsupported-image': 'The file is not a supported image. Use a PNG/JPEG/GIF/WebP export.',
      'image-processing-failed': 'Pi could not process this image. Submit a valid smaller PNG/JPEG/GIF/WebP export.',
      'image-unavailable': 'Check the image path and filesystem access; no image content was returned.',
      'image-changed-retry': 'The image changed during processing. Retry the read against the current file.',
      'image-arguments-required': 'Read images with path; need and exception: "image" are optional. Omit line ranges, receipts and evidence.',
    };
    return actions[reason] ?? 'Read images directly with path. No image selection or approval is needed; existing file-safety and runtime checks still apply.';
  }
  function inputRecovery(reason: string) {
    if (failure || !workflowInputFailures.has(reason)) return undefined;
    return 'Operator: correct the workflow invocation with /skill:<installed-skill> and its required subject (for example /skill:release 0.4.0 status); use an absolute --checkout path when selecting a checkout. Only a successful interactive skill selection clears this input hold. Other authority, integrity and persistence holds still require their own recovery; do not change enrollment, exclusions or accounting or bypass the hold with shell.';
  }
  function observe(event: Record<string, unknown>) {
    if (!live || !context) return;
    const observation = { version: 'fight-file-screen-observation-v1', session: hash(sessionId), sequence: ++sequence,
      phase: workflow.role, ...event };
    if (context.mode !== 'tui') {
      console.error(`Fight screening: ${JSON.stringify(observation)}`);
      return;
    }
    const actions: Record<string, string> = { 'blocked-read': 'File read blocked', 'blocked-write': 'File change blocked', 'command-held': 'Command held' };
    const action = actions[String(event.kind)];
    if (!action) return;
    const notice = `${action}: ${event.reason ?? 'unavailable'}. See the tool result for recovery.`;
    if (notice !== lastNotice) { context.ui.notify(notice, 'warning'); lastNotice = notice; }
  }
  function connect(callerId: string, authorize: ConsumerConnection['authorize']) {
    let capability: Connection | { state: string } | undefined;
    pi.events.emit('fight:jev:connect', { callerId, authorize,
      connected: (value: Connection | { state: string }) => { capability = value; } });
    return capability;
  }
  function checkBoundary() {
    if (!selected) return;
    if (failure) throw new PolicyDenied(failure);
    if (inputFailure) throw new PolicyDenied(inputFailure);
    try {
      const current = profiles.selection(context!.cwd);
      if (!current || current.identity !== authority) throw new PolicyDenied('profile-changed-reload-required');
      if (pi.getFlag('fight-tool-policy') !== undefined) throw new PolicyDenied('incompatible-tool-policy-selection');
      const state = guard?.inspect();
      if (state?.state !== 'active') throw new PolicyDenied(state?.reason ?? 'boundary-unavailable');
      if (!owner || allowed.filter(name => name !== 'bash').some(name => pi.getAllTools().find(tool => tool.name === name)?.sourceInfo?.path !== owner)) throw new PolicyDenied('unsupported-tool-replacement');
      if (!['read'].every(name => pi.getActiveTools().includes(name))) throw new PolicyDenied('required-tool-hidden');
    } catch (error) {
      failure = safeCode(error); admission?.close(); screening?.close(); guard?.cancel(failure);
      throw new PolicyDenied(failure);
    }
  }
  function status(ctx: ExtensionContext) {
    let budget: ReturnType<ProfileBudget['inspect']> | null = null;
    let allocation: { requestMicroUsd: number; sessionMicroUsd: number } | null = null;
    let reason = failure ?? inputFailure ?? evaluationFailure;
    if (selected) {
      try {
        checkBoundary();
        const policy = profiles.selection(ctx.cwd)!.profile;
        allocation = { requestMicroUsd: policy.requestMicroUsd, sessionMicroUsd: policy.sessionMicroUsd };
        budget = new ProfileBudget(sessionId, profiles.directory, ledger).inspect(policy.sessionMicroUsd);
      } catch (error) { reason = safeCode(error); }
      if (!reason && !dependencies.credentialConfigured(ctx)) reason = 'credential-unavailable';
      if (!reason && screening?.availability() !== 'available') reason = screening?.availability() ?? 'screening-unavailable';
      if (budget?.legacy?.held) reason = 'legacy-reconciliation-required';
      if (!reason && budget?.halted) reason = 'price-bound-exceeded';
      if (!reason && (budget?.availableMicroUsd ?? 0) < 2688) reason = 'monthly-budget';
      if (!reason && (budget?.session.availableMicroUsd ?? 0) < 2688) reason = 'session-budget';
    }
    return { state: selected ? reason ? 'degraded' : 'active' : 'disabled',
      enforcement: selected ? reason ? 'project-access-with-screening-degraded' : 'project-access-with-optional-screening' : 'not-enrolled',
      reason: reason ?? (selected ? null : 'not-enrolled'), session: hash(sessionId),
      phase: workflow.role, budget, allocation,
      workflow: { skill: workflow.skill, effect: skillEffects[workflow.skill], taskId: workflow.taskId,
        checkout: workflow.checkout, mode: workflow.mode, launcherRole: workflow.launcher },
      credential: selected && dependencies.credentialConfigured(ctx) ? 'configured-resolution-unchecked' : 'not-configured',
      bindingLimit: budget ? budget.availableMicroUsd <= budget.session.availableMicroUsd ? 'monthly' : 'session' : 'unavailable',
      coverage: selected ? `Pi ${VERSION} explicit read/write/edit and optional screening; preflighted provider Build children. Native shell is unchanged and makes no Jev request. Shell bypasses relevance; scripts/descendants/plugins/SDK OS calls are not intercepted or confined.` : 'none',
      enrollment: selected ? 'standing-until-revocation-or-authority-change; historical-profiles-require-explicit-conversion' : 'none',
      nextActionCode: !selected ? 'none' : reason && inputRecovery(reason) ? 'correct-workflow-input' : reason === 'stale-file-policy' ? 'review-connection-rebind' : reason === 'legacy-reconciliation-required' ? 'reconcile-legacy' : reason === 'credential-unavailable' ? 'configure-credentials' : reason === 'monthly-budget' ? 'wait-month-reset' : reason ? 'inspect-reload' : 'read-or-screen-discovery',
      nextAction: !selected ? 'Continue with native tools. Jev setup and file screening are optional.' : (reason && inputRecovery(reason)) || (reason === 'stale-file-policy' ? 'Operator: /jev inspect, then /file-screening rebind to review the current connection for ALL enrolled projects; reload after saving. Do not repeat setup or reset accounting.' : reason === 'repository-metadata-unavailable' ? repositoryRecovery : reason === 'historical-profile-conversion-required' ? 'Operator: /file-screening convert; explicitly review all formerly timeboxed roots or revoke them' : reason === 'legacy-reconciliation-required' ? 'Operator: /file-screening legacy; quiesce old clients and preserve liabilities' : reason === 'credential-unavailable' ? 'Configure Pi-managed OpenRouter, then /jev inspect; never enter keys here' : reason === 'monthly-budget' ? 'Wait for reset or inspect liabilities; never delete accounting' : reason ? 'Operator: /file-screening inspect; correct authority/setup and reload. Direct project reads remain available unless authority or file-access checks fail.' : 'Read known project targets directly; strongly prefer Jev for unfamiliar discovery. Native shell commands do not use Jev. No safety or universal relevance guarantee.') };
  }
  function present(ctx: ExtensionContext, notify = false) {
    const view = status(ctx);
    if (ctx.mode === 'tui') {
      ctx.ui.setStatus('fight-file-screen', selected ? `File screening: ${view.state}${view.reason ? ` (${view.reason})` : ''}` : undefined);
      const warning = view.state === 'degraded' ? String(view.reason) : '';
      if (notify) ctx.ui.notify(JSON.stringify(view, null, 2), view.state === 'degraded' ? 'warning' : 'info');
      else if (warning && warning !== lastWarning) ctx.ui.notify(`File screening needs attention: ${warning}. Use /file-screening inspect.`, 'warning');
      lastWarning = warning;
    } else if (notify) console.error(`Fight screening status: ${JSON.stringify(view)}`);
    return view;
  }
  function registerScreen(available: boolean) {
    pi.registerTool({ name: 'jev_read_file', label: 'Screen file relevance',
      description: 'Screen authorized files without returning contents. Supply one path, paths (1-50), or glob, information need and typed question. Each current positive/cache result has its own admission receipt; no batch permission or safety judgment.',
      exposure: available || selected ? 'direct' : 'hidden', parameters,
      promptGuidelines: available || selected ? [selected ? enforcedRule : rule] : [],
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: false, openWorldHint: true },
      async execute(_id, input, signal, _update, ctx) {
        try { if (selected) checkBoundary(); }
        catch (error) { return textResult({ status: safeCode(error), threshold: 'unresolved' }, true); }
        const result: ScreenResult = screening ? await screening.screen(input, { signal }) :
          { status: 'file-profile-unavailable', threshold: 'unresolved', policyVersion: 'file-screen-v1', judgment: null,
            source: null, receipt: null, coverage: { complete: false, evaluatedBytes: 0, totalBytes: null } };
        if (selected) {
          for (const value of result.results ?? [result]) observe({ kind: value.cache?.hit ? 'cache-reuse' : 'screen-complete',
            operationId: value.operationId ?? null, pathId: value.source?.id ?? null, needId: hash(input.need), decision: value.threshold, reason: value.status,
            costUsd: value.receipt?.usage?.costUsd ?? null, elapsedMs: value.receipt?.elapsedMs ?? null });
          const outcomes = result.results ?? [result];
          const outage = outcomes.find(value => !['complete', 'incomplete'].includes(value.status) && !localScreeningFailures.has(value.status));
          if (outage) evaluationFailure = outage.status;
          else if (outcomes.some(value => value.status === 'complete' && !value.cache?.hit)) evaluationFailure = undefined;
          try { checkBoundary(); admission?.accept(input, result); }
          catch { return textResult({ status: 'profile-changed', threshold: 'unresolved' }, true); }
          present(ctx);
        }
        const usage = result.accounting?.measuredUsage ?? result.receipt?.usage;
        const measuredUsage = usage?.costUsd !== null && usage?.costUsd !== undefined ? {
          input: usage.inputTokens, output: usage.outputTokens, cacheRead: 0, cacheWrite: 0, totalTokens: usage.inputTokens + usage.outputTokens,
          cost: { input: usage.costUsd, output: 0, cacheRead: 0, cacheWrite: 0, total: usage.costUsd },
        } : undefined;
        const structuredContent: Json = JSON.parse(JSON.stringify(result));
        return { ...textResult(result, !['complete', 'incomplete'].includes(result.status)), structuredContent, usage: measuredUsage };
      },
    });
  }
  function installBoundary(ctx: ExtensionContext) {
    const currentEpoch = epoch;
    pi.registerTool({ name: 'read', label: 'Read project files', description: 'Read project text directly with path; need and offset/limit are optional. Strongly prefer Jev before unfamiliar discovery reads, but known targets and core instructions need no receipt. Native skill instructions and their exact local Markdown references work across projects. The installed Pi README, Markdown documentation and published example source/documentation read directly in every role without Jev; use the advertised Pi documentation and examples paths. Read PNG/JPEG/GIF/WebP images directly with path (need and exception: "image" optional), without selection, approval or Jev disclosure. Format/size and protected-file checks remain. Shell is a separate, unenforced relevance route.',
      parameters: Type.Object({ path: Type.String(), need: Type.Optional(Type.String()), offset: Type.Optional(Type.Integer({ minimum: 1 })), limit: Type.Optional(Type.Integer({ minimum: 1, maximum: 1000 })),
        receipt: Type.Optional(Type.String()), questionDigest: Type.Optional(Type.String()), evidence: Type.Optional(Type.String()),
        exception: Type.Optional(Type.Union([Type.Literal('instruction'), Type.Literal('required-evidence'), Type.Literal('image')])) }, { additionalProperties: false }),
      promptGuidelines: [enforcedRule],
      async execute(id, input, signal, onUpdate, toolCtx) {
        try {
          if (currentEpoch !== epoch || signal?.aborted) throw new PolicyDenied('stale-operation');
          checkBoundary();
          if (input.exception === 'image' || isImagePath(input.path, ctx.cwd)) {
            const image = images!.read(input);
            const validate = (path: string) => {
              if (currentEpoch !== epoch || signal?.aborted || path !== image.path) throw new PolicyDenied('stale-operation');
              checkBoundary();
            };
            // Native Pi handles image conversion/resizing; its operations see only the admitted snapshot.
            const tool = createReadToolDefinition(ctx.cwd, { operations: {
              access: async path => { validate(path); },
              detectImageMimeType: async path => { validate(path); return image.mimeType; },
              readFile: async path => { validate(path); return image.data; },
            } });
            const result = await tool.execute(id, { path: image.path }, signal, onUpdate, toolCtx);
            validate(image.path);
            if (images!.read(input).admission.digest !== image.admission.digest) throw new PolicyDenied('image-changed-retry');
            if (!result.content.some(block => block.type === 'image')) throw new PolicyDenied('image-processing-failed');
            observe({ kind: 'exception', reason: 'image', pathId: image.admission.pathId, needId: hash(input.need ?? ''), decision: 'admitted' });
            return { ...result, details: image.admission };
          }
          const value = admission!.read({ ...input, exception: input.exception });
          const continuation = value.admission.nextOffset ? `\n\n[More lines available; continue with offset ${value.admission.nextOffset}.]` : '';
          return { content: [{ type: 'text' as const, text: value.text + continuation }], details: value.admission };
        } catch (error) { const reason = safeCode(error); observe({ kind: 'blocked-read', reason, operationId: randomUUID(),
          pathId: hash(resolve(ctx.cwd, input.path)), needId: hash(input.need ?? '') }); return textResult({ blocked: reason,
            nextAction: inputRecovery(reason) ?? (input.exception === 'image' || isImagePath(input.path, ctx.cwd) ? imageRecovery(reason) :
              reason === 'repository-metadata-unavailable' ? repositoryRecovery :
              reason === 'unambiguous-evidence-required' ? 'Use exactly one receipt, exception or file_locate evidence token. The evidence field is not explanatory prose; never invent a witness.' :
              reason === 'native-documentation-changed' ? 'Installed Pi documentation or example source changed during this session. Restart Pi to load the current installation, then retry the direct read. No Jev request or enrollment change is needed.' :
              'Use path for ordinary project reads and installed Pi README/Markdown docs/published examples; offset/limit are optional. Explicit receipts/evidence must still be current. Protected or other outside-root files remain unavailable; do not bypass access restrictions with shell. Native instructions must come from current host metadata or exact linked Markdown references.') }, true); }
      },
    });
    function registerMutation<T extends TSchema, D, S>(name: 'write' | 'edit', base: ToolDefinition<T, D | undefined, S>) {
      const factory = name === 'write' ? createWriteToolDefinition : createEditToolDefinition;
      pi.registerTool({ ...base, async execute(id, args, signal, _onUpdate, toolCtx) {
        let operation;
        try {
          if (currentEpoch !== epoch) throw new PolicyDenied('stale-operation');
          checkBoundary();
          operation = guard!.begin(name, args, toolCtx.sessionManager.getSessionId(), toolCtx.cwd, signal);
          const tool = factory(toolCtx.cwd, { operations: fileOperations(guard!, operation) });
          await tool.execute(id, operation.input, operation.controller.signal, undefined, toolCtx);
          return { ...textResult({ status: 'applied', pathId: hash(operation.input.path) }), details: undefined };
        } catch (error) { const reason = safeCode(error); observe({ kind: 'blocked-write', reason }); return { ...textResult({ blocked: reason,
          nextAction: inputRecovery(reason) ?? (reason === 'stale-file-policy' ? 'Operator: /jev inspect, then /file-screening rebind to review the current connection for ALL enrolled projects; reload after saving. Do not repeat setup or reset accounting.' : reason === 'repository-metadata-unavailable' ? repositoryRecovery : reason === 'workflow-checkout-unavailable' ? 'The selected checkout is no longer registered. Return to a valid checkout; the operator can explicitly select the intended workflow with --checkout /absolute/path. No new enrollment is needed.' :
            reason === 'workflow-write-restricted' ? `The selected ${workflow.skill} workflow permits only its task reports/scratch and documented outputs in its selected checkout. Report implementation changes for Work; only the operator can change the workflow or checkout.` : 'Check the target and project exclusions. Secret files, live harness resources and operator state remain protected; .env.example and project skills are ordinary editable files for Engineer/Land.') }, true), details: undefined }; }
        finally { operation?.close(); }
      } });
    }
    registerMutation('write', createWriteToolDefinition(ctx.cwd));
    registerMutation('edit', createEditToolDefinition(ctx.cwd));
    pi.registerTool({ name: 'file_locate', label: 'Locate exact structural target', description: 'Locate one code definition with symbol, OR one exact ATX Markdown heading with heading (title without # markers; spaces allowed). Never put a heading in symbol. Returns evidence, offset and maxLines for read with the same path/need; no source. Authorized files up to 1 MiB; 32 shared lookups/session, unique match, at most 40 lines. No sibling or whole-file grant.',
      parameters: Type.Object({ path: Type.String(), need: Type.String(),
        symbol: Type.Optional(Type.String({ pattern: '^[A-Za-z_][A-Za-z0-9_]{2,127}$', description: 'Code identifier, not a Markdown heading; supply this OR heading' })),
        heading: Type.Optional(Type.String({ minLength: 1, maxLength: 128, description: 'Exact case-sensitive ATX Markdown title without # markers; spaces allowed; supply this OR symbol' })),
      }, { additionalProperties: false }),
      async execute(_id, input, signal) {
        try { if (currentEpoch !== epoch || signal?.aborted) throw new PolicyDenied('stale-operation'); checkBoundary(); return textResult(admission!.locate(input)); }
        catch (error) {
          const blocked = safeCode(error);
          const nextAction = blocked === 'invalid-structural-query' ? 'Supply path, need and exactly one of symbol (a code identifier, no spaces) or heading (an exact Markdown title without # markers; spaces allowed).'
            : blocked === 'exact-heading-target-required' ? 'Use one existing unique ATX heading title. Missing or duplicate headings grant no read; narrow with bounded structural search or obtain exact operator evidence.'
              : blocked === 'source-limit' ? 'Structural discovery is limited to 1 MiB per file; request a bounded artifact or an independently established exact obligation, not a shell-read bypass.'
                : 'Respect this hold; do not invent evidence, replenish lookup limits or silently fall back to shell.';
          return textResult({ blocked, nextAction }, true);
        }
      } });
    pi.setActiveTools(allowed);
  }
  registerScreen(false);
  pi.registerTool({ name: 'file_screening_status', label: 'Inspect file screening', description: 'Read-only enrollment, selected workflow and shared monthly accounting. Inspect workflow.skill/effect for active workflow authority; phase is the workflow role (Build also uses qa), not the selected skill. Never resolves credentials or makes a provider request.',
    parameters: Type.Object({}, { additionalProperties: false }), async execute(_id, _args, _signal, _update, ctx) { return textResult(present(ctx)); } });
  hooks.on('session_start', (_event, ctx) => {
    screening?.close(); admission?.close(); images?.close(); guard?.cancel('session-replaced'); epoch++;
    context = ctx; sessionId = ctx.sessionManager.getSessionId(); live = true; failure = undefined; inputFailure = undefined; evaluationFailure = undefined; authority = ''; sequence = 0; lastNotice = ''; lastWarning = '';
    workflow = new WorkflowAccess('review');
    try {
      workflow = WorkflowAccess.restore({ phase: process.env.FIGHT_SCREENING_PHASE, sessionId,
        taskId: process.env.FIGHT_SCREENING_TASK, checkout: process.env.FIGHT_SCREENING_CHECKOUT ??
          profiles.selection(ctx.cwd)?.repository.roots.filter(root => ctx.cwd === root || ctx.cwd.startsWith(root + '/')).sort((a, b) => b.length - a.length)[0] ?? ctx.cwd,
        entries: ctx.sessionManager.getEntries(), parentSession: ctx.sessionManager.getHeader()?.parentSession });
    } catch (error) { failure = safeCode(error); }
    owner = pi.getAllTools().find(tool => tool.name === 'jev_read_file')?.sourceInfo?.path ?? '';
    try { const selection = profiles.selection(ctx.cwd); selected = Boolean(selection); authority = selection?.identity ?? ''; }
    catch (error) { selected = true; failure = safeCode(error); }
    const required = pi.getFlag('fight-screening-required');
    if (required !== undefined && (!selected || required !== authority)) { selected = true; failure = 'required-enrollment-missing-or-changed'; }
    const settings = selected ? profiles.fileSettings(sessionId, ctx.cwd) : dependencies.settings ?? new FileScreeningSettings();
    screening = new FileScreening({ settings, configuration: dependencies.configuration, connect: authorize => connect('file-screen-v1', authorize), sessionId, cwd: ctx.cwd, observe });
    if (selected) {
      try {
        const selection = profiles.selection(ctx.cwd);
        if (!selection) throw new PolicyDenied('profile-unavailable');
        if (failure) throw new PolicyDenied(failure);
        persistRole(workflow.role);
        guard = new ToolPolicy({ policyPath: selection.enforcementPath, cwd: ctx.cwd,
          workflowAccess: { root: selection.project.root, editableInstructions: selection.project.instructions.map(i => i.path)
            .filter(path => !selection.project.excludedPaths.some(excluded => path === excluded || path.startsWith(excluded + '/'))),
            scope: () => profiles.repository(ctx.cwd), revision: () => workflow.revision,
            authorizeWrite: path => workflow.assertWrite(path, { ...profiles.repository(ctx.cwd),
              checkout: ctx.cwd, taskId: null }) },
          resources: [profiles.directory, rulePath, sourceDir, getAgentDir(), getPackageDir(), join(homedir(), 'Library/Keychains'), ...(owner && !owner.startsWith('<') ? [owner] : [])],
          sources: [...sources, ...(owner && !owner.startsWith('<') && !sources.includes(owner) ? [owner] : [])] });
        const state = guard.start(sessionId, { version: VERSION, platform: process.platform, hooks: hooks.available() });
        if (state.state !== 'active') throw new PolicyDenied(state.reason!);
        admission = new FileAdmission({ profiles, configuration: dependencies.configuration, sessionId, cwd: ctx.cwd, observe,
          nativeDocumentation: { readmePath: getReadmePath(), docsPath: getDocsPath(), examplesPath: getExamplesPath() } });
        images = new ImageAdmission({ profiles, sessionId, cwd: ctx.cwd,
          resources: [sourceDir, getAgentDir(), getPackageDir(), join(homedir(), 'Library/Keychains')] });
      } catch (error) { failure = safeCode(error); }
      installBoundary(ctx);
    }
    registerScreen(screening.availability() === 'available');
    if (selected) observe({ kind: 'session-bound', decision: failure ? 'held' : 'bound' });
    present(ctx, ctx.mode !== 'tui');
  });
  hooks.on('before_agent_start', (event, ctx) => {
    if (!selected) { registerScreen(screening?.availability() === 'available'); return; }
    const options = event.systemPromptOptions;
    try { admission?.ingestNativeInstructions(options.contextFiles, options.skills, workflow.skill); } catch (error) { failure = safeCode(error); }
    if (options.forceSystemPrompt || !event.systemPrompt.includes(enforcedRule) || !options.selectedTools.includes('read') || options.hiddenTools.includes('read')) failure = 'required-tool-or-rule-missing';
    present(ctx, ctx.mode !== 'tui');
  });
  hooks.on('input', event => {
    if (!selected) return;
    try {
      if (workflow.input(event.text, event.source, persistRole)) inputFailure = undefined;
    } catch (error) {
      const reason = safeCode(error);
      // Rejected operator input is recoverable; never overwrite or clear an integrity/authority hold.
      if (workflowInputFailures.has(reason)) inputFailure = reason;
      else failure ??= reason;
    }
  });
  hooks.on('tool_call', event => {
    if (!selected) return;
    // File-profile restrictions cover explicit file tools; native shell is unchanged.
    if (event.toolName === 'bash') return;
    const reason = event.parentToolCallId ? 'unsupported-nested-route' : !allowed.includes(event.toolName) ? 'unsupported-explicit-tool-route' : undefined;
    if (reason) { observe({ kind: 'blocked-read', reason, operationId: randomUUID(),
      pathId: hash('path' in event.input && typeof event.input.path === 'string' ? resolve(context!.cwd, event.input.path) : `tool:${event.toolName}`),
      needId: hash('need' in event.input && typeof event.input.need === 'string' ? event.input.need : '') }); return { block: true, reason: `File screening: ${reason}` }; }
  });
  const invalidate = () => { epoch++; failure = 'session-changed-reload-required'; admission?.close(); images?.close(); screening?.close(); guard?.cancel('session-changed'); };
  hooks.on('session_before_switch', invalidate); hooks.on('session_before_fork', invalidate); hooks.on('session_before_tree', invalidate);
  hooks.on('session_shutdown', (_event, ctx) => { live = false; invalidate(); if (ctx.mode === 'tui') ctx.ui.setStatus('fight-file-screen', undefined); });
  pi.registerCommand('file-screening', { description: 'Inspect or explicitly enroll/revoke/convert project screening; no provider calls or key entry', handler: async (args, ctx) => {
    const action = args.trim();
    if (action.startsWith('role ')) {
      if (ctx.mode !== 'tui') return;
      try {
        workflow.select(action.slice(5).trim(), persistRole);
        ctx.ui.notify(`Workflow role: ${workflow.role}. Existing file exclusions and workflow restrictions remain; shell is unchanged.`, 'info');
      } catch { ctx.ui.notify('Role selection failed; previous authority retained. Use /file-screening role engineer, reviewer, qa or land.', 'warning'); }
      return;
    }
    if (!['setup', 'inspect', '', 'instruction', 'target', 'reconcile', 'legacy', 'convert', 'rebind', 'revoke'].includes(action)) { if (ctx.mode === 'tui') ctx.ui.notify('Use /file-screening role engineer|reviewer|qa|land, inspect, setup, instruction, target, reconcile, legacy, convert, rebind or revoke. Never pass credentials.', 'warning'); return; }
    if (action === '' || action === 'inspect') { present(ctx, true); return; }
    if (ctx.mode !== 'tui') { console.error('File screening: operator changes require native TUI confirmation'); return; }
    const resources = [sourceDir, getAgentDir(), getPackageDir(), join(homedir(), 'Library/Keychains')];
    try {
      if (action === 'rebind') {
        const configuration = dependencies.configuration();
        if (!configuration) throw new PolicyDenied('invalid-setup');
        const preview = profiles.previewRebind(configuration);
        if (!await reviewEnrollment(ctx, JSON.stringify({
          action: 'Review connection recovery for ALL enrolled projects', connection: configuration, enrollment: preview.profile,
          explanation: 'Bind every listed project to this connection. Existing roots, exclusions, instructions, disclosure permissions and protected runtime paths remain; spending ceilings can only decrease. No ledger, pending liability or finite test counter is reset. Cancel makes no change.' }, null, 2))) return;
        if (!await ctx.ui.confirm('Save connection recovery?', 'Apply the reviewed connection to all listed projects? Changed settings or enrollment will reject this preview.')) return;
        profiles.applyRebind(preview, resources); invalidate();
        ctx.ui.notify('Connection recovery saved for all enrolled projects; /reload. Existing permissions and all accounting retained.', 'info'); return;
      }
      if (action === 'convert') {
        const candidate = profiles.convert();
        if (!await ctx.ui.confirm('Explicitly convert formerly timeboxed enrollment to STANDING disclosure', JSON.stringify({ ...candidate,
          warning: 'ALL listed roots/source exclusions and budgets remain. Approve standing authorized file disclosure to the pinned evaluator, no expiry or routine renewal. Commands are never sent. Shell bypasses relevance; this is not a sandbox. Cancel preserves the bounded profile. No accounting or finite test grants change.' }, null, 2))) return;
        profiles.save(candidate, resources); invalidate(); ctx.ui.notify('Explicit conversion saved; /reload. All ledgers and finite test counters retained.', 'info'); return;
      }
      if (action === 'revoke') {
        const profile = profiles.load();
        if (!profile) throw new PolicyDenied('profile-unavailable');
        if (!await ctx.ui.confirm('Revoke this enrollment for ALL listed roots', JSON.stringify(profile.projects.map(p => p.root)))) return;
        // Historical profiles can be revoked without first granting permanent disclosure.
        profiles.save({ ...profile, version: 'screening-profile-v2', revision: randomUUID(), expiresAt: null, commandDisclosure: false, revoked: true }, resources);
        invalidate(); ctx.ui.notify('Enrollment revoked; existing/resumed tools hold. Accounting and pending effects retained; already-running shell/remote effects are not guaranteed stopped.', 'warning'); return;
      }
      if (action === 'legacy') {
        const snapshot = ledger.legacySnapshot();
        if (!await ctx.ui.confirm('Conservative legacy import — stop ALL legacy writers first', JSON.stringify({ ...snapshot,
          warning: 'Undated settled exposure is charged in full to this Chicago month. Unknown pending exposure binds every month until independently reconciled. Changed records add exposure, never reset it. Confirm only after all old clients are quiescent.' }, null, 2))) return;
        const evidence = await ctx.ui.input('Nonsecret legacy inventory/quiescence evidence identifier');
        if (!evidence) return;
        ledger.importLegacy(snapshot.digest, evidence); present(ctx, true); return;
      }
      if (action === 'reconcile') {
        const legacy = ledger.legacyPending();
        if (legacy.length) {
          const digest = await ctx.ui.select('Reconcile undated legacy pending effect, or cancel to inspect new reservations', legacy.map(p => p.digest));
          if (digest) {
            const amount = await ctx.ui.input('Independently verified actual integer micro-USD (unknown must remain reserved)');
            const month = await ctx.ui.input('Verified billing month YYYY-MM in America/Chicago');
            const evidence = await ctx.ui.input('Nonsecret independently verified billing evidence identifier');
            if (amount === undefined || !month || !evidence || !await ctx.ui.confirm('Settle legacy pending exposure once?', JSON.stringify({ digest, amount: Number(amount), month, evidence }))) return;
            ledger.reconcileLegacy(digest, Number(amount), month, evidence); present(ctx, true); return;
          }
        }
        const pending = ledger.inspect().pending;
        if (!pending.length) { ctx.ui.notify('No outstanding new reservations. Unknown legacy exposure remains charged until verified.', 'info'); return; }
        const id = await ctx.ui.select('Reconcile only an independently verified billed outcome; unknown stays reserved', pending.map(p => p.id));
        if (!id) return;
        const amount = await ctx.ui.input('Actual integer micro-USD, rounded upward (zero requires proof of no charge)');
        if (amount === undefined) return;
        const evidence = await ctx.ui.input('Nonsecret billing/dispatch evidence reference (identifier only; no bodies/keys)');
        if (!evidence || !/^[A-Za-z0-9._:/-]{1,128}$/.test(evidence)) return;
        if (!await ctx.ui.confirm('Settle this reservation once?', JSON.stringify({ id, amount: Number(amount), evidence, warning: 'Never release an uncertain request or replay it. Historical month and session attribution remain unchanged.' }))) return;
        ledger.settle(id, Number(amount), evidence); present(ctx, true); return;
      }
      if (action === 'instruction' || action === 'target') {
        const path = await ctx.ui.input('Exact file path (one file, no globs)');
        if (!path) return;
        const purpose = action === 'target' ? await ctx.ui.input('Exact edit/quote/verification obligation and purpose (must match the read need)') : undefined;
        if (action === 'target' && !purpose) return;
        const offset = action === 'target' ? await ctx.ui.input('First required line (positive integer)') : undefined;
        const limit = action === 'target' ? await ctx.ui.input('Smallest useful line count (1-1000)') : undefined;
        const candidate = profiles.addWitness(ctx.cwd, action, { path, purpose, offset: Number(offset), limit: Number(limit) });
        if (!await ctx.ui.confirm('Preview exact per-file exception authority', JSON.stringify(candidate, null, 2))) return;
        profiles.save(candidate, resources); invalidate(); ctx.ui.notify('Exact source-bound witness saved; /reload. No budget reset or provider request.', 'info'); return;
      }
      const configuration = dependencies.configuration();
      if (!configuration) { ctx.ui.notify('Configure the pinned Jev connection with /jev setup first. No enrollment changed.', 'warning'); return; }
      const excluded = await ctx.ui.input('Additional absolute protected paths (semicolon-separated; blank if none)');
      if (excluded === undefined) return;
      const instructions = await ctx.ui.input('Optional extra instruction paths (semicolon-separated; project files and native skills already read directly)');
      if (instructions === undefined) return;
      if (!await ctx.ui.confirm('Optional standing file disclosure to Jev',
        'Until explicit revocation/authority change, allow authorized UTF-8 source, excluding secrets, to the pinned evaluator when the optional screening tool is called? Native shell commands do not use Jev. Shell can bypass relevance and access same-user state; no safety/sandbox guarantee. No enrollment expiry or routine renewal. Existing task/role approval, shared budget and finite test grants still bind. Cancel declines enrollment.')) return;
      const candidate = profiles.preview({ cwd: ctx.cwd, configuration, excludedPaths: excluded.split(';').map(v => v.trim()).filter(Boolean),
        instructions: instructions.split(';').map(v => v.trim()).filter(Boolean), providerDisclosure: true });
      if (!await reviewEnrollment(ctx, JSON.stringify({ ...candidate,
        monthly: { microUsd: 10000000, timeZone: 'America/Chicago', rollover: false },
        credentialResolution: 'Pi-managed OpenRouter; no credential resolution during setup',
        connection: { endpoint: configuration.endpoint, evaluator: configuration.evaluator },
        coverage: 'Direct project reads, optional Jev discovery and role-scoped file writes; native shell unchanged. Shell relevance bypass, scripts/descendants/plugins/SDK OS calls are outside interception. Not a sandbox.',
        sessionAllocation: 'At most 100000 micro-USD, further bounded by connection; 2688/request; shared account with file relevance, 8 questions/request, 2 outstanding, zero retries' }, null, 2))) return;
      if (!await ctx.ui.confirm('Save enrollment?', 'Save the reviewed standing authority until revoked or changed?')) return;
      profiles.save(candidate, resources); invalidate();
      ctx.ui.notify('Standing enrollment saved; /reload. No credentials resolved or paid calls. Preserve all ledgers, pending liabilities and finite test counters.', 'info');
    } catch (error) { ctx.ui.notify(`File screening setup: ${safeCode(error)}. Existing settings/accounting preserved.`, 'warning'); }
  } });
}
