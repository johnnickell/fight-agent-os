import { randomUUID } from 'node:crypto';
import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { connection, Readiness } from './jev/readiness.mjs';
import { LocalSettings, SessionBudget } from './jev/local-store.mjs';
import { DecisionsTransport, PiCredentials } from './jev/adapters.mjs';
import { Judgments } from './jev/judgments.mjs';
import { retainSessionRequests } from './jev/session-requests.mjs';
import { installFileScreening } from './file-screening-extension.ts';
import { FileScreeningSettings } from './jev/local-store.mjs';
import { ScreeningProfiles } from './jev/screening-profile.mjs';
import { ProfileBudget } from './jev/monthly-budget.mjs';
import { installStatusFooter } from './status-footer.ts';

type Dependencies = {
  settings?: LocalSettings;
  credentials?: (ctx: ExtensionContext) => PiCredentials;
  transport?: DecisionsTransport;
  budget?: (sessionId: string) => SessionBudget;
  platform?: NodeJS.Platform;
  fileSettings?: FileScreeningSettings;
  profiles?: ScreeningProfiles;
  credentialConfigured?: (ctx: ExtensionContext) => boolean;
};

/** Install operator setup, shared judgments and opt-in file screening without changing Pi's model */
export default function jevExtension(pi: ExtensionAPI) { installJev(pi); }

/** Inject controlled host boundaries for qualification; normal loading always uses protected local adapters */
export function installJev(pi: ExtensionAPI, dependencies: Dependencies = {}) {
  installStatusFooter(pi);
  const settings = dependencies.settings ?? new LocalSettings();
  const profiles = dependencies.profiles ?? new ScreeningProfiles();
  let readiness: Readiness | undefined;
  let judgments: Judgments | undefined;
  let retained: ReturnType<typeof retainSessionRequests> | undefined;
  let disconnect: (() => void) | undefined;

  function refresh() {
    if (!readiness) return 'missing-session';
    try {
      const config = settings.load();
      if (!config) { readiness.invalidate(); judgments?.invalidate(); return 'missing-setup'; }
      const previous = readiness.inspect().configuration?.identity;
      readiness.configure(config);
      if (previous !== readiness.inspect().configuration?.identity) judgments?.invalidate();
      return undefined;
    } catch {
      readiness.invalidate(); judgments?.invalidate();
      return 'invalid-setup';
    }
  }
  function present(ctx: ExtensionContext, failure?: string) {
    if (ctx.mode !== 'tui') return;
    const view = readiness?.inspect();
    const state = failure ?? view?.state ?? 'missing-session';
    ctx.ui.setStatus('fight-jev', state === 'missing-setup' ? undefined : `Jev: ${state === 'unchecked' ? 'configured' : state}`);
  }
  function cleanup() {
    disconnect?.(); disconnect = undefined;
    judgments?.close(); judgments = undefined;
    readiness?.close(); readiness = undefined;
    retained?.release(); retained = undefined;
  }
  pi.on('session_start', async (_event, ctx) => {
    cleanup();
    const sessionId = ctx.sessionManager.getSessionId();
    retained = retainSessionRequests(sessionId);
    const shared = { credentials: dependencies.credentials?.(ctx) ?? new PiCredentials(ctx.modelRegistry),
      transport: dependencies.transport ?? new DecisionsTransport(),
      budget: dependencies.budget?.(sessionId) ?? (profiles.load() !== null ?
        new ProfileBudget(sessionId, profiles.directory) : new SessionBudget(sessionId)), scope: retained.scope,
      platform: dependencies.platform ?? process.platform };
    readiness = new Readiness(shared);
    judgments = new Judgments({ ...shared, sessionId, configuration: () => settings.load() });
    // In-process trusted extensions only. No model-callable tool, prompt/JSON command or credential access.
    disconnect = pi.events.on('fight:jev:connect', (data: unknown) => {
      const request = data as { callerId?: string; authorize?: (context: unknown) => boolean;
        connected?: (capability: unknown) => void };
      if (!request || typeof request.connected !== 'function' || typeof request.authorize !== 'function' ||
        typeof request.callerId !== 'string') return;
      const failure = refresh();
      if (failure || !judgments) { request.connected({ state: failure ?? 'missing-session' }); return; }
      try {
        request.connected({ state: 'available', configuration: readiness?.inspect().configuration,
          ...judgments.consumer(request.callerId, request.authorize) });
      } catch { request.connected({ state: 'invalid-consumer' }); }
    });
    present(ctx, refresh());
  });
  pi.on('session_shutdown', async (_event, ctx) => {
    cleanup();
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
        const disclosure = await ctx.ui.select('Operator disclosure ceiling (consumer permission is also required)',
          ['fixed-synthetic-only', 'authorized-supplied-state']);
        if (!disclosure) return;
        if (!await ctx.ui.confirm('One-time disclosure and price setup (no periodic renewal)',
          `Allow ${disclosure} to OpenRouter/TypeSafe at $0.042/M input, free output and 64000-token maximum; confirm these prices at the model page. Saved until you change or remove setup; changed connection/price pins require setup again. Consumer/project permission is separate. Real charges apply. No calls are authorized by setup alone.`)) return;
        try {
          const config = settings.save({ endpoint: connection.endpoint, model: connection.model, evaluator: connection.evaluator,
            price: connection.price,
            requestMicroUsd: Number(request), sessionMicroUsd: Number(session), disclosure,
            priceConfirmedAt: Date.now(), revision: randomUUID() });
          judgments?.invalidate();
          readiness?.configure(config);
          ctx.ui.notify('Jev configured; optional self-check not run. No credential was read and no request was sent.', 'info');
        } catch { ctx.ui.notify('Jev: invalid-setup. Settings require positive integer ceilings, with request no larger than session.', 'warning'); }
        present(ctx); return;
      }
      const failure = refresh();
      if (action === 'inspect') {
        const view = readiness?.inspect();
        const state = failure ?? view?.state ?? 'missing-session';
        ctx.ui.notify(JSON.stringify({ ...view, setupFailure: failure ?? null,
          state: state === 'unchecked' ? 'configured' : state,
          selfCheck: { optional: true, state: view?.outcome ? view.state : 'no-retained-result' },
        }, null, 2), 'info');
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
  installFileScreening(pi, { settings: dependencies.fileSettings, configuration: () => settings.load(), profiles,
    credentialConfigured: dependencies.credentialConfigured ?? (ctx => ctx.modelRegistry.getProviderAuthStatus('openrouter').configured) });
}
