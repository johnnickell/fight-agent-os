import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { isToolCallEventType } from '@earendil-works/pi-coding-agent';

/** Delegate shell output optimization to the installed RTK registry */
export default function installRtk(pi: ExtensionAPI) {
  let available = false;
  let warned = false;
  // Custom bash executors (including the deterministic policy) own their exact inputs.
  const disabled = () => process.env.RTK_DISABLED === '1' ||
    pi.getAllTools().find(tool => tool.name === 'bash')?.sourceInfo?.source !== 'builtin';
  const warn = (ctx: ExtensionContext, message = 'RTK rewriting is unavailable; native shell commands remain unchanged. Check rtk rewrite --help.') => {
    if (warned) return;
    warned = true;
    if (ctx.hasUI) ctx.ui.notify(message, 'warning');
    else console.error(message);
  };
  pi.on('session_start', async (_event, ctx) => {
    available = false;
    warned = false;
    if (disabled()) return;
    try {
      const probe = await pi.exec('rtk', ['rewrite', 'git status --short'], { cwd: ctx.cwd, timeout: 2000 });
      available = !probe.killed && [0, 3].includes(probe.code) && Boolean(probe.stdout.trim());
    } catch { /* Missing optional RTK leaves native execution intact */ }
    if (!available) warn(ctx);
  });
  pi.on('tool_call', async (event, ctx) => {
    if (!available || disabled() || !isToolCallEventType('bash', event)) return;
    const command = event.input.command;
    if (typeof command !== 'string' || !command.trim() || /^\s*rtk(?:\s|$)/.test(command)) return;
    try {
      const result = await pi.exec('rtk', ['rewrite', command], {
        cwd: ctx.cwd, timeout: 2000, signal: ctx.signal,
      });
      if (ctx.signal?.aborted) return;
      if (!result.killed && [0, 3].includes(result.code) && result.stdout.trim()) {
        const rewritten = result.stdout.trim();
        // RTK can mistake an assignment value containing "task-" for a command.
        // Preserve the complete original script rather than losing its shell state.
        if (/(?:^|[;&|\n])\s*rtk\s+[A-Za-z_][A-Za-z0-9_]*=/.test(rewritten)) {
          warn(ctx, 'RTK tried to rewrite a shell assignment; this script remains unchanged. Use explicit RTK adapters or rtk proxy bash -c for this script. RTK remains enabled.');
          return;
        }
        event.input.command = rewritten;
      } else if (result.killed || result.code !== 1) warn(ctx);
    } catch { if (!ctx.signal?.aborted) warn(ctx); }
  });
}
