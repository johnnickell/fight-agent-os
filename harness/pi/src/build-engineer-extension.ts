import type { ExtensionAPI } from '@earendil-works/pi-coding-agent';
import { Type } from 'typebox';
import { runEngineer } from './build/engineer.mjs';
import { sessionWorkflow } from './build/authority.mjs';

/** Offer one explicitly selected SDK Engineer route to the actual Build coordinator */
export default function installBuildEngineer(pi: ExtensionAPI) {
  pi.registerFlag('fight-build-engineer', { type: 'string',
    description: 'Explicitly select sdk for the real Build Engineer stage; other stages retain process execution' });
  let profile: unknown, running = false, unresolved = false;
  pi.on('session_start', (_event, ctx) => {
    const prior = [...ctx.sessionManager.getEntries()].reverse().find(entry =>
      entry.type === 'custom' && entry.customType === 'fight-build-sdk-attempt-v1');
    unresolved = prior?.type === 'custom' && (prior.data as { executionSettled?: boolean })?.executionSettled !== true;
  });
  const shutdown = new Set<() => void>();
  pi.events.on('subagents:settings_loaded', (event: { settings?: unknown }) => { profile = event?.settings; });
  pi.events.on('subagents:settings_changed', () => { for (const cancel of shutdown) cancel(); });
  pi.on('session_shutdown', () => { for (const cancel of shutdown) cancel(); shutdown.clear(); });
  pi.registerTool({ name: 'fight_build_engineer', label: 'Build Engineer (SDK)', defaultActive: false,
    description: 'Run the actual fresh Engineer/work stage through the bundled upstream engine. Requires explicit --fight-build-engineer sdk and current trusted Build/TASK/checkout authority. No fallback, publication or acceptance. Inspect canonical evidence before independent Review.',
    parameters: Type.Object({ task: Type.String({ pattern: '^TASK-\\d{5}$' }), role: Type.Literal('engineer'),
      checkout: Type.String(), head: Type.String({ pattern: '^[a-f0-9]{40}$' }), attempt: Type.String(),
      provider: Type.String(), model: Type.String(), thinking: Type.String(), timeout: Type.Integer({ minimum: 1, maximum: 21600 }),
    }, { additionalProperties: false }),
    executionMode: 'sequential',
    async execute(_id, request, signal, update, ctx) {
      if (running || unresolved) throw new Error('Reconcile the existing SDK Engineer attempt before any replacement. No executor fallback.');
      running = true;
      try {
        const outcome = await runEngineer(pi, ctx, request, { selected: pi.getFlag('fight-build-engineer') === 'sdk',
          registry: (globalThis as Record<symbol, unknown>)[Symbol.for('pi-subagents:manager')], profile, signal,
          dispatched() {
            unresolved = true;
            pi.appendEntry('fight-build-sdk-attempt-v1', { attempt: request.attempt, executionSettled: false });
          },
          onShutdown(cancel: () => void) { shutdown.add(cancel); return () => shutdown.delete(cancel); },
          progress(value: unknown) { update?.({ content: [{ type: 'text', text: JSON.stringify(value) }], details: value }); },
        });
        unresolved = outcome.executionSettled !== true;
        pi.appendEntry('fight-build-sdk-attempt-v1', outcome);
        return { content: [{ type: 'text', text: JSON.stringify(outcome) }], details: outcome,
          isError: !outcome.admitted || outcome.state !== 'completed' || !outcome.executionSettled };
      } finally { running = false; }
    },
  });
  // Enrollment may narrow the parent's tools at session_start. Activate only at
  // its turn boundary, after trusted workflow selection, never in the worker.
  pi.on('before_agent_start', (_event, ctx) => {
    if (pi.getFlag('fight-build-engineer') === 'sdk' && sessionWorkflow(ctx.sessionManager)?.skill === 'build') {
      pi.setActiveTools([...pi.getActiveTools(), 'fight_build_engineer']);
    }
  });
}
