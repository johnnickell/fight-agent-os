import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { HerdrWorkspace } from './herdr/workspace.mjs';
import { matchesKey, truncateToWidth, wrapTextWithAnsi } from '@earendil-works/pi-tui';
import { NativeAgentBrowser } from './workspace/native-agent-browser.mjs';

/** Keep the native Pi frontend and add only explicit Herdr companion controls */
export default function installHerdrWorkspace(pi: ExtensionAPI) {
  let workspace: HerdrWorkspace | undefined;
  let browser: NativeAgentBrowser | undefined;
  const act = async (ctx: ExtensionContext, action: 'open' | 'close') => {
    const currentContext = () => ({ cwd: ctx.cwd, sessionId: ctx.sessionManager.getSessionId(), sessionFile: ctx.sessionManager.getSessionFile() || null });
    workspace ||= new HerdrWorkspace({ ...currentContext(), currentContext });
    ctx.ui.notify(await workspace.act(action), 'info');
    if (action === 'close') { browser?.close(); browser = undefined; }
    else if (!browser && ctx.mode === 'tui') {
      if (typeof ctx.ui.setWidget !== 'function' || typeof ctx.ui.onTerminalInput !== 'function' || typeof ctx.ui.custom !== 'function') {
        ctx.ui.notify('Main agent picker unavailable: supported widget/input/custom UI capabilities required', 'warning');
        return;
      }
      browser = new NativeAgentBrowser(ctx, { matchesKey, truncateToWidth, wrapTextWithAnsi }); browser.start();
    }
  };
  // The separately loaded trusted startup hook validates resource ownership first.
  // This bus transfers only a local operation, never messages or log content.
  pi.events.on('fight:workspace-startup', (accept: unknown) => {
    if (typeof accept === 'function') accept((ctx: ExtensionContext) => act(ctx, 'open'));
  });
  pi.registerCommand('fight-workspace', {
    description: 'Open/reuse owned Herdr child and human-only verified log companions, or close them: /fight-workspace [open|close]',
    getArgumentCompletions: (prefix) => ['open', 'close'].filter((value) => value.startsWith(prefix)).map((value) => ({ value, label: value })),
    handler: async (args, ctx) => {
      if (ctx.mode !== 'tui') {
        ctx.ui.notify('Workspace layout requires native interactive Pi inside Herdr; no RPC fallback', 'warning');
        return;
      }
      const action = args.trim() || 'open';
      if (action !== 'open' && action !== 'close') {
        ctx.ui.notify('Use /fight-workspace [open|close]', 'warning');
        return;
      }
      try {
        await act(ctx, action);
      } catch (error) {
        ctx.ui.notify(error instanceof Error ? error.message : 'Workspace unavailable; ordinary Pi unchanged', 'warning');
      }
    },
  });
  pi.registerShortcut('ctrl+shift+a', {
    description: 'Focus the below-prompt passive agent picker',
    handler: async () => browser?.activate(),
  });
  pi.registerCommand('fight-agents', {
    description: 'Focus the below-prompt agent picker; Enter views in main, Esc returns',
    handler: async (_args, ctx) => {
      if (browser) browser.activate();
      else ctx.ui.notify('Open /fight-workspace in native Herdr first', 'warning');
    },
  });
  pi.on('ui_prompt_start', () => browser?.suspend());
  pi.on('session_shutdown', async (_event, ctx) => {
    browser?.close(); browser = undefined;
    if (!workspace) return;
    workspace.invalidate();
    try { await workspace.shutdown(); }
    catch { ctx.ui.notify('Workspace cleanup incomplete: unproven companions retained; inspect var/herdr-workspace before recovery', 'warning'); }
    workspace = undefined;
  });
}
