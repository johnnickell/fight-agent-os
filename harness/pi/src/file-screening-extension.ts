import { readFileSync, readdirSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { dirname, join, resolve } from 'node:path';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { Type, type TSchema } from 'typebox';
import { createEditToolDefinition, createWriteToolDefinition, getAgentDir, getPackageDir, VERSION } from '@earendil-works/pi-coding-agent';
import type { ExtensionAPI, ExtensionContext, ToolDefinition } from '@earendil-works/pi-coding-agent';
import { FileSource, FileScreening, type ScreenResult } from './jev/file-screening.mjs';
import { FileScreeningSettings, readPrivate } from './jev/local-store.mjs';
import { ScreeningDispatch, buildMonitor } from './jev/screening-dispatch.mjs';
import { ScreeningBuild, defaultBuildModels, type BuildRole } from './jev/screening-build.mjs';
import { getSupportedThinkingLevels } from '@earendil-works/pi-ai';
import { ScreeningProfiles, hash } from './jev/screening-profile.mjs';
import { FileAdmission } from './jev/file-admission.mjs';
import { MonthlyLedger, ProfileBudget } from './jev/monthly-budget.mjs';
import { ToolPolicy, PolicyDenied } from './tool-policy/policy.mjs';
import { fileOperations } from './tool-policy/effects.mjs';
import type { ConsumerConnection, Connection, OperatorConfiguration, Json } from './jev/judgments.mjs';

const sourceDir = dirname(fileURLToPath(import.meta.url));
const buildInstructions = resolve(dirname(buildMonitor), '../SKILL.md');
const rule = readFileSync(new URL('../FILE_SCREENING_RULE.md', import.meta.url), 'utf8');
const enforcedRule = `Enrolled file screening is enforced. Discovery content needs a current positive Jev receipt. Call jev_read_file with an information need and typed question; then read with its admission receipt, questionDigest, identical need and explicit offset/limit. Ordinary reads without evidence return no source. Instruction exceptions require a trusted exact operator/host instruction witness; required-evidence exceptions require a trusted operator/host assignment or verified required-reference path/purpose/range. file_locate establishes bounded verified-symbol targets without returning source. Never invent evidence, infer batch permission or bypass a rejection through another tool. Shell, content search, alternate/nested tools and generic child launchers are denied. For an ordinary Build the native /screened-build command records one bounded authorization; file_screening_stage prepares/runs only its pinned fixed roles. file_screening_dispatch accepts only a preauthorized immutable single-use ticket; neither tool accepts shell, prompt, path, model or authority overrides. file_screening_work supplies identity and bounded exact effective-change evidence; check/commit/publication wait for protected external host receipts under existing authority, never Agent shell/raw output. file_screening_report records only the actual child's identity-validated bounded final report; file_screening_handoff separately admits exact predecessor-report ranges, never a transcript or source permission. Trusted native/build processes independently bind actual sessions under the same monthly cap. Child output stays private; dispatch returns safe status, not source or prose. Mandatory native context and trusted host assignment/reference intake establish exact per-file witnesses, never model claims. Inspect file_screening_status or /file-screening for precise setup/recovery actions. This qualified Harness boundary is not an OS sandbox.`;
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
const allowed = ['read', 'write', 'edit', 'jev_read_file', 'file_locate', 'file_screening_status', 'file_screening_dispatch', 'file_screening_stage', 'file_screening_handoff', 'file_screening_report', 'file_screening_work'];
// Pin every executable admission/evaluator dependency, not only this entry module.
const sources = [fileURLToPath(import.meta.url), ...['jev', 'tool-policy'].flatMap(dir => readdirSync(join(sourceDir, dir))
  .filter(name => /\.(mjs|py)$/.test(name)).map(name => join(sourceDir, dir, name))), join(sourceDir, 'jev-extension.ts'), buildMonitor, buildInstructions, join(sourceDir, 'screening-bootstrap.mjs')];

type Dependencies = { settings?: FileScreeningSettings; configuration: () => OperatorConfiguration | null;
  profiles: ScreeningProfiles; credentialConfigured: (ctx: ExtensionContext) => boolean };

/** Couple local enrollment, true receipt admission and visible fail-closed native tool coverage */
export function installFileScreening(pi: ExtensionAPI, dependencies: Dependencies) {
  const profiles = dependencies.profiles;
  pi.registerFlag('fight-screening-required', { type: 'string', description: 'Trusted launcher profile identity; missing or changed enrollment holds all discovery' });
  let screening: FileScreening | undefined;
  let admission: FileAdmission | undefined;
  let guard: ToolPolicy | undefined;
  let selected = false, live = false, failure: string | undefined, evaluationFailure: string | undefined;
  let context: ExtensionContext | undefined;
  let sessionId = '', authority = '', owner = '', epoch = 0, sequence = 0;
  const ledger = new MonthlyLedger(profiles.directory);
  const dispatch = new ScreeningDispatch(profiles);
  const build = new ScreeningBuild(profiles, { dispatch });
  const safeCode = (error: unknown) => error instanceof Error && 'code' in error && typeof error.code === 'string' && /^[a-z-]{1,80}$/.test(error.code) ? error.code : 'profile-unavailable';
  function observe(event: Record<string, unknown>) {
    if (!live || !context) return;
    const observation = { version: 'fight-file-screen-observation-v1', session: hash(sessionId), sequence: ++sequence,
      phase: /^[A-Za-z0-9-]{1,48}$/.test(process.env.FIGHT_SCREENING_PHASE ?? '') ? process.env.FIGHT_SCREENING_PHASE : 'local', ...event };
    // No caller prose/paths, source bodies, command arguments, credentials or private reasoning.
    const line = JSON.stringify(observation);
    console.error(`Fight screening: ${line}`);
    if (context.mode === 'tui') context.ui.notify(`File screening: ${event.kind} ${event.reason ?? event.decision ?? ''}`, event.kind === 'blocked-read' ? 'warning' : 'info');
  }
  function connect(authorize: ConsumerConnection['authorize']) {
    let capability: Connection | { state: string } | undefined;
    pi.events.emit('fight:jev:connect', { callerId: 'file-screen-v1', authorize,
      connected: (value: Connection | { state: string }) => { capability = value; } });
    return capability;
  }
  function checkBoundary() {
    if (!selected) return;
    if (failure) throw new PolicyDenied(failure);
    try {
      const current = profiles.selection(context!.cwd);
      if (!current || current.identity !== authority) throw new PolicyDenied('profile-changed-reload-required');
      if (pi.getFlag('fight-tool-policy') !== undefined) throw new PolicyDenied('incompatible-tool-policy-selection');
      const state = guard?.inspect();
      if (state?.state !== 'active') throw new PolicyDenied(state?.reason ?? 'boundary-unavailable');
      if (!owner || allowed.some(name => pi.getAllTools().find(tool => tool.name === name)?.sourceInfo?.path !== owner)) throw new PolicyDenied('unsupported-tool-replacement');
      if (!pi.getActiveTools().includes('jev_read_file') || !pi.getActiveTools().includes('read')) throw new PolicyDenied('required-tool-hidden');
    } catch (error) {
      failure = safeCode(error); admission?.close(); screening?.close(); guard?.cancel(failure);
      throw new PolicyDenied(failure);
    }
  }
  function status(ctx: ExtensionContext) {
    let budget: ReturnType<ProfileBudget['inspect']> | null = null;
    let allocation: { requestMicroUsd: number; sessionMicroUsd: number } | null = null;
    let reason = failure ?? evaluationFailure;
    if (selected) {
      try {
        checkBoundary();
        const policy = profiles.selection(ctx.cwd)!.profile;
        allocation = { requestMicroUsd: policy.requestMicroUsd, sessionMicroUsd: policy.sessionMicroUsd };
        budget = new ProfileBudget(sessionId, profiles.directory, ledger).inspect(policy.sessionMicroUsd);
      }
      catch (error) { reason = safeCode(error); }
      if (!reason && !dependencies.credentialConfigured(ctx)) reason = 'credential-unavailable';
      if (!reason && screening?.availability() !== 'available') reason = screening?.availability() ?? 'screening-unavailable';
      if (budget?.legacy?.held) reason = 'legacy-reconciliation-required';
      if (!reason && budget?.halted) reason = 'price-bound-exceeded';
      if (!reason && (budget?.availableMicroUsd ?? 0) < 2688) reason = 'monthly-budget';
      if (!reason && (budget?.session.availableMicroUsd ?? 0) < 2688) reason = 'session-budget';
    }
    return { state: selected ? reason ? 'degraded' : 'active' : 'disabled', enforcement: selected ? reason ? 'held-with-bounded-exceptions' : 'receipt-enforced' : 'not-enrolled',
      reason: reason ?? (selected ? null : 'not-enrolled'), session: hash(sessionId),
      phase: /^[A-Za-z0-9-]{1,48}$/.test(process.env.FIGHT_SCREENING_PHASE ?? '') ? process.env.FIGHT_SCREENING_PHASE : 'local', budget, allocation,
      credential: selected && dependencies.credentialConfigured(ctx) ? 'configured-resolution-unchecked' : 'not-configured',
      bindingLimit: budget ? budget.availableMicroUsd <= budget.session.availableMicroUsd ? 'monthly' : 'session' : 'unavailable',
      coverage: selected ? 'Pi 1.2.0 native Agent tools and external build children; wrapped read/write/edit; shell/search/nested/generic-child denied; single-use operator/host-pinned build dispatch; generic child launchers denied' : 'none',
      dispatch: selected ? dispatch.inspect(ctx.cwd) : [],
      builds: selected ? build.inspect(ctx.cwd) : [],
      nextActionCode: !selected ? 'enroll-project' : reason === 'legacy-reconciliation-required' ? 'reconcile-legacy' : reason === 'credential-unavailable' ? 'configure-credentials' : reason === 'monthly-budget' ? 'wait-month-reset' : reason ? 'inspect-reload' : 'screen-then-read',
      nextAction: !selected ? 'Operator: /file-screening setup (preview before enrollment)' : reason === 'legacy-reconciliation-required' ? 'Operator: /file-screening legacy; quiesce legacy writers and conservatively import all undated liabilities; never reset accounting' : reason === 'credential-unavailable' ? 'Operator: configure Pi-managed OpenRouter, then /jev inspect; never enter keys here' :
        reason === 'monthly-budget' ? 'Wait until reset or inspect pending liabilities; never delete accounting' : reason ? 'Operator: /file-screening inspect; correct the named setup/coverage failure and reload. Bounded established exceptions remain available where access permits.' :
          'Use jev_read_file then receipt-bound read; configured credentials and live provider readiness are not proved by status' };
  }
  function present(ctx: ExtensionContext, notify = false) {
    const view = status(ctx);
    if (ctx.mode === 'tui') {
      ctx.ui.setStatus('fight-file-screen', `File screening: ${view.state}${view.reason ? ` (${view.reason})` : ''}${view.budget ? ` | available ${view.budget.availableMicroUsd}, reserved ${view.budget.reservedMicroUsd} micro-USD` : ''}`);
      if (notify) ctx.ui.notify(JSON.stringify(view, null, 2), view.state === 'active' ? 'info' : 'warning');
    } else if (notify) console.error(`Fight screening status: ${JSON.stringify(view)}`);
    return view;
  }
  function registerScreen(available: boolean) {
    pi.registerTool({ name: 'jev_read_file', label: 'Screen file relevance',
      description: 'Screen authorized files without returning contents. Supply exactly one of path, paths (1-50), or glob, information need and typed question. 32 KiB/file, 1 MiB/batch. Each current positive/cache result has its own admission receipt; no batch permission. No permission expansion.',
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
          evaluationFailure = (result.results ?? [result]).find(value => !['complete', 'incomplete'].includes(value.status))?.status;
          try { checkBoundary(); admission?.accept(input, result); }
          catch { return textResult({ status: 'profile-changed', threshold: 'unresolved' }, true); }
          present(ctx);
        }
        const usage = result.accounting?.measuredUsage ?? result.receipt?.usage;
        const measuredUsage = usage?.costUsd !== null && usage?.costUsd !== undefined ? {
          input: usage.inputTokens, output: usage.outputTokens, cacheRead: 0, cacheWrite: 0, totalTokens: usage.inputTokens + usage.outputTokens,
          cost: { input: usage.costUsd, output: 0, cacheRead: 0, cacheWrite: 0, total: usage.costUsd },
        } : undefined;
        // Normalize optional fields to the same JSON representation used by native transport.
        const structuredContent: Json = JSON.parse(JSON.stringify(result));
        return { ...textResult(result, !['complete', 'incomplete'].includes(result.status)), structuredContent, usage: measuredUsage };
      },
    });
  }
  function installBoundary(ctx: ExtensionContext) {
    const currentEpoch = epoch;
    pi.registerTool({ name: 'file_screening_dispatch', label: 'Dispatch approved screening child', exposure: 'model-only',
      description: 'Run one immutable operator/host-prepared build ticket listed by file_screening_status. No shell, prompt/path/model overrides, nested calls, recursive dispatch or child prose. Single use even on cancellation/crash; uncertain effects are never replayed. Returns safe completion/status observations only.',
      parameters: Type.Object({ ticket: Type.String({ pattern: '^[a-f0-9-]{36}$' }) }, { additionalProperties: false }),
      async execute(_id, input, signal, onUpdate) {
        try {
          const result = await dispatch.run(ctx.cwd, input.ticket, { signal, validate: () => {
            if (currentEpoch !== epoch) throw new PolicyDenied('stale-operation'); checkBoundary();
          }, observe: (event: Record<string, unknown>) => { observe(event); onUpdate?.(textResult(event)); } });
          return textResult(result, result.state !== 'completed');
        } catch (error) { return textResult({ blocked: safeCode(error) }, true); }
      } });
    pi.registerTool({ name: 'file_screening_stage', label: 'Run authorized Build role', exposure: 'model-only',
      description: 'Host prepares a fresh immutable attempt and runs one fixed role under the native screened-build authorization listed in status. No prompt/path/model/runtime overrides. Shared finite repair allowance, one role at a time, no recursive children. Read reports separately with file_screening_handoff.',
      parameters: Type.Object({ build: Type.String({ pattern: '^[a-f0-9-]{36}$' }), role: Type.Union([Type.Literal('engineer'), Type.Literal('review'), Type.Literal('qa'), Type.Literal('land')]) }, { additionalProperties: false }),
      async execute(_id, input, signal, onUpdate) {
        try { const result = await build.stage(ctx.cwd, input.build, input.role, { signal,
          validate: () => { if (currentEpoch !== epoch) throw new PolicyDenied('stale-operation'); checkBoundary(); },
          observe: event => { observe(event); onUpdate?.(textResult(event)); } }); return textResult(result, result.state !== 'completed'); }
        catch (error) { return textResult({ blocked: safeCode(error) }, true); }
      } });
    pi.registerTool({ name: 'file_screening_handoff', label: 'Read exact authorized role report', exposure: 'model-only',
      description: 'Separately authorized bounded report read, not automatic child output relay or discovery permission. Only a report ticket from this Build; children may read only predecessor reports. Role-authored untrusted evidence; no transcript/stderr/path access. At most 200 lines/8 KiB per read.',
      parameters: Type.Object({ build: Type.String(), ticket: Type.String(), offset: Type.Integer({ minimum: 1 }), limit: Type.Integer({ minimum: 1, maximum: 200 }) }, { additionalProperties: false }),
      async execute(_id, input) {
        try { if (currentEpoch !== epoch) throw new PolicyDenied('stale-operation'); checkBoundary();
          const result = build.handoff(ctx.cwd, input.build, input.ticket, input.offset, input.limit);
          observe({ kind: 'exception', reason: 'build-handoff', pathId: hash(input.ticket), needId: hash('inspect-role-handoff'), operationId: randomUUID() });
          return textResult(result); } catch (error) { return textResult({ blocked: safeCode(error) }, true); }
      } });
    pi.registerTool({ name: 'file_screening_work', label: 'Obtain bounded role work evidence', exposure: 'model-only',
      description: 'For this authorized Build: fixed identity metadata, effective-change manifest, or exact base/current source evidence ranges (200 lines/8 KiB). Check/commit/publication wait for protected external host completion under existing authority, not shell execution. Only Engineer/land can request commits; only land publication. No commands, caller paths, report prose or model overrides. A pending/unknown result is not success.',
      parameters: Type.Object({ build: Type.String(), operation: Type.Union([Type.Literal('identity'), Type.Literal('changes'), Type.Literal('evidence'), Type.Literal('check'), Type.Literal('commit'), Type.Literal('publication')]),
        evidence: Type.Optional(Type.String({ pattern: '^[a-f0-9]{64}$' })), side: Type.Optional(Type.Union([Type.Literal('base'), Type.Literal('current')])),
        offset: Type.Optional(Type.Integer({ minimum: 1 })), limit: Type.Optional(Type.Integer({ minimum: 1, maximum: 200 })) }, { additionalProperties: false }),
      async execute(_id, input, signal, onUpdate) {
        try {
          const result = await build.work(ctx.cwd, input.build, input, { signal,
            validate: () => { if (currentEpoch !== epoch) throw new PolicyDenied('stale-operation'); checkBoundary(); },
            onPending: event => { observe({ kind: 'work-required', reason: `external-${input.operation}`, decision: 'pending', operationId: event.request }); onUpdate?.(textResult(event)); } });
          if (['check', 'commit', 'publication'].includes(input.operation)) observe({ kind: 'work-complete', reason: `external-${input.operation}`, decision: result.outcome, operationId: result.request });
          if (input.operation === 'evidence') observe({ kind: 'exception', reason: 'assigned-effective-change-evidence', pathId: input.evidence,
            needId: hash('inspect-assigned-effective-changes'), operationId: randomUUID(), offset: input.offset, lines: input.limit });
          return textResult(result, result.outcome === 'fail' || result.outcome === 'unknown');
        } catch (error) { return textResult({ blocked: safeCode(error) }, true); }
      } });
    pi.registerTool({ name: 'file_screening_report', label: 'Record own Build role handoff', exposure: 'model-only',
      description: 'Child only: record ONE final Markdown report (at most 32 KiB) at the host-pinned stage handoff. Independent Review/QA also publish to their exact canonical TASK report path. No caller path or other role write. Never include credentials, source bodies or private reasoning.',
      parameters: Type.Object({ markdown: Type.String({ minLength: 1, maxLength: 32768 }) }, { additionalProperties: false }),
      async execute(_id, input) {
        try { if (currentEpoch !== epoch) throw new PolicyDenied('stale-operation'); checkBoundary(); return textResult(build.report(ctx.cwd, input.markdown, sessionId)); }
        catch (error) { return textResult({ blocked: safeCode(error) }, true); }
      } });
    pi.registerTool({ name: 'read', label: 'Admit file contents', description: 'Read only an explicit useful range after current screening or verified exception. Missing need/evidence returns no source. Supply admission.receipt, questionDigest and identical need from jev_read_file; or a trusted operator/native instruction, host assignment/required-reference exception or file_locate evidence.',
      parameters: Type.Object({ path: Type.String(), need: Type.Optional(Type.String()), offset: Type.Optional(Type.Integer({ minimum: 1 })), limit: Type.Optional(Type.Integer({ minimum: 1, maximum: 1000 })),
        receipt: Type.Optional(Type.String()), questionDigest: Type.Optional(Type.String()), evidence: Type.Optional(Type.String()),
        exception: Type.Optional(Type.Union([Type.Literal('instruction'), Type.Literal('required-evidence')])) }, { additionalProperties: false }),
      promptGuidelines: [enforcedRule],
      async execute(_id, input, signal) {
        try {
          if (currentEpoch !== epoch || signal?.aborted) throw new PolicyDenied('stale-operation');
          checkBoundary();
          const value = admission!.read(input);
          return { content: [{ type: 'text', text: value.text }], details: value.admission };
        } catch (error) { const reason = safeCode(error); observe({ kind: 'blocked-read', reason, operationId: randomUUID(),
          pathId: hash(resolve(ctx.cwd, input.path)), needId: hash(input.need ?? '') }); return textResult({ blocked: reason, nextAction: 'Supply need, explicit offset/limit and a current screening receipt or independently established exception; no source released' }, true); }
      },
    });
    function registerMutation<T extends TSchema, D, S>(name: 'write' | 'edit', base: ToolDefinition<T, D | undefined, S>) {
      const factory = name === 'write' ? createWriteToolDefinition : createEditToolDefinition;
      pi.registerTool({ ...base, async execute(id, args, signal, onUpdate, toolCtx) {
        let operation;
        try {
          if (currentEpoch !== epoch) throw new PolicyDenied('stale-operation');
          checkBoundary();
          operation = guard!.begin(name, args, toolCtx.sessionManager.getSessionId(), toolCtx.cwd, signal);
          const tool = factory(toolCtx.cwd, { operations: fileOperations(guard!, operation) });
          await tool.execute(id, operation.input, operation.controller.signal, undefined, toolCtx);
          // Native edit diffs and diagnostic previews are content-bearing; never return them unscreened.
          return { ...textResult({ status: 'applied', pathId: hash(operation.input.path) }), details: undefined };
        } catch (error) { const reason = safeCode(error); observe({ kind: 'blocked-write', reason }); return { ...textResult({ blocked: reason }, true), details: undefined }; }
        finally { operation?.close(); }
      } });
    }
    registerMutation('write', createWriteToolDefinition(ctx.cwd));
    registerMutation('edit', createEditToolDefinition(ctx.cwd));
    pi.registerTool({ name: 'file_locate', label: 'Locate exact definition', description: 'Verify one exact class/function/interface/trait/enum/type definition in an authorized file. Returns location/evidence only, no source. 32 lookups/session, one unique match, at most 40 target lines.',
      parameters: Type.Object({ path: Type.String(), symbol: Type.String(), need: Type.String() }, { additionalProperties: false }),
      async execute(_id, input, signal) {
        try { if (currentEpoch !== epoch || signal?.aborted) throw new PolicyDenied('stale-operation'); checkBoundary(); return textResult(admission!.locate(input)); }
        catch (error) { return textResult({ blocked: safeCode(error) }, true); }
      } });
    pi.setActiveTools(allowed);
  }
  registerScreen(false);
  pi.registerTool({ name: 'file_screening_status', label: 'Inspect file screening', description: 'Read-only local screening setup, declared coverage and shared monthly accounting; never resolves credentials or makes a provider request.',
    parameters: Type.Object({}, { additionalProperties: false }), async execute(_id, _args, _signal, _update, ctx) { return textResult(present(ctx)); } });
  pi.on('session_start', (_event, ctx) => {
    screening?.close(); admission?.close(); guard?.cancel('session-replaced'); epoch++;
    context = ctx; sessionId = ctx.sessionManager.getSessionId(); live = true; failure = undefined; evaluationFailure = undefined; authority = '';
    owner = pi.getAllTools().find(tool => tool.name === 'jev_read_file')?.sourceInfo?.path ?? '';
    try { const selection = profiles.selection(ctx.cwd); selected = Boolean(selection); authority = selection?.identity ?? ''; }
    catch (error) { selected = true; failure = safeCode(error); }
    const required = pi.getFlag('fight-screening-required');
    if (required !== undefined && (!selected || required !== authority)) { selected = true; failure = 'required-enrollment-missing-or-changed'; }
    const settings = selected ? profiles.fileSettings(sessionId, ctx.cwd) : dependencies.settings ?? new FileScreeningSettings();
    screening = new FileScreening({ settings, configuration: dependencies.configuration, connect, sessionId, cwd: ctx.cwd, observe });
    if (selected) {
      try {
        const selection = profiles.selection(ctx.cwd);
        if (!selection) throw new PolicyDenied('profile-unavailable');
        guard = new ToolPolicy({ policyPath: selection.enforcementPath, cwd: ctx.cwd,
          resources: [profiles.directory, sourceDir, dirname(dirname(buildMonitor)), getAgentDir(), getPackageDir(), join(homedir(), 'Library/Keychains'), ...(owner && !owner.startsWith('<') ? [owner] : [])],
          sources: [...sources, ...(owner && !owner.startsWith('<') && !sources.includes(owner) ? [owner] : [])] });
        const state = guard.start(sessionId, { version: VERSION, platform: process.platform, hooks: true });
        if (state.state !== 'active') throw new PolicyDenied(state.reason!);
        let intake = null;
        const intakePath = process.env.FIGHT_SCREENING_INTAKE;
        if (intakePath) {
          if (!intakePath.startsWith(join(profiles.directory, 'dispatch') + '/') || !/\/[a-f0-9-]{36}\/intake\.json$/.test(intakePath)) throw new PolicyDenied('invalid-trusted-intake');
          const ticket = readPrivate(join(dirname(intakePath), 'ticket.json'), null);
          intake = readPrivate(intakePath, null);
          if (!ticket || ticket.authority !== authority || ticket.expiresAt <= Date.now() || ticket.intake !== hash(JSON.stringify(intake))) throw new PolicyDenied('stale-trusted-intake');
        }
        admission = new FileAdmission({ profiles, configuration: dependencies.configuration, sessionId, cwd: ctx.cwd, observe, intake });
      } catch (error) { failure = safeCode(error); }
      installBoundary(ctx);
    }
    registerScreen(screening.availability() === 'available');
    if (selected) observe({ kind: 'session-bound', decision: failure ? 'held' : 'bound' });
    present(ctx, true);
  });
  pi.on('before_agent_start', (event, ctx) => {
    if (!selected) { registerScreen(screening?.availability() === 'available'); return; }
    const options = event.systemPromptOptions;
    admission?.ingestNativeInstructions(options.contextFiles);
    if (options.forceSystemPrompt || !event.systemPrompt.includes(enforcedRule) || !options.selectedTools.includes('jev_read_file') || options.hiddenTools.includes('jev_read_file')) failure = 'required-tool-or-rule-missing';
    present(ctx, true);
  });
  pi.on('tool_call', (event) => {
    if (!selected) return;
    // Visibility is not a boundary. Every alternate/nested tool is denied before execution.
    const reason = event.parentToolCallId ? 'unsupported-nested-route' : !allowed.includes(event.toolName) ? 'uncovered-content-route' : undefined;
    if (reason) { observe({ kind: 'blocked-read', reason, operationId: randomUUID(),
      pathId: hash('path' in event.input && typeof event.input.path === 'string' ? resolve(context!.cwd, event.input.path) : `tool:${event.toolName}`),
      needId: hash('need' in event.input && typeof event.input.need === 'string' ? event.input.need : '') }); return { block: true, reason: `File screening: ${reason}` }; }
  });
  pi.on('user_bash', () => selected ? { result: { output: 'File screening: unsupported-user-shell-route', exitCode: 1, cancelled: false, truncated: false } } : undefined);
  const invalidate = () => { epoch++; failure = 'session-changed-reload-required'; admission?.close(); screening?.close(); guard?.cancel('session-changed'); };
  pi.on('session_before_switch', invalidate); pi.on('session_before_fork', invalidate); pi.on('session_before_tree', invalidate);
  pi.on('session_shutdown', (_event, ctx) => { live = false; invalidate(); if (ctx.mode === 'tui') ctx.ui.setStatus('fight-file-screen', undefined); });
  pi.registerCommand('screened-build', { description: 'Authorize/resume one bounded enrolled Build TASK using native host preparation; no per-child approval', handler: async (args, ctx) => {
    // Only an explicit native command can establish this grant; never an Agent tool or extension message.
    if (ctx.mode !== 'tui' || process.env.FIGHT_SCREENING_CHILD === '1') return;
    try {
      checkBoundary();
      if (!selected || !/^TASK-\d{5}$/.test(args.trim())) throw new PolicyDenied('build-requires-enrolled-task');
      const task = args.trim(), existing = build.inspect(ctx.cwd).find(item => item.task === task);
      if (existing) { ctx.ui.notify(JSON.stringify(existing), 'info'); return; }
      const contextPath = await ctx.ui.input('Exact resolved project-context Markdown path (main-role disclosure only, never sent to Jev)');
      if (!contextPath) return;
      const configuration = profiles.fileSettings(sessionId, ctx.cwd).load();
      if (!configuration) throw new PolicyDenied('profile-unavailable');
      const path = resolve(ctx.cwd, contextPath);
      const contextText = new FileSource().snapshot(path, { ...configuration, allowedRoots: [dirname(path)] }).text;
      const taskDefault = `planning/tasks/${task.slice(5)}-TASK.md`;
      const taskAnswer = await ctx.ui.input(`Exact accepted TASK record path (blank: ${taskDefault})`, taskDefault);
      if (taskAnswer === undefined) return;
      const taskPath = taskAnswer.trim() || taskDefault;
      const models = structuredClone(defaultBuildModels);
      if (ctx.model?.provider !== 'openai-codex') throw new PolicyDenied('build-role-model-map-required');
      for (const role of Object.keys(models) as BuildRole[]) {
        const model = await ctx.ui.input(`Pinned ${role} model ID, openai-codex (blank: ${models[role].model})`, models[role].model);
        if (model === undefined) return;
        models[role].model = model.trim() || models[role].model;
        const selectedModel = ctx.modelRegistry.find(models[role].provider, models[role].model);
        if (!selectedModel || !getSupportedThinkingLevels(selectedModel).some(level => level === models[role].thinking)) throw new PolicyDenied('build-role-model-unavailable');
      }
      const repairAnswer = await ctx.ui.input('Shared repair allowance (1-10; blank uses 5; repeated invocation never resets it)', '5');
      const timeoutAnswer = await ctx.ui.input('Maximum seconds per role (1-1800; blank uses 1800)', '1800');
      if (repairAnswer === undefined || timeoutAnswer === undefined) return;
      const repairs = repairAnswer.trim() || '5', timeout = timeoutAnswer.trim() || '1800';
      if (!await ctx.ui.confirm('Authorize ONE TASK Build under existing source authority', JSON.stringify({ task, taskPath, contextDigest: hash(contextText), models,
        repairs: Number(repairs), timeout: Number(timeout), maxRoles: 3 * (Number(repairs) + 1) + 1,
        reportDisclosure: 'Exact bounded stage reports to coordinator and subsequent independent roles; Review/QA canonical report writes only',
        effects: 'Main-role inference, owned implementation commits, independent Review/QA and land publication. No merge/approval/force-push/release/deploy. No source-root, Jev disclosure or check capability expansion. Unknown effects hold, never replay.' }, null, 2))) return;
      checkBoundary(); // Dialog time cannot retain a stale instruction/profile boundary.
      const view = build.authorize({ cwd: ctx.cwd, task, taskPath, context: `Resolved project-context path: ${path}\n${contextText}`, models, repairs: Number(repairs), timeout: Number(timeout) });
      ctx.ui.notify(`Build ${view.build} ready; use file_screening_stage. No role launched.`, 'info');
      observe({ kind: 'exception', reason: 'mandatory-instruction', pathId: hash(buildInstructions), needId: hash('execute-authorized-build'), operationId: randomUUID() });
      observe({ kind: 'exception', reason: 'trusted-assignment', pathId: hash(path), needId: hash('load-resolved-project-context'), operationId: randomUUID() });
      // sendUserMessage does not expand skills by default; load this provider's selected instruction asset,
      // not a target checkout's potentially absent or colliding /skill:build alias.
      pi.sendUserMessage(`Owning provider Build instructions: ${buildInstructions}\n${readFileSync(buildInstructions, 'utf8')}\n\nExecute ${task} using authorized screened Build ${view.build}. Host owns attempt/request preparation; use file_screening_status, file_screening_stage and bounded file_screening_handoff. Never use mktemp/write outside roots or shell. Pinned role map: ${JSON.stringify(models)}.\nResolved project context from ${path} (already loaded verbatim; no new file/disclosure authority):\n${contextText}`, { deliverAs: 'followUp' });
    } catch (error) { ctx.ui.notify(`Screened Build: ${safeCode(error)}. Existing authority and counters retained.`, 'warning'); }
  } });
  pi.registerCommand('file-screening', { description: 'Inspect screening or trusted operator setup/preview (no provider calls, no key entry)', handler: async (args, ctx) => {
    const action = args.trim();
    if (!['setup', 'inspect', '', 'instruction', 'target', 'reconcile', 'legacy', 'dispatch'].includes(action)) { if (ctx.mode === 'tui') ctx.ui.notify('Use /file-screening inspect, setup, instruction, target, reconcile, legacy or dispatch. Never pass credentials.', 'warning'); return; }
    if (action === '' || action === 'inspect') { present(ctx, true); return; }
    if (ctx.mode !== 'tui') { console.error('File screening: operator changes require native TUI confirmation'); return; }
    try {
      if (action === 'legacy') {
        const snapshot = ledger.legacySnapshot();
        if (!await ctx.ui.confirm('Conservative legacy import — stop ALL legacy writers first', JSON.stringify({ ...snapshot,
          warning: 'Undated settled exposure is charged in full to this Chicago month. Unknown pending exposure binds every month until independently reconciled. Changed records add exposure, never reset it. Confirm only after all old clients are quiescent.' }, null, 2))) return;
        const evidence = await ctx.ui.input('Nonsecret legacy inventory/quiescence evidence identifier');
        if (!evidence) return;
        ledger.importLegacy(snapshot.digest, evidence); present(ctx, true); return;
      }
      if (action === 'dispatch') {
        const path = await ctx.ui.input('Exact prepared child request Markdown path under this project (no shell commands)');
        if (!path) return;
        const task = await ctx.ui.input('Authorized TASK ID');
        const role = await ctx.ui.input('Bounded stage label (letters, numbers, hyphens)');
        const timeout = await ctx.ui.input('Maximum runtime seconds (1-1800)', '300');
        if (!task || !role || !timeout || !ctx.model) return;
        const settings = profiles.fileSettings(sessionId, ctx.cwd).load();
        if (!settings) throw new PolicyDenied('profile-unavailable');
        const request = new FileSource().snapshot(resolve(ctx.cwd, path), settings).text;
        const plan = { cwd: ctx.cwd, request, task, role, provider: ctx.model.provider, model: ctx.model.id, thinking: ctx.thinkingLevel ?? 'off', timeout: Number(timeout) };
        if (!await ctx.ui.confirm('Authorize ONE native build child with unchanged enrollment', JSON.stringify({ ...plan, request: undefined, requestDigest: hash(request),
          warning: 'Main-provider inference is a separate effect from Jev. No recursive delegation, shell access, permission expansion or raw child output. Failed/uncertain tickets cannot replay.' }, null, 2))) return;
        const prepared = dispatch.prepare(plan);
        ctx.ui.notify(`Prepared single-use ticket ${prepared.id}; inspect file_screening_status. No child launched.`, 'info'); return;
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
        ledger.settle(id, Number(amount), evidence);
        present(ctx, true); return;
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
        profiles.save(candidate, [sourceDir, getAgentDir(), getPackageDir(), join(homedir(), 'Library/Keychains')]);
        ctx.ui.notify('Exact source-bound witness saved; /reload. No budget reset or provider request.', 'info'); return;
      }
      const configuration = dependencies.configuration();
      if (!configuration) { ctx.ui.notify('Configure the pinned Jev connection with /jev setup first. No enrollment changed.', 'warning'); return; }
      const excluded = await ctx.ui.input('Additional absolute protected paths (semicolon-separated; blank if none)');
      if (excluded === undefined) return;
      const instructions = await ctx.ui.input('Exact governing/selected-skill instruction paths (semicolon-separated; only explicitly listed files get direct exceptions)');
      if (instructions === undefined) return;
      const days = await ctx.ui.input('Enrollment expiry in days (1-90)', '30');
      if (days === undefined) return;
      const disclosure = await ctx.ui.confirm('Source disclosure to Jev', 'Allow UTF-8 source within this canonical project root, excluding protected paths/names, to the pinned OpenRouter/TypeSafe evaluator? This is separate from the $10/month planning approval. Cancel declines enrollment.');
      if (!disclosure) return;
      const candidate = profiles.preview({ cwd: ctx.cwd, configuration, excludedPaths: excluded.split(';').map(v => v.trim()).filter(Boolean),
        instructions: instructions.split(';').map(v => v.trim()).filter(Boolean), providerDisclosure: true, days: Number(days || '30') });
      const preview = { ...candidate, monthly: { microUsd: 10000000, timeZone: 'America/Chicago', rollover: false },
        credentialResolution: 'Pi-managed OpenRouter; no credential resolution during setup',
        connection: { endpoint: configuration.endpoint, evaluator: configuration.evaluator },
        coverage: 'Implementation candidate: Pi 1.2.0 native tools + immutable single-use build dispatch tickets. Read receipts; write/edit protected. Shell/search/nested/alternate/generic-child routes disabled. Prompt attachments/startup resources/trusted plugins are outside Agent discovery coverage. Not a sandbox.',
        sessionAllocation: 'At most 100000 micro-USD, further bounded by connection; 2688/request; 8 questions/request, 2 in-process outstanding, no retries' };
      if (!await ctx.ui.confirm('Preview effective file screening policy — explicit enrollment', JSON.stringify(preview, null, 2))) return;
      profiles.save(candidate, [sourceDir, getAgentDir(), getPackageDir(), join(homedir(), 'Library/Keychains')]);
      ctx.ui.notify('Enrollment saved outside repository instructions; no credentials resolved or paid calls. /reload to bind this session. Keep all existing ledgers and pending liabilities.', 'info');
    } catch (error) { ctx.ui.notify(`File screening setup: ${safeCode(error)}. Existing settings/accounting preserved.`, 'warning'); }
  } });
}
