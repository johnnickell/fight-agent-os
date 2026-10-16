import { homedir } from 'node:os';
import { isAbsolute, relative, sep } from 'node:path';
import type { ExtensionAPI } from '@earendil-works/pi-coding-agent';
import { truncateToWidth, visibleWidth, wrapTextWithAnsi } from '@earendil-works/pi-tui';
import { collectFooterUsage, footerStatusRows, footerTokens } from './footer.mjs';

/** Render grouped status rows through Pi's supported footer API without modifying its runtime or other extensions */
export function installStatusFooter(pi: ExtensionAPI) {
  pi.on('session_start', (_event, ctx) => {
    if (ctx.mode !== 'tui') return;
    ctx.ui.setFooter((tui, theme, footerData) => {
      const unsubscribe = footerData.onBranchChange(() => tui.requestRender());
      let cached: { session: string; leaf: string | null; count: number; usage: ReturnType<typeof collectFooterUsage> } | undefined;
      return {
        dispose: unsubscribe,
        invalidate() { cached = undefined; },
        render(width) {
          if (width < 1) return [];
          const manager = ctx.sessionManager, entries = manager.getEntries();
          const session = manager.getSessionId(), leaf = manager.getLeafId();
          if (!cached || cached.session !== session || cached.leaf !== leaf || cached.count !== entries.length) {
            cached = { session, leaf, count: entries.length, usage: collectFooterUsage(entries) };
          }
          const usage = cached.usage, model = ctx.model, context = ctx.getContextUsage();
          const percent = context?.percent, limit = context?.contextWindow ?? model?.contextWindow ?? 0;
          const parts: string[] = [];
          for (const [key, label] of [['input', '↑'], ['output', '↓'], ['cacheRead', 'R'], ['cacheWrite', 'W']] as const) {
            if (usage[key]) parts.push(`${label}${footerTokens(usage[key])}`);
          }
          if ((usage.cacheRead || usage.cacheWrite) && usage.cacheHitRate !== undefined) parts.push(`CH${usage.cacheHitRate.toFixed(1)}%`);
          const subscription = model && (model.provider === 'kimi-coding' ||
            (ctx.modelRegistry.isUsingOAuth(model) && ctx.modelRegistry.getProvider(model.provider)?.auth?.oauth?.isSubscription === true));
          if (usage.cost || subscription) parts.push(`$${usage.cost.toFixed(3)}${subscription ? ' (sub)' : ''}`);
          const automatic = pi.getSettings().compaction?.enabled !== false ? ' (auto)' : '';
          const percentage = `${percent === null ? '?' : (percent ?? 0).toFixed(1) + '%'}/${footerTokens(limit)}${automatic}`;
          parts.push(theme.fg((percent ?? 0) > 90 ? 'error' : (percent ?? 0) > 70 ? 'warning' : 'dim', percentage));
          const left = truncateToWidth(theme.fg('dim', parts.join(' ')), width);
          const thinking = pi.getThinkingLevel();
          const modelLabel = `${model?.id ?? 'no-model'}${model?.reasoning ? ` • ${thinking === 'off' ? 'thinking off' : thinking}` : ''}`;
          const providerLabel = model && footerData.getAvailableProviderCount() > 1 ? `(${model.provider}) ${modelLabel}` : modelLabel;
          const room = width - visibleWidth(left) - 2;
          const right = room > 0 ? truncateToWidth(visibleWidth(providerLabel) <= room ? providerLabel : modelLabel, room, '') : '';
          const stats = left + (right ? ' '.repeat(width - visibleWidth(left) - visibleWidth(right)) + theme.fg('dim', right) : '');
          const cwd = manager.getCwd(), fromHome = relative(homedir(), cwd);
          let location = fromHome === '' ? '~' : fromHome !== '..' && !fromHome.startsWith(`..${sep}`) && !isAbsolute(fromHome) ? `~${sep}${fromHome}` : cwd;
          const branch = footerData.getGitBranch(), name = manager.getSessionName();
          if (branch) location += ` (${branch})`;
          if (name) location += ` • ${name}`;
          return [truncateToWidth(theme.fg('dim', location), width), stats,
            ...footerStatusRows(footerData.getExtensionStatuses()).flatMap(row => wrapTextWithAnsi(row, width).map(line => truncateToWidth(line, width)))];
        },
      };
    });
  });
  pi.on('session_shutdown', (_event, ctx) => {
    if (ctx.mode === 'tui') ctx.ui.setFooter(undefined);
  });
}
