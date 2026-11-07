import type { ExtensionAPI } from '@earendil-works/pi-coding-agent';
import { Type } from 'typebox';
import { runStage, type StageRequest } from './build/stages.mjs';
import { stageRouteSelected, selectStageRoute, sessionWorkflow } from './build/authority.mjs';
import { stageRole } from './stage-roles.mjs';

/** Execute fresh role owners only for an explicitly selected Build coordinator */
export default function installBuildStages(pi: ExtensionAPI) {
  pi.registerFlag('fight-build-stages', { type: 'string',
    description: 'Select sdk for fresh Work/Review/QA/Land through the bundled engine; requires actual Build authority' });
  let profile: unknown, running = false, unresolved = false;
  pi.on('session_start', (_event, ctx) => {
    selectStageRoute(ctx.sessionManager, pi.getFlag('fight-build-stages') === 'sdk');
    const prior = [...ctx.sessionManager.getEntries()].reverse().find(entry =>
      entry.type === 'custom' && entry.customType === 'fight-build-sdk-attempt-v1');
    unresolved = prior?.type === 'custom' && (prior.data as { executionSettled?: boolean })?.executionSettled !== true;
  });
  const shutdown = new Set<() => void>();
  pi.events.on('subagents:settings_loaded', event => { profile = (event as { settings?: unknown } | undefined)?.settings; });
  pi.events.on('subagents:settings_changed', () => { for (const cancel of shutdown) cancel(); });
  pi.on('session_shutdown', (_event, ctx) => {
    selectStageRoute(ctx.sessionManager, false);
    for (const cancel of shutdown) cancel(); shutdown.clear();
  });
  pi.registerTool({ name: 'fight_build_stage', label: 'Build stage (SDK)', defaultActive: false,
    description: 'Execute one fresh Work/Review/QA/Land owner through the bundled engine. Requires --fight-build-stages sdk and actual Build/TASK/checkout authority. No scheduler, fallback or acceptance; inspect canonical reports and original repair ledger before advancement.',
    prepareArguments(args) {
      const input = args as { role?: unknown };
      return { ...input, role: stageRole(input?.role) ?? input?.role } as StageRequest;
    },
    parameters: Type.Object({ task: Type.String({ pattern: '^TASK-\\d{5}$' }),
      role: Type.Union([Type.Literal('engineer'), Type.Literal('reviewer'), Type.Literal('qa'), Type.Literal('land')]),
      checkout: Type.String(), base: Type.String(), branch: Type.String(), context: Type.String(), record: Type.String(),
      head: Type.String({ pattern: '^[a-f0-9]{40}$' }), attempt: Type.String(),
      provider: Type.String(), model: Type.String(), thinking: Type.String(), timeout: Type.Integer({ minimum: 1, maximum: 21600 }),
      repair: Type.Boolean(), repairsUsed: Type.Integer({ minimum: 0, maximum: 10 }), repairLimit: Type.Integer({ minimum: 1, maximum: 10 }),
    }, { additionalProperties: false }),
    executionMode: 'sequential',
    async execute(_id, request, signal, update, ctx) {
      if (running || unresolved) throw new Error('Reconcile the existing SDK stage attempt before any replacement. No executor fallback.');
      running = true;
      try {
        const outcome = await runStage(pi, ctx, request, { selected: stageRouteSelected(ctx.sessionManager),
          registry: (globalThis as Record<symbol, unknown>)[Symbol.for('pi-subagents:manager')], profile, signal,
          dispatched(identity: unknown) {
            unresolved = true;
            pi.appendEntry('fight-build-sdk-attempt-v1', { ...(identity as object), executionSettled: false });
          },
          onShutdown(cancel: () => void) { shutdown.add(cancel); return () => shutdown.delete(cancel); },
          progress(value: unknown) { update?.({ content: [{ type: 'text', text: JSON.stringify(value) }], details: value }); },
        });
        unresolved = outcome.executionSettled !== true;
        pi.appendEntry('fight-build-sdk-attempt-v1', outcome);
        return { content: [{ type: 'text', text: JSON.stringify(outcome) }], details: outcome,
          isError: !outcome.admitted || outcome.state !== 'completed' || !!outcome.reason || !outcome.executionSettled };
      } finally { running = false; }
    },
  });
  pi.on('before_agent_start', (_event, ctx) => {
    const workflow = sessionWorkflow(ctx.sessionManager);
    const active = pi.getActiveTools().filter(name => name !== 'fight_build_stage');
    if (stageRouteSelected(ctx.sessionManager) && workflow?.skill === 'build' && workflow.mode === 'default') active.push('fight_build_stage');
    pi.setActiveTools(active);
  });
}
