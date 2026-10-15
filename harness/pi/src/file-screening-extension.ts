import { readFileSync, readdirSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { dirname, join, resolve } from 'node:path';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { Type, type TSchema } from 'typebox';
import { createBashToolDefinition, createLocalBashOperations, createEditToolDefinition, createWriteToolDefinition,
  getAgentDir, getPackageDir, VERSION } from '@earendil-works/pi-coding-agent';
import type { BashOperations, ExtensionAPI, ExtensionContext, ToolDefinition } from '@earendil-works/pi-coding-agent';
import { FileScreening, type ScreenResult } from './jev/file-screening.mjs';
import { FileScreeningSettings } from './jev/local-store.mjs';
import { ScreeningProfiles, hash } from './jev/screening-profile.mjs';
import { FileAdmission } from './jev/file-admission.mjs';
import { CommandRisk } from './jev/command-risk.mjs';
import { MonthlyLedger, ProfileBudget } from './jev/monthly-budget.mjs';
import { ToolPolicy, PolicyDenied } from './tool-policy/policy.mjs';
import { fileOperations } from './tool-policy/effects.mjs';
import type { ConsumerConnection, Connection, OperatorConfiguration, Json } from './jev/judgments.mjs';

const sourceDir = dirname(fileURLToPath(import.meta.url));
const rule = readFileSync(new URL('../FILE_SCREENING_RULE.md', import.meta.url), 'utf8');
const enforcedRule = `Standing enrolled profile: explicit file reads require a current positive Jev receipt or validated per-file exception. Use jev_read_file then read with its admission receipt, questionDigest, identical need and explicit offset/limit. Instruction and required-evidence exceptions need exact trusted witnesses; file_locate establishes bounded verified-symbol targets. Never invent evidence or infer batch permission. Ordinary authorized bash remains available with separate final-command Jev dangerous-operation assessment, not relevance screening. Shell source reads can bypass relevance admission. This is not universal enforcement, OS containment or protection against all same-user access to operator state. Never embed secrets in commands; command/context disclosure is bounded and source/environment/script contents are not inspected by the risk evaluator. Ordinary judgments are advisory, not task/role authority or a safety guarantee. Risky, uncertain, unavailable or denied judgments hold for an explicit operator decision; no hidden bypass. Preserve existing task/role permissions, independent roles, budgets and all finite test counters. Use the normal provider-owned Build skill/monitor and canonical role reports, not a replacement executor. Inspect file_screening_status or /file-screening for coverage and recovery.`;
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
const allowed = ['read', 'write', 'edit', 'bash', 'jev_read_file', 'file_locate', 'file_screening_status'];
const sources = [fileURLToPath(import.meta.url), ...['jev', 'tool-policy'].flatMap(dir => readdirSync(join(sourceDir, dir))
  .filter(name => /\.(mjs|py)$/.test(name)).map(name => join(sourceDir, dir, name))), join(sourceDir, 'jev-extension.ts'), join(sourceDir, 'screening-bootstrap.mjs')];
type Dependencies = { settings?: FileScreeningSettings; configuration: () => OperatorConfiguration | null;
  profiles: ScreeningProfiles; credentialConfigured: (ctx: ExtensionContext) => boolean };

/** Bind standing enrollment to explicit file admission and separate advisory command-risk assessment */
export function installFileScreening(pi: ExtensionAPI, dependencies: Dependencies) {
  const profiles = dependencies.profiles;
  pi.registerFlag('fight-screening-required', { type: 'string', description: 'Trusted launcher enrollment identity; missing/changed authority holds the native tools' });
  let screening: FileScreening | undefined, admission: FileAdmission | undefined, guard: ToolPolicy | undefined;
  let selected = false, live = false, failure: string | undefined, evaluationFailure: string | undefined;
  let context: ExtensionContext | undefined;
  let sessionId = '', authority = '', owner = '', epoch = 0, sequence = 0;
  const ledger = new MonthlyLedger(profiles.directory);
  const safeCode = (error: unknown) => error instanceof Error && 'code' in error && typeof error.code === 'string' && /^[a-z-]{1,80}$/.test(error.code) ? error.code : 'profile-unavailable';
  function observe(event: Record<string, unknown>) {
    if (!live || !context) return;
    const observation = { version: 'fight-file-screen-observation-v1', session: hash(sessionId), sequence: ++sequence,
      phase: /^[A-Za-z0-9-]{1,48}$/.test(process.env.FIGHT_SCREENING_PHASE ?? '') ? process.env.FIGHT_SCREENING_PHASE : 'local', ...event };
    console.error(`Fight screening: ${JSON.stringify(observation)}`);
    if (context.mode === 'tui') context.ui.notify(`File screening: ${event.kind} ${event.reason ?? event.decision ?? ''}`, ['blocked-read', 'command-held'].includes(String(event.kind)) ? 'warning' : 'info');
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
    try {
      const current = profiles.selection(context!.cwd);
      if (!current || current.identity !== authority) throw new PolicyDenied('profile-changed-reload-required');
      if (pi.getFlag('fight-tool-policy') !== undefined) throw new PolicyDenied('incompatible-tool-policy-selection');
      const state = guard?.inspect();
      if (state?.state !== 'active') throw new PolicyDenied(state?.reason ?? 'boundary-unavailable');
      if (!owner || allowed.some(name => pi.getAllTools().find(tool => tool.name === name)?.sourceInfo?.path !== owner)) throw new PolicyDenied('unsupported-tool-replacement');
      if (!['jev_read_file', 'read', 'bash'].every(name => pi.getActiveTools().includes(name))) throw new PolicyDenied('required-tool-hidden');
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
        if (!reason && !policy.commandDisclosure) reason = 'command-disclosure-not-approved';
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
      enforcement: selected ? reason ? 'held-with-bounded-exceptions' : 'explicit-file-receipts-and-command-risk' : 'not-enrolled',
      reason: reason ?? (selected ? null : 'not-enrolled'), session: hash(sessionId),
      phase: /^[A-Za-z0-9-]{1,48}$/.test(process.env.FIGHT_SCREENING_PHASE ?? '') ? process.env.FIGHT_SCREENING_PHASE : 'local', budget, allocation,
      credential: selected && dependencies.credentialConfigured(ctx) ? 'configured-resolution-unchecked' : 'not-configured',
      bindingLimit: budget ? budget.availableMicroUsd <= budget.session.availableMicroUsd ? 'monthly' : 'session' : 'unavailable',
      coverage: selected ? 'Pi 1.2.0 explicit read/write/edit; final native Agent bash and user-bash operations; preflighted provider Build children. Shell bypasses relevance; scripts/descendants/plugins/SDK OS calls are not intercepted or confined.' : 'none',
      enrollment: selected ? 'standing-until-revocation-or-authority-change; historical-profiles-require-explicit-conversion' : 'none',
      nextActionCode: !selected ? 'enroll-project' : reason === 'legacy-reconciliation-required' ? 'reconcile-legacy' : reason === 'credential-unavailable' ? 'configure-credentials' : reason === 'monthly-budget' ? 'wait-month-reset' : reason ? 'inspect-reload' : 'screen-then-read',
      nextAction: !selected ? 'Operator: /file-screening setup (preview before enrollment)' : reason === 'historical-profile-conversion-required' ? 'Operator: /file-screening convert; explicitly review all formerly timeboxed roots or revoke them' : reason === 'legacy-reconciliation-required' ? 'Operator: /file-screening legacy; quiesce old clients and preserve liabilities' : reason === 'credential-unavailable' ? 'Configure Pi-managed OpenRouter, then /jev inspect; never enter keys here' : reason === 'monthly-budget' ? 'Wait for reset or inspect liabilities; never delete accounting' : reason ? 'Operator: /file-screening inspect; correct authority/setup and reload. Established bounded file exceptions remain where access permits.' : 'Screen explicit discovery reads; use ordinary authorized shell with advisory command-risk assessment. No safety or universal relevance guarantee.' };
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
        const structuredContent: Json = JSON.parse(JSON.stringify(result));
        return { ...textResult(result, !['complete', 'incomplete'].includes(result.status)), structuredContent, usage: measuredUsage };
      },
    });
  }
  function shellOperations(ctx: ExtensionContext): BashOperations {
    const currentEpoch = epoch;
    const settings = pi.getSettings();
    const local = createLocalBashOperations({ shellPath: settings.shellPath });
    const risk = new CommandRisk({ profiles, configuration: dependencies.configuration, connect: authorize => connect('shell-risk-v1', authorize),
      sessionId, cwd: ctx.cwd, observe, context: () => ({ phase: /^[A-Za-z0-9-]{1,48}$/.test(process.env.FIGHT_SCREENING_PHASE ?? '') ? process.env.FIGHT_SCREENING_PHASE : 'local',
        shellPath: pi.getSettings().shellPath, commandPrefix: pi.getSettings().shellCommandPrefix }) });
    return { async exec(command, cwd, options) {
      const validate = () => {
        if (currentEpoch !== epoch || options.signal?.aborted) throw new PolicyDenied('stale-operation');
        checkBoundary();
        const current = pi.getSettings();
        if (settings.shellPath !== current.shellPath || settings.shellCommandPrefix !== current.shellCommandPrefix) throw new PolicyDenied('command-context-changed');
      };
      try {
        // Pi has applied final tool-call mutations and its prefix before this operations boundary.
        await risk.assess(command, cwd, { signal: options.signal, validate, confirm: async decision => {
          if (!ctx.hasUI) return false;
          return ctx.ui.confirm(`Command held: ${decision.outcome} (${decision.reason})`,
            `No safe verdict is claimed. Confirm this ONE exact command only if it is within existing task/role authority. No permission expansion.\nDirectory: ${decision.cwd}\n${decision.command}`);
        } });
        validate();
        return local.exec(command, cwd, options);
      } catch (error) {
        const reason = safeCode(error);
        observe({ kind: 'command-held', reason, decision: 'blocked', operationId: randomUUID(), pathId: hash(cwd), needId: hash(command) });
        throw new PolicyDenied(reason);
      }
    } };
  }
  function installBoundary(ctx: ExtensionContext) {
    const currentEpoch = epoch;
    pi.registerTool({ name: 'read', label: 'Admit file contents', description: 'Explicit bounded file read after current screening or verified exception. Supply need, offset/limit and admission receipt/questionDigest, exact instruction/required-evidence exception or file_locate evidence. Shell is a separate, unenforced relevance route.',
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
          pathId: hash(resolve(ctx.cwd, input.path)), needId: hash(input.need ?? '') }); return textResult({ blocked: reason, nextAction: 'Supply need, explicit range and a current receipt or independently established exception; no source released' }, true); }
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
        } catch (error) { const reason = safeCode(error); observe({ kind: 'blocked-write', reason }); return { ...textResult({ blocked: reason }, true), details: undefined }; }
        finally { operation?.close(); }
      } });
    }
    registerMutation('write', createWriteToolDefinition(ctx.cwd));
    registerMutation('edit', createEditToolDefinition(ctx.cwd));
    const baseBash = createBashToolDefinition(ctx.cwd);
    pi.registerTool({ ...baseBash, description: `${baseBash.description} Final-command dangerous-operation assessment uses shared Jev, not file relevance. No new task authority or safety guarantee. Never embed secrets.`,
      async execute(id, input, signal, onUpdate, toolCtx) {
        if (currentEpoch !== epoch) throw new PolicyDenied('stale-operation');
        checkBoundary();
        const settings = pi.getSettings();
        return createBashToolDefinition(toolCtx.cwd, { commandPrefix: settings.shellCommandPrefix, shellPath: settings.shellPath,
          operations: shellOperations(toolCtx) }).execute(id, structuredClone(input), signal, onUpdate, toolCtx);
      } });
    pi.registerTool({ name: 'file_locate', label: 'Locate exact definition', description: 'Verify one exact definition in an authorized file. Returns bounded location/evidence only, no source. 32 lookups/session, one unique match, at most 40 lines.',
      parameters: Type.Object({ path: Type.String(), symbol: Type.String(), need: Type.String() }, { additionalProperties: false }),
      async execute(_id, input, signal) {
        try { if (currentEpoch !== epoch || signal?.aborted) throw new PolicyDenied('stale-operation'); checkBoundary(); return textResult(admission!.locate(input)); }
        catch (error) { return textResult({ blocked: safeCode(error) }, true); }
      } });
    pi.setActiveTools(allowed);
  }
  registerScreen(false);
  pi.registerTool({ name: 'file_screening_status', label: 'Inspect file screening', description: 'Read-only enrollment, declared coverage and shared monthly accounting; never resolves credentials or makes a provider request.',
    parameters: Type.Object({}, { additionalProperties: false }), async execute(_id, _args, _signal, _update, ctx) { return textResult(present(ctx)); } });
  pi.on('session_start', (_event, ctx) => {
    screening?.close(); admission?.close(); guard?.cancel('session-replaced'); epoch++;
    context = ctx; sessionId = ctx.sessionManager.getSessionId(); live = true; failure = undefined; evaluationFailure = undefined; authority = ''; sequence = 0;
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
        guard = new ToolPolicy({ policyPath: selection.enforcementPath, cwd: ctx.cwd,
          resources: [profiles.directory, sourceDir, getAgentDir(), getPackageDir(), join(homedir(), 'Library/Keychains'), ...(owner && !owner.startsWith('<') ? [owner] : [])],
          sources: [...sources, ...(owner && !owner.startsWith('<') && !sources.includes(owner) ? [owner] : [])] });
        const state = guard.start(sessionId, { version: VERSION, platform: process.platform, hooks: true });
        if (state.state !== 'active') throw new PolicyDenied(state.reason!);
        admission = new FileAdmission({ profiles, configuration: dependencies.configuration, sessionId, cwd: ctx.cwd, observe });
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
    try { admission?.ingestNativeInstructions(options.contextFiles); } catch (error) { failure = safeCode(error); }
    if (options.forceSystemPrompt || !event.systemPrompt.includes(enforcedRule) || !options.selectedTools.includes('jev_read_file') || options.hiddenTools.includes('jev_read_file')) failure = 'required-tool-or-rule-missing';
    present(ctx, true);
  });
  pi.on('tool_call', event => {
    if (!selected) return;
    // Only declared native explicit tools are covered. Bash itself is assessed at FINAL operations, not here.
    const reason = event.parentToolCallId ? 'unsupported-nested-route' : !allowed.includes(event.toolName) ? 'unsupported-explicit-tool-route' : undefined;
    if (reason) { observe({ kind: 'blocked-read', reason, operationId: randomUUID(),
      pathId: hash('path' in event.input && typeof event.input.path === 'string' ? resolve(context!.cwd, event.input.path) : `tool:${event.toolName}`),
      needId: hash('need' in event.input && typeof event.input.need === 'string' ? event.input.need : '') }); return { block: true, reason: `File screening: ${reason}` }; }
  });
  pi.on('user_bash', (_event, ctx) => {
    if (!selected) return;
    try { checkBoundary(); return { operations: shellOperations(ctx) }; }
    catch (error) { return { result: { output: `File screening: ${safeCode(error)}`, exitCode: 1, cancelled: false, truncated: false } }; }
  });
  const invalidate = () => { epoch++; failure = 'session-changed-reload-required'; admission?.close(); screening?.close(); guard?.cancel('session-changed'); };
  pi.on('session_before_switch', invalidate); pi.on('session_before_fork', invalidate); pi.on('session_before_tree', invalidate);
  pi.on('session_shutdown', (_event, ctx) => { live = false; invalidate(); if (ctx.mode === 'tui') ctx.ui.setStatus('fight-file-screen', undefined); });
  pi.registerCommand('file-screening', { description: 'Inspect or explicitly enroll/revoke/convert project screening; no provider calls or key entry', handler: async (args, ctx) => {
    const action = args.trim();
    if (!['setup', 'inspect', '', 'instruction', 'target', 'reconcile', 'legacy', 'convert', 'revoke'].includes(action)) { if (ctx.mode === 'tui') ctx.ui.notify('Use /file-screening inspect, setup, instruction, target, reconcile, legacy, convert or revoke. Never pass credentials.', 'warning'); return; }
    if (action === '' || action === 'inspect') { present(ctx, true); return; }
    if (ctx.mode !== 'tui') { console.error('File screening: operator changes require native TUI confirmation'); return; }
    const resources = [sourceDir, getAgentDir(), getPackageDir(), join(homedir(), 'Library/Keychains')];
    try {
      if (action === 'convert') {
        const candidate = profiles.convert();
        if (!await ctx.ui.confirm('Explicitly convert formerly timeboxed enrollment to STANDING disclosure', JSON.stringify({ ...candidate,
          warning: 'ALL listed roots/source exclusions and budgets remain. Approve standing nonsecret command/context disclosure to the pinned evaluator, no expiry or routine renewal. Shell bypasses relevance; this is not a sandbox. Cancel preserves the bounded profile. No accounting or finite test grants change.' }, null, 2))) return;
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
      const instructions = await ctx.ui.input('Exact governing/selected-skill instruction paths (semicolon-separated; only listed files get direct exceptions)');
      if (instructions === undefined) return;
      if (!await ctx.ui.confirm('Standing source AND bounded nonsecret command disclosure to Jev',
        'Until explicit revocation/authority change, allow authorized UTF-8 source and final shell commands (<=16 KiB) with cwd/root/phase/shell settings, excluding secrets, to the pinned evaluator? No environment values, prompt/history or script bodies are fetched for command risk. Shell can bypass relevance and access same-user state; no safety/sandbox guarantee. No enrollment expiry or routine renewal. Existing task/role approval, shared budget and finite test grants still bind. Cancel declines enrollment.')) return;
      const candidate = profiles.preview({ cwd: ctx.cwd, configuration, excludedPaths: excluded.split(';').map(v => v.trim()).filter(Boolean),
        instructions: instructions.split(';').map(v => v.trim()).filter(Boolean), providerDisclosure: true, commandDisclosure: true });
      if (!await ctx.ui.confirm('Preview STANDING project enrollment — no periodic renewal', JSON.stringify({ ...candidate,
        monthly: { microUsd: 10000000, timeZone: 'America/Chicago', rollover: false },
        credentialResolution: 'Pi-managed OpenRouter; no credential resolution during setup',
        connection: { endpoint: configuration.endpoint, evaluator: configuration.evaluator },
        coverage: 'Explicit file receipts; final native Agent/user bash risk. Shell relevance bypass, scripts/descendants/plugins/SDK OS calls are outside interception. Not a sandbox.',
        sessionAllocation: 'At most 100000 micro-USD, further bounded by connection; 2688/request; shared account with file relevance, 8 questions/request, 2 outstanding, zero retries' }, null, 2))) return;
      profiles.save(candidate, resources); invalidate();
      ctx.ui.notify('Standing enrollment saved; /reload. No credentials resolved or paid calls. Preserve all ledgers, pending liabilities and finite test counters.', 'info');
    } catch (error) { ctx.ui.notify(`File screening setup: ${safeCode(error)}. Existing settings/accounting preserved.`, 'warning'); }
  } });
}
