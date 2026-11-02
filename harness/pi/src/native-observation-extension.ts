import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { fileURLToPath } from 'node:url';
import { NativeObservation } from './observation/native-observation.mjs';
import { SubagentObserver } from './observation/subagents.mjs';

/** Observe actual native session lifetime without participating in execution */
export default function installNativeObservation(pi: ExtensionAPI) {
  pi.events.on('fight:workspace-observer-resource', (accept: unknown) => {
    if (typeof accept === 'function') accept(fileURLToPath(import.meta.url));
  });
  let observation: NativeObservation | undefined;
  let subagents: SubagentObserver | undefined;
  let observerTimer: ReturnType<typeof setInterval> | undefined;
  let epoch = 0;
  let currentContext: ExtensionContext | undefined;
  const status = () => ({ ...(subagents?.inspect() ?? { capability: 'native-observer-unavailable', active: 0 }),
    transport: !observation || observation.closed ? 'unavailable' : !observation.ready ? 'native-start-pending-or-unavailable'
      : observation.parentFile ? 'ready' : 'parent-file-pending' });
  const present = () => {
    if (currentContext?.mode !== 'tui') return;
    const state = status();
    currentContext.ui.setStatus('fight-subagents', state.active ? `Fight observing ${state.active} upstream agent(s)` : undefined);
  };
  pi.registerCommand('fight-subagents', {
    description: 'Inspect passive upstream Agent observation capabilities and finite refusal diagnostics',
    handler: async (_args, ctx) => {
      ctx.ui.notify(JSON.stringify(status()) + '\nSupported: top-level Agent tool with exact root identity. RPC/direct rootless routes refused. Reader close is passive; upstream runtime shutdown/reload aborts its agents.', 'info');
    },
  });
  const close = () => {
    epoch++; clearInterval(observerTimer); observerTimer = undefined;
    subagents?.close(); subagents = undefined; observation?.close(); observation = undefined;
    if (currentContext?.mode === 'tui') currentContext.ui.setStatus('fight-subagents', undefined);
    currentContext = undefined;
  };
  pi.events.on('subagents:started', event => { subagents?.started(event); });
  pi.events.on('subagents:completed', () => { subagents?.tick(); present(); });
  pi.events.on('subagents:failed', () => { subagents?.tick(); present(); });
  pi.events.on('subagents:disposed', () => { subagents?.close(); present(); });
  // Never await observation I/O on an execution/session notification. No event data is modified.
  const later = (ctx: ExtensionContext, start = false) => {
    const current = epoch, started = Number(process.hrtime.bigint() / 1000000n), wallUs = Date.now() * 1000;
    setImmediate(() => {
      if (epoch !== current) return;
      try {
        if (start && ctx.mode === 'tui') {
          currentContext = ctx;
          const resource = observation = new NativeObservation(ctx, { started, wallUs,
            refused: message => subagents?.refused(message) });
          subagents = new SubagentObserver(ctx.sessionManager.getSessionId(), message => {
            try { resource.syncSessionFile(ctx); resource.observeSubagent(message); } catch { subagents?.close(); }
          }, { changed: present });
          observerTimer = setInterval(() => { subagents?.tick(); present(); }, 1000);
          observerTimer.unref();
          void resource.start().catch(() => { if (observation === resource) close(); });
        }
        else observation?.syncSessionFile(ctx);
      } catch { close(); /* Association unavailable; ordinary execution remains wholly with Pi. */ }
    });
  };
  pi.on('session_start', (_event, ctx) => { close(); later(ctx, true); });
  pi.on('before_provider_request', (_event, ctx) => { later(ctx); });
  pi.on('tool_execution_start', (event, ctx) => {
    if (event.toolName === 'Agent' && typeof event.args?.resume === 'string') subagents?.resuming(event.args.resume);
    later(ctx);
  });
  pi.on('tool_execution_end', (_event, ctx) => { subagents?.tick(); later(ctx); });
  pi.on('agent_end', (_event, ctx) => { later(ctx); });
  // Before-switch/fork are cancellable proposals; target validation can also fail.
  // Only shutdown confirms teardown (new/resume/fork/reload/quit). Keep the current
  // route and its original context-start stamp until that supported boundary.
  pi.on('session_shutdown', (_event, ctx) => {
    try { observation?.syncSessionFile(ctx); } catch { /* No execution effect. */ }
    close();
  });
}
