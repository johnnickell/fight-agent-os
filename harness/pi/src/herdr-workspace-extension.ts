import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { HerdrWorkspace } from './herdr/workspace.mjs';

/** Collect fresh human declarations outside prompts, transcripts and saved preferences */
async function selectLogs(ctx: ExtensionContext) {
  const tests: { main: string; context: string; project: string; service: string }[] = [];
  let shared: (typeof tests)[number] | null = null;
  while (true) {
    const choice = await ctx.ui.select('Bind only existing sources you are authorized to observe. No containers will be changed.', [
      ...(tests.length < 8 ? ['Add worktree TEST source'] : []),
      ...(!shared ? ['Add explicitly SHARED main source'] : []),
      `Open with ${tests.length} test binding(s)${shared ? ' and optional shared mode' : ''}`,
    ]);
    if (!choice) return null;
    if (choice.startsWith('Open with')) return { tests, shared };
    const values: string[] = [];
    for (const label of ['Absolute canonical checkout/worktree path', 'Docker context (local Unix endpoint required)', 'Existing Compose project', 'Eligible single-container service']) {
      const value = await ctx.ui.input(label);
      if (!value?.trim()) return null;
      values.push(value.trim());
    }
    if (!await ctx.ui.confirm('Authorize this existing source for this open generation?', 'I am authorized to observe it; TEST means an existing test stack. Nothing is inferred from names or cwd.')) return null;
    const source = { main: values[0], context: values[1], project: values[2], service: values[3] };
    if (choice === 'Add worktree TEST source') tests.push(source); else shared = source;
  }
}

/** Keep the native Pi frontend and add only explicit Herdr companion controls */
export default function installHerdrWorkspace(pi: ExtensionAPI) {
  let workspace: HerdrWorkspace | undefined;
  const act = async (ctx: ExtensionContext, action: 'open' | 'close') => {
    const currentContext = () => ({ cwd: ctx.cwd, sessionId: ctx.sessionManager.getSessionId(), sessionFile: ctx.sessionManager.getSessionFile() || null });
    workspace ||= new HerdrWorkspace({ ...currentContext(), currentContext, selectLogs: () => selectLogs(ctx) });
    ctx.ui.notify(await workspace.act(action), 'info');
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
  pi.on('session_shutdown', async (_event, ctx) => {
    if (!workspace) return;
    workspace.invalidate();
    try { await workspace.shutdown(); }
    catch { ctx.ui.notify('Workspace cleanup incomplete: unproven companions retained; inspect var/herdr-workspace before recovery', 'warning'); }
    workspace = undefined;
  });
}
