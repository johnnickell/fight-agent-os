import { randomUUID } from 'node:crypto';
import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { connection, Readiness } from './jev/readiness.mjs';
import { LocalSettings, SessionBudget } from './jev/local-store.mjs';
import { DecisionsTransport, PiCredentials } from './jev/adapters.mjs';

/** Install operator-only setup and fixed synthetic readiness without changing Pi's model or tools */
export default function jevExtension(pi: ExtensionAPI) {
  const settings = new LocalSettings();
  let readiness: Readiness | undefined;

  function refresh() {
    if (!readiness) return 'missing-session';
    try {
      const config = settings.load();
      if (!config) { readiness.invalidate(); return 'missing-setup'; }
      readiness.configure(config);
      return undefined;
    } catch {
      readiness.invalidate();
      return 'invalid-or-expired-setup';
    }
  }
  function present(ctx: ExtensionContext, failure?: string) {
    if (ctx.mode !== 'tui') return;
    const view = readiness?.inspect();
    ctx.ui.setStatus('fight-jev', `Jev: ${failure ?? view?.state ?? 'missing-session'}`);
  }
  pi.on('session_start', async (_event, ctx) => {
    readiness?.close();
    readiness = new Readiness({ credentials: new PiCredentials(ctx.modelRegistry), transport: new DecisionsTransport(),
      budget: new SessionBudget(ctx.sessionManager.getSessionId()) });
    present(ctx, refresh());
  });
  pi.on('session_shutdown', async (_event, ctx) => {
    readiness?.close(); readiness = undefined;
    if (ctx.mode === 'tui') ctx.ui.setStatus('fight-jev', undefined);
  });
  pi.registerCommand('jev', {
    description: 'Operator-only Jev setup, inspect or fixed synthetic check; never pass an API key',
    handler: async (args, ctx) => {
      if (ctx.mode !== 'tui') return;
      const action = args.trim() || await ctx.ui.select('Jev (no automatic calls)', ['inspect', 'setup', 'check']);
      if (!action) return;
      if (!['inspect', 'setup', 'check'].includes(action)) {
        ctx.ui.notify('Use /jev setup, /jev inspect or /jev check. Never pass credentials.', 'warning'); return;
      }
      if (action === 'setup') {
        if (process.platform !== 'darwin') { ctx.ui.notify('Jev: unsupported-host (only macOS local settings/runtime qualified)', 'warning'); return; }
        if (!await ctx.ui.confirm('Jev setup — no credential entry here',
          'Use Pi-managed OpenRouter authentication and the README. Select OpenRouter Decisions / Jev 1.13, expected build 20260917? No credentials are resolved during setup.')) return;
        const request = await ctx.ui.input('Request ceiling in integer micro-USD (1 USD = 1000000; reserve 2688)');
        if (request === undefined) return;
        const session = await ctx.ui.input('Session ceiling in integer micro-USD (reload preserves charges)');
        if (session === undefined) return;
        if (!await ctx.ui.confirm('Disclosure and price permission (valid 24 hours)',
          'Allow only the fixed synthetic kettle question to OpenRouter/TypeSafe; confirm current $0.042/M input, free output and 64000-token maximum at the model page. Real charges apply.')) return;
        try {
          const config = settings.save({ endpoint: connection.endpoint, model: connection.model, evaluator: connection.evaluator,
            price: connection.price,
            requestMicroUsd: Number(request), sessionMicroUsd: Number(session), disclosure: 'fixed-synthetic-only',
            priceConfirmedAt: Date.now(), revision: randomUUID() });
          readiness?.configure(config);
          ctx.ui.notify('Jev settings saved; readiness unchecked. No credential was read and no request was sent.', 'info');
        } catch { ctx.ui.notify('Jev: invalid-setup. Settings require positive integer ceilings, with request no larger than session.', 'warning'); }
        present(ctx); return;
      }
      const failure = refresh();
      if (action === 'inspect') {
        ctx.ui.notify(JSON.stringify({ setupFailure: failure ?? null, ...readiness?.inspect() }, null, 2), 'info');
        present(ctx, failure); return;
      }
      if (failure) { ctx.ui.notify(`Jev: ${failure}. Use /jev setup.`, 'warning'); present(ctx, failure); return; }
      const approvedIdentity = readiness?.inspect().configuration?.identity;
      if (!await ctx.ui.confirm('One billed synthetic readiness request',
        'Resolve Pi-managed OpenRouter authentication (its source may prompt) and send only the fixed kettle question? Reserve $0.002688; uncertain billing retains this amount. No retries.')) return;
      // Configuration may have changed while the confirmation was open.
      const changed = refresh();
      if (changed || !readiness || approvedIdentity !== readiness.inspect().configuration?.identity) {
        ctx.ui.notify('Jev: setup changed or unavailable; inspect and confirm again.', 'warning'); return;
      }
      const current = readiness;
      const outcome = await current.check();
      // Reload/session replacement owns a new UI context; never present an old completion there.
      if (current !== readiness) return;
      refresh();
      if (approvedIdentity !== readiness.inspect().configuration?.identity) {
        ctx.ui.notify('Jev: configuration changed; old result discarded.', 'warning'); present(ctx); return;
      }
      ctx.ui.notify(JSON.stringify(outcome), outcome.state === 'ready' ? 'info' : 'warning');
      present(ctx);
    },
  });
}
