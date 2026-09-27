import type { ExtensionAPI, ExtensionContext, Theme } from '@earendil-works/pi-coding-agent';
import { truncateToWidth } from '@earendil-works/pi-tui';
import { displayMode, hasConversation, modes, parseMode, renderHeader } from './header.mjs';

/** Install presentation only; tools, prompts, credentials and Workflow authority are untouched. */
export default function fightTerminal(pi: ExtensionAPI) {
  let preference = 'auto';
  let started = false;
  const plain = process.env.TERM === 'dumb' || process.env.NO_COLOR !== undefined;

  pi.registerFlag('fight-header', {
    description: 'Fight appearance: auto, port, wordmark, compact, ascii, off',
    type: 'string',
  });

  function style(theme: Theme, role: string, text: string): string {
    if (role === 'text') return text;
    if (role === 'accent') {
      // Preserve custom themes. The bundled Pi palettes receive the approved orange accent.
      if (theme.name === 'dark' || theme.name === 'light') {
        const light = theme.name === 'light';
        const color = theme.getColorMode() === 'truecolor'
          ? `38;2;${light ? '178;59;18' : '255;122;69'}`
          : `38;5;${light ? '130' : '209'}`;
        return `\u001b[${color}m${text}\u001b[39m`;
      }
      return theme.fg('accent', text);
    }
    return theme.fg(role === 'muted' ? 'muted' : 'dim', text);
  }

  function update(ctx: ExtensionContext) {
    if (ctx.mode !== 'tui') return;
    if (preference === 'off') {
      ctx.ui.setHeader(undefined);
      return;
    }
    ctx.ui.setHeader((_tui, theme) => ({
      render(width) {
        return renderHeader(displayMode(preference, started), width,
          (role: string, text: string) => style(theme, role, text), plain)
          .map((line: string) => truncateToWidth(line, width));
      },
      invalidate() {},
    }));
  }

  pi.on('session_start', async (_event, ctx) => {
    if (ctx.mode !== 'tui') return;
    const requested = pi.getFlag('fight-header') ?? process.env.FIGHT_PI_HEADER ?? 'auto';
    preference = parseMode(requested) ?? 'auto';
    if (!parseMode(requested)) {
      ctx.ui.notify(`Unknown Fight header mode. Use ${modes.join(', ')}. Using auto.`, 'warning');
    }
    started = hasConversation(ctx.sessionManager.getBranch());
    update(ctx);
  });

  pi.on('agent_start', async (_event, ctx) => {
    started = true;
    if (preference === 'auto') update(ctx);
  });

  pi.registerCommand('fight-header', {
    description: 'Choose Fight appearance: auto, port, wordmark, compact, ascii, off',
    handler: async (args, ctx) => {
      if (ctx.mode !== 'tui') return;
      const choice = args.trim() || await ctx.ui.select('Fight terminal appearance', [...modes]);
      if (!choice) return;
      const selected = parseMode(choice);
      if (!selected) {
        ctx.ui.notify(`Choose ${modes.join(', ')}.`, 'warning');
        return;
      }
      preference = selected;
      update(ctx);
    },
  });

  pi.on('session_shutdown', async (_event, ctx) => {
    if (ctx.mode === 'tui') ctx.ui.setHeader(undefined);
  });
}
