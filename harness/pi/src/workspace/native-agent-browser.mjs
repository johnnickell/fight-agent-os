import { RegisteredChildren } from './registered-children.mjs';
import { agentDetail, agentRow } from './agent-presentation.mjs';

/** Add a below-editor picker and passive main overlay without replacing Pi's editor/session */
export class NativeAgentBrowser {
  constructor(ctx, uiTools, { reader = (...args) => new RegisteredChildren(...args) } = {}) {
    this.ctx = ctx; this.tools = uiTools; this.active = false; this.closed = false;
    this.children = reader(ctx.cwd, () => this.changed(), { preferences: false });
  }
  start() {
    const { ui, sessionManager } = this.ctx;
    this.children.start(sessionManager.getSessionId(), sessionManager.getSessionFile());
    ui.setWidget('fight-agent-picker', (tui, theme) => {
      this.tui = tui;
      return { render: width => this.picker(width, theme), invalidate() {} };
    }, { placement: 'belowEditor' });
    this.unsubscribe = ui.onTerminalInput(data => this.input(data));
    this.timer = setInterval(() => this.children.tick(), 1000);
  }
  changed() { if (!this.closed) { this.tui?.requestRender(); this.viewerTui?.requestRender(); } }
  activate() { if (!this.closed && !this.viewing) { this.active = !this.active; this.changed(); } }
  suspend() { this.active = false; this.changed(); }
  picker(width, theme) {
    const rows = this.children.orderedSources, selected = rows.findIndex(([key]) => key === this.children.selected);
    const first = Math.max(0, Math.min(selected - 1, rows.length - 3));
    return [theme.fg('accent', '● main · coordinating session'),
      theme.fg(this.active ? 'accent' : 'dim', this.active ? '↑↓ choose · Enter view in main · Esc editor' : 'Ctrl+Shift+A · inspect agent in main'),
      ...rows.slice(first, first + 3).map(([key, item]) => {
        const row = agentRow(this.children, key, item);
        return theme.fg(key === this.children.selected && this.active ? 'accent' : item.lifecycle === 'completed' ? 'success' : 'muted', row);
      }),
      ...(rows.length > 3 ? [theme.fg('dim', `${first + 1}–${Math.min(rows.length, first + 3)} of ${rows.length}/8 retained · ↑↓ more`)] : []),
      ...(!rows.length ? [theme.fg('dim', 'No eligible agents · bounded verified activity only')] : []),
      ...(this.children.removed ? [theme.fg('dim', `Observed lost/evicted: ${this.children.removed} · not complete history`)] : []),
    ].map(line => this.tools.truncateToWidth(line, width));
  }
  input(data) {
    if (!this.active || this.viewing || this.closed) return;
    const { matchesKey } = this.tools;
    if (matchesKey(data, 'escape')) this.suspend();
    else if (matchesKey(data, 'up')) this.children.action('previous');
    else if (matchesKey(data, 'down')) this.children.action('next');
    else if (matchesKey(data, 'return')) { this.active = false; void this.view().catch(() => this.ctx.ui.notify('Agent viewer unavailable; coordinator unchanged', 'warning')); }
    // While explicitly focused, consume everything, including paste/abort keys. Never submit to the coordinator.
    return { consume: true };
  }
  async view() {
    const key = this.children.selected;
    if (!key || this.closed || this.viewing) { this.changed(); return; }
    this.viewing = true; this.active = false;
    let scroll = 0, paused = null, sequence = 0;
    try {
      await this.ctx.ui.custom((tui, theme, _keybindings, done) => {
        this.viewerTui = tui; this.finishViewer = () => done(undefined);
        return {
          render: width => {
            const item = this.children.sources.get(key);
            const height = Math.max(4, tui.terminal.rows - 2);
            const detail = agentDetail(this.children, key);
            const body = (paused && item?.verified ? paused : detail.slice(1)).flatMap(line => this.tools.wrapTextWithAnsi(line, width));
            const space = Math.max(0, height - 4);
            scroll = Math.min(Math.max(0, body.length - space), Math.max(0, scroll));
            const end = body.length - scroll;
            return [theme.fg('accent', 'AGENT VIEW · coordinator continues in background'),
              detail[0], theme.fg('dim', `Esc main · PgUp/PgDn scroll · p pause${paused ? ` · PAUSED +${Math.max(0, (item?.sequence ?? 0) - sequence)}` : ''}`),
              ...body.slice(Math.max(0, end - space), end),
              theme.fg('dim', `${scroll ? '↑ Earlier activity · ' : ''}Bounded window, not a transcript${item?.omitted ? ` · omitted ${item.omitted}` : ''}${this.children.removed ? ` · lost/evicted ${this.children.removed}` : ''}`),
            ].map(line => this.tools.truncateToWidth(line, width));
          },
          handleInput: data => {
            const { matchesKey } = this.tools;
            if (matchesKey(data, 'escape')) { done(undefined); return; }
            if (matchesKey(data, 'pageUp') || matchesKey(data, 'up')) scroll += 5;
            else if (matchesKey(data, 'pageDown') || matchesKey(data, 'down')) scroll -= 5;
            else if (data === 'p') { paused = paused ? null : agentDetail(this.children, key).slice(1); sequence = this.children.sources.get(key)?.sequence ?? 0; }
            tui.requestRender();
          },
          invalidate() {},
        };
      }, { overlay: true, overlayOptions: { width: '100%', maxHeight: '100%', anchor: 'top-left' } });
    } finally { this.viewerTui = null; this.finishViewer = null; this.viewing = false; this.changed(); }
  }
  close() {
    if (this.closed) return;
    this.closed = true; this.active = false; clearInterval(this.timer); this.unsubscribe?.(); this.finishViewer?.();
    this.children.close(); this.ctx.ui.setWidget('fight-agent-picker', undefined);
  }
}
