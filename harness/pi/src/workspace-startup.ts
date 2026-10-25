import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { realpathSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

/** Open companions once for the explicit executable, without conversation input */
export default function installWorkspaceStartup(pi: ExtensionAPI) {
  let requested = process.env.FIGHT_WORKSPACE_LAUNCH_PID === String(process.pid);
  delete process.env.FIGHT_WORKSPACE_LAUNCH_PID;
  pi.on('session_start', async (_event, ctx) => {
    if (!requested) return;
    requested = false; // New/resume/reload never inherit another automatic opening.
    try {
      if (ctx.mode !== 'tui' || typeof pi.getCommands !== 'function' || !pi.events?.emit) throw Error('Native startup APIs unavailable');
      const root = dirname(fileURLToPath(import.meta.url));
      const expected = new Map([
        ['fight-header', 'header-extension.ts'], ['jev', 'jev-extension.ts'],
        ['file-screening', 'jev-extension.ts'], ['fight-policy', 'tool-policy-extension.ts'],
        ['fight-workspace', 'herdr-workspace-extension.ts'],
      ]);
      const commands = pi.getCommands();
      for (const [name, file] of expected) {
        const owners = commands.filter(command => command.source === 'extension' && command.name.split(':')[0] === name);
        if (owners.length !== 1 || !owners[0].sourceInfo?.path || realpathSync(owners[0].sourceInfo.path) !== realpathSync(join(root, file))) {
          throw Error('Harness missing, duplicate, filtered or from another installation. Inspect pi list; keep one complete trusted package matching this executable, then fully restart');
        }
      }
      const observers: string[] = [];
      pi.events.emit('fight:workspace-observer-resource', (path: string) => observers.push(path));
      if (observers.length !== 1 || realpathSync(observers[0]) !== realpathSync(join(root, 'native-observation-extension.ts'))) throw Error('Native observer missing or duplicated; inspect Harness resource filters and restart');
      const openers: ((ctx: ExtensionContext) => Promise<void>)[] = [];
      pi.events.emit('fight:workspace-startup', (open: (ctx: ExtensionContext) => Promise<void>) => openers.push(open));
      if (openers.length !== 1) throw Error('Workspace startup capability unavailable or duplicated; update the owned package and restart');
      await openers[0](ctx);
    } catch (error) {
      ctx.ui.notify(`Workspace automatic open unavailable: ${error instanceof Error ? error.message : 'required capability missing'}. Native Pi remains running; no second Pi was started. Recover with /fight-workspace open after resolving prerequisites.`, 'warning');
    }
  });
}
