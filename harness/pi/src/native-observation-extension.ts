import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { NativeObservation } from './observation/native-observation.mjs';

/** Observe actual native session lifetime without participating in execution */
export default function installNativeObservation(pi: ExtensionAPI) {
  let observation: NativeObservation | undefined;
  let epoch = 0;
  const close = () => { epoch++; observation?.close(); observation = undefined; };
  // Never await observation I/O on an execution/session notification. No event data is modified.
  const later = (ctx: ExtensionContext, start = false) => {
    const current = epoch, started = Number(process.hrtime.bigint() / 1000000n), wallUs = Date.now() * 1000;
    setImmediate(() => {
      if (epoch !== current) return;
      try {
        if (start && ctx.mode === 'tui') {
          const resource = observation = new NativeObservation(ctx, { started, wallUs });
          void resource.start().catch(() => { if (observation === resource) close(); });
        }
        else observation?.syncSessionFile(ctx);
      } catch { close(); /* Association unavailable; ordinary execution remains wholly with Pi. */ }
    });
  };
  pi.on('session_start', (_event, ctx) => { close(); later(ctx, true); });
  pi.on('before_provider_request', (_event, ctx) => { later(ctx); });
  pi.on('tool_execution_start', (_event, ctx) => { later(ctx); });
  pi.on('tool_execution_end', (_event, ctx) => { later(ctx); });
  pi.on('agent_end', (_event, ctx) => { later(ctx); });
  // Before-switch/fork are cancellable proposals; target validation can also fail.
  // Only shutdown confirms teardown (new/resume/fork/reload/quit). Keep the current
  // route and its original context-start stamp until that supported boundary.
  pi.on('session_shutdown', (_event, ctx) => {
    try { observation?.syncSessionFile(ctx); } catch { /* No execution effect. */ }
    close();
  });
}
