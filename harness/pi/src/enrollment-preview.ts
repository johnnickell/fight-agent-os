import type { ExtensionContext } from '@earendil-works/pi-coding-agent';
import { Key, matchesKey, ScrollView, Text, truncateToWidth } from '@earendil-works/pi-tui';

/**
 * Show the complete enrollment candidate without granting consent or saving authority
 */
export function reviewEnrollment(ctx: ExtensionContext, details: string): Promise<boolean> {
  return ctx.ui.custom<boolean>((tui, theme, keybindings, done) => {
    const content = new Text(details, 0, 0);
    const scroll = new ScrollView(content, { follow: 'none' });
    let canContinue = false;
    const keys = (action: 'tui.select.confirm' | 'tui.select.up' | 'tui.select.down' |
      'tui.select.pageUp' | 'tui.select.pageDown') => keybindings.getKeys(action).join('/') || 'unbound';

    return {
      render(width) {
        const height = Math.max(1, tui.terminal.rows - 2);
        const heading = new Text(theme.fg('accent', 'Enrollment preview (read-only)'), 0, 0).render(width);
        const controls = new Text([
          `${keys('tui.select.up')}/${keys('tui.select.down')}: scroll; ${keys('tui.select.pageUp')}/${keys('tui.select.pageDown')}: page`,
          'Home/End: jump; Esc: cancel',
          `${keys('tui.select.confirm')}: continue (not save)`,
        ].join('\n'), 0, 0).render(width);
        const bodyHeight = height - heading.length - controls.length - 1;
        canContinue = width >= 20 && bodyHeight >= 1;
        if (!canContinue) {
          return ['Esc: cancel', 'Enlarge terminal to review enrollment'].slice(0, height)
            .map(line => truncateToWidth(line, width));
        }

        // Regular-mode custom overlays render lines rather than layout nodes. Use Pi's ScrollView
        // for scroll bounds/state and explicitly project its viewport in both terminal modes.
        const lines = scroll.render(width);
        scroll.updateLayout(lines.length, bodyHeight, () => tui.requestRender());
        const start = scroll.scrollTop;
        const end = Math.min(lines.length, start + bodyHeight);
        return [...heading, ...lines.slice(start, end),
          theme.fg('muted', truncateToWidth(`Lines ${start + 1}-${end} of ${lines.length}`, width)), ...controls];
      },
      invalidate() { scroll.invalidate(); },
      handleInput(data) {
        if (matchesKey(data, Key.escape) || keybindings.matches(data, 'tui.select.cancel')) { done(false); return; }
        if (keybindings.matches(data, 'tui.select.confirm')) { if (canContinue) done(true); return; }
        if (keybindings.matches(data, 'tui.select.up')) scroll.scrollBy(-1);
        else if (keybindings.matches(data, 'tui.select.down')) scroll.scrollBy(1);
        else if (keybindings.matches(data, 'tui.select.pageUp')) scroll.scrollBy(-Math.max(1, scroll.viewportHeight - 1));
        else if (keybindings.matches(data, 'tui.select.pageDown')) scroll.scrollBy(Math.max(1, scroll.viewportHeight - 1));
        else if (matchesKey(data, Key.home)) scroll.scrollToStart();
        else if (matchesKey(data, Key.end)) scroll.scrollToEnd();
        tui.requestRender();
      },
    };
  }, { overlay: true, overlayOptions: { width: '100%', anchor: 'center', margin: { top: 1, bottom: 1 } } });
}
