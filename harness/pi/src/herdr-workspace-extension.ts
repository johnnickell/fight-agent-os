import type { ExtensionAPI } from '@earendil-works/pi-coding-agent';
import { HerdrWorkspace } from './herdr/workspace.mjs';

/** Keep the native Pi frontend and add only explicit Herdr companion controls */
export default function installHerdrWorkspace(pi: ExtensionAPI) {
  let workspace: HerdrWorkspace | undefined;
  pi.registerCommand('fight-workspace', {
    description: 'Open/reuse owned Herdr placeholders, or close companions: /fight-workspace [open|close]',
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
        workspace ||= new HerdrWorkspace({ cwd: ctx.cwd, sessionId: ctx.sessionManager.getSessionId(), sessionFile: ctx.sessionManager.getSessionFile() });
        ctx.ui.notify(await workspace.act(action), 'info');
      } catch (error) {
        ctx.ui.notify(error instanceof Error ? error.message : 'Workspace unavailable; ordinary Pi unchanged', 'warning');
      }
    },
  });
  pi.on('session_shutdown', async (_event, ctx) => {
    if (!workspace) return;
    try { await workspace.shutdown(); }
    catch { ctx.ui.notify('Workspace cleanup incomplete: unproven companions retained; inspect var/herdr-workspace before recovery', 'warning'); }
    workspace = undefined;
  });
}
