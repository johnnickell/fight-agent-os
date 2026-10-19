import type { ExtensionAPI, ExtensionContext, ToolDefinition } from '@earendil-works/pi-coding-agent';
import { createBashToolDefinition, createEditToolDefinition, createWriteToolDefinition,
  getAgentDir, getPackageDir, VERSION } from '@earendil-works/pi-coding-agent';
import { dirname, join } from 'node:path';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { PolicyDenied, ToolPolicy } from './tool-policy/policy.mjs';
import { fileOperations, runScript } from './tool-policy/effects.mjs';
import { trackRuntimeHooks } from './tool-policy/runtime.mjs';

/** Install an opt-in deterministic boundary, never activate it from repository instructions */
export default function fightToolPolicy(pi: ExtensionAPI) {
  const hooks = trackRuntimeHooks(pi);
  const extensionPath = fileURLToPath(import.meta.url);
  const sourceDir = dirname(extensionPath);
  const guardedTools = ['write', 'edit', 'bash'] as const;
  let policy: ToolPolicy | undefined;
  let selected = false;
  let unavailable = 'not-started';
  let installed = false;
  let liveSession = false;

  pi.registerFlag('fight-tool-policy', { type: 'string',
    description: 'Select a private absolute machine-local policy file; deterministic protection only' });

  const status = () => {
    if (installed && policy && guardedTools.some((name) =>
      pi.getAllTools().find((tool) => tool.name === name)?.sourceInfo?.path !== extensionPath)) {
      policy.cancel('unsupported-tool-replacement');
    }
    return policy?.inspect() ?? { state: selected ? 'unavailable' : 'inactive',
      protection: 'deterministic protection only', reason: selected ? unavailable : 'not-selected',
      profile: undefined, revision: undefined, tools: [] as string[] };
  };
  function present(ctx: ExtensionContext, notify = false) {
    const state = status();
    if (ctx.mode === 'tui') {
      ctx.ui.setStatus('fight-policy', selected
        ? `Fight policy ${state.state}${state.profile ? ` ${state.profile}@${state.revision}` : ''} | deterministic protection only` : undefined);
      if (notify) ctx.ui.notify(JSON.stringify(state, null, 2), state.state === 'active' ? 'info' : 'warning');
    } else if (notify) console.error(`Fight policy: ${JSON.stringify(state)}`);
    return state;
  }
  function denied(error: unknown, ctx: ExtensionContext) {
    const safe = error instanceof PolicyDenied ? error : new PolicyDenied('operation-failed');
    // Reload invalidates Pi's old contexts; a late cancellation must not call that runtime/UI again.
    if (!liveSession) return safe;
    if (ctx.mode === 'tui') ctx.ui.notify(safe.message, 'warning');
    present(ctx);
    return safe;
  }
  function install(ctx: ExtensionContext) {
    const factories = { write: createWriteToolDefinition,
      edit: createEditToolDefinition, bash: createBashToolDefinition };
    for (const name of guardedTools) {
      const base = factories[name](ctx.cwd) as ToolDefinition;
      pi.registerTool({ ...base,
        description: `${base.description} Fight deterministic policy applies. Bash accepts only exact approved script/argv forms.`,
        async execute(id, args, signal, onUpdate, toolCtx) {
          let operation;
          try {
            if (!policy) throw new PolicyDenied(unavailable);
            operation = policy.begin(name, args, toolCtx.sessionManager.getSessionId(), toolCtx.cwd, signal);
            if (name === 'bash') {
              const started = performance.now();
              const result = await runScript(policy, operation);
              return { content: [{ type: 'text', text: result.output + (result.truncated ? '\n[Output truncated at 64 KiB]' : '') +
                `\n[Script exit ${result.exitCode}]` }], details: { exitCode: result.exitCode, truncated: result.truncated },
                structuredContent: { output: result.output, truncated: result.truncated, exit_code: result.exitCode,
                  wall_time_seconds: Math.round((performance.now() - started) / 100) / 10 },
                isError: result.exitCode !== 0 };
            }
            const definition = factories[name](toolCtx.cwd, { operations: fileOperations(policy, operation) });
            return await definition.execute(id, operation.input, operation.controller.signal, onUpdate, toolCtx);
          } catch (error) { throw denied(error, toolCtx); }
          finally { operation?.close(); }
        },
      });
    }
  }
  hooks.on('session_start', (_event, ctx) => {
    policy?.cancel('session-replaced');
    liveSession = true;
    installed = false;
    const path = pi.getFlag('fight-tool-policy');
    selected = path !== undefined;
    if (!selected) return;
    try {
      if (typeof path !== 'string') throw new PolicyDenied('invalid-policy-location');
      policy = new ToolPolicy({ policyPath: path, cwd: ctx.cwd,
        resources: [sourceDir, getAgentDir(), getPackageDir(), join(homedir(), '.ssh'), join(homedir(), '.aws'),
          join(homedir(), '.gnupg'), join(homedir(), 'Library/Keychains')],
        sources: [extensionPath, join(sourceDir, 'tool-policy/policy.mjs'), join(sourceDir, 'tool-policy/effects.mjs'),
          join(sourceDir, 'tool-policy/runtime.mjs')] });
      policy.start(ctx.sessionManager.getSessionId(), { version: VERSION, platform: process.platform,
        hooks: hooks.available() });
    } catch { policy = undefined; unavailable = 'policy-or-source-unavailable'; }
    install(ctx);
    installed = true;
    // Tool visibility alone is not exclusion: the pre-execution hook below also denies every other name.
    pi.setActiveTools(['read', ...(policy?.inspect().tools ?? []).filter((name: string) => name !== 'read')]);
    present(ctx, true);
  });
  hooks.on('tool_call', (event, ctx) => {
    if (!selected || event.toolName === 'read') return;
    const state = status();
    const reason = state.state !== 'active' ? state.reason : event.parentToolCallId ? 'unsupported-nested-route'
      : !guardedTools.some((name) => name === event.toolName) || !state.tools.includes(event.toolName) ? 'disallowed-tool' : undefined;
    if (reason) return { block: true, reason: denied(new PolicyDenied(reason), ctx).message };
    // This hook intentionally grants nothing: execution wrappers check the FINAL post-hook inputs.
  });
  hooks.on('user_bash', () => {
    if (selected) return { result: { output: 'Fight policy blocked: unsupported-user-shell-route',
      exitCode: 1, cancelled: false, truncated: false } };
  });
  const cancel = (_event: unknown, ctx: ExtensionContext) => { policy?.cancel('session-changed'); present(ctx); };
  hooks.on('session_before_switch', cancel);
  hooks.on('session_before_fork', cancel);
  hooks.on('session_before_tree', cancel);
  hooks.on('session_shutdown', (_event, ctx) => {
    liveSession = false;
    policy?.cancel('shutdown-or-reload');
    if (ctx.mode === 'tui') ctx.ui.setStatus('fight-policy', undefined);
  });
  pi.registerCommand('fight-policy', { description: 'Inspect selected deterministic policy, coverage and redacted restrictions',
    handler: async (_args, ctx) => { present(ctx, true); } });
}
